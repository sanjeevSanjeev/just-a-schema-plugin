import { __, sprintf, _n } from '@wordpress/i18n';

/**
 * "What's live" + "At a glance" blocks -- purely presentational, reused
 * content model from the old one-time post-wizard landing screen, now
 * shown permanently. Deliberately NOT the deferred Completeness
 * Dashboard: plain counts only, no scoring or percentages.
 */
export default function StatusSummary( { status } ) {
	return (
		<>
			<div className="schema-dashboard-live-block">
				<h2>{ __( "What's live right now", 'schema-plugin' ) }</h2>
				<ul className="schema-dashboard-live-list">
					<li>
						<span className="schema-dashboard-check" aria-hidden="true">✓</span>
						{ sprintf(
							/* translators: %s: site type label, e.g. "Local or Small Business". */
							__( '%s entity -- appears sitewide', 'schema-plugin' ),
							status.site_type_label
						) }
					</li>
					<li>
						<span className="schema-dashboard-check" aria-hidden="true">✓</span>
						{ sprintf(
							/* translators: %d: number of active default rules. */
							_n( 'Default schema rules -- %d rule configured', 'Default schema rules -- %d rules configured', status.rule_count, 'schema-plugin' ),
							status.rule_count
						) }
					</li>
					<li>
						<span className="schema-dashboard-check" aria-hidden="true">✓</span>
						{ status.search_enabled
							? __( 'WebSite entity + sitelinks search box enabled', 'schema-plugin' )
							: __( 'WebSite entity (sitelinks search box not enabled)', 'schema-plugin' ) }
					</li>
				</ul>
			</div>

			<div className="schema-dashboard-glance-block">
				<h2>{ __( 'At a glance', 'schema-plugin' ) }</h2>
				<dl className="schema-dashboard-glance-list">
					<div className="schema-dashboard-glance-row">
						<dt>{ __( 'Entity', 'schema-plugin' ) }</dt>
						<dd>{ status.entity_configured ? __( 'Configured', 'schema-plugin' ) : __( 'Not configured', 'schema-plugin' ) }</dd>
					</div>
					<div className="schema-dashboard-glance-row">
						<dt>{ __( 'Default rules', 'schema-plugin' ) }</dt>
						<dd>
							{ sprintf(
								/* translators: 1: total rule count, 2: how many are custom, 3: how many are from the starter set. */
								__( '%1$d rules (%2$d custom, %3$d from starter set)', 'schema-plugin' ),
								status.rule_count,
								status.custom_rule_count,
								status.rule_count - status.custom_rule_count
							) }
						</dd>
					</div>
					<div className="schema-dashboard-glance-row">
						<dt>{ __( 'Exclusions', 'schema-plugin' ) }</dt>
						<dd>
							{ sprintf(
								/* translators: %d: number of excluded URL patterns. */
								_n( '%d pattern excluded', '%d patterns excluded', status.exclusion_count, 'schema-plugin' ),
								status.exclusion_count
							) }
						</dd>
					</div>
				</dl>
			</div>
		</>
	);
}
