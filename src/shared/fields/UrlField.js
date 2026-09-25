import { TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import withRequiredMark from './withRequiredMark';

function isValidUrl( value ) {
	if ( ! value ) {
		return true; // emptiness is a required-field concern, handled separately by lint warnings.
	}
	try {
		// No base argument: resolving against a base treats almost any
		// string as a valid relative path (e.g. "notaurl" silently
		// "succeeds"), which defeats the check entirely. Requiring an
		// absolute URL here also matches the server-side
		// FILTER_VALIDATE_URL check (CLAUDE.md Section 10.5.C).
		// eslint-disable-next-line no-new
		new URL( value );
		return true;
	} catch ( e ) {
		return false;
	}
}

/**
 * Shared <UrlField> -- CLAUDE.md Section 10.6.A. Inline client-side check,
 * red-outline + text error (never color alone) on invalid format.
 */
export default function UrlField( { label, value, onChange, help, required = false } ) {
	const [ touched, setTouched ] = useState( false );
	const valid = isValidUrl( value );
	const showError = touched && ! valid;
	const fieldLabel = withRequiredMark( label, required );

	return (
		<div className={ `schema-field schema-field-url${ showError ? ' has-error' : '' }` }>
			<TextControl
				type="url"
				label={ fieldLabel }
				value={ value || '' }
				onChange={ onChange }
				onBlur={ () => setTouched( true ) }
				help={ help }
			/>
			{ showError && (
				<p className="schema-field-error">
					{ __( "This doesn't look like a valid URL.", 'schema-plugin' ) }
				</p>
			) }
		</div>
	);
}
