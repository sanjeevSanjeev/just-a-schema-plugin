import { Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const bootstrap = window.SchemaPluginData || {};

/**
 * Warns when another plugin also prints schema. Only the SEO plugins that
 * can be switched off (Yoast, Rank Math) raise it; WooCommerce is shown on
 * the settings tab only.
 */
export default function CompatNotice( { compat } ) {
	const others = ( compat?.plugins || [] ).filter( ( p ) => 'woocommerce' !== p.key );
	if ( ! others.length ) {
		return null;
	}
	const names = others.map( ( p ) => p.label ).join( ', ' );
	const replacing = 'replace' === compat.mode;

	return (
		<Notice status={ replacing ? 'info' : 'warning' } isDismissible={ false } className="schema-compat-notice">
			{ replacing
				? sprintf(
						/* translators: %s: plugin names. */
						__(
							'%s also prints structured data. Its output is switched off on pages where this plugin prints its own, so each page carries one graph.',
							'schema-plugin'
						),
						names
				  )
				: sprintf(
						/* translators: %s: plugin names. */
						__(
							'%s also prints structured data, and both are being shown. Pages may carry two graphs that define the same items.',
							'schema-plugin'
						),
						names
				  ) }{ ' ' }
			<a href={ bootstrap.featuresUrl }>{ __( 'Change this', 'schema-plugin' ) }</a>
		</Notice>
	);
}
