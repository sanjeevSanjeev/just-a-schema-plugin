import { createRoot, useEffect, useState } from '@wordpress/element';
import { Button, Notice, Panel, PanelBody, RadioControl, Spinner, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import UrlField from '../shared/fields/UrlField';
import LivePreview from '../shared/LivePreview';
import { getNetworkIdentity, saveNetworkIdentity } from '../shared/api';
import '../shared/style.scss';

const bootstrap = window.SchemaPluginData || {};

/**
 * v1.1 Phase 4 -- Network Identity settings (CLAUDE.md complaint #1).
 * All resolution happens in PHP (Schema_Network_Identity); the preview
 * posts the draft settings to /preview and shows the sitewide nodes
 * exactly as wp_head would render them.
 */
function NetworkIdentityPage() {
	const [ data, setData ] = useState( null );
	const [ settings, setSettings ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ saved, setSaved ] = useState( false );
	const [ error, setError ] = useState( '' );

	const load = ( res ) => {
		setData( res );
		setSettings( res.settings );
	};

	useEffect( () => {
		getNetworkIdentity().then( load );
	}, [] );

	if ( ! data ) {
		return <Spinner />;
	}

	const set = ( patch ) => {
		setSettings( { ...settings, ...patch } );
		setSaved( false );
	};

	const save = () => {
		setSaving( true );
		setError( '' );
		saveNetworkIdentity( settings )
			.then( ( res ) => {
				load( res );
				setSaved( true );
			} )
			.catch( ( err ) => setError( err.message || __( 'Something went wrong.', 'schema-plugin' ) ) )
			.finally( () => setSaving( false ) );
	};

	const isMember = 'member' === settings.role;
	const entityWord = data.entity_is_person ? __( 'person', 'schema-plugin' ) : __( 'organization', 'schema-plugin' );
	const suggestion = data.suggested_parent_url;

	return (
		<div className="schema-network-page schema-page schema-page-wide schema-card">
			<p className="schema-breadcrumb">
				<a href={ bootstrap.dashboardUrl }>{ __( '← Dashboard', 'schema-plugin' ) }</a>
			</p>
			<h1>{ __( 'Network Identity', 'schema-plugin' ) }</h1>
			<p className="schema-wizard-step-intro">
				{ __(
					'If this site is a subdomain, subdirectory or separate install belonging to a bigger brand (e.g. blog.example.com or example.com/shop), link it to the main site so search engines see one connected identity instead of unrelated duplicates.',
					'schema-plugin'
				) }
			</p>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			<div className="schema-field-group">
				<RadioControl
					label={ __( 'How does this site relate to other sites?', 'schema-plugin' ) }
					selected={ settings.role }
					options={ [
						{ label: __( 'It stands on its own', 'schema-plugin' ), value: 'standalone' },
						{ label: __( "It's part of a main brand's website", 'schema-plugin' ), value: 'member' },
					] }
					onChange={ ( role ) => set( { role } ) }
				/>
			</div>

			{ isMember && (
				<>
					<div className="schema-field-group">
						<UrlField
							label={ __( "Main site's address", 'schema-plugin' ) }
							value={ settings.parent_url }
							onChange={ ( parent_url ) => set( { parent_url } ) }
							required
						/>
						{ suggestion && suggestion !== settings.parent_url && (
							<p>
								<Button variant="link" onClick={ () => set( { parent_url: suggestion } ) }>
									{ sprintf(
										/* translators: %s: suggested URL of the main site. */
										__( 'Use %s', 'schema-plugin' ),
										suggestion
									) }
								</Button>
							</p>
						) }
					</div>

					<div className="schema-field-group">
						<RadioControl
							label={ __( 'Who runs this site?', 'schema-plugin' ) }
							selected={ settings.relationship }
							options={ [
								{
									label: sprintf(
										/* translators: %s: "organization" or "person". */
										__( 'The same %s as the main site (e.g. the company blog)', 'schema-plugin' ),
										entityWord
									),
									value: 'same',
								},
								{
									label: __( 'A separate brand or division owned by the main organization', 'schema-plugin' ),
									value: 'sub',
								},
							] }
							onChange={ ( relationship ) => set( { relationship } ) }
						/>
						<p className="description">
							{ 'same' === settings.relationship
								? __(
										"This site will use the main site's identity: its publisher and other references point to the main site's entity, and its own Entity settings describe that same entity.",
										'schema-plugin'
								  )
								: __(
										'This site keeps its own identity (from Entity settings) and is marked as belonging to the main site.',
										'schema-plugin'
								  ) }
						</p>
					</div>

					{ 'same' === settings.relationship && (
						<div className="schema-field-group">
							<TextControl
								label={ __( "Main brand's name (optional)", 'schema-plugin' ) }
								help={ __( "Used only if this site's Entity settings have no name.", 'schema-plugin' ) }
								value={ settings.parent_name }
								onChange={ ( parent_name ) => set( { parent_name } ) }
							/>
						</div>
					) }

					<Panel>
						<PanelBody title={ __( 'Advanced: identifiers', 'schema-plugin' ) } initialOpen={ false }>
							<p className="description">
								{ __(
									'Leave blank unless the main site uses a different @id convention. To check, view its page source and search for "@id".',
									'schema-plugin'
								) }
							</p>
							<UrlField
								label={ sprintf(
									/* translators: %s: "organization" or "person". */
									__( "Main site's %s @id", 'schema-plugin' ),
									entityWord
								) }
								help={ sprintf(
									/* translators: %s: default @id. */
									__( 'Default: %s', 'schema-plugin' ),
									data.derived_ids.entity
								) }
								value={ settings.parent_entity_id }
								onChange={ ( parent_entity_id ) => set( { parent_entity_id } ) }
							/>
							<UrlField
								label={ __( "Main site's WebSite @id", 'schema-plugin' ) }
								help={ sprintf(
									/* translators: %s: default @id. */
									__( 'Default: %s', 'schema-plugin' ),
									data.derived_ids.website
								) }
								value={ settings.parent_website_id }
								onChange={ ( parent_website_id ) => set( { parent_website_id } ) }
							/>
						</PanelBody>
					</Panel>
				</>
			) }

			<LivePreview payload={ { network_identity: settings } } />

			<div className="schema-wizard-nav">
				<span />
				<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving }>
					{ __( 'Save', 'schema-plugin' ) }
				</Button>
			</div>
			{ saved && (
				<Notice status="success" isDismissible={ false }>
					{ __( 'Saved.', 'schema-plugin' ) }
				</Notice>
			) }
		</div>
	);
}

const container = document.getElementById( 'schema-plugin-network-root' );
if ( container ) {
	createRoot( container ).render( <NetworkIdentityPage /> );
}
