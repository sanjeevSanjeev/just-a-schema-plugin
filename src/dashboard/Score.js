import { __ } from '@wordpress/i18n';

/**
 * Score bands, always shown as text next to the number (never color
 * alone, CLAUDE.md 10.6.E). An entry with an error-level issue or a
 * missing required field never gets a reassuring label, whatever its score.
 */
export function band( score, issues = [], missingRequired = [] ) {
	if ( missingRequired.length || issues.some( ( issue ) => 'error' === issue.severity ) ) {
		return { key: 'error', label: __( 'Needs fixing', 'schema-plugin' ) };
	}
	if ( issues.length ) {
		return { key: 'warning', label: __( 'Has warnings', 'schema-plugin' ) };
	}
	if ( score >= 90 ) {
		return { key: 'complete', label: __( 'Complete', 'schema-plugin' ) };
	}
	if ( score >= 60 ) {
		return { key: 'good', label: __( 'Good', 'schema-plugin' ) };
	}
	return { key: 'weak', label: __( 'Needs work', 'schema-plugin' ) };
}

export function ScoreMeter( { score, label, issues = [], missingRequired = [], large = false } ) {
	const b = band( score, issues, missingRequired );
	return (
		<div className={ `schema-score schema-score-${ b.key }${ large ? ' is-large' : '' }` }>
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

/** Missing fields and issues for one entry. */
export function Gaps( { item } ) {
	const hasGaps = item.missing_required.length || item.missing_recommended.length || item.issues.length;
	if ( ! hasGaps ) {
		return <p className="schema-completeness-ok">{ __( 'Nothing missing.', 'schema-plugin' ) }</p>;
	}
	return (
		<ul className="schema-completeness-gaps">
			{ item.issues.map( ( issue ) => (
				<li key={ issue.text } className={ `is-${ issue.severity }` }>
					<strong>
						{ 'error' === issue.severity
							? __( 'Needs fixing:', 'schema-plugin' )
							: __( 'Warning:', 'schema-plugin' ) }
					</strong>{ ' ' }
					{ issue.text }
				</li>
			) ) }
			{ item.missing_required.length > 0 && (
				<li className="is-error">
					<strong>{ __( 'Missing (required):', 'schema-plugin' ) }</strong> { item.missing_required.join( ', ' ) }
				</li>
			) }
			{ item.missing_recommended.length > 0 && (
				<li>
					<strong>{ __( 'Missing (recommended):', 'schema-plugin' ) }</strong> { item.missing_recommended.join( ', ' ) }
				</li>
			) }
		</ul>
	);
}
