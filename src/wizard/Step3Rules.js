import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import DefaultRulesManager from '../shared/DefaultRulesManager';
import { resetDefaultRulesToType } from '../shared/api';
import { getWizardCopy } from '../shared/wizardCopy';

const bootstrap = window.SchemaPluginData || {};

/**
 * Wizard Step 3 -- CLAUDE.md Section 7 item 2: "reuse the Default Rules
 * manager component built in item 3, just embedded in the wizard." Same
 * component, unmodified; only the intro copy personalizes to the chosen
 * type. The "Reset to default rules for this type" action (item 6) only
 * appears when re-running the wizard, and is a separate, explicit action
 * -- never an automatic side effect.
 */
export default function Step3Rules( { entity, onBack, onContinue } ) {
	const [ resetKey, setResetKey ] = useState( 0 );
	const isRerun = !! bootstrap.wizardCompletedAt;
	const typeLabel = bootstrap.lockedTypes?.[ entity.entity_type ]?.label;
	const copy = getWizardCopy( entity.entity_type );

	const resetToStock = () => {
		// eslint-disable-next-line no-alert
		if (
			! window.confirm(
				__(
					'Replace the current rules with the stock defaults for this type? This cannot be undone.',
					'schema-plugin'
				)
			)
		) {
			return;
		}
		resetDefaultRulesToType( entity.entity_type ).then( () => setResetKey( ( k ) => k + 1 ) );
	};

	return (
		<div className="schema-wizard-step">
			<h1>{ __( 'Review your Default Rules', 'schema-plugin' ) }</h1>
			<p className="schema-wizard-step-intro">
				{ copy.rules_intro }{ ' ' }
				{ __( 'Click "Save rules" below before continuing if you make changes.', 'schema-plugin' ) }
			</p>

			{ isRerun && entity.entity_type && (
				<p>
					<Button variant="secondary" isDestructive onClick={ resetToStock }>
						{ typeLabel
							? __( 'Reset to default rules for this type', 'schema-plugin' )
							: __( 'Reset to default rules', 'schema-plugin' ) }
					</Button>
				</p>
			) }

			<DefaultRulesManager key={ resetKey } />

			<div className="schema-wizard-nav">
				<Button variant="secondary" onClick={ onBack }>
					{ __( 'Back', 'schema-plugin' ) }
				</Button>
				<Button variant="primary" onClick={ onContinue }>
					{ __( 'Continue', 'schema-plugin' ) }
				</Button>
			</div>
		</div>
	);
}
