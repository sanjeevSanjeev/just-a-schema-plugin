import { TextareaControl } from '@wordpress/components';
import withRequiredMark from './withRequiredMark';

/** Shared multi-line text field, used for `description`-shaped properties. */
export default function TextareaField( { label, value, onChange, required = false, help } ) {
	const fieldLabel = withRequiredMark( label, required );

	return (
		<TextareaControl
			label={ fieldLabel }
			value={ value || '' }
			onChange={ onChange }
			help={ help }
			rows={ 3 }
		/>
	);
}
