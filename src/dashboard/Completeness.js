import { useEffect, useState } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { getCompleteness } from '../shared/api';

const bootstrap = window.SchemaPluginData || {};

/**
 * v1.1 Phase 6 -- Completeness. Scores come from PHP
 * (Schema_Completeness), computed on the emitted nodes; this component
 * only renders them. Every score carries a text band next to the number,
 * never color alone (CLAUDE.md 10.6.E).
 */

// An entry with issues never gets a reassuring label, whatever its score:
// e.g. an online-only event can have every field and still be ineligible.
function band( score, hasIssues = false ) {
	if ( hasIssues ) {
		return { key: 'issues', label: __( 'Has issues', 'schema-plugin' ) };
	}
	if ( score >= 90 ) {
		return { key: 'complete', label: __( 'Complete', 'schema-plugin' ) };
	}
	if ( score >= 60 ) {
		return { key: 'good', label: __( 'Good', 'schema-plugin' ) };
	}
	return { key: 'weak', label: __( 'Needs work', 'schema-plugin' ) };
}

function ScoreMeter( { score, label, hasIssues = false } ) {
	const b = band( score, hasIssues );
	return (
		<div className={ `schema-score schema-score-${ b.key }` }>
			<div
				className="schema-score-bar"
				role="meter"
				aria-label={ label }
				aria-valuemin={ 0 }
				aria-valuemax={ 100 }
				aria-valuenow={ score }
				aria-valuetext={ `${ score }% (${ b.label })` }
			>
				<span style={ { width: `${ score }%` } } />
			</div>
			<span className="schema-score-text">
				{ score }% <span className="schema-score-band">{ b.label }</span>
			</span>
		</div>
	);
}

function Gaps( { item } ) {
	const hasGaps = item.missing_required.length || item.missing_recommended.length || item.issues.length;
	if ( ! hasGaps ) {
		return <p className="schema-completeness-ok">{ __( 'Nothing missing.', 'schema-plugin' ) }</p>;
	}
	return (
		<ul className="schema-completeness-gaps">
			{ item.missing_required.length > 0 && (
				<li>
					<strong>{ __( 'Missing (required):', 'schema-plugin' ) }</strong> { item.missing_required.join( ', ' ) }
				</li>
			) }
			{ item.missing_recommended.length > 0 && (
				<li>
					<strong>{ __( 'Missing (recommended):', 'schema-plugin' ) }</strong> { item.missing_recommended.join( ', ' ) }
				</li>
			) }
			{ item.issues.map( ( issue ) => (
				<li key={ issue }>
					<strong>{ __( 'Issue:', 'schema-plugin' ) }</strong> { issue }
				</li>
			) ) }
		</ul>
	);
}

export default function Completeness() {
	const [ report, setReport ] = useState( null );
	const [ failed, setFailed ] = useState( false );

	useEffect( () => {
		getCompleteness()
			.then( setReport )
			.catch( () => setFailed( true ) );
	}, [] );

	if ( failed ) {
		return <p>{ __( 'Completeness could not be loaded.', 'schema-plugin' ) }</p>;
	}
	if ( ! report ) {
		return <Spinner />;
	}

	const { entity, groups } = report;
	const populated = groups.filter( ( group ) => group.items.length > 0 );

	return (
		<section className="schema-completeness" aria-labelledby="schema-completeness-heading">
			<h2 id="schema-completeness-heading">{ __( 'Completeness', 'schema-plugin' ) }</h2>
			<p className="description">
				{ __(
					"How much of what Google marks as required or recommended your structured data includes. Required fields count double. This is a guide, not a Google score.",
					'schema-plugin'
				) }
			</p>

			<ScoreMeter score={ report.overall } label={ __( 'Overall completeness', 'schema-plugin' ) } />

			<div className="schema-completeness-card">
				<div className="schema-completeness-card-head">
					<h3>
						{ sprintf(
							/* translators: %s: site type label. */
							__( 'Site entity: %s', 'schema-plugin' ),
							entity.label
						) }
					</h3>
					<a href={ entity.edit_url }>{ __( 'Edit', 'schema-plugin' ) }</a>
				</div>
				<ScoreMeter
					score={ entity.score }
					hasIssues={ entity.issues.length > 0 }
					label={ __( 'Site entity completeness', 'schema-plugin' ) }
				/>
				<Gaps item={ entity } />
			</div>

			{ populated.map( ( group ) => (
				<div className="schema-completeness-card" key={ group.entity_type }>
					<div className="schema-completeness-card-head">
						<h3>{ group.label }</h3>
						<a href={ group.edit_url }>{ __( 'Manage', 'schema-plugin' ) }</a>
					</div>
					{ group.note && <p className="description">{ group.note }</p> }
					{ group.items.map( ( item ) => (
						<div className="schema-completeness-item" key={ item.id }>
							<div className="schema-completeness-item-head">
								<span className="schema-completeness-item-name">{ item.name }</span>
								<ScoreMeter
									score={ item.score }
									hasIssues={ item.issues.length > 0 }
									label={ sprintf(
										/* translators: %s: entry name. */
										__( '%s completeness', 'schema-plugin' ),
										item.name
									) }
								/>
							</div>
							<Gaps item={ item } />
						</div>
					) ) }
				</div>
			) ) }

			{ 0 === populated.length && (
				<p className="description">
					{ bootstrap.contentUrl
						? __( 'Entries you add under Content are scored here too.', 'schema-plugin' )
						: __( 'Turn on Services, Team, Locations or Events under Features and their entries are scored here too.', 'schema-plugin' ) }
				</p>
			) }
		</section>
	);
}
