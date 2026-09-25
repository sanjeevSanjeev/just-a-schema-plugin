import { useState } from '@wordpress/element';
import { Button, Card, CardBody, CardHeader } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const SHOWN = 3;

/**
 * "Needs attention": the top problems as compact rows, most severe first,
 * each with a Fix link. Severity is a colored tag *with* a text label
 * ("Needs fixing" / "Warning"), never color alone. "View all" expands in
 * place.
 */
export default function AttentionPanel( { alerts } ) {
	const [ isExpanded, setExpanded ] = useState( false );
	const toggle = () => setExpanded( ! isExpanded );
	const shown = isExpanded ? alerts : alerts.slice( 0, SHOWN );

	return (
		<Card className="schema-dashboard-card schema-attention" id="schema-attention">
			<CardHeader>
				<h2>{ __( 'Needs attention', 'schema-plugin' ) }</h2>
			</CardHeader>
			<CardBody>
				{ alerts.length ? (
					<ul className="schema-attention-list">
						{ shown.map( ( alert, index ) => (
							// eslint-disable-next-line react/no-array-index-key
							<li key={ index } className={ `schema-attention-row is-${ alert.severity }` }>
								<span className="schema-attention-tag">
									{ 'error' === alert.severity
										? __( 'Needs fixing', 'schema-plugin' )
										: __( 'Warning', 'schema-plugin' ) }
								</span>
								<span className="schema-attention-text">
									<strong>{ alert.where }</strong> — { alert.text }
								</span>
								<a className="schema-attention-fix" href={ alert.url }>
									{ __( 'Fix', 'schema-plugin' ) }
									<span className="screen-reader-text">{ ` ${ alert.where }` }</span>
								</a>
							</li>
						) ) }
					</ul>
				) : (
					<p className="schema-attention-ok">
						<strong>{ __( 'All good:', 'schema-plugin' ) }</strong>{ ' ' }
						{ __( 'nothing stops search engines using your structured data.', 'schema-plugin' ) }
					</p>
				) }

				{ alerts.length > SHOWN && (
					<Button variant="link" onClick={ toggle } aria-expanded={ isExpanded }>
						{ isExpanded
							? __( 'Show fewer', 'schema-plugin' )
							: sprintf(
									/* translators: %d: total number of issues. */
									__( 'View all %d', 'schema-plugin' ),
									alerts.length
							  ) }
					</Button>
				) }
			</CardBody>
		</Card>
	);
}
