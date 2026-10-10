<?php
/**
 * Schema_Token_Resolver
 *
 * Token resolution for Default Rules templates -- CLAUDE.md Section 3.4.
 * Supports: {{post_title}}, {{post_excerpt}}, {{featured_image}},
 * {{post_author_id}}, {{post_date}}, {{post_modified}}, {{permalink}},
 * {{organization_id}}, plus {{homepage_id}} used in match.value, and the
 * WooCommerce-only {{product_price}}, {{product_currency}} and
 * {{product_availability}} (empty when WooCommerce is off or the post isn't
 * a product, so a template using them just omits the property).
 *
 * @package Schema_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema_Token_Resolver {

	/** Tokens that don't depend on a specific queried post. */
	const POST_INDEPENDENT_TOKENS = array( 'organization_id', 'homepage_id' );

	/**
	 * @return string
	 */
	public static function resolve( string $token, int $post_id ) {
		// On an archive/blog-index request there is no single queried post
		// (get_queried_object_id() is 0). Post-dependent WP functions like
		// get_the_title(0) silently fall back to the global $post rather
		// than returning empty, which would leak whatever post happens to
		// be in the loop -- so resolve to '' explicitly instead.
		if ( $post_id <= 0 && ! in_array( $token, self::POST_INDEPENDENT_TOKENS, true ) ) {
			return '';
		}

		switch ( $token ) {
			case 'post_title':
				return get_the_title( $post_id );

			case 'post_excerpt':
				$excerpt = get_the_excerpt( $post_id );
				return $excerpt ? wp_strip_all_tags( $excerpt ) : '';

			case 'featured_image':
				$image_id = get_post_thumbnail_id( $post_id );
				return $image_id ? (string) wp_get_attachment_image_url( $image_id, 'full' ) : '';

			case 'post_author_id':
				// Resolves to a proper @id-shaped URI (the same
				// author-archive-URL + #person fragment convention already
				// used for auto-linked Person entities in
				// Schema_Linked_Content_Entity::get_id_uri()), not a bare
				// user ID -- a JSON-LD @id must be an IRI.
				$author_id = (int) get_post_field( 'post_author', $post_id );
				if ( ! $author_id ) {
					return '';
				}
				$archive_url = get_author_posts_url( $author_id );
				return $archive_url ? trailingslashit( $archive_url ) . '#person' : '';

			case 'post_date':
				$date = get_the_date( DATE_W3C, $post_id );
				return $date ? $date : '';

			case 'post_modified':
				$date = get_the_modified_date( DATE_W3C, $post_id );
				return $date ? $date : '';

			case 'permalink':
				$permalink = get_permalink( $post_id );
				return $permalink ? $permalink : '';

			case 'product_price':
				return self::get_product_value( $post_id, 'price' );

			case 'product_currency':
				return $post_id > 0 && function_exists( 'get_woocommerce_currency' ) && self::get_wc_product( $post_id )
					? (string) get_woocommerce_currency()
					: '';

			case 'product_availability':
				return self::get_product_value( $post_id, 'availability' );

			case 'organization_id':
				return class_exists( 'Schema_Entity_Registry' ) ? Schema_Entity_Registry::get_entity_id_uri() : '';

			case 'homepage_id':
				return (string) self::get_homepage_id();

			default:
				return '';
		}
	}

	public static function get_homepage_id(): int {
		if ( 'page' === get_option( 'show_on_front' ) ) {
			return (int) get_option( 'page_on_front' );
		}
		return 0;
	}

	/**
	 * The WooCommerce product for a post, or null (WooCommerce off, or the
	 * post isn't a product).
	 *
	 * @param int $post_id Post ID.
	 * @return object|null
	 */
	private static function get_wc_product( int $post_id ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}
		$product = wc_get_product( $post_id );
		return $product ? $product : null;
	}

	/**
	 * A product's displayed price (honouring the store's tax display
	 * setting) or its schema.org availability URL; '' when unavailable.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $what    'price' or 'availability'.
	 * @return string
	 */
	private static function get_product_value( int $post_id, string $what ): string {
		$product = self::get_wc_product( $post_id );
		if ( ! $product ) {
			return '';
		}

		if ( 'availability' === $what ) {
			$map    = array(
				'instock'     => 'InStock',
				'outofstock'  => 'OutOfStock',
				'onbackorder' => 'BackOrder',
			);
			$status = (string) $product->get_stock_status();
			return isset( $map[ $status ] ) ? 'https://schema.org/' . $map[ $status ] : '';
		}

		// A product with no price set must not read as free: the display
		// helper turns an empty price into 0.
		if ( '' === (string) $product->get_price() ) {
			return '';
		}
		$price = function_exists( 'wc_get_price_to_display' ) ? wc_get_price_to_display( $product ) : $product->get_price();
		if ( '' === (string) $price || ! is_numeric( $price ) ) {
			return '';
		}
		return function_exists( 'wc_format_decimal' ) && function_exists( 'wc_get_price_decimals' )
			? (string) wc_format_decimal( $price, wc_get_price_decimals() )
			: (string) $price;
	}

	public static function resolve_string( string $value, int $post_id ): string {
		return (string) preg_replace_callback(
			'/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
			static function ( $matches ) use ( $post_id ) {
				return (string) self::resolve( $matches[1], $post_id );
			},
			$value
		);
	}

	/**
	 * Resolves every token in a template recursively. A string leaf whose
	 * fully-resolved value is empty is dropped from the result -- an
	 * absent property is valid schema, an empty-string property often
	 * isn't (CLAUDE.md Section 10.5.C).
	 */
	public static function resolve_template( array $template, int $post_id ): array {
		$resolved = array();

		foreach ( $template as $key => $value ) {
			if ( is_array( $value ) ) {
				$child = self::resolve_template( $value, $post_id );
				// A typed node left with nothing but its @type (an Offer whose
				// price didn't resolve) is dropped; a bare @id reference stays.
				if ( isset( $child['@type'] ) && array() === array_diff( array_keys( $child ), array( '@type', '@id', '@context' ) ) ) {
					continue;
				}
				// Google requires a price on an Offer, so one without it is
				// dropped rather than emitted half-empty.
				if ( 'Offer' === ( $child['@type'] ?? '' ) && ! isset( $child['price'] ) && ! isset( $child['priceSpecification'] ) ) {
					continue;
				}
				if ( ! empty( $child ) ) {
					$resolved[ $key ] = $child;
				}
			} elseif ( is_string( $value ) ) {
				$string = self::resolve_string( $value, $post_id );
				if ( '' !== trim( $string ) ) {
					$resolved[ $key ] = $string;
				}
			} else {
				$resolved[ $key ] = $value;
			}
		}

		return $resolved;
	}
}
