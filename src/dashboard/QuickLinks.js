import { __ } from '@wordpress/i18n';

const bootstrap = window.SchemaPluginData || {};

/** Plain navigation links to the other admin pages -- not client-side routes. */
export default function QuickLinks() {
	const links = [
		{ href: bootstrap.entitySettingsUrl, label: __( 'Entity Settings', 'schema-plugin' ) },
		{ href: bootstrap.defaultRulesUrl, label: __( 'Default Rules', 'schema-plugin' ) },
		{ href: bootstrap.exclusionsUrl, label: __( 'Exclusions', 'schema-plugin' ) },
		{ href: bootstrap.networkIdentityUrl, label: __( 'Network Identity', 'schema-plugin' ) },
		{ href: bootstrap.wizardUrl, label: __( 'Re-run Setup Wizard', 'schema-plugin' ) },
	];

	return (
		<div className="schema-dashboard-quicklinks">
			<h2>{ __( 'Manage', 'schema-plugin' ) }</h2>
			<div className="schema-dashboard-quicklinks-row">
				{ links.map( ( link ) => (
					<a key={ link.href } className="schema-dashboard-quicklink" href={ link.href }>
						{ link.label } →
					</a>
				) ) }
			</div>
		</div>
	);
}
