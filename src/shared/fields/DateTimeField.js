import { TextControl } from '@wordpress/components';
import withRequiredMark from './withRequiredMark';

/**
 * Shared <DateTimeField> -- native date + time picker. The server appends
 * the site's timezone offset on save (Schema_Sanitizer::sanitize_datetime),
 * so a stored value like "2026-10-01T19:00:00+05:45" is trimmed back to the
 * "YYYY-MM-DDTHH:MM" shape the native input expects.
 */
export default function DateTimeField( { label, value, onChange, required = false } ) {
	const fieldLabel = withRequiredMark( label, required );
	const inputValue = 'string' === typeof value ? value.slice( 0, 16 ) : '';

	return <TextControl type="datetime-local" label={ fieldLabel } value={ inputValue } onChange={ onChange } />;
}
