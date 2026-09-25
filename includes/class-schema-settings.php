<?php
/**
 * Schema_Settings
 *
 * Thin wrapper around wp_options for exclusion lists and toggles.
 * See CLAUDE.md Section 3.5. The entity registry (3.1) and default rules
 * (3.4) get their own, richer classes.
 *
 * @package Schema_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema_Settings {

	const OPTION_BACKEND_EXCLUSIONS = 'schema_plugin_backend_exclusions';
	const OPTION_EXCLUDE_SEARCH     = 'schema_plugin_exclude_search';
	const OPTION_EXCLUDE_404        = 'schema_plugin_exclude_404';
	const OPTION_EXCLUDE_NOINDEX    = 'schema_plugin_exclude_noindex';
	const OPTION_WIZARD_COMPLETED   = 'schema_plugin_wizard_completed_at';

	/**
	 * @param string $key     One of 'exclude_search', 'exclude_404', 'exclude_noindex'.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public static function get( string $key, $default = false ) {
		$map = array(
			'exclude_search'  => self::OPTION_EXCLUDE_SEARCH,
			'exclude_404'     => self::OPTION_EXCLUDE_404,
			'exclude_noindex' => self::OPTION_EXCLUDE_NOINDEX,
		);
		$option = $map[ $key ] ?? $key;
		return get_option( $option, $default );
	}

	public static function set( string $key, $value ): void {
		$map = array(
			'exclude_search'  => self::OPTION_EXCLUDE_SEARCH,
			'exclude_404'     => self::OPTION_EXCLUDE_404,
			'exclude_noindex' => self::OPTION_EXCLUDE_NOINDEX,
		);
		$option = $map[ $key ] ?? $key;
		update_option( $option, $value );
	}

	public static function get_backend_exclusions(): array {
		$exclusions = get_option( self::OPTION_BACKEND_EXCLUSIONS, null );
		if ( ! is_array( $exclusions ) ) {
			return self::default_backend_exclusions();
		}
		return $exclusions;
	}

	public static function save_backend_exclusions( array $exclusions ): void {
		$clean = array();
		foreach ( $exclusions as $rule ) {
			if ( empty( $rule['pattern'] ) ) {
				continue;
			}
			$clean[] = array(
				'pattern' => sanitize_text_field( $rule['pattern'] ),
				'enabled' => ! empty( $rule['enabled'] ),
				'source'  => isset( $rule['source'] ) ? sanitize_text_field( $rule['source'] ) : 'custom',
			);
		}
		update_option( self::OPTION_BACKEND_EXCLUSIONS, $clean );
	}

	/**
	 * The three generic defaults always ship. WooCommerce paths are
	 * suggested (CLAUDE.md: "Auto-suggest (not auto-add)") only at
	 * activation time via seed_defaults_on_activation(), never injected
	 * silently after the fact.
	 */
	public static function default_backend_exclusions(): array {
		return array(
			array(
				'pattern' => '/my-account/*',
				'enabled' => true,
				'source'  => 'default',
			),
			array(
				'pattern' => '/cart/*',
				'enabled' => true,
				'source'  => 'default',
			),
			array(
				'pattern' => '/checkout/*',
				'enabled' => true,
				'source'  => 'default',
			),
		);
	}

	public static function is_woocommerce_active(): bool {
		if ( in_array( 'woocommerce/woocommerce.php', (array) get_option( 'active_plugins', array() ), true ) ) {
			return true;
		}
		if ( is_multisite() ) {
			$network_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
			return array_key_exists( 'woocommerce/woocommerce.php', $network_plugins );
		}
		return false;
	}

	/**
	 * Runs once on plugin activation. Never overwrites existing option
	 * values on reactivation (CLAUDE.md Section 9).
	 */
	public static function seed_defaults_on_activation(): void {
		if ( ! is_array( get_option( self::OPTION_BACKEND_EXCLUSIONS, null ) ) ) {
			// WooCommerce paths are already part of the generic defaults
			// above; the "auto-suggest" behavior lives in the wizard UI
			// which reads is_woocommerce_active() to show a suggestion
			// chip rather than silently changing stored state.
			update_option( self::OPTION_BACKEND_EXCLUSIONS, self::default_backend_exclusions() );
		}
		if ( '' === get_option( self::OPTION_EXCLUDE_SEARCH, '' ) ) {
			update_option( self::OPTION_EXCLUDE_SEARCH, true );
		}
		if ( '' === get_option( self::OPTION_EXCLUDE_404, '' ) ) {
			update_option( self::OPTION_EXCLUDE_404, true );
		}
		if ( '' === get_option( self::OPTION_EXCLUDE_NOINDEX, '' ) ) {
			update_option( self::OPTION_EXCLUDE_NOINDEX, true );
		}
	}

	public static function get_wizard_completed_at() {
		return get_option( self::OPTION_WIZARD_COMPLETED, '' );
	}

	/**
	 * @return bool True only if THIS call is what completed the wizard for
	 *              the first time -- used to decide whether to show the
	 *              first-run landing screen (CLAUDE.md Section 7, item 6).
	 */
	public static function mark_wizard_completed(): bool {
		if ( self::get_wizard_completed_at() ) {
			return false;
		}
		update_option( self::OPTION_WIZARD_COMPLETED, current_time( 'mysql' ) );
		return true;
	}
}
