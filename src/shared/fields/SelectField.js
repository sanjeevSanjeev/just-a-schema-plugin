import { SelectControl } from '@wordpress/components';
import withRequiredMark from './withRequiredMark';

/**
 * Shared <SelectField> -- CLAUDE.md Section 10.6.A/B: used for any
 * property with a bounded set of valid values instead of free text.
 * Accepts options as plain strings or { label, value } objects.
 */
export default function SelectField( { label, value, options = [], onChange, help, required = false } ) {
	const normalized = options.map( ( option ) =>
		'string' === typeof option ? { label: option, value: option } : option
	);
	const fieldLabel = withRequiredMark( label, required );

	return (
		<SelectControl
			label={ fieldLabel }
			value={ value || '' }
			options={ [ { label: '—', value: '' }, ...normalized ] }
			onChange={ onChange }
			help={ help }
		/>
	);
}
