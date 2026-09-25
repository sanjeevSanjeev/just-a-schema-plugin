<?php
/**
 * Schema_Admin
 *
 * Admin menu registration, per-screen script/style enqueueing (CLAUDE.md
 * Section 9: "Enqueue React build output only on relevant wp-admin
 * screens... never load the admin JS bundle on the public-facing site"),
 * activation redirect, and the bootstrap data handed to React.
 *
 * @package Schema_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema_Admin {

	const PAGE_WIZARD     = 'schema-plugin-wizard';
	const PAGE_ENTITY     = 'schema-plugin-entity';
	const PAGE_RULES      = 'schema-plugin-rules';
	const PAGE_EXCLUSIONS = 'schema-plugin-exclusions';
	const PAGE_DASHBOARD  = 'schema-plugin-dashboard';
	const PAGE_NETWORK    = 'schema-plugin-network';

	/** @var array<string,string|false> */
	private static array $hooks = array();

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_after_activation' ) );
	}

	/**
	 * One top-level "Schema" menu, Dashboard as its primary/first
	 * submenu item, with Entity / Default Rules / Exclusions / Setup
	 * Wizard alongside it -- the Wizard is now a normal, always-visible
	 * menu entry rather than a hidden direct-link-only page, since
	 * re-running it is a supported, expected action, not an edge case.
	 * The slug passed to add_menu_page() is deliberately the same as the
	 * first submenu page below -- the standard WP idiom for relabeling
	 * the auto-created first submenu item instead of it duplicating the
	 * top-level menu's own title.
	 */
	public static function register_menus(): void {
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

		self::$hooks['entity'] = add_submenu_page(
			self::PAGE_DASHBOARD,
			__( 'Schema: Entity', 'schema-plugin' ),
			__( 'Entity', 'schema-plugin' ),
			'manage_options',
			self::PAGE_ENTITY,
			array( __CLASS__, 'render_entity_page' )
		);

		self::$hooks['rules'] = add_submenu_page(
			self::PAGE_DASHBOARD,
			__( 'Schema: Default Rules', 'schema-plugin' ),
			__( 'Default Rules', 'schema-plugin' ),
			'manage_options',
			self::PAGE_RULES,
			array( __CLASS__, 'render_rules_page' )
		);

		self::$hooks['exclusions'] = add_submenu_page(
			self::PAGE_DASHBOARD,
			__( 'Schema: Exclusions', 'schema-plugin' ),
			__( 'Exclusions', 'schema-plugin' ),
			'manage_options',
			self::PAGE_EXCLUSIONS,
			array( __CLASS__, 'render_exclusions_page' )
		);

		// v1.1 Generic Linked Entity Manager -- one page per registered
		// entity type (Services, Locations, Events, ...), all mounting the
		// same bundle with a different data-entity-type.
		foreach ( Schema_Linked_Entity_Types::get_registry() as $entity_type => $config ) {
			self::$hooks[ 'linked_' . $entity_type ] = add_submenu_page(
				self::PAGE_DASHBOARD,
				sprintf(
					/* translators: %s: plural entity label, e.g. "Services". */
					__( 'Schema: %s', 'schema-plugin' ),
					$config['label']
				),
				$config['label'],
				'manage_options',
				self::linked_entity_page_slug( $entity_type ),
				static function () use ( $entity_type ) {
					self::render_linked_entity_page( $entity_type );
				}
			);
		}

		self::$hooks['network'] = add_submenu_page(
			self::PAGE_DASHBOARD,
			__( 'Schema: Network Identity', 'schema-plugin' ),
			__( 'Network Identity', 'schema-plugin' ),
			'manage_options',
			self::PAGE_NETWORK,
			array( __CLASS__, 'render_network_page' )
		);

		self::$hooks['wizard'] = add_submenu_page(
			self::PAGE_DASHBOARD,
			__( 'Schema Setup Wizard', 'schema-plugin' ),
			__( 'Setup Wizard', 'schema-plugin' ),
			'manage_options',
			self::PAGE_WIZARD,
			array( __CLASS__, 'render_wizard_page' )
		);
	}

	public static function render_dashboard_page(): void {
		echo '<div class="wrap schema-plugin-admin"><div id="schema-plugin-dashboard-root"></div></div>';
	}

	/**
	 * e.g. "service" -> "schema-plugin-services".
	 */
	public static function linked_entity_page_slug( string $entity_type ): string {
		return 'schema-plugin-' . $entity_type . 's';
	}

	public static function render_linked_entity_page( string $entity_type ): void {
		echo '<div class="wrap schema-plugin-admin"><div id="schema-plugin-linked-entity-root" data-entity-type="' . esc_attr( $entity_type ) . '"></div></div>';
	}

	public static function render_entity_page(): void {
		echo '<div class="wrap schema-plugin-admin"><div id="schema-plugin-entity-root"></div></div>';
	}

	public static function render_rules_page(): void {
		echo '<div class="wrap schema-plugin-admin"><div id="schema-plugin-rules-root"></div></div>';
	}

	public static function render_exclusions_page(): void {
		echo '<div class="wrap schema-plugin-admin"><div id="schema-plugin-exclusions-root"></div></div>';
	}

	public static function render_network_page(): void {
		echo '<div class="wrap schema-plugin-admin"><div id="schema-plugin-network-root"></div></div>';
	}

	public static function render_wizard_page(): void {
		echo '<div class="wrap schema-plugin-admin schema-plugin-wizard-wrap"><div id="schema-plugin-wizard-root"></div></div>';
	}

	public static function enqueue_admin_assets( string $hook ): void {
		$bundle = null;

		if ( isset( self::$hooks['dashboard'] ) && $hook === self::$hooks['dashboard'] ) {
			$bundle = 'dashboard';
		} elseif ( isset( self::$hooks['entity'] ) && $hook === self::$hooks['entity'] ) {
			$bundle = 'entity-settings';
		} elseif ( isset( self::$hooks['rules'] ) && $hook === self::$hooks['rules'] ) {
			$bundle = 'default-rules';
		} elseif ( isset( self::$hooks['exclusions'] ) && $hook === self::$hooks['exclusions'] ) {
			$bundle = 'exclusions';
		} elseif ( isset( self::$hooks['network'] ) && $hook === self::$hooks['network'] ) {
			$bundle = 'network-identity';
		} elseif ( in_array( $hook, self::linked_entity_hooks(), true ) ) {
			$bundle = 'linked-entity-manager';
		} elseif ( isset( self::$hooks['wizard'] ) && $hook === self::$hooks['wizard'] ) {
			$bundle = 'wizard';
		}

		if ( ! $bundle ) {
			return;
		}

		self::enqueue_bundle( $bundle );
	}

	/**
	 * @return string[] Page hook suffixes of every linked-entity manager page.
	 */
	private static function linked_entity_hooks(): array {
		$hooks = array();
		foreach ( self::$hooks as $key => $hook ) {
			if ( $hook && str_starts_with( $key, 'linked_' ) ) {
				$hooks[] = $hook;
			}
		}
		return $hooks;
	}

	public static function enqueue_editor_assets(): void {
		self::enqueue_bundle( 'post-editor' );
	}

	private static function enqueue_bundle( string $handle_slug ): void {
		$asset_file = SCHEMA_PLUGIN_DIR . 'build/' . $handle_slug . '.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset  = require $asset_file;
		$handle = 'schema-plugin-' . $handle_slug;

		// The wizard, Entity Settings, and linked-entity manager pages use
		// the WP media library picker (<MediaField>) for image/logo
		// fields; Gutenberg's own screens already load this, but our
		// standalone admin pages don't by default.
		if ( in_array( $handle_slug, array( 'wizard', 'entity-settings', 'linked-entity-manager' ), true ) ) {
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
	 */
	private static function get_bootstrap_data(): array {
		return array(
			'restUrl'                => esc_url_raw( rest_url( Schema_Rest_Controller::NAMESPACE_V1 ) ),
			'entitySettingsUrl'      => menu_page_url( self::PAGE_ENTITY, false ),
			'defaultRulesUrl'        => menu_page_url( self::PAGE_RULES, false ),
			'exclusionsUrl'          => menu_page_url( self::PAGE_EXCLUSIONS, false ),
			'wizardUrl'              => menu_page_url( self::PAGE_WIZARD, false ),
			'dashboardUrl'           => menu_page_url( self::PAGE_DASHBOARD, false ),
			'networkIdentityUrl'     => menu_page_url( self::PAGE_NETWORK, false ),
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
	 * configured site's data is still there). Whether the wizard has
	 * ever completed before is the single source of truth here, exactly
	 * as it already is for the wizard's own re-run pre-fill/reset logic.
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

		$destination = Schema_Settings::get_wizard_completed_at()
			? menu_page_url( self::PAGE_DASHBOARD, false )
			: menu_page_url( self::PAGE_WIZARD, false );

		wp_safe_redirect( $destination );
		exit;
	}
}
