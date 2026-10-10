import { __, sprintf } from '@wordpress/i18n';

/**
 * The completeness report (Schema_Completeness, computed in PHP) as one
 * flat list: the site entity first, then every linked entry, each with a
 * display name and the page where it's edited.
 */
export function entriesFrom( report ) {
	const entries = [
		{
			...report.entity,
			key: 'entity',
			name: sprintf(
				/* translators: %s: site type label. */
				__( 'Site entity (%s)', 'schema-plugin' ),
				report.entity.label
			),
			group: '',
			editUrl: report.entity.edit_url,
		},
	];
	report.groups.forEach( ( group ) => {
		group.items.forEach( ( item ) => {
			entries.push( {
				...item,
				key: `${ group.entity_type }-${ item.id }`,
				group: group.label,
				editUrl: item.edit_url || group.edit_url,
			} );
		} );
	} );
	return entries;
}

/**
 * Every problem worth acting on, most severe first: issues (with PHP's
 * severity) plus missing required fields (always 'error'). Missing
 * *recommended* fields aren't alerts; they'd be noise.
 */
export function alertsFrom( report ) {
	const alerts = [];
	entriesFrom( report ).forEach( ( entry ) => {
		const where = entry.group ? `${ entry.name } · ${ entry.group }` : entry.name;
		entry.issues.forEach( ( issue ) =>
			alerts.push( { severity: issue.severity, where, text: issue.text, url: entry.editUrl } )
		);
		if ( entry.missing_required.length ) {
			alerts.push( {
				severity: 'error',
				where,
				text: sprintf(
					/* translators: %s: comma-separated field names. */
					__( 'Missing required fields: %s.', 'schema-plugin' ),
					entry.missing_required.join( ', ' )
				),
				url: entry.editUrl,
			} );
		}
	} );
	return alerts.sort( ( a, b ) => ( a.severity === b.severity ? 0 : 'error' === a.severity ? -1 : 1 ) );
}
