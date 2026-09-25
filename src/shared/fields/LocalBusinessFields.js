import { TextControl, Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import withRequiredMark from './withRequiredMark';
import { CountrySelect } from './CountryField';

/**
 * The LocalBusiness Tier 1.5 composite fields -- CLAUDE.md Section 8. This
 * is the one type with full Google-doc field verification in MVP.
 */

export function AddressField( { value = {}, onChange, required = false } ) {
	const set = ( key, v ) => onChange( { ...value, [ key ]: v } );
	const label = withRequiredMark( __( 'Address', 'schema-plugin' ), required );

	return (
		<fieldset className="schema-field schema-field-address">
			<legend>{ label }</legend>
			{ /* Grid: street full width, the rest two per row on wide screens. */ }
			<div className="schema-address-grid">
				<TextControl
					label={ __( 'Street address', 'schema-plugin' ) }
					value={ value.streetAddress || '' }
					onChange={ ( v ) => set( 'streetAddress', v ) }
				/>
				<TextControl
					label={ __( 'City', 'schema-plugin' ) }
					value={ value.addressLocality || '' }
					onChange={ ( v ) => set( 'addressLocality', v ) }
				/>
				<TextControl
					label={ __( 'State / region', 'schema-plugin' ) }
					value={ value.addressRegion || '' }
					onChange={ ( v ) => set( 'addressRegion', v ) }
				/>
				<TextControl
					label={ __( 'Postal code', 'schema-plugin' ) }
					value={ value.postalCode || '' }
					onChange={ ( v ) => set( 'postalCode', v ) }
				/>
				<CountrySelect value={ value.addressCountry } onChange={ ( v ) => set( 'addressCountry', v ) } />
			</div>
		</fieldset>
	);
}

export function LatLongField( { value = {}, onChange } ) {
	const set = ( key, v ) => onChange( { ...value, [ key ]: v } );

	return (
		<fieldset className="schema-field schema-field-latlong">
			<legend>{ __( 'Geo coordinates', 'schema-plugin' ) }</legend>
			<TextControl
				type="number"
				label={ __( 'Latitude', 'schema-plugin' ) }
				value={ value.latitude ?? '' }
				onChange={ ( v ) => set( 'latitude', v ) }
			/>
			<TextControl
				type="number"
				label={ __( 'Longitude', 'schema-plugin' ) }
				value={ value.longitude ?? '' }
				onChange={ ( v ) => set( 'longitude', v ) }
			/>
		</fieldset>
	);
}

const DAYS = [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ];

export function HoursField( { value, onChange } ) {
	const rows = Array.isArray( value ) ? value : [];

	const updateRow = ( index, patch ) => {
		const next = [ ...rows ];
		next[ index ] = { ...next[ index ], ...patch };
		onChange( next );
	};

	const removeRow = ( index ) => {
		const next = [ ...rows ];
		next.splice( index, 1 );
		onChange( next );
	};

	return (
		<div className="schema-field schema-field-hours">
			<p className="schema-field-label">{ __( 'Opening hours', 'schema-plugin' ) }</p>
			{ rows.map( ( row, index ) => (
				// eslint-disable-next-line react/no-array-index-key
				<div className="schema-hours-row" key={ index }>
					<select
						aria-label={ __( 'Day of week', 'schema-plugin' ) }
						value={ row.dayOfWeek || '' }
						onChange={ ( e ) => updateRow( index, { dayOfWeek: e.target.value } ) }
					>
						<option value="">{ __( 'Day…', 'schema-plugin' ) }</option>
						{ DAYS.map( ( day ) => (
							<option key={ day } value={ day }>
								{ day }
							</option>
						) ) }
					</select>
					<TextControl
						type="time"
						label={ __( 'Opens', 'schema-plugin' ) }
						hideLabelFromVision
						value={ row.opens || '' }
						onChange={ ( v ) => updateRow( index, { opens: v } ) }
					/>
					<TextControl
						type="time"
						label={ __( 'Closes', 'schema-plugin' ) }
						hideLabelFromVision
						value={ row.closes || '' }
						onChange={ ( v ) => updateRow( index, { closes: v } ) }
					/>
					<Button
						icon="no-alt"
						label={ __( 'Remove this row', 'schema-plugin' ) }
						aria-label={ __( 'Remove this row', 'schema-plugin' ) }
						onClick={ () => removeRow( index ) }
					/>
				</div>
			) ) }
			<Button variant="secondary" onClick={ () => onChange( [ ...rows, { dayOfWeek: '', opens: '', closes: '' } ] ) }>
				{ __( 'Add opening hours row', 'schema-plugin' ) }
			</Button>
		</div>
	);
}

export function AggregateRatingField( { value = {}, onChange } ) {
	const set = ( key, v ) => onChange( { ...value, [ key ]: v } );

	return (
		<fieldset className="schema-field schema-field-aggregate-rating">
			<legend>{ __( 'Aggregate rating', 'schema-plugin' ) }</legend>
			<Notice status="warning" isDismissible={ false }>
				{ __( 'Only use this for sites that aggregate reviews of OTHER businesses -- not self-testimonials.', 'schema-plugin' ) }
			</Notice>
			<TextControl
				type="number"
				label={ __( 'Rating value', 'schema-plugin' ) }
				value={ value.ratingValue ?? '' }
				onChange={ ( v ) => set( 'ratingValue', v ) }
			/>
			<TextControl
				type="number"
				label={ __( 'Review count', 'schema-plugin' ) }
				value={ value.reviewCount ?? '' }
				onChange={ ( v ) => set( 'reviewCount', v ) }
			/>
		</fieldset>
	);
}

export function ReviewField( { value = {}, onChange } ) {
	const set = ( key, v ) => onChange( { ...value, [ key ]: v } );

	return (
		<fieldset className="schema-field schema-field-review">
			<legend>{ __( 'Review', 'schema-plugin' ) }</legend>
			<Notice status="warning" isDismissible={ false }>
				{ __( 'Only use this for sites that aggregate reviews of OTHER businesses -- not self-testimonials.', 'schema-plugin' ) }
			</Notice>
			<TextControl
				label={ __( "Reviewer's name", 'schema-plugin' ) }
				value={ value.author || '' }
				onChange={ ( v ) => set( 'author', v ) }
			/>
			<TextControl
				label={ __( 'Review body', 'schema-plugin' ) }
				value={ value.reviewBody || '' }
				onChange={ ( v ) => set( 'reviewBody', v ) }
			/>
		</fieldset>
	);
}
