import { __ } from '@wordpress/i18n';
import { ScoreMeter, Gaps } from './Score';
import { entriesFrom } from './entries';

const bootstrap = window.SchemaPluginData || {};

/**
 * Per-entry health, collapsed below the fold (the Health tile has the
 * summary and links here). Entries are grouped under Sitewide / Services /
 * Locations / Events; each is a one-line row that expands, and rows with
 * problems start expanded once the section is open.
 */
export default function HealthDetails( { report } ) {
	const entries = entriesFrom( report );
	const rawPages = report.raw_pages || [];
	const groups = [];
	entries.forEach( ( entry ) => {
		const heading = entry.group || __( 'Sitewide', 'schema-plugin' );
		let group = groups.find( ( g ) => g.heading === heading );
		if ( ! group ) {
			group = { heading, entries: [] };
			groups.push( group );
		}
		group.entries.push( entry );
	} );
	// Per-feature notes (e.g. Services: only required fields are scored).
	const notes = Object.fromEntries( report.groups.filter( ( g ) => g.note ).map( ( g ) => [ g.label, g.note ] ) );

	return (
		<details className="schema-health-details-section" id="schema-health-details">
			<summary>
				<h2>{ __( 'Health details', 'schema-plugin' ) }</h2>
				<span className="description">
					{ __( 'Per entry: what Google marks as required or recommended, and what is missing.', 'schema-plugin' ) }
				</span>
			</summary>

			<div className="schema-health-details-body">
				<p className="description">
					{ __(
						'Required fields count double. A guide, not a Google score.',
						'schema-plugin'
					) }
				</p>
				{ groups.map( ( group ) => (
					<div className="schema-health-group" key={ group.heading }>
						<h3 className="schema-health-group-heading">{ group.heading }</h3>
						{ notes[ group.heading ] && <p className="description">{ notes[ group.heading ] }</p> }
						{ group.entries.map( ( entry ) => {
							const hasProblems = entry.issues.length > 0 || entry.missing_required.length > 0;
							return (
								<details className="schema-health-row" key={ entry.key } open={ hasProblems }>
									<summary>
										<span className="schema-health-name">{ entry.name }</span>
										<ScoreMeter
											score={ entry.score }
											issues={ entry.issues }
											missingRequired={ entry.missing_required }
											label={ entry.name }
										/>
									</summary>
									<div className="schema-health-details">
										<Gaps item={ entry } />
										<a href={ entry.editUrl }>{ __( 'Edit', 'schema-plugin' ) }</a>
									</div>
								</details>
							);
						} ) }
					</div>
				) ) }
				{ rawPages.length > 0 && (
					<div className="schema-health-group" id="schema-raw-pages">
						<h3 className="schema-health-group-heading">
							{ __( 'Pages with raw JSON-LD', 'schema-plugin' ) }
						</h3>
						<p className="description">
							{ __(
								'These pages output JSON-LD you wrote by hand, so there are no fields to score. Each opens in its editor, in the Schema panel, where you change or remove it.',
								'schema-plugin'
							) }
						</p>
						<ul className="schema-raw-pages">
							{ rawPages.map( ( page ) => (
								<li key={ page.id }>
									<a href={ page.edit_url }>{ page.name }</a>
									{ page.type && <span className="schema-raw-page-type"> · { page.type }</span> }
									{ ! page.published && (
										<span className="schema-raw-page-status">
											{ ' · ' }
											{ __( 'Not published, so not live yet', 'schema-plugin' ) }
										</span>
									) }
								</li>
							) ) }
						</ul>
					</div>
				) }
				{ 1 === entries.length && rawPages.length === 0 && (
					<p className="description">
						{ bootstrap.contentUrl ? (
							__( 'Entries you add under Content are scored here too.', 'schema-plugin' )
						) : (
							<>
								{ __( 'Services, Team, Locations and Events are scored here too once switched on in', 'schema-plugin' ) }{ ' ' }
								<a href={ bootstrap.featuresUrl }>{ __( 'Settings → Features', 'schema-plugin' ) }</a>.
							</>
						) }
					</p>
				) }
			</div>
		</details>
	);
}

/** Opens the section (the Health tile's link points here). */
export function openHealthDetails() {
	const section = document.getElementById( 'schema-health-details' );
	if ( section ) {
		section.open = true;
	}
}
