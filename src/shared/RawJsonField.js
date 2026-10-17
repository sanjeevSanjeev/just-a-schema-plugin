import { Button, Notice, TextareaControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * What is wrong with this raw JSON-LD, in words, or '' if it is fine (or
 * empty). Mirrors Schema_Sanitizer::sanitize_raw_node(): one JSON object.
 *
 * @param {string} text Raw editor text.
 * @return {string} Message, or ''.
 */
export function rawJsonProblem( text ) {
	if ( ! text || ! text.trim() ) {
		return '';
	}
	let parsed;
	try {
		parsed = JSON.parse( text );
	} catch ( e ) {
		return __( 'This is not valid JSON.', 'schema-plugin' );
	}
	if ( null === parsed || 'object' !== typeof parsed || Array.isArray( parsed ) || ! Object.keys( parsed ).length ) {
		return __( 'This must be a single JSON object, like { "@type": "Organization", ... }.', 'schema-plugin' );
	}
	return '';
}

/**
 * Raw JSON-LD box for ONE node (the site entity, or a default rule), with
 * inline validation in text and a "Format" button. Saving is still allowed
 * with a problem showing; the server refuses invalid JSON and says why.
 *
 * @param {Object}   props
 * @param {string}   props.value    Current text.
 * @param {Function} props.onChange Called with the new text.
 * @param {string}   props.label    Field label.
 * @param {string}   [props.help]   Help text under the box.
 * @param {number}   [props.rows]   Textarea rows.
 */
export default function RawJsonField( { value, onChange, label, help, rows = 10 } ) {
	const problem = rawJsonProblem( value );
	const format = () => {
		try {
			onChange( JSON.stringify( JSON.parse( value ), null, 2 ) );
		} catch ( e ) {
			// The inline message already says what is wrong.
		}
	};

	return (
		<div className="schema-field-group schema-raw-override">
			<TextareaControl
				label={ label }
				help={ help }
				value={ value || '' }
				onChange={ onChange }
				rows={ rows }
				spellCheck={ false }
				className="schema-raw-json"
			/>
			{ problem && (
				<Notice status="error" isDismissible={ false }>
					{ problem }
				</Notice>
			) }
			<Button variant="secondary" onClick={ format } disabled={ ! value || !! problem }>
				{ __( 'Format JSON', 'schema-plugin' ) }
			</Button>
		</div>
	);
}
