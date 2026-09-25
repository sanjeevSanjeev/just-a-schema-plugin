import { TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import withRequiredMark from './withRequiredMark';

function isValidEmail( value ) {
	if ( ! value ) {
		return true; // emptiness is a required-field concern, handled separately by lint warnings.
	}
	return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( value );
}

/**
 * Shared <EmailField> -- CLAUDE.md Section 10.6.A pattern extended to
 * email: inline client-side check, red-outline + text error (never color
 * alone) on invalid format, matching <UrlField>'s behavior.
 */
export default function EmailField( { label, value, onChange, required = false, help } ) {
	const [ touched, setTouched ] = useState( false );
	const valid = isValidEmail( value );
	const showError = touched && ! valid;
	const fieldLabel = withRequiredMark( label, required );

	return (
		<div className={ `schema-field schema-field-email${ showError ? ' has-error' : '' }` }>
			<TextControl
				type="email"
				label={ fieldLabel }
				value={ value || '' }
				onChange={ onChange }
				onBlur={ () => setTouched( true ) }
				help={ help }
			/>
			{ showError && (
				<p className="schema-field-error">
					{ __( "This doesn't look like a valid email address.", 'schema-plugin' ) }
				</p>
			) }
		</div>
	);
}
