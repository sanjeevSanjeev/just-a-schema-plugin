<?php
/**
 * Schema_Admin
 *
 * Admin menu registration, per-screen script/style enqueueing (CLAUDE.md
 * Section 9: "Enqueue React build output only on relevant wp-admin
 * screens... never load the admin JS bundle on the public-facing site"),
 * activation redirect, and the bootstrap data handed to React.
 *
 * Menu, kept deliberately small:
 *   Schema
 *   ├─ Dashboard  home: status, completeness, features, re-run wizard
 *   ├─ Settings   tabs: Site entity · Default rules · Exclusions
 *   │             (+ Network identity when that feature is on)
 *   └─ Content    only when a content feature is on; one tab per feature
 * The wizard has no menu entry (reached from the Dashboard and the
 * first-run redirect). Pre-restructure page slugs redirect to their new
 * tab so old links and bookmarks keep working.
 *
 * @package Schema_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema_Admin {

	const PAGE_DASHBOARD = 'schema-plugin-dashboard';
	const PAGE_SETTINGS  = 'schema-plugin-settings';
	const PAGE_CONTENT   = 'schema-plugin-content';
	const PAGE_WIZARD    = 'schema-plugin-wizard';

	/**
	 * Old slug => [ new page, tab ].
	 */
	const LEGACY_PAGES = array(
		'schema-plugin-entity'     => array( self::PAGE_SETTINGS, 'entity' ),
		'schema-plugin-rules'      => array( self::PAGE_SETTINGS, 'rules' ),
		'schema-plugin-exclusions' => array( self::PAGE_SETTINGS, 'exclusions' ),
		'schema-plugin-network'    => array( self::PAGE_SETTINGS, 'network' ),
		'schema-plugin-services'   => array( self::PAGE_CONTENT, 'service' ),
		'schema-plugin-teams'      => array( self::PAGE_CONTENT, 'team' ),
		'schema-plugin-locations'  => array( self::PAGE_CONTENT, 'location' ),
		'schema-plugin-events'     => array( self::PAGE_CONTENT, 'event' ),
	);

	/** @var array<string,string|false> */
	private static array $hooks = array();

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menus' ) );
		add_action( 'admin_head', array( __CLASS__, 'hide_wizard_menu_item' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_after_activation' ) );
	}

	/**
	 * admin_url(), not menu_page_url(): works in REST requests too, and for
	 * the wizard after its menu item is hidden.
	 */
	public static function page_url( string $page, string $tab = '' ): string {
		return admin_url( 'admin.php?page=' . $page . ( $tab ? '&tab=' . $tab : '' ) );
	}

	public static function register_menus(): void {
		// Same slug as the first submenu: the WP idiom for relabeling the
		// auto-created first item instead of duplicating the menu title.
		self::$hooks['dashboard'] = add_menu_page(
			__( 'Schema', 'schema-plugin' ),
			__( 'Schema', 'schema-plugin' ),
			'manage_options',
			self::PAGE_DASHBOARD,
			array( __CLASS__, 'render_dashboard_page' ),
			'dashicons-networking',
			80
		);

		add_submenu_page(
			self::PAGE_DASHBOARD,
			__( 'Schema: Dashboard', 'schema-plugin' ),
			__( 'Dashboard', 'schema-plugin' ),
			'manage_options',
			self::PAGE_DASHBOARD,
			array( __CLASS__, 'render_dashboard_page' )
		);

		self::$hooks['settings'] = add_submenu_page(
			self::PAGE_DASHBOARD,
			__( 'Schema: Settings', 'schema-plugin' ),
			__( 'Settings', 'schema-plugin' ),
			'manage_options',
			self::PAGE_SETTINGS,
			array( __CLASS__, 'render_settings_page' )
		);

		if ( Schema_Linked_Entity_Types::get_enabled_registry() ) {
			self::$hooks['content'] = add_submenu_page(
				self::PAGE_DASHBOARD,
				__( 'Schema: Content', 'schema-plugin' ),
				__( 'Content', 'schema-plugin' ),
				'manage_options',
				self::PAGE_CONTENT,
				array( __CLASS__, 'render_content_page' )
			);
		}

		// Registered normally so it gets a proper page title; its menu item
		// is removed in admin_head (after the title is resolved).
		self::$hooks['wizard'] = add_submenu_page(
			self::PAGE_DASHBOARD,
			__( 'Schema Setup Wizard', 'schema-plugin' ),
			__( 'Setup Wizard', 'schema-plugin' ),
			'manage_options',
			self::PAGE_WIZARD,
			array( __CLASS__, 'render_wizard_page' )
		);

		// Hidden pages for the old slugs; they redirect before any output.
		foreach ( self::LEGACY_PAGES as $slug => $target ) {
			$hook = add_submenu_page( '', '', '', 'manage_options', $slug, '__return_null' );
			if ( $hook ) {
				add_action(
					'load-' . $hook,
					static function () use ( $target ) {
						wp_safe_redirect( self::legacy_target_url( $target[0], $target[1] ) );
						exit;
					}
				);
			}
		}
	}

	/**
	 * An old link to a feature that's now off goes to the Dashboard's
	 * Features card (where it can be turned on) rather than a page that
	 * no longer exists.
	 */
	private static function legacy_target_url( string $page, string $tab ): string {
		$feature = self::PAGE_CONTENT === $page ? $tab : ( 'network' === $tab ? 'network' : '' );
		if ( $feature && ! Schema_Features::is_enabled( $feature ) ) {
			return self::page_url( self::PAGE_DASHBOARD ) . '#schema-features-heading';
		}
		return self::page_url( $page, $tab );
	}

	public static function hide_wizard_menu_item(): void {
		remove_submenu_page( self::PAGE_DASHBOARD, self::PAGE_WIZARD );
	}

	public static function render_dashboard_page(): void {
		echo '<div class="wrap schema-plugin-admin"><div id="schema-plugin-dashboard-root"></div></div>';
	}

	public static function render_settings_page(): void {
		echo '<div class="wrap schema-plugin-admin"><div id="schema-plugin-settings-root"></div></div>';
	}

	public static function render_content_page(): void {
		echo '<div class="wrap schema-plugin-admin"><div id="schema-plugin-linked-entity-root"></div></div>';
	}

	public static function render_wizard_page(): void {
		echo '<div class="wrap schema-plugin-admin schema-plugin-wizard-wrap"><div id="schema-plugin-wizard-root"></div></div>';
	}

	/**
	 * Content tab URL for a linked entity type (used by Completeness).
	 */
	public static function linked_entity_page_url( string $entity_type ): string {
		return self::page_url( self::PAGE_CONTENT, $entity_type );
	}

	public static function enqueue_admin_assets( string $hook ): void {
		$bundles = array(
			'dashboard' => 'dashboard',
			'settings'  => 'settings',
			'content'   => 'linked-entity-manager',
			'wizard'    => 'wizard',
		);
		foreach ( $bundles as $key => $bundle ) {
			if ( isset( self::$hooks[ $key ] ) && $hook === self::$hooks[ $key ] ) {
				self::enqueue_bundle( $bundle );
				return;
			}
		}
	}

	/**
	 * Only in the post editor, for post types that have public pages --
	 * not the Site Editor, templates, patterns or navigation.
	 */
	public static function enqueue_editor_assets(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->base || ! $screen->post_type || ! is_post_type_viewable( $screen->post_type ) ) {
			return;
		}
		self::enqueue_bundle( 'post-editor' );
	}

	private static function enqueue_bundle( string $handle_slug ): void {
		$asset_file = SCHEMA_PLUGIN_DIR . 'build/' . $handle_slug . '.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset  = require $asset_file;
		$handle = 'schema-plugin-' . $handle_slug;

		// Pages with image/logo fields use the WP media library picker
		// (<MediaField>); Gutenberg loads it itself, our pages don't.
		if ( in_array( $handle_slug, array( 'wizard', 'settings', 'linked-entity-manager' ), true ) ) {
			wp_enqueue_media();
		}

		wp_enqueue_script(
			$handle,
			SCHEMA_PLUGIN_URL . 'build/' . $handle_slug . '.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( $handle, 'schema-plugin', SCHEMA_PLUGIN_DIR . 'languages' );
		wp_localize_script( $handle, 'SchemaPluginData', self::get_bootstrap_data() );

		self::enqueue_shared_style( $asset['version'] );
	}

	/**
	 * Every bundle imports the same shared/style.scss (CLAUDE.md Section
	 * 10.7.A: one custom stylesheet for layout/spacing not covered by
	 * @wordpress/components), so wp-scripts' webpack config dedupes it
	 * into a single chunk rather than one file per entry. Enqueued once,
	 * by its actual emitted name, regardless of which bundle loaded it.
	 */
	private static function enqueue_shared_style( string $version ): void {
		if ( wp_style_is( 'schema-plugin-admin', 'enqueued' ) ) {
			return;
		}

		$css_path = SCHEMA_PLUGIN_DIR . 'build/style-post-editor.css';
		if ( ! file_exists( $css_path ) ) {
			return;
		}

		wp_enqueue_style(
			'schema-plugin-admin',
			SCHEMA_PLUGIN_URL . 'build/style-post-editor.css',
			array( 'wp-components' ),
			$version
		);
		wp_style_add_data( 'schema-plugin-admin', 'rtl', 'replace' );
	}

	/**
	 * Static config + current state handed to every React bundle. Locked
	 * type list, badges, field defs, and composition/disambiguation
	 * signals are read-only PHP-side config -- not user state -- so they
	 * travel via wp_localize_script rather than an extra REST round trip;
	 * everything React treats as live, editable data goes through the
	 * schema-plugin/v1 REST API (CLAUDE.md Architecture).
	 *
	 * The *Url keys predate the Settings/Content restructure and now point
	 * at the matching tab, so existing React code needs no changes.
	 */
	private static function get_bootstrap_data(): array {
		$content_types = array();
		foreach ( Schema_Linked_Entity_Types::get_enabled_registry() as $key => $config ) {
			$content_types[] = array(
				'key'   => $key,
				'label' => $config['label'],
			);
		}

		return array(
			'restUrl'                => esc_url_raw( rest_url( Schema_Rest_Controller::NAMESPACE_V1 ) ),
			'dashboardUrl'           => self::page_url( self::PAGE_DASHBOARD ),
			'settingsUrl'            => self::page_url( self::PAGE_SETTINGS ),
			'contentUrl'             => $content_types ? self::page_url( self::PAGE_CONTENT ) : '',
			'entitySettingsUrl'      => self::page_url( self::PAGE_SETTINGS, 'entity' ),
			'defaultRulesUrl'        => self::page_url( self::PAGE_SETTINGS, 'rules' ),
			'exclusionsUrl'          => self::page_url( self::PAGE_SETTINGS, 'exclusions' ),
			'networkIdentityUrl'     => self::page_url( self::PAGE_SETTINGS, 'network' ),
			'wizardUrl'              => self::page_url( self::PAGE_WIZARD ),
			'features'               => Schema_Features::get_status(),
			'featureSuggestions'     => array_combine(
				array_keys( Schema_Types::get_locked_types() ),
				array_map( array( 'Schema_Features', 'get_suggested' ), array_keys( Schema_Types::get_locked_types() ) )
			),
			'contentTypes'           => $content_types,
			'lockedTypes'            => Schema_Types::get_locked_types(),
			'typeStatus'             => Schema_Types::get_type_status(),
			'compositionSuggestion'  => Schema_Types::get_composition_suggestion(),
			'disambiguationRules'    => Schema_Types::get_disambiguation_rules(),
			'wizardCopy'             => Schema_Types::get_wizard_copy(),
			'friendlySchemaTypes'    => Schema_Types::get_friendly_schema_types(),
			'postTypeOptions'        => Schema_Types::get_post_type_options(),
			'fieldDefs'              => array(
				'tier1'         => Schema_Types::TIER1_COMMON_FIELDS,
				'localBusiness' => Schema_Types::LOCAL_BUSINESS_FIELDS,
				'entityExtra'   => Schema_Types::get_entity_extra_fields(),
				'genericBlock'  => Schema_Types::GENERIC_BLOCK_FIELDS,
			),
			'wizardCompletedAt'      => Schema_Settings::get_wizard_completed_at(),
			'isWooCommerceActive'    => Schema_Settings::is_woocommerce_active(),
			'responsiveBreakpoint'   => 782,
			// CLAUDE.md 10.6.C: pre-fill currency from the store when known,
			// otherwise leave blank rather than guess.
			'defaultCurrency'        => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			// BCP 47 tag for Intl.DisplayNames (country/currency names).
			'locale'                 => str_replace( '_', '-', get_user_locale() ),
			'textDomain'             => 'schema-plugin',
		);
	}

	/**
	 * Redirects once, right after activation -- to the wizard on a
	 * genuine first run, or straight to the Dashboard on a reactivation
	 * (deactivating never deletes options/post meta, so a previously
	 * configured site's data is still there).
	 */
	public static function maybe_redirect_after_activation(): void {
		if ( ! get_transient( 'schema_plugin_activation_redirect' ) ) {
			return;
		}
		delete_transient( 'schema_plugin_activation_redirect' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of core's bulk-activation flag.
		if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) || isset( $_GET['activate-multi'] ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		wp_safe_redirect( self::page_url( Schema_Settings::get_wizard_completed_at() ? self::PAGE_DASHBOARD : self::PAGE_WIZARD ) );
		exit;
	}
}
