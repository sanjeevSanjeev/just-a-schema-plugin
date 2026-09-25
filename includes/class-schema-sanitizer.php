<?php
/**
 * Schema_Sanitizer
 *
 * Layer A + C of CLAUDE.md Section 10.5: sanitize every field on save
 * (no exceptions), and produce non-blocking lint warnings for preview/save.
 *
 * @package Schema_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema_Sanitizer {

	/**
	 * @param array $raw_fields Field values keyed by schema.org property name.
	 * @param array $field_defs Field definitions (Schema_Types::get_field_defs()).
	 */
	public static function sanitize_fields( array $raw_fields, array $field_defs ): array {
		$clean = array();
		foreach ( $raw_fields as $key => $value ) {
			if ( ! is_string( $key ) || ! preg_match( '/^[A-Za-z][A-Za-z0-9]*$/', $key ) ) {
				continue; // reject anything that isn't a plain schema.org-style property name.
			}
			$type            = $field_defs[ $key ]['type'] ?? 'text';
			$sanitized_value = self::sanitize_value( $value, $type );
			if ( 'select' === $type && ! empty( $field_defs[ $key ]['options'] ) && ! in_array( $sanitized_value, self::option_values( $field_defs[ $key ]['options'] ), true ) ) {
				continue; // a bounded field only ever stores one of its defined values.
			}
			if ( '' !== $sanitized_value && array() !== $sanitized_value && null !== $sanitized_value ) {
				$clean[ $key ] = $sanitized_value;
			}
		}
		return $clean;
	}

	/**
	 * Select options may be plain strings or { label, value } pairs.
	 *
	 * @return string[]
	 */
	private static function option_values( array $options ): array {
		return array_map(
			static fn( $option ) => (string) ( is_array( $option ) ? ( $option['value'] ?? '' ) : $option ),
			$options
		);
	}

	/**
	 * Accepts "YYYY-MM-DD" or "YYYY-MM-DDTHH:MM[:SS][offset]". A datetime
	 * without an offset is read in the site's timezone and stored with
	 * that offset appended, since Google flags event times that lack one.
	 */
	private static function sanitize_datetime( string $value ): string {
		$value = trim( $value );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return $value;
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?(Z|[+-]\d{2}:\d{2})?$/', $value ) ) {
			return '';
		}
		try {
			$date = new DateTimeImmutable( $value, wp_timezone() );
		} catch ( Exception $e ) {
			return '';
		}
		return $date->format( 'Y-m-d\TH:i:sP' );
	}

	/**
	 * Enum values accepted for MerchantReturnPolicy, stored as full
	 * schema.org URLs (Google's return-policy doc, updated 2026-09-08).
	 */
	const RETURN_POLICY_ENUMS = array(
		'returnPolicyCategory' => array( 'MerchantReturnFiniteReturnWindow', 'MerchantReturnNotPermitted', 'MerchantReturnUnlimitedWindow' ),
		'returnFees'           => array( 'FreeReturn', 'ReturnFeesCustomerResponsibility', 'ReturnShippingFees' ),
		'returnMethod'         => array( 'ReturnByMail', 'ReturnInStore', 'ReturnAtKiosk' ),
		'refundType'           => array( 'FullRefund', 'ExchangeRefund', 'StoreCreditRefund' ),
	);

	/** ISO 3166-1 alpha-2 shape; uppercased. Returns '' if not a code. */
	public static function sanitize_country_code( $value ): string {
		$code = is_string( $value ) ? strtoupper( trim( $value ) ) : '';
		return preg_match( '/^[A-Z]{2}$/', $code ) ? $code : '';
	}

	/** ISO 4217 shape; uppercased. Returns '' if not a code. */
	public static function sanitize_currency_code( $value ): string {
		$code = is_string( $value ) ? strtoupper( trim( $value ) ) : '';
		return preg_match( '/^[A-Z]{3}$/', $code ) ? $code : '';
	}

	/**
	 * Accepts the form's shape ({ mode, merchantReturnLink, applicableCountry[],
	 * returnPolicyCategory, merchantReturnDays, returnFees, returnMethod,
	 * refundType }) or the stored MerchantReturnPolicy node. mode 'link'
	 * keeps only the link (Google's simple option); anything else keeps
	 * every valid property.
	 */
	private static function sanitize_return_policy( array $value ): array {
		$policy = array( '@type' => 'MerchantReturnPolicy' );

		$link = self::sanitize_value( $value['merchantReturnLink'] ?? '', 'url' );
		if ( $link ) {
			$policy['merchantReturnLink'] = $link;
		}
		if ( 'link' === ( $value['mode'] ?? '' ) ) {
			return count( $policy ) > 1 ? $policy : array();
		}

		$countries = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'sanitize_country_code' ), (array) ( $value['applicableCountry'] ?? array() ) ) ) ) );
		if ( $countries ) {
			$policy['applicableCountry'] = $countries;
		}

		foreach ( self::RETURN_POLICY_ENUMS as $key => $allowed ) {
			$enum = is_string( $value[ $key ] ?? null ) ? preg_replace( '#^https?://schema\.org/#', '', $value[ $key ] ) : '';
			if ( in_array( $enum, $allowed, true ) ) {
				$policy[ $key ] = 'https://schema.org/' . $enum;
			}
		}

		// Days only mean something for a finite window.
		$days = isset( $value['merchantReturnDays'] ) ? absint( $value['merchantReturnDays'] ) : 0;
		if ( $days > 0 && 'https://schema.org/MerchantReturnFiniteReturnWindow' === ( $policy['returnPolicyCategory'] ?? '' ) ) {
			$policy['merchantReturnDays'] = $days;
		}

		return count( $policy ) > 1 ? $policy : array();
	}

	/**
	 * Accepts a list of flat form rows ({ country, cost, currency, free,
	 * minDays, maxDays }) or the stored ShippingService node, and returns a
	 * ShippingService with one ShippingConditions per non-empty row
	 * (Google's shipping-policy doc, updated 2026-09-08).
	 */
	private static function sanitize_shipping_service( array $value ): array {
		$rows = isset( $value['shippingConditions'] ) ? array_map( array( __CLASS__, 'flatten_shipping_condition' ), (array) $value['shippingConditions'] ) : $value;

		$conditions = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$condition = array( '@type' => 'ShippingConditions' );

			$country = self::sanitize_country_code( $row['country'] ?? '' );
			if ( $country ) {
				$condition['shippingDestination'] = array(
					'@type'          => 'DefinedRegion',
					'addressCountry' => $country,
				);
			}

			$currency = self::sanitize_currency_code( $row['currency'] ?? '' );
			$has_cost = isset( $row['cost'] ) && '' !== $row['cost'] && is_numeric( $row['cost'] );
			if ( $currency && ( ! empty( $row['free'] ) || $has_cost ) ) {
				$condition['shippingRate'] = array(
					'@type'    => 'MonetaryAmount',
					'value'    => ! empty( $row['free'] ) ? 0 : max( 0, round( (float) $row['cost'], 2 ) ),
					'currency' => $currency,
				);
			}

			$min = isset( $row['minDays'] ) && '' !== $row['minDays'] ? absint( $row['minDays'] ) : null;
			$max = isset( $row['maxDays'] ) && '' !== $row['maxDays'] ? absint( $row['maxDays'] ) : null;
			if ( null !== $min && null !== $max && $min > $max ) {
				list( $min, $max ) = array( $max, $min );
			}
			if ( null !== $min || null !== $max ) {
				$duration = array(
					'@type'    => 'QuantitativeValue',
					'unitCode' => 'DAY',
				);
				if ( null !== $min ) {
					$duration['minValue'] = $min;
				}
				if ( null !== $max ) {
					$duration['maxValue'] = $max;
				}
				$condition['transitTime'] = array(
					'@type'    => 'ServicePeriod',
					'duration' => $duration,
				);
			}

			if ( count( $condition ) > 1 ) {
				$conditions[] = $condition;
			}
		}

		return $conditions
			? array(
				'@type'              => 'ShippingService',
				'shippingConditions' => $conditions,
			)
			: array();
	}

	/**
	 * Stored ShippingConditions node -> flat form row.
	 *
	 * @param mixed $condition
	 */
	private static function flatten_shipping_condition( $condition ): array {
		if ( ! is_array( $condition ) ) {
			return array();
		}
		$rate     = (array) ( $condition['shippingRate'] ?? array() );
		$duration = (array) ( $condition['transitTime']['duration'] ?? array() );
		return array(
			'country'  => $condition['shippingDestination']['addressCountry'] ?? '',
			'cost'     => $rate['value'] ?? '',
			'currency' => $rate['currency'] ?? '',
			'free'     => isset( $rate['value'] ) && 0.0 === (float) $rate['value'],
			'minDays'  => $duration['minValue'] ?? '',
			'maxDays'  => $duration['maxValue'] ?? '',
		);
	}

	/**
	 * Re-saving an untouched event sends back the stored Place/VirtualLocation
	 * shape rather than the form's flat { name, address, url } shape.
	 */
	private static function flatten_event_location( array $value ): array {
		$nodes = isset( $value['@type'] ) ? array( $value ) : ( array_is_list( $value ) ? $value : null );
		if ( null === $nodes ) {
			return $value;
		}
		$flat = array();
		foreach ( $nodes as $node ) {
			$type = is_array( $node ) ? ( $node['@type'] ?? '' ) : '';
			if ( 'Place' === $type ) {
				$flat['name']    = $node['name'] ?? '';
				$flat['address'] = $node['address'] ?? array();
			} elseif ( 'VirtualLocation' === $type ) {
				$flat['url'] = $node['url'] ?? '';
			}
		}
		return $flat;
	}

	/**
	 * @return mixed
	 */
	public static function sanitize_value( $value, string $type ) {
		switch ( $type ) {
			case 'url':
			case 'media': // WP media library picker -- still just a URL string on the wire.
				return is_string( $value ) ? esc_url_raw( trim( $value ) ) : '';

			case 'email':
				return is_string( $value ) ? sanitize_email( $value ) : '';

			case 'textarea':
				return is_string( $value ) ? sanitize_textarea_field( $value ) : '';

			case 'tel':
			case 'date':
				return is_string( $value ) ? sanitize_text_field( $value ) : '';

			case 'select':
				return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';

			case 'datetime':
				return is_string( $value ) ? self::sanitize_datetime( $value ) : '';

			case 'return_policy':
				return is_array( $value ) ? self::sanitize_return_policy( $value ) : array();

			case 'shipping_service':
				return is_array( $value ) ? self::sanitize_shipping_service( $value ) : array();

			case 'event_location':
				// A physical venue (Place), an online URL (VirtualLocation),
				// or both for mixed-attendance events.
				if ( ! is_array( $value ) ) {
					return array();
				}
				$value     = self::flatten_event_location( $value );
				$locations = array();
				$place     = array( '@type' => 'Place' );
				if ( ! empty( $value['name'] ) && is_string( $value['name'] ) ) {
					$place['name'] = sanitize_text_field( $value['name'] );
				}
				$address = self::sanitize_value( $value['address'] ?? array(), 'address' );
				if ( $address ) {
					$place['address'] = $address;
				}
				if ( count( $place ) > 1 ) {
					$locations[] = $place;
				}
				$url = self::sanitize_value( $value['url'] ?? '', 'url' );
				if ( $url ) {
					$locations[] = array(
						'@type' => 'VirtualLocation',
						'url'   => $url,
					);
				}
				return 1 === count( $locations ) ? $locations[0] : $locations;

			case 'lat_long':
				if ( ! is_array( $value ) ) {
					return array();
				}
				$geo = array( '@type' => 'GeoCoordinates' );
				if ( isset( $value['latitude'] ) && '' !== $value['latitude'] ) {
					$geo['latitude'] = floatval( $value['latitude'] );
				}
				if ( isset( $value['longitude'] ) && '' !== $value['longitude'] ) {
					$geo['longitude'] = floatval( $value['longitude'] );
				}
				return count( $geo ) > 1 ? $geo : array();

			case 'sameAs_list':
				if ( ! is_array( $value ) ) {
					return array();
				}
				return array_values( array_unique( array_filter( array_map( 'esc_url_raw', $value ) ) ) );

			case 'address':
				if ( ! is_array( $value ) ) {
					return array();
				}
				$address = array( '@type' => 'PostalAddress' );
				foreach ( array( 'streetAddress', 'addressLocality', 'addressRegion', 'postalCode', 'addressCountry' ) as $part ) {
					if ( ! empty( $value[ $part ] ) && is_scalar( $value[ $part ] ) ) {
						$address[ $part ] = sanitize_text_field( (string) $value[ $part ] );
					}
				}
				// The form now offers ISO codes; older free-text values
				// (e.g. "Nepal") are kept rather than silently dropped.
				if ( isset( $address['addressCountry'] ) && self::sanitize_country_code( $address['addressCountry'] ) ) {
					$address['addressCountry'] = self::sanitize_country_code( $address['addressCountry'] );
				}
				return count( $address ) > 1 ? $address : array();

			case 'repeatable_hours':
				if ( ! is_array( $value ) ) {
					return array();
				}
				$rows = array();
				foreach ( $value as $row ) {
					if ( empty( $row['dayOfWeek'] ) || empty( $row['opens'] ) || empty( $row['closes'] ) ) {
						continue;
					}
					$rows[] = array(
						'@type'     => 'OpeningHoursSpecification',
						'dayOfWeek' => sanitize_text_field( $row['dayOfWeek'] ),
						'opens'     => sanitize_text_field( $row['opens'] ),
						'closes'    => sanitize_text_field( $row['closes'] ),
					);
				}
				return $rows;

			case 'aggregate_rating':
				if ( ! is_array( $value ) ) {
					return array();
				}
				$rating = array( '@type' => 'AggregateRating' );
				if ( isset( $value['ratingValue'] ) && '' !== $value['ratingValue'] ) {
					$rating['ratingValue'] = floatval( $value['ratingValue'] );
				}
				if ( isset( $value['reviewCount'] ) && '' !== $value['reviewCount'] ) {
					$rating['reviewCount'] = absint( $value['reviewCount'] );
				}
				return count( $rating ) > 1 ? $rating : array();

			case 'review':
				if ( ! is_array( $value ) ) {
					return array();
				}
				$review = array( '@type' => 'Review' );
				if ( ! empty( $value['author'] ) ) {
					$review['author'] = array(
						'@type' => 'Person',
						'name'  => sanitize_text_field( $value['author'] ),
					);
				}
				if ( ! empty( $value['reviewBody'] ) ) {
					$review['reviewBody'] = sanitize_textarea_field( $value['reviewBody'] );
				}
				return count( $review ) > 1 ? $review : array();

			case 'text':
			default:
				return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
		}
	}

	/**
	 * @return array|WP_Error Decoded object on success.
	 */
	public static function sanitize_raw_override( string $json ) {
		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'schema_plugin_invalid_json',
				__( 'The raw JSON-LD override is not valid JSON.', 'schema-plugin' ),
				array( 'status' => 400 )
			);
		}
		return $decoded;
	}

	/**
	 * Non-blocking lint pass -- CLAUDE.md Section 10.5.C. Never prevents a
	 * save; only surfaces guidance in the UI.
	 *
	 * @return string[]
	 */
	public static function lint_fields( array $fields, array $field_defs, string $type_label = '' ): array {
		$warnings = array();

		foreach ( $field_defs as $key => $def ) {
			if ( ! empty( $fields[ $key ] ) ) {
				continue;
			}
			if ( empty( $def['required'] ) ) {
				// Softer wording for properties Google recommends but doesn't require.
				if ( ! empty( $def['nudge'] ) ) {
					$warnings[] = sprintf(
						/* translators: 1: schema.org property name, 2: schema type label. */
						__( "Google recommends adding '%1\$s' for %2\$s. Optional, but it helps search engines identify you.", 'schema-plugin' ),
						$key,
						$type_label ? $type_label : __( 'this type', 'schema-plugin' )
					);
				}
				continue;
			}
			$warnings[] = $type_label
				? sprintf(
					/* translators: 1: schema.org property name, 2: schema type label. */
					__( "Google requires '%1\$s' for %2\$s -- this page won't be rich-result eligible until it's filled in.", 'schema-plugin' ),
					$key,
					$type_label
				)
				: sprintf(
					/* translators: %s: schema.org property name. */
					__( "'%s' is required for this type to be rich-result eligible.", 'schema-plugin' ),
					$key
				);
		}

		foreach ( $fields as $key => $value ) {
			$type = $field_defs[ $key ]['type'] ?? null;
			$warnings = array_merge( $warnings, self::lint_commerce_value( $value, (string) $type ) );
			if ( in_array( $type, array( 'url', 'media' ), true ) && ! empty( $value ) && ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
				$warnings[] = sprintf(
					/* translators: %s: schema.org property name. */
					__( "'%s' doesn't look like a valid URL.", 'schema-plugin' ),
					$key
				);
			}
		}

		return $warnings;
	}

	/**
	 * Completeness checks for the store policies, per Google's return- and
	 * shipping-policy docs. Non-blocking.
	 *
	 * @param mixed $value Sanitized field value.
	 * @return string[]
	 */
	public static function lint_commerce_value( $value, string $type ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		if ( 'return_policy' === $type ) {
			$structured = ! empty( $value['applicableCountry'] ) && ! empty( $value['returnPolicyCategory'] );
			if ( ! $structured && empty( $value['merchantReturnLink'] ) ) {
				return array( __( 'Return policy: Google needs either a link to your returns page, or the countries it applies to plus a policy type.', 'schema-plugin' ) );
			}
			if ( 'https://schema.org/MerchantReturnFiniteReturnWindow' === ( $value['returnPolicyCategory'] ?? '' ) && empty( $value['merchantReturnDays'] ) ) {
				return array( __( 'Return policy: enter how many days customers have to return items.', 'schema-plugin' ) );
			}
			return array();
		}

		if ( 'shipping_service' === $type ) {
			foreach ( (array) ( $value['shippingConditions'] ?? array() ) as $index => $condition ) {
				if ( empty( $condition['shippingDestination'] ) || empty( $condition['shippingRate'] ) ) {
					return array(
						sprintf(
							/* translators: %d: shipping row number. */
							__( 'Shipping row %d: add a destination country and a cost (or mark it free) so Google can use it.', 'schema-plugin' ),
							$index + 1
						),
					);
				}
			}
		}

		return array();
	}
}
