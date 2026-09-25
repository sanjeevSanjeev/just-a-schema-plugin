import { createRoot, useEffect, useState } from '@wordpress/element';
import { Button, Notice, RadioControl, SelectControl, Spinner, TabPanel } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import TypedField from '../shared/fields/TypedField';
import PagePicker from '../shared/fields/PagePicker';
import {
	getLinkedEntityConfig,
	getLinkedEntities,
	createLinkedEntity,
	updateLinkedEntity,
	deleteLinkedEntity,
	getUsers,
} from '../shared/api';
import '../shared/style.scss';

const bootstrap = window.SchemaPluginData || {};

function emptyEntity( config ) {
	return {
		id: '',
		fields: {},
		link_mode: config.link_modes[ 0 ],
		linked_post_id: null,
		wp_user_id: null,
	};
}

/**
 * ONE generic manager for every repeatable, page-linkable entity type
 * (Services, Team, Locations, Events), shown as a tab per enabled
 * feature on the Content page.
 *
 * A type whose link_modes include 'auto' (Team) can instead be bound to a
 * WP user: it then appears on every post that user authors.
 */
function LinkedEntityManager( { entityType } ) {
	const [ config, setConfig ] = useState( null );
	const [ entities, setEntities ] = useState( null );
	const [ editing, setEditing ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ users, setUsers ] = useState( [] );

	useEffect( () => {
		getLinkedEntityConfig( entityType ).then( ( res ) => {
			setConfig( res );
			if ( res.link_modes.includes( 'auto' ) ) {
				getUsers().then( setUsers );
			}
		} );
		getLinkedEntities( entityType ).then( ( res ) => setEntities( res.entities ) );
	}, [ entityType ] );

	if ( ! config || ! entities ) {
		return <Spinner />;
	}

	const allowsAuto = config.link_modes.includes( 'auto' );
	const userName = ( id ) => users.find( ( user ) => user.id === id )?.name || `#${ id }`;

	const setField = ( key, value ) => setEditing( { ...editing, fields: { ...editing.fields, [ key ]: value } } );

	const save = () => {
		setSaving( true );
		setError( '' );
		const request = editing.id
			? updateLinkedEntity( entityType, editing.id, editing )
			: createLinkedEntity( entityType, editing );

		request
			.then( ( record ) => {
				setEntities( ( prev ) => {
					const index = prev.findIndex( ( entity ) => entity.id === record.id );
					if ( -1 === index ) {
						return [ ...prev, record ];
					}
					const next = [ ...prev ];
					next[ index ] = record;
					return next;
				} );
				setEditing( null );
				setSaving( false );
			} )
			.catch( ( err ) => {
				setError( err.message || __( 'Something went wrong.', 'schema-plugin' ) );
				setSaving( false );
			} );
	};

	const remove = ( id ) => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Delete this entry? This cannot be undone.', 'schema-plugin' ) ) ) {
			return;
		}
		deleteLinkedEntity( entityType, id ).then( () => {
			setEntities( ( prev ) => prev.filter( ( entity ) => entity.id !== id ) );
		} );
	};

	return (
		<div className="schema-linked-entity-manager">

			{ ! editing && (
				<>
					<div className="schema-rules-toolbar">
						<Button variant="primary" onClick={ () => setEditing( emptyEntity( config ) ) }>
							{ sprintf(
								/* translators: %s: singular entity label, e.g. "Service". */
								__( 'Add %s', 'schema-plugin' ),
								config.singular_label
							) }
						</Button>
					</div>

					{ 0 === entities.length && (
						<p>{ __( 'Nothing here yet.', 'schema-plugin' ) }</p>
					) }

					{ entities.map( ( entity ) => (
						<div className="schema-linked-entity-row" key={ entity.id }>
							<span>
								{ entity.fields.name ||
									( 'auto' === entity.link_mode ? userName( entity.wp_user_id ) : __( '(untitled)', 'schema-plugin' ) ) }
								{ 'auto' === entity.link_mode && (
									<span className="schema-linked-entity-row-meta">
										{ sprintf(
											/* translators: %s: WordPress user display name. */
											__( 'Author: %s', 'schema-plugin' ),
											userName( entity.wp_user_id )
										) }
									</span>
								) }
							</span>
							<div className="schema-linked-entity-row-actions">
								<Button variant="secondary" onClick={ () => setEditing( { ...entity } ) }>
									{ __( 'Edit', 'schema-plugin' ) }
								</Button>
								<Button variant="tertiary" isDestructive onClick={ () => remove( entity.id ) }>
									{ __( 'Delete', 'schema-plugin' ) }
								</Button>
							</div>
						</div>
					) ) }
				</>
			) }

			{ editing && (
				<div className="schema-linked-entity-editor">
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }

					{ allowsAuto && (
						<div className="schema-field-group">
							<RadioControl
								label={ __( 'Where does this person appear?', 'schema-plugin' ) }
								selected={ editing.link_mode }
								options={ [
									{ label: __( 'On one page I choose (e.g. their team bio page)', 'schema-plugin' ), value: 'manual' },
									{ label: __( 'On every post written by a WordPress user', 'schema-plugin' ), value: 'auto' },
								] }
								onChange={ ( v ) => setEditing( { ...editing, link_mode: v } ) }
							/>
						</div>
					) }

					{ 'auto' === editing.link_mode && (
						<div className="schema-field-group">
							<SelectControl
								label={ __( 'WordPress user', 'schema-plugin' ) }
								value={ editing.wp_user_id ? String( editing.wp_user_id ) : '' }
								options={ [
									{ label: __( '— Choose a user —', 'schema-plugin' ), value: '' },
									...users.map( ( user ) => ( { label: user.name, value: String( user.id ) } ) ),
								] }
								onChange={ ( v ) => setEditing( { ...editing, wp_user_id: v ? parseInt( v, 10 ) : null } ) }
							/>
							<Notice status="info" isDismissible={ false }>
								{ __(
									"Any field left blank here is filled from the user's profile (Users → Profile): name, bio, website, and the Schema section's job title, photo and social profiles. Their posts' author information will point to this entry.",
									'schema-plugin'
								) }
							</Notice>
						</div>
					) }

					<div className="schema-field-group">
						{ Object.entries( config.fields ).map( ( [ key, def ] ) => (
							<TypedField
								key={ key }
								fieldKey={ key }
								// Auto mode can fill any field from the user's profile.
								def={ 'auto' === editing.link_mode ? { ...def, required: false } : def }
								value={ editing.fields[ key ] }
								onChange={ ( v ) => setField( key, v ) }
							/>
						) ) }
					</div>

					{ 'manual' === editing.link_mode && (
						<div className="schema-field-group">
							<p className="schema-field-label">{ __( 'Linked page (optional)', 'schema-plugin' ) }</p>
							<PagePicker
								value={ editing.linked_post_id ? String( editing.linked_post_id ) : '' }
								onChange={ ( v ) =>
									setEditing( {
										...editing,
										linked_post_id: /^\d+$/.test( v ) ? parseInt( v, 10 ) : null,
									} )
								}
							/>
						</div>
					) }

					<div className="schema-wizard-nav">
						<Button variant="secondary" onClick={ () => setEditing( null ) } disabled={ saving }>
							{ __( 'Cancel', 'schema-plugin' ) }
						</Button>
						<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving }>
							{ __( 'Save', 'schema-plugin' ) }
						</Button>
					</div>
				</div>
			) }
		</div>
	);
}

const contentTypes = bootstrap.contentTypes || [];

function initialTab() {
	const tab = new URLSearchParams( window.location.search ).get( 'tab' );
	return contentTypes.some( ( t ) => t.key === tab ) ? tab : contentTypes[ 0 ]?.key;
}

function rememberTab( name ) {
	const url = new URL( window.location.href );
	url.searchParams.set( 'tab', name );
	window.history.replaceState( null, '', url );
}

/**
 * Content page: one tab per enabled content feature (Schema_Features).
 * With a single feature on, no tab bar is shown.
 */
function ContentPage() {
	return (
		<div className="schema-content-page schema-page schema-page-wide schema-card">
			<p className="schema-breadcrumb">
				<a href={ bootstrap.dashboardUrl }>{ __( '← Dashboard', 'schema-plugin' ) }</a>
			</p>
			<h1>{ __( 'Content', 'schema-plugin' ) }</h1>
			{ 1 === contentTypes.length ? (
				<>
					<h2>{ contentTypes[ 0 ].label }</h2>
					<LinkedEntityManager entityType={ contentTypes[ 0 ].key } />
				</>
			) : (
				<TabPanel
					className="schema-tabs"
					tabs={ contentTypes.map( ( t ) => ( { name: t.key, title: t.label } ) ) }
					initialTabName={ initialTab() }
					onSelect={ rememberTab }
				>
					{ ( tab ) => <LinkedEntityManager key={ tab.name } entityType={ tab.name } /> }
				</TabPanel>
			) }
		</div>
	);
}

const container = document.getElementById( 'schema-plugin-linked-entity-root' );
if ( container && contentTypes.length ) {
	createRoot( container ).render( <ContentPage /> );
}
