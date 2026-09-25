import { Button, SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import withRequiredMark from './withRequiredMark';

const bootstrap = window.SchemaPluginData || {};

/** ISO 3166-1 alpha-2 codes (officially assigned). */
const COUNTRY_CODES = (
	'AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ ' +
	'CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR ' +
	'GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP ' +
	'KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT ' +
	'MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW ' +
	'SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG ' +
	'UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW'
).split( ' ' );

let cachedOptions = null;

/**
 * Localized { label, value } options, sorted by name. Names come from the
 * browser (Intl.DisplayNames), so no translation files are needed.
 */
export function getCountryOptions() {
	if ( cachedOptions ) {
		return cachedOptions;
	}
	let names = null;
	try {
		names = new Intl.DisplayNames( [ bootstrap.locale || 'en', 'en' ], { type: 'region' } );
	} catch ( e ) {
		names = null;
	}
	cachedOptions = COUNTRY_CODES.map( ( code ) => ( {
		value: code,
		label: `${ names?.of( code ) || code } (${ code })`,
	} ) ).sort( ( a, b ) => a.label.localeCompare( b.label ) );
	return cachedOptions;
}

/**
 * Shared <CountrySelect> -- CLAUDE.md 10.6.B: country is a dropdown, not
 * free text. Stores the ISO 3166-1 alpha-2 code Google expects. A saved
 * value that isn't a code (older free-text entries like "Nepal") is shown
 * as its own option so it isn't silently lost.
 */
export function CountrySelect( { label, value, onChange, required = false, help } ) {
	const options = getCountryOptions();
	const isLegacy = value && ! options.some( ( option ) => option.value === value );

	return (
		<SelectControl
			label={ withRequiredMark( label || __( 'Country', 'schema-plugin' ), required ) }
			value={ value || '' }
			options={ [
				{ label: '—', value: '' },
				...( isLegacy
					? [
							{
								/* translators: %s: a previously saved free-text country value. */
								label: sprintf( __( '%s (not a country code; choose from the list)', 'schema-plugin' ), value ),
								value,
							},
					  ]
					: [] ),
				...options,
			] }
			onChange={ onChange }
			help={
				isLegacy
					? __( 'Google expects a two-letter country code. Pick the matching country to fix this.', 'schema-plugin' )
					: help
			}
		/>
	);
}

/**
 * Multi-country picker (e.g. where a return policy applies): the chosen
 * countries as removable rows, plus a dropdown to add another.
 */
export function CountryListField( { label, value, onChange, required = false } ) {
	const codes = Array.isArray( value ) ? value : [];
	const options = getCountryOptions();
	const nameOf = ( code ) => options.find( ( option ) => option.value === code )?.label || code;

	return (
		<fieldset className="schema-field schema-country-list">
			<legend>{ withRequiredMark( label, required ) }</legend>
			{ codes.length > 0 && (
				<ul className="schema-country-list-items">
					{ codes.map( ( code ) => (
						<li key={ code }>
							<span>{ nameOf( code ) }</span>
							<Button
								icon="no-alt"
								size="small"
								label={ sprintf(
									/* translators: %s: country name. */
									__( 'Remove %s', 'schema-plugin' ),
									nameOf( code )
								) }
								onClick={ () => onChange( codes.filter( ( c ) => c !== code ) ) }
							/>
						</li>
					) ) }
				</ul>
			) }
			<SelectControl
				label={ __( 'Add a country', 'schema-plugin' ) }
				value=""
				options={ [
					{ label: __( '— Choose —', 'schema-plugin' ), value: '' },
					...options.filter( ( option ) => ! codes.includes( option.value ) ),
				] }
				onChange={ ( code ) => code && onChange( [ ...codes, code ] ) }
			/>
		</fieldset>
	);
}
