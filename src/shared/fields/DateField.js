import { TextControl } from '@wordpress/components';
import withRequiredMark from './withRequiredMark';

/** Shared <DateField> -- native date picker, ISO 8601 output (CLAUDE.md Section 10.6.A). */
export default function DateField( { label, value, onChange, required = false } ) {
	const fieldLabel = withRequiredMark( label, required );

	return (
		<TextControl
			type="date"
			label={ fieldLabel }
			value={ value || '' }
			onChange={ onChange }
		/>
	);
}
