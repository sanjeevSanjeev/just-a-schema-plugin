import { TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import withRequiredMark from './withRequiredMark';
import { AddressField } from './LocalBusinessFields';
import UrlField from './UrlField';

/**
 * The server stores an event location as a Place, a VirtualLocation, or a
 * list of both (Schema_Sanitizer's `event_location` case). The form edits
 * a flat { name, address, url } shape; both shapes are accepted on save.
 */
function flatten( value ) {
	if ( ! value || 'object' !== typeof value ) {
		return {};
	}
	const nodes = Array.isArray( value ) ? value : [ value ];
	if ( ! nodes.some( ( node ) => node && node[ '@type' ] ) ) {
		return value;
	}
	const flat = {};
	nodes.forEach( ( node ) => {
		if ( 'Place' === node[ '@type' ] ) {
			flat.name = node.name || '';
			flat.address = node.address || {};
		} else if ( 'VirtualLocation' === node[ '@type' ] ) {
			flat.url = node.url || '';
		}
	} );
	return flat;
}

export default function EventLocationField( { value, onChange, required = false } ) {
	const flat = flatten( value );
	const set = ( key, v ) => onChange( { ...flat, [ key ]: v } );

	return (
		<fieldset className="schema-field schema-field-event-location">
			<legend>{ withRequiredMark( __( 'Location', 'schema-plugin' ), required ) }</legend>
			<p className="description">
				{ __(
					'Fill in the venue for an in-person event, the online URL for a virtual one, or both for a mixed event.',
					'schema-plugin'
				) }
			</p>
			<TextControl
				label={ __( 'Venue name', 'schema-plugin' ) }
				value={ flat.name || '' }
				onChange={ ( v ) => set( 'name', v ) }
			/>
			<AddressField value={ flat.address || {} } onChange={ ( v ) => set( 'address', v ) } />
			<UrlField
				label={ __( 'Online event URL', 'schema-plugin' ) }
				value={ flat.url || '' }
				onChange={ ( v ) => set( 'url', v ) }
			/>
		</fieldset>
	);
}
