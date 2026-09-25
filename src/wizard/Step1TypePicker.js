import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { TypePicker } from '../shared/EntityForm';
import { continueAsLabel } from '../shared/wizardCopy';

const bootstrap = window.SchemaPluginData || {};

/**
 * Wizard Step 1 -- type selection only (CLAUDE.md Section 2/7 item 2: the
 * 5 locked types + Show More, composition chip, disambiguation nudge,
 * Google badges, all inherited from the shared <TypePicker>). Site
 * details move to their own screen (Step 2) so onboarding reads as a
 * sequence of small, focused decisions rather than one long form.
 */
export default function Step1TypePicker( { entity, onChange, onContinue } ) {
	const canContinue = !! entity.entity_type;

	return (
		<div className="schema-wizard-step">
			<h1>{ __( 'What kind of site is this?', 'schema-plugin' ) }</h1>
			<p className="schema-wizard-step-intro">
				{ __( "We'll use this to set up sensible schema defaults for your whole site -- you can change everything later.", 'schema-plugin' ) }
			</p>

			<TypePicker value={ entity } onChange={ onChange } />

			<div className="schema-wizard-nav">
				<span />
				<Button variant="primary" disabled={ ! canContinue } onClick={ onContinue }>
					{ continueAsLabel( bootstrap.lockedTypes, entity.entity_type ) }
				</Button>
			</div>
		</div>
	);
}
