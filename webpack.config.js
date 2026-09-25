const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

/**
 * Independent admin bundles (CLAUDE.md Section 7 + the v1.1-in-progress
 * Dashboard/Exclusions additions): the post editor sidebar panel, the
 * setup wizard, the Default Rules manager, the Entity settings page, the
 * Dashboard, and Exclusions. Schema_Admin enqueues exactly one of these
 * per screen -- never the whole set, and never on the public-facing site.
 */
module.exports = {
	...defaultConfig,
	entry: {
		'post-editor': path.resolve( __dirname, 'src/post-editor/index.js' ),
		wizard: path.resolve( __dirname, 'src/wizard/index.js' ),
		'default-rules': path.resolve( __dirname, 'src/default-rules/index.js' ),
		'entity-settings': path.resolve( __dirname, 'src/entity-settings/index.js' ),
		dashboard: path.resolve( __dirname, 'src/dashboard/index.js' ),
		exclusions: path.resolve( __dirname, 'src/exclusions/index.js' ),
		'linked-entity-manager': path.resolve( __dirname, 'src/linked-entity-manager/index.js' ),
		'network-identity': path.resolve( __dirname, 'src/network-identity/index.js' ),
	},
};
