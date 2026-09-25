<?php
/**
 * Uninstall -- CLAUDE.md Section 9: prompt-free full cleanup of all
 * schema_plugin_* options and _schema_plugin_blocks post meta. Only runs
 * on actual uninstall, never on deactivation.
 *
 * @package Schema_Plugin
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete this plugin's data from the current site.
 */
function schema_plugin_uninstall_site(): void {
	global $wpdb;

	// A wildcard delete is the only way to catch every schema_plugin_* option,
	// including the per-type linked-entity options. Runs once, on uninstall.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'schema_plugin_' ) . '%' ) );
	wp_cache_flush();

	delete_transient( 'schema_plugin_activation_redirect' );
	delete_post_meta_by_key( '_schema_plugin_blocks' );
}

// User meta is network-wide, so it is cleared once, not per site.
foreach ( array( 'schema_plugin_job_title', 'schema_plugin_same_as', 'schema_plugin_photo' ) as $schema_plugin_meta_key ) {
	delete_metadata( 'user', 0, $schema_plugin_meta_key, '', true );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $schema_plugin_site_id ) {
		switch_to_blog( $schema_plugin_site_id );
		schema_plugin_uninstall_site();
		restore_current_blog();
	}
} else {
	schema_plugin_uninstall_site();
}
