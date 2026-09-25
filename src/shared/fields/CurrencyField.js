import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import withRequiredMark from './withRequiredMark';

const bootstrap = window.SchemaPluginData || {};

let cachedOptions = null;

/**
 * ISO 4217 codes with localized names, both from the browser
 * (Intl.supportedValuesOf / Intl.DisplayNames; evergreen browsers only,
 * per CLAUDE.md 10.7.F).
 */
function getCurrencyOptions() {
	if ( cachedOptions ) {
		return cachedOptions;
	}
	let codes = [];
	let names = null;
	try {
		codes = Intl.supportedValuesOf( 'currency' );
		names = new Intl.DisplayNames( [ bootstrap.locale || 'en', 'en' ], { type: 'currency' } );
	} catch ( e ) {
		codes = [ 'USD', 'EUR', 'GBP' ];
	}
	cachedOptions = codes
		.map( ( code ) => ( { value: code, label: `${ code } — ${ names?.of( code ) || code }` } ) )
		.sort( ( a, b ) => a.label.localeCompare( b.label ) );
	return cachedOptions;
}

/**
 * Shared <CurrencySelect> -- CLAUDE.md 10.6.B/C: a bounded select, never
 * free text. Callers pre-fill from bootstrap.defaultCurrency (the
 * WooCommerce store currency) when there is one.
 */
export default function CurrencySelect( { label, value, onChange, required = false } ) {
	return (
		<SelectControl
			label={ withRequiredMark( label || __( 'Currency', 'schema-plugin' ), required ) }
			value={ value || '' }
			options={ [ { label: '—', value: '' }, ...getCurrencyOptions() ] }
			onChange={ onChange }
		/>
	);
}
