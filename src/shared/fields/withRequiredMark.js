import { __ } from '@wordpress/i18n';

/**
 * A required field's label gets a visually distinct "Required" badge
 * (red pill, not just plain parenthetical text easy to miss at a glance)
 * alongside the word itself -- still conveyed via text, never color alone
 * (CLAUDE.md Section 10.6.E).
 *
 * @param {string}  text
 * @param {boolean} required
 * @return {string|JSX.Element} Either the plain label or a label + badge node.
 */
export default function withRequiredMark( text, required ) {
	if ( ! required ) {
		return text;
	}

	return (
		<span className="schema-field-label-wrap">
			{ text }
			<span className="schema-required-mark">{ __( 'Required', 'schema-plugin' ) }</span>
		</span>
	);
}
