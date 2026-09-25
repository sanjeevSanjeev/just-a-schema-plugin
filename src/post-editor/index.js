import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { PanelBody, Button, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PostBlockForm from '../shared/PostBlockForm';
import { getPostSchema, savePostSchema, deletePostSchema } from '../shared/api';
import '../shared/style.scss';

const SIDEBAR_NAME = 'schema-plugin-sidebar';
const SIDEBAR_ICON = 'editor-code';

function emptyBlock() {
	return { schema_type: 'WebPage', fields: {}, raw_override: '' };
}

/**
 * Post editor sidebar panel -- CLAUDE.md Section 7 item 1. Two states:
 * "Using site default" (read-only resolved preview, links to customize
 * or edit the default rule) and "Customized for this page" (structured
 * fields + raw override, live preview, revert).
 */
function SchemaPanel() {
	const postId = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostId(), [] );
	const [ state, setState ] = useState( null );
	const [ draftBlock, setDraftBlock ] = useState( emptyBlock() );
	const [ saving, setSaving ] = useState( false );

	useEffect( () => {
		if ( ! postId ) {
			return;
		}
		getPostSchema( postId ).then( ( res ) => {
			setState( res );
			setDraftBlock( res.blocks?.[ 0 ] || emptyBlock() );
		} );
	}, [ postId ] );

	if ( ! state ) {
		return <Spinner />;
	}

	const isCustomized = 'custom' === state.source;

	const customize = () => {
		setState( { ...state, source: 'custom' } );
		// Start from whatever type the page currently resolves to via
		// Default Rules, not a generic WebPage -- a page already rendering
		// as Article should let the user fine-tune that Article, not force
		// them to rebuild it under a different type.
		const resolvedType = state.resolved_preview?.[ '@type' ];
		if ( resolvedType && 'WebPage' === draftBlock.schema_type && ! Object.keys( draftBlock.fields ).length ) {
			setDraftBlock( { ...draftBlock, schema_type: resolvedType } );
		}
	};

	const save = () => {
		setSaving( true );
		savePostSchema( postId, [ draftBlock ] ).then( ( res ) => {
			setState( { source: 'custom', blocks: res.blocks, resolved_preview: res.resolved_preview } );
			// Carry the server-confirmed block_id back into the draft --
			// without this, a second save in the same session (no reload
			// in between) would keep sending an empty block_id, generating
			// a brand-new one server-side each time instead of matching
			// the record just saved, silently breaking the non-destructive
			// type-switch migration's "previous" lookup.
			if ( res.blocks?.[ 0 ]?.block_id ) {
				setDraftBlock( { ...draftBlock, block_id: res.blocks[ 0 ].block_id } );
			}
			setSaving( false );
		} );
	};

	const revert = () => {
		setSaving( true );
		deletePostSchema( postId ).then( ( res ) => {
			setState( res );
			setDraftBlock( emptyBlock() );
			setSaving( false );
		} );
	};

	return (
		<div className="schema-post-editor-panel">
			{ ! isCustomized && (
				<PanelBody title={ __( 'Using site default', 'schema-plugin' ) } initialOpen>
					<p>
						{ __( 'This page currently renders schema from your Default Rules.', 'schema-plugin' ) }
					</p>

					<details className="schema-live-preview">
						<summary>{ __( 'Show live preview', 'schema-plugin' ) }</summary>
						<pre className="schema-live-preview-json">
							{ JSON.stringify( state.resolved_preview, null, 2 ) }
						</pre>
					</details>

					<div className="schema-field-group">
						<Button variant="primary" onClick={ customize }>
							{ __( 'Customize schema for this page', 'schema-plugin' ) }
						</Button>
						<Button variant="link" href={ window.SchemaPluginData?.defaultRulesUrl }>
							{ __( 'Edit the default rule', 'schema-plugin' ) }
						</Button>
					</div>
				</PanelBody>
			) }

			{ isCustomized && (
				<PanelBody title={ __( 'Customized for this page', 'schema-plugin' ) } initialOpen>
					<PostBlockForm block={ draftBlock } onChange={ setDraftBlock } postId={ postId } />

					<div className="schema-field-group">
						<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving }>
							{ __( 'Save', 'schema-plugin' ) }
						</Button>
						<Button variant="secondary" onClick={ revert } disabled={ saving }>
							{ __( 'Revert to site default', 'schema-plugin' ) }
						</Button>
					</div>
				</PanelBody>
			) }
		</div>
	);
}

registerPlugin( 'schema-plugin', {
	icon: SIDEBAR_ICON,
	render: () => (
		<>
			<PluginSidebarMoreMenuItem target={ SIDEBAR_NAME } icon={ SIDEBAR_ICON }>
				{ __( 'Schema', 'schema-plugin' ) }
			</PluginSidebarMoreMenuItem>
			<PluginSidebar name={ SIDEBAR_NAME } title={ __( 'Schema', 'schema-plugin' ) } icon={ SIDEBAR_ICON }>
				<SchemaPanel />
			</PluginSidebar>
		</>
	),
} );
