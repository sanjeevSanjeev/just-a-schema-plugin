import { useState } from '@wordpress/element';
import { TextControl, TextareaControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import TypedField from './fields/TypedField';
import MediaField from './fields/MediaField';
import TextareaField from './fields/TextareaField';
import SameAsBuilder from './fields/SameAsBuilder';
import LivePreview from './LivePreview';
import { badgeLabel, resolveBadgeColor } from './EntityForm';

const bootstrap = window.SchemaPluginData || {};
const fieldDefsConfig = bootstrap.fieldDefs || {};
const COMMON_TYPES = [ 'Article', 'BlogPosting', 'Product', 'Service', 'CollectionPage', 'WebPage', 'FAQPage', 'HowTo' ];
const SKIP_KEYS = [ 'headline', 'image', 'name', 'url', 'description', 'sameAs' ];

function getFieldDefsFor( schemaType ) {
	if ( 'LocalBusiness' === schemaType ) {
		return { ...fieldDefsConfig.localBusiness };
	}
	return { ...fieldDefsConfig.genericBlock };
}

/**
 * Shared per-post schema block editor -- CLAUDE.md Section 7 item 1.
 * Controlled component: parent owns { schema_type, fields, raw_override }.
 * The raw JSON-LD escape hatch (complaint #2) is always present; when
 * non-empty, it wins over the structured fields entirely.
 */
export default function PostBlockForm( { block, onChange, postId } ) {
	const { schema_type: schemaType = 'WebPage', fields = {} } = block;
	// A saved block's raw_override is stored as PHP null when unset, which
	// JSON-decodes to JS null -- destructuring defaults only cover
	// undefined, not null, so that must be coalesced explicitly or every
	// re-render of a non-raw block crashes on rawOverride.trim().
	const rawOverride = block.raw_override ?? '';
	const [ jsonError, setJsonError ] = useState( '' );

	const setSchemaType = ( type ) => onChange( { ...block, schema_type: type } );
	const setField = ( key, value ) => onChange( { ...block, fields: { ...fields, [ key ]: value } } );

	const setRawOverride = ( value ) => {
		onChange( { ...block, raw_override: value } );
		if ( ! value.trim() ) {
			setJsonError( '' );
			return;
		}
		try {
			JSON.parse( value );
			setJsonError( '' );
		} catch ( e ) {
			setJsonError( __( 'This is not valid JSON.', 'schema-plugin' ) );
		}
	};

	const fieldDefs = getFieldDefsFor( schemaType );
	const badgeColor = resolveBadgeColor( schemaType );
	const usingRaw = !! rawOverride.trim();

	return (
		<div className="schema-post-block-form">
			<div className="schema-field-group">
				<TextControl
					label={ __( 'Schema type', 'schema-plugin' ) }
					value={ schemaType }
					onChange={ setSchemaType }
					list="schema-plugin-common-types"
				/>
				<datalist id="schema-plugin-common-types">
					{ COMMON_TYPES.map( ( type ) => (
						<option key={ type } value={ type } />
					) ) }
				</datalist>
				<span className={ `schema-badge schema-badge-${ badgeColor }` }>
					{ badgeLabel( badgeColor ) }
				</span>
			</div>

			{ ! usingRaw && (
				<div className="schema-field-group">
					<TextControl
						label={ __( 'Headline / Name', 'schema-plugin' ) }
						value={ fields.headline || fields.name || '' }
						onChange={ ( v ) => setField( 'headline', v ) }
					/>
					<TextareaField
						label={ __( 'Description', 'schema-plugin' ) }
						value={ fields.description }
						onChange={ ( v ) => setField( 'description', v ) }
					/>
					<MediaField
						label={ __( 'Image', 'schema-plugin' ) }
						value={ fields.image }
						onChange={ ( v ) => setField( 'image', v ) }
					/>
					<SameAsBuilder value={ fields.sameAs } onChange={ ( v ) => setField( 'sameAs', v ) } />

					{ Object.entries( fieldDefs )
						.filter( ( [ key ] ) => ! SKIP_KEYS.includes( key ) )
						.map( ( [ key, def ] ) => (
							<TypedField
								key={ key }
								fieldKey={ key }
								def={ def }
								value={ fields[ key ] }
								onChange={ ( v ) => setField( key, v ) }
							/>
						) ) }
				</div>
			) }

			<div className="schema-field-group schema-raw-override">
				<TextareaControl
					label={ __( 'Raw JSON-LD override (advanced)', 'schema-plugin' ) }
					help={ __(
						"If filled in, this replaces the structured fields above entirely -- use it for any schema.org type the fields above don't cover.",
						'schema-plugin'
					) }
					value={ rawOverride }
					onChange={ setRawOverride }
					rows={ 6 }
				/>
				{ jsonError && (
					<Notice status="error" isDismissible={ false }>
						{ jsonError }
					</Notice>
				) }
			</div>

			<LivePreview
				payload={
					usingRaw
						? { raw_override: rawOverride }
						: { schema_type: schemaType, fields, post_id: postId }
				}
			/>
		</div>
	);
}
