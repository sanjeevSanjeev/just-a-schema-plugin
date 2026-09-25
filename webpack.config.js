const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

/**
 * Independent admin bundles: the post editor sidebar panel, the setup
 * wizard, the Dashboard, Settings (tabs load as separate chunks on
 * demand) and Content (the linked-entity manager). Schema_Admin enqueues
 * exactly one of these per screen -- never the whole set, and never on
 * the public-facing site.
 */
module.exports = {
	...defaultConfig,
	entry: {
		'post-editor': path.resolve( __dirname, 'src/post-editor/index.js' ),
		wizard: path.resolve( __dirname, 'src/wizard/index.js' ),
		dashboard: path.resolve( __dirname, 'src/dashboard/index.js' ),
		settings: path.resolve( __dirname, 'src/settings/index.js' ),
		'linked-entity-manager': path.resolve( __dirname, 'src/linked-entity-manager/index.js' ),
	},
	output: {
		...defaultConfig.output,
		// On-demand chunks (Settings tabs) aren't versioned by
		// wp_enqueue_script, so bust caches with a content hash.
		chunkFilename: '[name].js?ver=[contenthash:8]',
	},
};
