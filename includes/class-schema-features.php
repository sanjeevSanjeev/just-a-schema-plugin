<?php
/**
 * Schema_Features
 *
 * Optional features, so a site only carries what it uses. A feature that
 * is off costs nothing: no menu entry, no REST routes, no admin script,
 * no frontend work (its option isn't read), and it's left out of
 * Completeness. Turning one off never deletes its data; turning it back
 * on restores it.
 *
 * Keys match Schema_Linked_Entity_Types registry keys for the four
 * content features, plus 'network' for Network Identity.
 *
 * @package Schema_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema_Features {

	const OPTION = 'schema_plugin_features';

	/**
	 * `suggested_for`: locked site types the wizard pre-ticks it for.
	 */
	public static function definitions(): array {
		return array(
			'service'  => array(
				'label'         => __( 'Services', 'schema-plugin' ),
				'description'   => __( 'Describe the services you offer, each linked to its page.', 'schema-plugin' ),
				'suggested_for' => array( 'local_business', 'general_business' ),
			),
			'location' => array(
				'label'         => __( 'Locations', 'schema-plugin' ),
				'description'   => __( 'Branches or stores, each with its own address and opening hours.', 'schema-plugin' ),
				'suggested_for' => array( 'local_business' ),
			),
			'event'    => array(
				'label'         => __( 'Events', 'schema-plugin' ),
				'description'   => __( 'Upcoming events with dates and venues.', 'schema-plugin' ),
				'suggested_for' => array( 'local_business', 'blog_publication' ),
			),
			'team'     => array(
				'label'         => __( 'Team & authors', 'schema-plugin' ),
				'description'   => __( 'Team members, and schema fields (job title, photo, social profiles) on user profiles for post authors.', 'schema-plugin' ),
				'suggested_for' => array( 'general_business', 'blog_publication' ),
			),
			'network'  => array(
				'label'         => __( 'Network identity', 'schema-plugin' ),
				'description'   => __( 'Link this site to a main brand site it belongs to (subdomain, subdirectory or separate install).', 'schema-plugin' ),
				'suggested_for' => array(),
			),
		);
	}

	/** @return string[] */
	public static function get_enabled(): array {
		$saved = get_option( self::OPTION, array() );
		return is_array( $saved ) ? array_values( array_intersect( array_keys( self::definitions() ), $saved ) ) : array();
	}

	public static function is_enabled( string $key ): bool {
		return in_array( $key, self::get_enabled(), true );
	}

	/**
	 * @param string[] $keys
	 * @return string[] The enabled set as saved.
	 */
	public static function set_enabled( array $keys ): array {
		$clean = array_values( array_intersect( array_keys( self::definitions() ), array_map( 'sanitize_key', $keys ) ) );
		update_option( self::OPTION, $clean );
		return $clean;
	}

	/**
	 * What the wizard pre-ticks for a site type. Network identity is
	 * suggested only when this install looks like part of a bigger site
	 * (a multisite subsite, or a WordPress in a subdirectory).
	 *
	 * @return string[]
	 */
	public static function get_suggested( string $site_type ): array {
		$suggested = array();
		foreach ( self::definitions() as $key => $definition ) {
			if ( in_array( $site_type, $definition['suggested_for'], true ) ) {
				$suggested[] = $key;
			}
		}
		if ( '' !== Schema_Network_Identity::get_suggested_parent_url() ) {
			$suggested[] = 'network';
		}
		return $suggested;
	}

	/**
	 * Definitions plus enabled state and how much data each holds, for
	 * the Dashboard's Features card and the wizard.
	 */
	public static function get_status(): array {
		$enabled = self::get_enabled();
		$status  = array();
		foreach ( self::definitions() as $key => $definition ) {
			$status[] = array_merge(
				$definition,
				array(
					'key'                => $key,
					'enabled'            => in_array( $key, $enabled, true ),
					'entry_count'        => self::entry_count( $key ),
					'suggested_anywhere' => 'network' === $key && '' !== Schema_Network_Identity::get_suggested_parent_url(),
				)
			);
		}
		return $status;
	}

	private static function entry_count( string $key ): int {
		if ( 'network' === $key ) {
			$saved = get_option( Schema_Network_Identity::OPTION, array() );
			return is_array( $saved ) && 'member' === ( $saved['role'] ?? '' ) ? 1 : 0;
		}
		return count( Schema_Linked_Entity_Store::get_all( $key ) );
	}

	/**
	 * One-time upgrade, run until the option exists: enable every
	 * feature that already has data or is configured, so nothing
	 * disappears from an existing site's output. A fresh install starts
	 * with nothing enabled; the wizard pre-ticks suggestions.
	 *
	 * Also stops admin-only options autoloading on every frontend request.
	 */
	public static function maybe_upgrade(): void {
		if ( null !== get_option( self::OPTION, null ) ) {
			return;
		}

		$enabled = array();
		foreach ( array_keys( self::definitions() ) as $key ) {
			if ( self::entry_count( $key ) > 0 ) {
				$enabled[] = $key;
			}
		}
		add_option( self::OPTION, $enabled );

		if ( function_exists( 'wp_set_option_autoload_values' ) ) {
			wp_set_option_autoload_values(
				array(
					Schema_Dashboard::OPTION_RECOMMENDATIONS => false,
					Schema_Settings::OPTION_WIZARD_COMPLETED => false,
				)
			);
		}
	}
}
