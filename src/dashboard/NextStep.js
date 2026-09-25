import { useState } from '@wordpress/element';
import { Button, Card, CardBody, CardHeader } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { updateRecommendation } from '../shared/api';

const bootstrap = window.SchemaPluginData || {};

/**
 * One suggestion at a time: the first pending recommendation, with its
 * action and a Dismiss link. Dismissing moves on to the next. The rest
 * are counted, not listed, so they don't compete with the issues panel.
 */
export default function NextStep( { recommendations, onStatusChange } ) {
	const [ busy, setBusy ] = useState( false );
	const pending = recommendations.filter( ( rec ) => 'pending' === rec.status );
	const current = pending[ 0 ];

	const dismiss = () => {
		setBusy( true );
		updateRecommendation( current.key, 'dismissed' ).then( () => {
			setBusy( false );
			onStatusChange( current.key, 'dismissed' );
		} );
	};

	return (
		<Card className="schema-dashboard-card schema-next-step">
			<CardHeader>
				<h2>{ __( 'Next step', 'schema-plugin' ) }</h2>
			</CardHeader>
			<CardBody>
				{ current ? (
					<>
						<p className="schema-next-step-label">{ current.label }</p>
						<p className="schema-next-step-description">{ current.description }</p>
						<div className="schema-next-step-actions">
							{ bootstrap[ current.action_key ] && (
								<Button variant="secondary" href={ bootstrap[ current.action_key ] }>
									{ current.action_label }
								</Button>
							) }
							<Button variant="tertiary" onClick={ dismiss } disabled={ busy }>
								{ __( 'Dismiss', 'schema-plugin' ) }
							</Button>
						</div>
						{ pending.length > 1 && (
							<p className="schema-next-step-more">
								{ sprintf(
									/* translators: %d: number of further suggestions. */
									_n( '%d more suggestion after this.', '%d more suggestions after this.', pending.length - 1, 'schema-plugin' ),
									pending.length - 1
								) }
							</p>
						) }
					</>
				) : (
					<p className="schema-next-step-description">{ __( 'All caught up.', 'schema-plugin' ) }</p>
				) }
			</CardBody>
		</Card>
	);
}
