import { Button, CheckboxControl, Notice, RadioControl, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import withRequiredMark from './withRequiredMark';
import UrlField from './UrlField';
import SelectField from './SelectField';
import CurrencySelect from './CurrencyField';
import { CountrySelect, CountryListField } from './CountryField';

const bootstrap = window.SchemaPluginData || {};
const SCHEMA = 'https://schema.org/';

/**
 * OnlineStore store-wide policies (Google's return- and shipping-policy
 * docs, updated 2026-09-08). Enum values are full schema.org URLs, the
 * same shape Schema_Sanitizer stores. Both fields accept the stored node
 * shape and emit a flat form shape; the sanitizer accepts either.
 */

const CATEGORY_FINITE = SCHEMA + 'MerchantReturnFiniteReturnWindow';

const RETURN_OPTIONS = {
	returnPolicyCategory: [
		{ label: __( 'Returns accepted within a set number of days', 'schema-plugin' ), value: CATEGORY_FINITE },
		{ label: __( 'Returns accepted any time', 'schema-plugin' ), value: SCHEMA + 'MerchantReturnUnlimitedWindow' },
		{ label: __( 'No returns', 'schema-plugin' ), value: SCHEMA + 'MerchantReturnNotPermitted' },
	],
	returnFees: [
		{ label: __( 'Free returns', 'schema-plugin' ), value: SCHEMA + 'FreeReturn' },
		{ label: __( 'Customer pays return shipping', 'schema-plugin' ), value: SCHEMA + 'ReturnShippingFees' },
		{ label: __( 'Customer is responsible for return costs', 'schema-plugin' ), value: SCHEMA + 'ReturnFeesCustomerResponsibility' },
	],
	returnMethod: [
		{ label: __( 'By mail', 'schema-plugin' ), value: SCHEMA + 'ReturnByMail' },
		{ label: __( 'In store', 'schema-plugin' ), value: SCHEMA + 'ReturnInStore' },
		{ label: __( 'At a kiosk', 'schema-plugin' ), value: SCHEMA + 'ReturnAtKiosk' },
	],
	refundType: [
		{ label: __( 'Full refund', 'schema-plugin' ), value: SCHEMA + 'FullRefund' },
		{ label: __( 'Exchange', 'schema-plugin' ), value: SCHEMA + 'ExchangeRefund' },
		{ label: __( 'Store credit', 'schema-plugin' ), value: SCHEMA + 'StoreCreditRefund' },
	],
};

export function ReturnPolicyField( { label, value, onChange } ) {
	const policy = value && 'object' === typeof value ? value : {};
	const mode = policy.mode || ( policy.applicableCountry || policy.returnPolicyCategory ? 'structured' : 'link' );
	const set = ( patch ) => onChange( { ...policy, mode, ...patch } );
	const isFinite = CATEGORY_FINITE === policy.returnPolicyCategory;

	return (
		<fieldset className="schema-field schema-field-return-policy">
			<legend>{ label }</legend>
			<RadioControl
				label={ __( 'How do you want to describe it?', 'schema-plugin' ) }
				hideLabelFromVision
				selected={ mode }
				options={ [
					{ label: __( 'Link to my returns page (simplest)', 'schema-plugin' ), value: 'link' },
					{ label: __( 'Describe the policy (countries, time window, fees)', 'schema-plugin' ), value: 'structured' },
				] }
				onChange={ ( next ) => set( { mode: next } ) }
			/>

			{ 'structured' === mode && (
				<>
					<CountryListField
						label={ __( 'Countries this policy applies to', 'schema-plugin' ) }
						value={ policy.applicableCountry }
						onChange={ ( applicableCountry ) => set( { applicableCountry } ) }
						required
					/>
					<SelectField
						label={ __( 'Policy', 'schema-plugin' ) }
						value={ policy.returnPolicyCategory }
						options={ RETURN_OPTIONS.returnPolicyCategory }
						onChange={ ( returnPolicyCategory ) => set( { returnPolicyCategory } ) }
						required
					/>
					{ isFinite && (
						<TextControl
							type="number"
							min={ 1 }
							label={ withRequiredMark( __( 'Days to return an item', 'schema-plugin' ), true ) }
							value={ policy.merchantReturnDays ?? '' }
							onChange={ ( merchantReturnDays ) => set( { merchantReturnDays } ) }
						/>
					) }
					<SelectField
						label={ __( 'Return fees', 'schema-plugin' ) }
						value={ policy.returnFees }
						options={ RETURN_OPTIONS.returnFees }
						onChange={ ( returnFees ) => set( { returnFees } ) }
					/>
					<SelectField
						label={ __( 'Return method', 'schema-plugin' ) }
						value={ policy.returnMethod }
						options={ RETURN_OPTIONS.returnMethod }
						onChange={ ( returnMethod ) => set( { returnMethod } ) }
					/>
					<SelectField
						label={ __( 'Refund type', 'schema-plugin' ) }
						value={ policy.refundType }
						options={ RETURN_OPTIONS.refundType }
						onChange={ ( refundType ) => set( { refundType } ) }
					/>
				</>
			) }

			<UrlField
				label={
					'link' === mode
						? __( 'Returns page URL', 'schema-plugin' )
						: __( 'Returns page URL (optional)', 'schema-plugin' )
				}
				value={ policy.merchantReturnLink }
				onChange={ ( merchantReturnLink ) => set( { merchantReturnLink } ) }
				required={ 'link' === mode }
			/>
		</fieldset>
	);
}

/** Stored ShippingService node, or already-flat rows, -> flat rows. */
function toRows( value ) {
	if ( Array.isArray( value ) ) {
		return value;
	}
	const conditions = value?.shippingConditions;
	if ( ! Array.isArray( conditions ) ) {
		return [];
	}
	return conditions.map( ( condition ) => {
		const rate = condition.shippingRate || {};
		const duration = condition.transitTime?.duration || {};
		return {
			country: condition.shippingDestination?.addressCountry || '',
			cost: rate.value ?? '',
			currency: rate.currency || '',
			free: 0 === Number( rate.value ) && undefined !== rate.value,
			minDays: duration.minValue ?? '',
			maxDays: duration.maxValue ?? '',
		};
	} );
}

export function ShippingField( { label, value, onChange } ) {
	const rows = toRows( value );

	const update = ( index, patch ) => {
		const next = [ ...rows ];
		next[ index ] = { ...next[ index ], ...patch };
		onChange( next );
	};
	const remove = ( index ) => onChange( rows.filter( ( row, i ) => i !== index ) );
	const add = () =>
		onChange( [
			...rows,
			{
				country: '',
				cost: '',
				// New rows reuse the last row's currency, else the store's.
				currency: rows[ rows.length - 1 ]?.currency || bootstrap.defaultCurrency || '',
				free: false,
				minDays: '',
				maxDays: '',
			},
		] );

	return (
		<fieldset className="schema-field schema-field-shipping">
			<legend>{ label }</legend>
			<p className="description">
				{ __(
					'Your standard shipping, one row per destination country. Products with different shipping can override this in their own markup or Merchant Center.',
					'schema-plugin'
				) }
			</p>

			{ rows.map( ( row, index ) => (
				<fieldset
					// eslint-disable-next-line react/no-array-index-key
					key={ index }
					className="schema-field schema-shipping-row"
				>
					<legend>
						{ sprintf(
							/* translators: %d: shipping row number. */
							__( 'Destination %d', 'schema-plugin' ),
							index + 1
						) }
					</legend>
					<CountrySelect
						label={ __( 'Ships to', 'schema-plugin' ) }
						value={ row.country }
						onChange={ ( country ) => update( index, { country } ) }
						required
					/>
					<CheckboxControl
						label={ __( 'Free shipping', 'schema-plugin' ) }
						checked={ !! row.free }
						onChange={ ( free ) => update( index, { free } ) }
					/>
					{ ! row.free && (
						<TextControl
							type="number"
							min={ 0 }
							step="0.01"
							label={ withRequiredMark( __( 'Shipping cost', 'schema-plugin' ), true ) }
							value={ row.cost ?? '' }
							onChange={ ( cost ) => update( index, { cost } ) }
						/>
					) }
					<CurrencySelect
						value={ row.currency }
						onChange={ ( currency ) => update( index, { currency } ) }
						required
					/>
					<div className="schema-shipping-days">
						<TextControl
							type="number"
							min={ 0 }
							label={ __( 'Delivery: minimum days', 'schema-plugin' ) }
							value={ row.minDays ?? '' }
							onChange={ ( minDays ) => update( index, { minDays } ) }
						/>
						<TextControl
							type="number"
							min={ 0 }
							label={ __( 'Delivery: maximum days', 'schema-plugin' ) }
							value={ row.maxDays ?? '' }
							onChange={ ( maxDays ) => update( index, { maxDays } ) }
						/>
					</div>
					<Button variant="tertiary" isDestructive onClick={ () => remove( index ) }>
						{ __( 'Remove this destination', 'schema-plugin' ) }
					</Button>
				</fieldset>
			) ) }

			{ 0 === rows.length && ! bootstrap.defaultCurrency && (
				<Notice status="info" isDismissible={ false }>
					{ __( 'No store currency detected, so you\'ll pick one per row.', 'schema-plugin' ) }
				</Notice>
			) }

			<Button variant="secondary" onClick={ add }>
				{ __( 'Add a destination', 'schema-plugin' ) }
			</Button>
		</fieldset>
	);
}

const AVAILABILITY_OPTIONS = [
	{ label: __( 'In stock', 'schema-plugin' ), value: 'InStock' },
	{ label: __( 'Out of stock', 'schema-plugin' ), value: 'OutOfStock' },
	{ label: __( 'Pre-order', 'schema-plugin' ), value: 'PreOrder' },
	{ label: __( 'Back-order', 'schema-plugin' ), value: 'BackOrder' },
	{ label: __( 'Limited availability', 'schema-plugin' ), value: 'LimitedAvailability' },
	{ label: __( 'Online only', 'schema-plugin' ), value: 'OnlineOnly' },
	{ label: __( 'In store only', 'schema-plugin' ), value: 'InStoreOnly' },
	{ label: __( 'Sold out', 'schema-plugin' ), value: 'SoldOut' },
	{ label: __( 'Discontinued', 'schema-plugin' ), value: 'Discontinued' },
];

/**
 * A product Offer (Google's Product snippet doc, updated 2026-09-08):
 * price is required; currency, availability and "price valid until" are
 * recommended. Holds the flat form shape; the sanitizer accepts it or the
 * stored Offer node (availability stored as a schema.org URL).
 */
export function OfferField( { label, value, onChange } ) {
	const offer = value && 'object' === typeof value ? value : {};
	const set = ( patch ) => onChange( { ...offer, ...patch } );
	const availability = String( offer.availability || '' ).split( '/' ).pop();

	return (
		<fieldset className="schema-field schema-field-offer">
			<legend>{ label }</legend>
			<TextControl
				type="number"
				min={ 0 }
				step="0.01"
				label={ __( 'Price', 'schema-plugin' ) }
				value={ offer.price ?? '' }
				onChange={ ( price ) => set( { price } ) }
			/>
			<CurrencySelect
				value={ offer.priceCurrency || bootstrap.defaultCurrency || '' }
				onChange={ ( priceCurrency ) => set( { priceCurrency } ) }
			/>
			<SelectField
				label={ __( 'Availability', 'schema-plugin' ) }
				value={ availability }
				options={ AVAILABILITY_OPTIONS }
				onChange={ ( v ) => set( { availability: v } ) }
			/>
			<TextControl
				type="date"
				label={ __( 'Price valid until (optional)', 'schema-plugin' ) }
				value={ offer.priceValidUntil || '' }
				onChange={ ( priceValidUntil ) => set( { priceValidUntil } ) }
			/>
		</fieldset>
	);
}
