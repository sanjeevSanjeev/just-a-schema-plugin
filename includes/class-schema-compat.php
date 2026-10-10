<?php
/**
 * Schema_Compat
 *
 * Yoast SEO and Rank Math print their own JSON-LD graph. Left alone, a page
 * carries two graphs that define the same @ids (#website, #article) and two
 * different site entities. In "replace" mode (the default) this plugin turns
 * their graph off, but only on requests where this plugin is actually
 * printing one, so an excluded page is never left with no schema at all.
 *
 * WooCommerce is detected and reported only; its product markup is not
 * suppressed.
 *
 * @package Schema_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Switches off other SEO plugins' schema when this plugin prints its own.
 */
class Schema_Compat {

	const OPTION = 'schema_plugin_compat_mode';

	const MODE_REPLACE = 'replace';
	const MODE_BOTH    = 'both';

	/**
	 * Hooks the suppression filters unless the mode is "both".
	 */
	public static function init(): void {
		if ( self::MODE_REPLACE !== self::get_mode() ) {
			return;
		}
		// Yoast: returning false from this filter skips its JSON-LD output.
		add_filter( 'wpseo_json_ld_output', array( __CLASS__, 'filter_yoast' ), 99 );
		// Rank Math: an empty data array prints nothing.
		add_filter( 'rank_math/json_ld', array( __CLASS__, 'filter_rank_math' ), 99 );
	}

	/**
	 * The saved mode, defaulting to "replace".
	 *
	 * @return string
	 */
	public static function get_mode(): string {
		$mode = get_option( self::OPTION, self::MODE_REPLACE );
		return self::MODE_BOTH === $mode ? self::MODE_BOTH : self::MODE_REPLACE;
	}

	/**
	 * Turns Yoast's JSON-LD off when this plugin is printing a graph.
	 *
	 * @param mixed $output Yoast's current value.
	 * @return mixed
	 */
	public static function filter_yoast( $output ) {
		return self::we_are_printing() ? false : $output;
	}

	/**
	 * Empties Rank Math's JSON-LD when this plugin is printing a graph.
	 *
	 * @param mixed $data Rank Math's JSON-LD data.
	 * @return mixed
	 */
	public static function filter_rank_math( $data ) {
		return self::we_are_printing() ? array() : $data;
	}

	/**
	 * Active plugins that print schema of their own.
	 *
	 * @return array<int, array{key: string, label: string, suppressed: bool}>
	 */
	public static function detect(): array {
		$replace  = self::MODE_REPLACE === self::get_mode();
		$detected = array();

		if ( defined( 'WPSEO_VERSION' ) ) {
			$detected[] = array(
				'key'        => 'yoast',
				'label'      => 'Yoast SEO',
				'suppressed' => $replace,
			);
		}
		if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
			$detected[] = array(
				'key'        => 'rank_math',
				'label'      => 'Rank Math',
				'suppressed' => $replace,
			);
		}
		if ( class_exists( 'WooCommerce' ) ) {
			$detected[] = array(
				'key'        => 'woocommerce',
				'label'      => 'WooCommerce',
				'suppressed' => false,
			);
		}

		return $detected;
	}

	/**
	 * Mode plus detected plugins, for the dashboard and settings.
	 *
	 * @return array{mode: string, plugins: array}
	 */
	public static function get_status(): array {
		return array(
			'mode'    => self::get_mode(),
			'plugins' => self::detect(),
		);
	}

	/**
	 * Saves the mode; anything other than "both" means "replace".
	 *
	 * @param string $mode Requested mode.
	 */
	public static function set_mode( string $mode ): void {
		update_option( self::OPTION, self::MODE_BOTH === $mode ? self::MODE_BOTH : self::MODE_REPLACE );
	}

	/**
	 * Whether this plugin prints a graph on the current request.
	 *
	 * @return bool
	 */
	private static function we_are_printing(): bool {
		$graph = Schema_Graph_Builder::build_for_current_request();
		return ! empty( $graph );
	}
}
