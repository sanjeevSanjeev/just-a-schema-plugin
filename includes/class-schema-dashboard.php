<?php
/**
 * Schema_Dashboard
 *
 * Backs the persistent Admin Dashboard (mid-build addition -- supersedes
 * the one-time post-wizard landing screen, whose "what's live" content
 * now lives here permanently instead of being shown once and discarded).
 *
 * Deliberately NOT the deferred Completeness Dashboard (CLAUDE.md Section
 * 8): no scoring, no percentages, no per-post warnings aggregated across
 * the site -- just a plain factual "what's configured" count plus a
 * small set of dismissable/completable next-step recommendations.
 *
 * @package Schema_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema_Dashboard {

	const OPTION_RECOMMENDATIONS = 'schema_plugin_dashboard_recommendations';

	public static function get_status(): array {
		$entity_type = Schema_Entity_Registry::get_entity_type();
		$locked      = Schema_Types::get_locked_types();
		$rules       = Schema_Default_Rules::get_rules();
		$exclusions  = array_filter( Schema_Settings::get_backend_exclusions(), static fn( $rule ) => ! empty( $rule['enabled'] ) );

		return array(
			'entity_configured' => '' !== $entity_type,
			'site_type'         => $entity_type,
			'site_type_label'   => $locked[ $entity_type ]['label'] ?? $entity_type,
			'rule_count'        => count( $rules ),
			'custom_rule_count' => self::count_custom_rules( $rules, $entity_type ),
			'exclusion_count'   => count( $exclusions ),
			'search_enabled'    => Schema_Entity_Registry::get_website_search_enabled(),
			'recommendations'   => self::get_recommendations( $entity_type ),
		);
	}

	/**
	 * Heuristic, not a stored provenance flag: a rule counts as "custom"
	 * if its (match, schema_type) shape doesn't appear anywhere in this
	 * type's stock seed set, regardless of position or id.
	 */
	private static function count_custom_rules( array $rules, string $entity_type ): int {
		$stock = Schema_Types::DEFAULT_RULE_SETS[ $entity_type ] ?? array();
		$stock_shapes = array_map(
			static fn( $rule ) => wp_json_encode( array( $rule['match'], $rule['schema_type'] ) ),
			$stock
		);

		$custom = 0;
		foreach ( $rules as $rule ) {
			$shape = wp_json_encode( array( $rule['match'] ?? array(), $rule['schema_type'] ?? '' ) );
			if ( ! in_array( $shape, $stock_shapes, true ) ) {
				++$custom;
			}
		}
		return $custom;
	}

	/**
	 * Per-type recommendation definitions. Content is this plugin's own
	 * (no external wizard-content-spec.md exists in this project) --
	 * kept deliberately small and genuinely actionable given Services/
	 * Team/Locations managers are still v1.1 in progress.
	 */
	public static function get_recommendation_definitions( string $entity_type ): array {
		$defs = array(
			'social_profiles' => array(
				'label'       => __( 'Add your social profile links', 'schema-plugin' ),
				'description' => __( 'Link your X, Facebook, Instagram, or other profiles so search engines can connect them to your site.', 'schema-plugin' ),
				'action_key'  => 'entitySettingsUrl',
				'action_label' => __( 'Go to Entity settings', 'schema-plugin' ),
			),
			'search_box'      => array(
				'label'       => __( 'Enable the sitelinks search box', 'schema-plugin' ),
				'description' => __( 'Let Google show a search box for your site directly in search results.', 'schema-plugin' ),
				'action_key'  => 'entitySettingsUrl',
				'action_label' => __( 'Go to Entity settings', 'schema-plugin' ),
			),
			'default_rules_reviewed' => array(
				'label'       => __( 'Review your Default Rules', 'schema-plugin' ),
				'description' => __( 'Check that each type of page on your site is tagged the way you expect.', 'schema-plugin' ),
				'action_key'  => 'defaultRulesUrl',
				'action_label' => __( 'Go to Default Rules', 'schema-plugin' ),
			),
		);

		if ( 'local_business' === $entity_type ) {
			$defs['local_business_address'] = array(
				'label'       => __( 'Add your business address', 'schema-plugin' ),
				'description' => __( 'Google requires an address for Local Business rich results.', 'schema-plugin' ),
				'action_key'  => 'entitySettingsUrl',
				'action_label' => __( 'Go to Entity settings', 'schema-plugin' ),
			);
		}

		if ( Schema_Settings::is_woocommerce_active() ) {
			$defs['woocommerce_exclusions'] = array(
				'label'       => __( 'Exclude cart, checkout, and account pages', 'schema-plugin' ),
				'description' => __( 'These pages usually should not carry product or article schema.', 'schema-plugin' ),
				'action_key'  => 'exclusionsUrl',
				'action_label' => __( 'Go to Exclusions', 'schema-plugin' ),
			);
		} else {
			$defs['exclusions_reviewed'] = array(
				'label'       => __( 'Check which pages are excluded from schema', 'schema-plugin' ),
				'description' => __( 'Search, 404, and admin pages are always excluded -- review any extra patterns here.', 'schema-plugin' ),
				'action_key'  => 'exclusionsUrl',
				'action_label' => __( 'Go to Exclusions', 'schema-plugin' ),
			);
		}

		return $defs;
	}

	/**
	 * A stored "dismissed" status always wins. Otherwise, a handful of
	 * recommendations can be positively auto-detected as complete rather
	 * than requiring the user to say so themselves.
	 */
	private static function detect_auto_status( string $key ): ?string {
		switch ( $key ) {
			case 'search_box':
				return Schema_Entity_Registry::get_website_search_enabled() ? 'completed' : null;

			case 'social_profiles':
				$fields = Schema_Entity_Registry::get_entity_fields();
				return ! empty( $fields['sameAs'] ) ? 'completed' : null;

			case 'local_business_address':
				$fields = Schema_Entity_Registry::get_entity_fields();
				return ! empty( $fields['address'] ) ? 'completed' : null;

			case 'woocommerce_exclusions':
				$patterns = wp_list_pluck(
					array_filter( Schema_Settings::get_backend_exclusions(), static fn( $rule ) => ! empty( $rule['enabled'] ) ),
					'pattern'
				);
				$needed = array( '/cart/*', '/checkout/*', '/my-account/*' );
				return empty( array_diff( $needed, $patterns ) ) ? 'completed' : null;

			default:
				return null;
		}
	}

	public static function get_recommendations( string $entity_type ): array {
		$defs   = self::get_recommendation_definitions( $entity_type );
		$stored = get_option( self::OPTION_RECOMMENDATIONS, array() );
		$out    = array();

		foreach ( $defs as $key => $def ) {
			$status = $stored[ $key ] ?? 'pending';
			if ( 'dismissed' !== $status ) {
				$auto = self::detect_auto_status( $key );
				if ( $auto ) {
					$status = $auto;
				}
			}
			$out[] = array_merge( array( 'key' => $key, 'status' => $status ), $def );
		}

		return $out;
	}

	/**
	 * @return true|WP_Error
	 */
	public static function set_recommendation_status( string $entity_type, string $key, string $status ) {
		if ( ! in_array( $status, array( 'dismissed', 'completed' ), true ) ) {
			return new WP_Error( 'invalid_status', __( 'Status must be "dismissed" or "completed".', 'schema-plugin' ), array( 'status' => 400 ) );
		}
		$defs = self::get_recommendation_definitions( $entity_type );
		if ( ! isset( $defs[ $key ] ) ) {
			return new WP_Error( 'invalid_recommendation_key', __( 'Unknown recommendation for the current site type.', 'schema-plugin' ), array( 'status' => 422 ) );
		}

		$stored          = get_option( self::OPTION_RECOMMENDATIONS, array() );
		$stored[ $key ]  = $status;
		update_option( self::OPTION_RECOMMENDATIONS, $stored );

		return true;
	}
}
