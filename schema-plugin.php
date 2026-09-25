<?php
/**
 * Plugin Name:       Schema Plugin
 * Description:       Flexible JSON-LD structured data with non-destructive schema blocks, a data-driven Default Rules engine, and a raw JSON-LD escape hatch.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            Personal use
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       schema-plugin
 * Domain Path:       /languages
 *
 * @package Schema_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SCHEMA_PLUGIN_VERSION', '0.1.0' );
define( 'SCHEMA_PLUGIN_FILE', __FILE__ );
define( 'SCHEMA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCHEMA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Classes load on first use, so a frontend request only loads what wp_head
 * touches; the REST controllers, sanitizer, admin and completeness code
 * never load for visitors. Explicit map: three scaffolded files predate
 * the class-schema-* naming.
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		static $map = array(
			'Schema_Activation'                    => 'class-schema-activation.php',
			'Schema_Admin'                         => 'class-schema-admin.php',
			'Schema_Author_Profile'                => 'class-schema-author-profile.php',
			'Schema_Completeness'                  => 'class-schema-completeness.php',
			'Schema_Dashboard'                     => 'class-schema-dashboard.php',
			'Schema_Default_Rules'                 => 'class-schema-default-rules.php',
			'Schema_Entity_Registry'               => 'class-schema-entity-registry.php',
			'Schema_Entity_Resolver'               => 'class-entity-resolver.php',
			'Schema_Features'                      => 'class-schema-features.php',
			'Schema_Field_Migrator'                => 'class-schema-field-migrator.php',
			'Schema_Graph_Builder'                 => 'class-graph-builder.php',
			'Schema_Linked_Content_Entity'         => 'class-linked-content-entity.php',
			'Schema_Linked_Entity_Rest_Controller' => 'class-schema-linked-entity-rest-controller.php',
			'Schema_Linked_Entity_Store'           => 'class-schema-linked-entity-store.php',
			'Schema_Linked_Entity_Types'           => 'class-schema-linked-entity-types.php',
			'Schema_Network_Identity'              => 'class-schema-network-identity.php',
			'Schema_Post_Meta'                     => 'class-schema-post-meta.php',
			'Schema_Rest_Controller'               => 'class-schema-rest-controller.php',
			'Schema_Sanitizer'                     => 'class-schema-sanitizer.php',
			'Schema_Settings'                      => 'class-schema-settings.php',
			'Schema_Token_Resolver'                => 'class-schema-token-resolver.php',
			'Schema_Types'                         => 'class-schema-types.php',
		);
		if ( isset( $map[ $class_name ] ) ) {
			require_once SCHEMA_PLUGIN_DIR . 'includes/' . $map[ $class_name ];
		}
	}
);

register_activation_hook( __FILE__, array( 'Schema_Activation', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Schema_Activation', 'deactivate' ) );

add_action( 'plugins_loaded', 'schema_plugin_bootstrap' );

function schema_plugin_bootstrap(): void {
	// Not hosted on WordPress.org, so bundled /languages files are not auto-loaded.
	load_plugin_textdomain( // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
		'schema-plugin',
		false,
		dirname( plugin_basename( SCHEMA_PLUGIN_FILE ) ) . '/languages'
	);

	// Creates the features option on first run after upgrading (enabling
	// whatever already has data); a no-op afterwards.
	Schema_Features::maybe_upgrade();

	Schema_Graph_Builder::init();

	// Class-string callables: the controllers only load when REST runs.
	add_action( 'rest_api_init', array( 'Schema_Rest_Controller', 'register_routes' ) );
	add_action( 'rest_api_init', array( 'Schema_Linked_Entity_Rest_Controller', 'register_routes' ) );

	if ( is_admin() ) {
		Schema_Admin::init();
		// Profile screen fields belong to the "Team & authors" feature.
		if ( Schema_Features::is_enabled( 'team' ) ) {
			Schema_Author_Profile::init();
		}
	}
}
