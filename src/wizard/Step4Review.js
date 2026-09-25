import { useState } from '@wordpress/element';
import { Button, CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { saveEntity, saveFeatures } from '../shared/api';
import { getWizardCopy } from '../shared/wizardCopy';

const bootstrap = window.SchemaPluginData || {};

/**
 * Wizard Step 4 -- review + finish, plus the optional features checklist:
 * on a first run the suggestions for the chosen type are pre-ticked (opt
 * out); on a re-run it starts from what's currently enabled, so re-running
 * never switches anything off by itself. On finish, always returns to the
 * persistent Dashboard now (it absorbed the old one-time landing
 * screen's content, so first-run vs. re-run no longer need different
 * destinations). Heading personalizes to the chosen type ("Your business
 * is ready to be found!" etc.).
 */
export default function Step4Review( { entity, onBack } ) {
	const [ saving, setSaving ] = useState( false );
	const typeLabel = bootstrap.lockedTypes?.[ entity.entity_type ]?.label || entity.entity_type;
	const copy = getWizardCopy( entity.entity_type );
	const features = bootstrap.features || [];
	const suggested = bootstrap.featureSuggestions?.[ entity.entity_type ] || [];
	const [ enabled, setEnabled ] = useState( () =>
		bootstrap.wizardCompletedAt ? features.filter( ( f ) => f.enabled ).map( ( f ) => f.key ) : suggested
	);
	const toggle = ( key, on ) => setEnabled( on ? [ ...enabled, key ] : enabled.filter( ( k ) => k !== key ) );

	const finish = () => {
		setSaving( true );
		saveEntity( { ...entity, complete_wizard: true } )
			.then( () => saveFeatures( enabled ) )
			.then( () => {
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

			<fieldset className="schema-field-group schema-wizard-features">
				<legend>
					<h2>{ __( 'Optional features', 'schema-plugin' ) }</h2>
				</legend>
				<p className="description">
					{ __(
						"Only what you tick is added. Suggestions for your site type are pre-selected; untick anything you don't need. You can change this later on the Dashboard.",
						'schema-plugin'
					) }
				</p>
				{ features.map( ( feature ) => (
					<CheckboxControl
						key={ feature.key }
						label={
							suggested.includes( feature.key ) ? (
								<span className="schema-field-label-wrap">
									{ feature.label }
									<span className="schema-recommended-mark">{ __( 'Suggested', 'schema-plugin' ) }</span>
								</span>
							) : (
								feature.label
							)
						}
						help={ feature.description }
						checked={ enabled.includes( feature.key ) }
						onChange={ ( on ) => toggle( feature.key, on ) }
					/>
				) ) }
			</fieldset>

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
