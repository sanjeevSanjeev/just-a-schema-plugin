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
 * Data layer, in dependency order.
 */
require_once SCHEMA_PLUGIN_DIR . 'includes/class-schema-settings.php';
require_once SCHEMA_PLUGIN_DIR . 'includes/class-schema-types.php';
require_once SCHEMA_PLUGIN_DIR . 'includes/class-schema-sanitizer.php';
require_once SCHEMA_PLUGIN_DIR . 'includes/class-schema-field-migrator.php';
require_once SCHEMA_PLUGIN_DIR . 'includes/class-schema-token-resolver.php';
require_once SCHEMA_PLUGIN_DIR . 'includes/class-schema-network-identity.php';
require_once SCHEMA_PLUGIN_DIR . 'includes/class-schema-entity-registry.php';
require_once SCHEMA_PLUGIN_DIR . 'includes/class-linked-content-entity.php';
require_once SCHEMA_PLUGIN_DIR . 'includes/class-entity-resolver.php';
require_once SCHEMA_PLUGIN_DIR . 'includes/class-schema-post-meta.php';
require_once SCHEMA_PLUGIN_DIR . 'includes/class-schema-default-rules.php';
require_once SCHEMA_PLUGIN_DIR . 'includes/class-graph-builder.php';
require_once SCHEMA_PLUGIN_DIR . 'includes/class-schema-dashboard.php';
// v1.1 Phase 1 (Generic Linked Entity Manager) -- wired into
// Schema_Entity_Registry's linked-entity methods above.
require_once SCHEMA_PLUGIN_DIR . 'includes/class-schema-linked-entity-types.php';
require_once SCHEMA_PLUGIN_DIR . 'includes/class-schema-linked-entity-store.php';
require_once SCHEMA_PLUGIN_DIR . 'includes/class-schema-author-profile.php';
require_once SCHEMA_PLUGIN_DIR . 'includes/class-schema-rest-controller.php';
require_once SCHEMA_PLUGIN_DIR . 'includes/class-schema-linked-entity-rest-controller.php';
require_once SCHEMA_PLUGIN_DIR . 'includes/class-schema-activation.php';
require_once SCHEMA_PLUGIN_DIR . 'includes/class-schema-completeness.php';
// Always loaded (REST handlers use its page slugs); only init()'d in admin.
require_once SCHEMA_PLUGIN_DIR . 'includes/class-schema-admin.php';

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

	Schema_Graph_Builder::init();
	Schema_Author_Profile::init();
	Schema_Rest_Controller::init();
	Schema_Linked_Entity_Rest_Controller::init();

	if ( is_admin() ) {
		Schema_Admin::init();
	}
}
