import { createRoot, useEffect, useState } from '@wordpress/element';
import { Button, Notice, Spinner, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import EntityForm from '../shared/EntityForm';
import LivePreview from '../shared/LivePreview';
import { getEntity, saveEntity } from '../shared/api';
import { getWizardCopy } from '../shared/wizardCopy';
import '../shared/style.scss';

const bootstrap = window.SchemaPluginData || {};

/**
 * Entity Settings page -- CLAUDE.md Section 7 item 4: "reachable at any
 * time after setup completes... Reuse the same React components and the
 * same /entity REST endpoint the wizard uses; do not build a second,
 * separate implementation of this form." Two-column layout with a sticky
 * live preview at >=1200px (Section 10.8.B).
 */
function EntitySettingsPage() {
	const [ value, setValue ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ saved, setSaved ] = useState( false );

	useEffect( () => {
		getEntity().then( ( res ) => {
			setValue( {
				entity_type: res.entity_type,
				flavor: res.flavor,
				fields: res.fields,
				website_enabled_search: res.website_enabled_search,
			} );
		} );
	}, [] );

	if ( ! value ) {
		return <Spinner />;
	}

	const save = () => {
		setSaving( true );
		saveEntity( value ).then( ( res ) => {
			setValue( { ...value, fields: res.fields, website_enabled_search: res.website_enabled_search } );
			setSaving( false );
			setSaved( true );
		} );
	};

	const copy = getWizardCopy( value.entity_type );

	return (
		<div className="schema-entity-settings schema-page schema-page-wide schema-card">
			<p className="schema-breadcrumb">
				<a href={ bootstrap.dashboardUrl }>{ __( '← Dashboard', 'schema-plugin' ) }</a>
			</p>
			<h1>{ value.entity_type ? copy.details_heading : __( 'Schema: Entity', 'schema-plugin' ) }</h1>
			<p className="schema-wizard-step-intro">
				{ __( 'This shows up in your structured data across the whole site.', 'schema-plugin' ) }{ ' ' }
				<a href={ bootstrap.wizardUrl }>{ __( 'Re-run setup wizard', 'schema-plugin' ) }</a>
			</p>

			<div className="schema-entity-settings-layout">
				<div className="schema-entity-settings-form">
					<EntityForm value={ value } onChange={ setValue } showLivePreview={ false } />

					<div className="schema-field-group">
						<ToggleControl
							label={ __( 'Enable sitelinks search box markup (SearchAction)', 'schema-plugin' ) }
							checked={ !! value.website_enabled_search }
							onChange={ ( website_enabled_search ) =>
								setValue( { ...value, website_enabled_search } )
							}
						/>
					</div>

					<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving }>
						{ __( 'Save', 'schema-plugin' ) }
					</Button>
					{ saved && (
						<Notice status="success" isDismissible={ false } onRemove={ () => setSaved( false ) }>
							{ __( 'Saved.', 'schema-plugin' ) }
						</Notice>
					) }
				</div>

				<div className="schema-entity-settings-preview">
					<LivePreview
						payload={ { entity_type: value.entity_type, flavor: value.flavor, fields: value.fields } }
					/>
				</div>
			</div>
		</div>
	);
}

const container = document.getElementById( 'schema-plugin-entity-root' );
if ( container ) {
	createRoot( container ).render( <EntitySettingsPage /> );
}
