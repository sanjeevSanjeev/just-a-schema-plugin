import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { updateRecommendation } from '../shared/api';

const bootstrap = window.SchemaPluginData || {};

/**
 * One recommendation, in one of three states (pending / dismissed /
 * completed) -- handles its own dismiss/complete action and reports the
 * result back up so the parent can move it between lists without a full
 * refetch.
 */
export default function RecommendationCard( { recommendation, onStatusChange } ) {
	const [ busy, setBusy ] = useState( false );
	const actionUrl = bootstrap[ recommendation.action_key ];

	const setStatus = ( status ) => {
		setBusy( true );
		updateRecommendation( recommendation.key, status ).then( () => {
			setBusy( false );
			onStatusChange( recommendation.key, status );
		} );
	};

	if ( 'completed' === recommendation.status ) {
		return (
			<div className="schema-recommendation-card is-completed">
				<span className="schema-dashboard-check" aria-hidden="true">✓</span>
				<span>{ recommendation.label }</span>
			</div>
		);
	}

	return (
		<div className="schema-recommendation-card">
			<div className="schema-recommendation-body">
				<p className="schema-recommendation-label">{ recommendation.label }</p>
				<p className="schema-recommendation-description">{ recommendation.description }</p>
			</div>
			<div className="schema-recommendation-actions">
				{ actionUrl && (
					<Button variant="primary" href={ actionUrl }>
						{ recommendation.action_label }
					</Button>
				) }
				<Button variant="tertiary" disabled={ busy } onClick={ () => setStatus( 'dismissed' ) }>
					{ __( 'Dismiss', 'schema-plugin' ) }
				</Button>
			</div>
		</div>
	);
}
