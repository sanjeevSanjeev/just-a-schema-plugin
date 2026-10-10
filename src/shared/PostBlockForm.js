import { useState } from '@wordpress/element';
import { TextControl, TextareaControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import TypedField from './fields/TypedField';
import LivePreview from './LivePreview';
import { badgeLabel, resolveBadgeColor } from './EntityForm';

const bootstrap = window.SchemaPluginData || {};
const fieldDefsConfig = bootstrap.fieldDefs || {};
const COMMON_TYPES = [ 'Article', 'BlogPosting', 'Product', 'Service', 'CollectionPage', 'WebPage', 'FAQPage', 'HowTo' ];
// Title-like field first, then the content fields, then the rest in the
// order PHP defines them.
const FIELD_ORDER = [ 'name', 'headline', 'description', 'image', 'url', 'datePublished', 'dateModified' ];

const FIELD_LABELS = {
	name: __( 'Name', 'schema-plugin' ),
	headline: __( 'Headline', 'schema-plugin' ),
	description: __( 'Description', 'schema-plugin' ),
	image: __( 'Image', 'schema-plugin' ),
	url: __( 'URL', 'schema-plugin' ),
	datePublished: __( 'Date published', 'schema-plugin' ),
	dateModified: __( 'Date modified', 'schema-plugin' ),
	offers: __( 'Price and availability', 'schema-plugin' ),
};

function orderedFields( defs ) {
	const rank = ( key ) => {
		const index = FIELD_ORDER.indexOf( key );
		return -1 === index ? FIELD_ORDER.length : index;
	};
	return Object.entries( defs )
		.map( ( [ key, def ], position ) => ( { key, def, position } ) )
		.sort( ( a, b ) => rank( a.key ) - rank( b.key ) || a.position - b.position );
}

// Per-type field sets come from PHP (Schema_Types::get_block_field_defs());
// types without their own use the default.
function getFieldDefsFor( schemaType ) {
	const byType = fieldDefsConfig.block || {};
	return { ...( byType[ schemaType ] || byType._default || fieldDefsConfig.genericBlock ) };
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
					{ orderedFields( fieldDefs ).map( ( { key, def } ) => (
						<TypedField
							key={ key }
							fieldKey={ key }
							def={ { ...def, label: def.label || FIELD_LABELS[ key ] } }
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
