<?php
/**
 * Schema_Activation
 *
 * CLAUDE.md Section 9: activation seeds default rules (only if none exist
 * yet); deactivation never deletes data; full cleanup lives in
 * uninstall.php and runs only on actual uninstall.
 *
 * @package Schema_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema_Activation {

	public static function activate(): void {
		Schema_Settings::seed_defaults_on_activation();

		// Type isn't chosen yet (wizard hasn't run) -- seed a sensible
		// neutral default (CLAUDE.md Section 10 Definition of Done). The
		// wizard re-seeds this for the actually-chosen type on first
		// completion, see Schema_Default_Rules::seed_for_type_if_pre_completion().
		Schema_Default_Rules::seed_defaults( 'individual' );

		set_transient( 'schema_plugin_activation_redirect', true, 30 );
	}

	public static function deactivate(): void {
		// Intentionally empty -- deactivation must never delete data.
	}
}
