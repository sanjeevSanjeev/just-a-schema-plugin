import { useEffect, useState } from '@wordpress/element';
import { Button, Notice, Panel, PanelBody, RadioControl, Spinner, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import UrlField from '../shared/fields/UrlField';
import LivePreview from '../shared/LivePreview';
import { getNetworkIdentity, saveNetworkIdentity } from '../shared/api';

/**
 * v1.1 Phase 4 -- Network Identity settings (CLAUDE.md complaint #1). A site
 * is standalone, a member of a main brand's site, or the main site itself
 * (lists its member sites).
 * All resolution happens in PHP (Schema_Network_Identity); the preview
 * posts the draft settings to /preview and shows the sitewide nodes
 * exactly as wp_head would render them.
 */
export default function NetworkTab() {
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
	const isParent = 'parent' === settings.role;
	const memberSites = settings.member_sites || [];
	const setMember = ( index, patch ) =>
		set( { member_sites: memberSites.map( ( row, i ) => ( i === index ? { ...row, ...patch } : row ) ) } );
	const addMember = () => set( { member_sites: [ ...memberSites, { url: '', name: '' } ] } );
	const removeMember = ( index ) => set( { member_sites: memberSites.filter( ( _, i ) => i !== index ) } );
	const entityWord = data.entity_is_person ? __( 'person', 'schema-plugin' ) : __( 'organization', 'schema-plugin' );
	const suggestion = data.suggested_parent_url;

	return (
		<div className="schema-network-page schema-settings-tab">
			<h2>{ __( 'Network Identity', 'schema-plugin' ) }</h2>
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
						{ label: __( 'It is the main brand site, with other sites under it', 'schema-plugin' ), value: 'parent' },
					] }
					onChange={ ( role ) => set( { role } ) }
				/>
			</div>

			{ isParent && (
				<div className="schema-field-group schema-network-members">
					<h3>{ __( 'Sites under this one', 'schema-plugin' ) }</h3>
					<p className="description">
						{ __(
							"List the separate brands or divisions that belong to this site, each by its own address. They're added to your structured data so search engines see the whole group. Each of those sites should be set to \"part of a main brand's website\", pointing back here.",
							'schema-plugin'
						) }
					</p>
					{ data.entity_is_person && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								"A person can't have sub-organizations, so nothing will be listed. Change the site type to an organization to use this.",
								'schema-plugin'
							) }
						</Notice>
					) }
					{ memberSites.map( ( row, index ) => (
						<div className="schema-network-member-row" key={ index }>
							<UrlField
								label={ sprintf(
									/* translators: %d: row number. */
									__( 'Site %d address', 'schema-plugin' ),
									index + 1
								) }
								value={ row.url }
								onChange={ ( url ) => setMember( index, { url } ) }
								required
							/>
							<TextControl
								label={ sprintf(
									/* translators: %d: row number. */
									__( 'Site %d name (optional)', 'schema-plugin' ),
									index + 1
								) }
								value={ row.name || '' }
								onChange={ ( name ) => setMember( index, { name } ) }
							/>
							<Button variant="link" isDestructive onClick={ () => removeMember( index ) }>
								{ sprintf(
									/* translators: %d: row number. */
									__( 'Remove site %d', 'schema-plugin' ),
									index + 1
								) }
							</Button>
						</div>
					) ) }
					<Button variant="secondary" onClick={ addMember }>
						{ __( 'Add a site', 'schema-plugin' ) }
					</Button>
				</div>
			) }

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
