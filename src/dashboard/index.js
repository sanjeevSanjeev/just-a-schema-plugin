import { createRoot, useEffect, useState } from '@wordpress/element';
import { Button, Card, CardBody, Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import Tiles from './Tiles';
import AttentionPanel from './AttentionPanel';
import NextStep from './NextStep';
import HealthDetails from './HealthDetails';
import { alertsFrom } from './entries';
import { getDashboardStatus, getCompleteness } from '../shared/api';
import '../shared/style.scss';

const bootstrap = window.SchemaPluginData || {};

/**
 * The plugin's home (the "Schema" menu opens it), sized to fit one screen:
 * header (site type + "Change type"), three summary tiles (Health,
 * Issues, Live), then "Needs attention" beside a single "Next step".
 * Per-entry health details are collapsed below the fold.
 *
 * The only primary-styled action is "Run setup wizard", and only while the
 * site isn't set up. Otherwise the wizard is a quiet "Change type" link
 * next to what it changes, and fixing issues is the main path.
 */
function DashboardApp() {
	const [ status, setStatus ] = useState( null );
	const [ report, setReport ] = useState( null );
	const [ failed, setFailed ] = useState( false );

	useEffect( () => {
		Promise.all( [ getDashboardStatus(), getCompleteness() ] )
			.then( ( [ statusRes, reportRes ] ) => {
				setStatus( statusRes );
				setReport( reportRes );
			} )
			.catch( () => setFailed( true ) );
	}, [] );

	if ( failed ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ __( 'The dashboard could not be loaded. Reload the page to try again.', 'schema-plugin' ) }
			</Notice>
		);
	}
	if ( ! status || ! report ) {
		return <Spinner />;
	}

	if ( ! status.entity_configured ) {
		return (
			<div className="schema-dashboard">
				<header className="schema-dashboard-head">
					<h1>{ __( 'Schema', 'schema-plugin' ) }</h1>
				</header>
				<Card className="schema-dashboard-welcome">
					<CardBody>
						<h2>{ __( "Let's set up your structured data", 'schema-plugin' ) }</h2>
						<p>
							{ __(
								'Tell us what kind of site this is and we will set sensible defaults for every page. It takes about two minutes, and you can change anything later.',
								'schema-plugin'
							) }
						</p>
						<Button variant="primary" href={ bootstrap.wizardUrl }>
							{ __( 'Run setup wizard', 'schema-plugin' ) }
						</Button>
					</CardBody>
				</Card>
			</div>
		);
	}

	const alerts = alertsFrom( report );
	const setRecommendationStatus = ( key, newStatus ) => {
		setStatus( ( prev ) => ( {
			...prev,
			recommendations: prev.recommendations.map( ( rec ) =>
				rec.key === key ? { ...rec, status: newStatus } : rec
			),
		} ) );
	};

	return (
		<div className="schema-dashboard">
			<header className="schema-dashboard-head">
				<h1>{ __( 'Schema', 'schema-plugin' ) }</h1>
				<span className="schema-dashboard-type">{ status.site_type_label }</span>
				<a className="schema-dashboard-change-type" href={ bootstrap.wizardUrl }>
					{ __( 'Change type', 'schema-plugin' ) }
					<span className="screen-reader-text">{ __( '(re-runs the setup wizard)', 'schema-plugin' ) }</span>
				</a>
			</header>

			<Tiles report={ report } status={ status } alerts={ alerts } />

			<div className="schema-dashboard-panels">
				<AttentionPanel alerts={ alerts } />
				<NextStep recommendations={ status.recommendations } onStatusChange={ setRecommendationStatus } />
			</div>

			<HealthDetails report={ report } />
		</div>
	);
}

const container = document.getElementById( 'schema-plugin-dashboard-root' );
if ( container ) {
	createRoot( container ).render( <DashboardApp /> );
}
