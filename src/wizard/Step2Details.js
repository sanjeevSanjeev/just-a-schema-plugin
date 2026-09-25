import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { EntityFields } from '../shared/EntityForm';
import LivePreview from '../shared/LivePreview';
import { getWizardCopy } from '../shared/wizardCopy';
import { seedDefaultRulesForType } from '../shared/api';

const bootstrap = window.SchemaPluginData || {};

/**
 * Wizard Step 2 -- site details, personalized to the type chosen in Step
 * 1 (heading and field labels change per type via getWizardCopy(), e.g.
 * "Tell us about your business" / "Business name" for Local or Small
 * Business vs. "Tell us about yourself" / "Your name" for Individual).
 * Same shared <EntityFields> + <LivePreview> used by Entity Settings, per
 * CLAUDE.md Section 7 item 4's reuse requirement.
 */
export default function Step2Details( { entity, onChange, onBack, onContinue } ) {
	const copy = getWizardCopy( entity.entity_type );
	const canContinue = !! entity.fields?.name;

	const handleContinue = () => {
		if ( bootstrap.lockedTypes?.[ entity.entity_type ] ) {
			// Pre-fills Step 3 with this type's stock rule set -- only takes
			// effect while the wizard has never completed before.
			seedDefaultRulesForType( entity.entity_type ).finally( onContinue );
		} else {
			onContinue();
		}
	};

	return (
		<div className="schema-wizard-step">
			<h1>{ copy.details_heading }</h1>
			<p className="schema-wizard-step-intro">
				{ __( "This shows up in your site's structured data and search results.", 'schema-plugin' ) }
			</p>

			<EntityFields value={ entity } onChange={ onChange } />

			<LivePreview
				payload={ { entity_type: entity.entity_type, flavor: entity.flavor, fields: entity.fields } }
			/>

			<div className="schema-wizard-nav">
				<Button variant="secondary" onClick={ onBack }>
					{ __( 'Back', 'schema-plugin' ) }
				</Button>
				<Button variant="primary" disabled={ ! canContinue } onClick={ handleContinue }>
					{ __( 'Continue', 'schema-plugin' ) }
				</Button>
			</div>
		</div>
	);
}
