import { Card, CardBody } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { ScoreMeter } from './Score';
import { openHealthDetails } from './HealthDetails';

const bootstrap = window.SchemaPluginData || {};

/**
 * The three at-a-glance tiles: is my schema complete (Health), is
 * anything wrong (Issues), what's being output (Live). Each answers one
 * question; details are further down or one click away.
 */
export default function Tiles( { report, status, alerts } ) {
	const errors = alerts.filter( ( a ) => 'error' === a.severity ).length;
	const warnings = alerts.length - errors;
	const featuresOn = ( bootstrap.features || [] ).filter( ( f ) => f.enabled ).length;

	return (
		<div className="schema-tiles">
			<Card className="schema-tile">
				<CardBody>
					<h2 className="schema-tile-label">{ __( 'Health', 'schema-plugin' ) }</h2>
					<ScoreMeter score={ report.overall } label={ __( 'Overall completeness', 'schema-plugin' ) } large />
					<a className="schema-tile-link" href="#schema-health-details" onClick={ openHealthDetails }>
						{ __( 'Health details', 'schema-plugin' ) }
					</a>
				</CardBody>
			</Card>

			<Card className={ `schema-tile schema-tile-issues ${ errors ? 'is-error' : warnings ? 'is-warning' : 'is-ok' }` }>
				<CardBody>
					<h2 className="schema-tile-label">{ __( 'Issues', 'schema-plugin' ) }</h2>
					{ alerts.length ? (
						<ul className="schema-tile-counts">
							{ errors > 0 && (
								<li className="is-error">
									{ sprintf(
										/* translators: %d: number of problems that need fixing. */
										_n( '%d to fix', '%d to fix', errors, 'schema-plugin' ),
										errors
									) }
								</li>
							) }
							{ warnings > 0 && (
								<li className="is-warning">
									{ sprintf(
										/* translators: %d: number of warnings. */
										_n( '%d warning', '%d warnings', warnings, 'schema-plugin' ),
										warnings
									) }
								</li>
							) }
						</ul>
					) : (
						<p className="schema-tile-counts is-ok">{ __( 'All good', 'schema-plugin' ) }</p>
					) }
					{ alerts.length > 0 && (
						<a className="schema-tile-link" href="#schema-attention">
							{ __( 'Review issues', 'schema-plugin' ) }
						</a>
					) }
				</CardBody>
			</Card>

			<Card className="schema-tile">
				<CardBody>
					<h2 className="schema-tile-label">{ __( 'Live', 'schema-plugin' ) }</h2>
					<ul className="schema-tile-facts">
						<li>
							<a href={ bootstrap.entitySettingsUrl }>{ status.site_type_label }</a>
						</li>
						<li>
							<a href={ bootstrap.defaultRulesUrl }>
								{ sprintf(
									/* translators: %d: number of default rules. */
									_n( '%d rule', '%d rules', status.rule_count, 'schema-plugin' ),
									status.rule_count
								) }
							</a>
							{ ' · ' }
							<a href={ bootstrap.exclusionsUrl }>
								{ sprintf(
									/* translators: %d: number of exclusion patterns. */
									_n( '%d exclusion', '%d exclusions', status.exclusion_count, 'schema-plugin' ),
									status.exclusion_count
								) }
							</a>
						</li>
						<li>
							<a href={ bootstrap.featuresUrl }>
								{ sprintf(
									/* translators: %d: number of optional features switched on. */
									_n( '%d feature on', '%d features on', featuresOn, 'schema-plugin' ),
									featuresOn
								) }
							</a>
						</li>
					</ul>
				</CardBody>
			</Card>
		</div>
	);
}
