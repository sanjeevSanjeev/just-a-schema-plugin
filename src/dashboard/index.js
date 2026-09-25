import { createRoot, useEffect, useState } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import StatusSummary from './StatusSummary';
import RecommendationCard from './RecommendationCard';
import QuickLinks from './QuickLinks';
import Completeness from './Completeness';
import { getDashboardStatus } from '../shared/api';
import '../shared/style.scss';

/**
 * The persistent Admin Dashboard -- supersedes the old one-time
 * post-wizard landing screen; its "what's live" content now lives here
 * permanently and reflects live, current state rather than a snapshot
 * from the moment the wizard finished. Plain counts, a small set of
 * dismissable/completable next steps, and (v1.1 Phase 6) the
 * Completeness section with per-entity scores.
 */
function DashboardApp() {
	const [ status, setStatus ] = useState( null );

	useEffect( () => {
		getDashboardStatus().then( setStatus );
	}, [] );

	if ( ! status ) {
		return <Spinner />;
	}

	const setLocalStatus = ( key, newStatus ) => {
		setStatus( ( prev ) => ( {
			...prev,
			recommendations: prev.recommendations.map( ( rec ) =>
				rec.key === key ? { ...rec, status: newStatus } : rec
			),
		} ) );
	};

	const pending = status.recommendations.filter( ( rec ) => 'pending' === rec.status );
	const completed = status.recommendations.filter( ( rec ) => 'completed' === rec.status );
	const dismissed = status.recommendations.filter( ( rec ) => 'dismissed' === rec.status );

	return (
		<div className="schema-dashboard schema-page schema-page-wide schema-card">
			<h1>{ __( 'Schema Dashboard', 'schema-plugin' ) }</h1>
			<p className="schema-wizard-step-intro">
				{ sprintf(
					/* translators: %s: site type label, e.g. "Local or Small Business". */
					__( 'Type: %s', 'schema-plugin' ),
					status.entity_configured ? status.site_type_label : __( 'Not set up yet', 'schema-plugin' )
				) }
			</p>

			<StatusSummary status={ status } />

			<Completeness />

			{ ( pending.length > 0 || completed.length > 0 ) && (
				<div className="schema-dashboard-recommendations">
					<h2>{ __( 'Recommended next steps', 'schema-plugin' ) }</h2>
					{ pending.map( ( rec ) => (
						<RecommendationCard key={ rec.key } recommendation={ rec } onStatusChange={ setLocalStatus } />
					) ) }
					{ completed.map( ( rec ) => (
						<RecommendationCard key={ rec.key } recommendation={ rec } onStatusChange={ setLocalStatus } />
					) ) }
				</div>
			) }

			{ dismissed.length > 0 && (
				<details className="schema-dashboard-dismissed">
					<summary>
						{ sprintf(
							/* translators: %d: number of dismissed suggestions. */
							__( 'Dismissed suggestions (%d)', 'schema-plugin' ),
							dismissed.length
						) }
					</summary>
					<ul>
						{ dismissed.map( ( rec ) => (
							<li key={ rec.key }>{ rec.label }</li>
						) ) }
					</ul>
				</details>
			) }

			<QuickLinks />
		</div>
	);
}

const container = document.getElementById( 'schema-plugin-dashboard-root' );
if ( container ) {
	createRoot( container ).render( <DashboardApp /> );
}
