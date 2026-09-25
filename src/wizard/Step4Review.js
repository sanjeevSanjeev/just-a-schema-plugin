import { useState } from '@wordpress/element';
import { Button, Panel, PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { saveEntity } from '../shared/api';
import { getWizardCopy } from '../shared/wizardCopy';

const bootstrap = window.SchemaPluginData || {};

/**
 * Wizard Step 4 -- review + finish, with a de-emphasized, collapsed
 * "Advanced: Network Identity" entry point linking to its settings page
 * (v1.1 Phase 4; non-blocking, not part of the wizard flow). On finish, always returns to the
 * persistent Dashboard now (it absorbed the old one-time landing
 * screen's content, so first-run vs. re-run no longer need different
 * destinations). Heading personalizes to the chosen type ("Your business
 * is ready to be found!" etc.).
 */
export default function Step4Review( { entity, onBack } ) {
	const [ saving, setSaving ] = useState( false );
	const typeLabel = bootstrap.lockedTypes?.[ entity.entity_type ]?.label || entity.entity_type;
	const copy = getWizardCopy( entity.entity_type );

	const finish = () => {
		setSaving( true );
		saveEntity( { ...entity, complete_wizard: true } ).then( () => {
			window.location.href = bootstrap.dashboardUrl;
		} );
	};

	return (
		<div className="schema-wizard-step">
			<h1>{ copy.finish_heading }</h1>
			<p className="schema-wizard-step-intro">
				{ __( "One last look before we make this live.", 'schema-plugin' ) }
			</p>

			<div className="schema-review-summary">
				<div className="schema-review-row">
					<span className="schema-review-label">{ __( 'Site type', 'schema-plugin' ) }</span>
					<strong>{ typeLabel }</strong>
				</div>
				<div className="schema-review-row">
					<span className="schema-review-label">{ copy.name_label }</span>
					<strong>{ entity.fields?.name || '—' }</strong>
				</div>
				{ entity.fields?.description && (
					<div className="schema-review-row">
						<span className="schema-review-label">{ copy.description_label }</span>
						<span>{ entity.fields.description }</span>
					</div>
				) }
			</div>

			<Panel>
				<PanelBody title={ __( 'Advanced: Network Identity', 'schema-plugin' ) } initialOpen={ false }>
					<p>
						{ __(
							"Is this site a subdomain, subdirectory or separate install of a bigger brand? After finishing, you can link it to the main site's identity. Optional; most sites don't need it.",
							'schema-plugin'
						) }
					</p>
					<p>
						<a href={ bootstrap.networkIdentityUrl }>{ __( 'Network Identity settings', 'schema-plugin' ) }</a>
					</p>
				</PanelBody>
			</Panel>

			<div className="schema-wizard-nav">
				<Button variant="secondary" onClick={ onBack } disabled={ saving }>
					{ __( 'Back', 'schema-plugin' ) }
				</Button>
				<Button variant="primary" onClick={ finish } isBusy={ saving } disabled={ saving }>
					{ __( 'Activate Schema', 'schema-plugin' ) }
				</Button>
			</div>
		</div>
	);
}
