import { TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import withRequiredMark from './withRequiredMark';

/** Shared <PhoneField> -- CLAUDE.md Section 10.6.A. */
export default function PhoneField( { label, value, onChange, required = false } ) {
	const fieldLabel = withRequiredMark( label, required );

	return (
		<TextControl
			type="tel"
			label={ fieldLabel }
			value={ value || '' }
			onChange={ onChange }
			placeholder={ __( '+1 555-123-4567', 'schema-plugin' ) }
		/>
	);
}
