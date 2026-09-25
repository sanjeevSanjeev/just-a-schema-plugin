import { createRoot, lazy, Suspense } from '@wordpress/element';
import { Spinner, TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import '../shared/style.scss';

const bootstrap = window.SchemaPluginData || {};

// Each tab is its own chunk, fetched when first opened, so the page only
// downloads the tab you're looking at.
const EntityTab = lazy( () => import( /* webpackChunkName: "settings-entity" */ './EntityTab' ) );
const RulesTab = lazy( () => import( /* webpackChunkName: "settings-rules" */ './RulesTab' ) );
const ExclusionsTab = lazy( () => import( /* webpackChunkName: "settings-exclusions" */ './ExclusionsTab' ) );
const NetworkTab = lazy( () => import( /* webpackChunkName: "settings-network" */ './NetworkTab' ) );

const networkEnabled = ( bootstrap.features || [] ).some( ( feature ) => 'network' === feature.key && feature.enabled );

const TABS = [
	{ name: 'entity', title: __( 'Site entity', 'schema-plugin' ), Component: EntityTab },
	{ name: 'rules', title: __( 'Default rules', 'schema-plugin' ), Component: RulesTab },
	{ name: 'exclusions', title: __( 'Exclusions', 'schema-plugin' ), Component: ExclusionsTab },
	...( networkEnabled ? [ { name: 'network', title: __( 'Network identity', 'schema-plugin' ), Component: NetworkTab } ] : [] ),
];

/** The ?tab= query arg, so links, reloads and old-slug redirects land on the right tab. */
function initialTab() {
	const tab = new URLSearchParams( window.location.search ).get( 'tab' );
	return TABS.some( ( t ) => t.name === tab ) ? tab : 'entity';
}

function rememberTab( name ) {
	const url = new URL( window.location.href );
	url.searchParams.set( 'tab', name );
	window.history.replaceState( null, '', url );
}

/**
 * Settings -- one page for the sitewide configuration: Site entity,
 * Default rules and Exclusions (+ Network identity when that feature is
 * on). Replaces four separate admin pages.
 */
function SettingsPage() {
	return (
		<div className="schema-settings-page schema-page schema-page-wide schema-card">
			<p className="schema-breadcrumb">
				<a href={ bootstrap.dashboardUrl }>{ __( '← Dashboard', 'schema-plugin' ) }</a>
			</p>
			<h1>{ __( 'Settings', 'schema-plugin' ) }</h1>
			<TabPanel
				className="schema-tabs"
				tabs={ TABS.map( ( { name, title } ) => ( { name, title } ) ) }
				initialTabName={ initialTab() }
				onSelect={ rememberTab }
			>
				{ ( tab ) => {
					const { Component } = TABS.find( ( t ) => t.name === tab.name );
					return (
						<Suspense fallback={ <Spinner /> }>
							<Component />
						</Suspense>
					);
				} }
			</TabPanel>
		</div>
	);
}

const container = document.getElementById( 'schema-plugin-settings-root' );
if ( container ) {
	createRoot( container ).render( <SettingsPage /> );
}
