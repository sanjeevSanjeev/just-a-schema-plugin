import { useEffect, useState } from '@wordpress/element';
import { Button, SelectControl, TextControl, Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { getDefaultRules, saveDefaultRules } from './api';
import PagePicker from './fields/PagePicker';
import RawJsonField from './RawJsonField';

const bootstrap = window.SchemaPluginData || {};
const POST_TYPE_OPTIONS = bootstrap.postTypeOptions || [];
const FRIENDLY_SCHEMA_TYPES = bootstrap.friendlySchemaTypes || [];
const CUSTOM_TYPE_VALUE = '__custom__';

/**
 * Plain-language condition choices -- CLAUDE.md Section 3.4's match
 * types, worded for a site owner who has never heard "post_type" or
 * "taxonomy" rather than a developer. The underlying data model (match.type
 * values sent to the API) is unchanged; only the label text changes here.
 */
const MATCH_TYPES = [
	{ label: __( 'A specific type of content', 'schema-plugin' ), value: 'post_type' },
	{ label: __( 'The web address matches a pattern', 'schema-plugin' ), value: 'url_pattern' },
	{ label: __( 'Content in a specific category or tag', 'schema-plugin' ), value: 'post_type_taxonomy' },
	{ label: __( 'One specific page', 'schema-plugin' ), value: 'post_id' },
	{ label: __( 'Everything else (recommended for your last rule)', 'schema-plugin' ), value: 'catch_all' },
];

/** Known template property keys, in plain language, for the collapsed "Includes:" summary. */
const FIELD_FRIENDLY_LABELS = {
	name: __( 'Title', 'schema-plugin' ),
	headline: __( 'Title', 'schema-plugin' ),
	description: __( 'Description', 'schema-plugin' ),
	image: __( 'Image', 'schema-plugin' ),
	url: __( 'Link', 'schema-plugin' ),
	author: __( 'Author', 'schema-plugin' ),
	datePublished: __( 'Publish date', 'schema-plugin' ),
	dateModified: __( 'Last updated date', 'schema-plugin' ),
	publisher: __( 'Publisher', 'schema-plugin' ),
	provider: __( 'Provider', 'schema-plugin' ),
	'@id': __( 'Reference link', 'schema-plugin' ),
};

function summarizeTemplate( template ) {
	const keys = Object.keys( template || {} );
	if ( ! keys.length ) {
		return __( '(nothing yet)', 'schema-plugin' );
	}
	return keys.map( ( key ) => FIELD_FRIENDLY_LABELS[ key ] || key ).join( ', ' );
}

function emptyRule() {
	return {
		id: '',
		match: { type: 'url_pattern', value: '' },
		schema_type: 'WebPage',
		template: { name: '{{post_title}}', url: '{{permalink}}' },
	};
}

/** The condition-specific control shown inline in the rule's sentence. */
function ConditionControl( { rule, onChange } ) {
	const { type, value } = rule.match;

	if ( 'catch_all' === type ) {
		return <span className="schema-rule-catchall-note">{ __( 'every other page', 'schema-plugin' ) }</span>;
	}

	if ( 'post_type' === type ) {
		return (
			<SelectControl
				label={ __( 'Content type', 'schema-plugin' ) }
				hideLabelFromVision
				value={ 'string' === typeof value ? value : '' }
				options={ [ { label: __( 'Choose one…', 'schema-plugin' ), value: '' }, ...POST_TYPE_OPTIONS ] }
				onChange={ onChange }
			/>
		);
	}

	if ( 'url_pattern' === type ) {
		return (
			<TextControl
				label={ __( 'Web address pattern', 'schema-plugin' ) }
				hideLabelFromVision
				value={ 'string' === typeof value ? value : '' }
				placeholder="/shop/*"
				help={ __( 'Use * as a wildcard -- /shop/* matches every page starting with /shop/.', 'schema-plugin' ) }
				onChange={ onChange }
			/>
		);
	}

	if ( 'post_type_taxonomy' === type ) {
		const v = value && 'object' === typeof value ? value : { post_type: '', taxonomy: '', term: '' };
		return (
			<div className="schema-rule-taxonomy">
				<SelectControl
					label={ __( 'Content type', 'schema-plugin' ) }
					hideLabelFromVision
					value={ v.post_type }
					options={ [ { label: __( 'Choose one…', 'schema-plugin' ), value: '' }, ...POST_TYPE_OPTIONS ] }
					onChange={ ( post_type ) => onChange( { ...v, post_type } ) }
				/>
				<span className="schema-rule-taxonomy-label">{ __( 'tagged with', 'schema-plugin' ) }</span>
				<TextControl
					label={ __( 'Category or tag type', 'schema-plugin' ) }
					hideLabelFromVision
					value={ v.taxonomy }
					placeholder={ __( 'e.g. category', 'schema-plugin' ) }
					onChange={ ( taxonomy ) => onChange( { ...v, taxonomy } ) }
				/>
				<TextControl
					label={ __( 'Category or tag name', 'schema-plugin' ) }
					hideLabelFromVision
					value={ v.term }
					placeholder={ __( 'e.g. news', 'schema-plugin' ) }
					onChange={ ( term ) => onChange( { ...v, term } ) }
				/>
			</div>
		);
	}

	if ( 'post_id' === type ) {
		return <PagePicker value={ 'string' === typeof value ? value : '' } onChange={ onChange } />;
	}

	return null;
}

/** The "tag it as" dropdown -- friendly labels, with an "Other" escape hatch for any schema.org type string. */
function SchemaTypeControl( { value, onChange } ) {
	const [ forceCustom, setForceCustom ] = useState( false );
	const isKnown = FRIENDLY_SCHEMA_TYPES.some( ( option ) => option.value === value );
	const showCustom = forceCustom || ! isKnown;

	return (
		<div className="schema-rule-type-control">
			<SelectControl
				label={ __( 'Tag it as', 'schema-plugin' ) }
				hideLabelFromVision
				value={ showCustom ? CUSTOM_TYPE_VALUE : value }
				options={ [
					...FRIENDLY_SCHEMA_TYPES,
					{ label: __( 'Other (advanced)', 'schema-plugin' ), value: CUSTOM_TYPE_VALUE },
				] }
				onChange={ ( newValue ) => {
					if ( CUSTOM_TYPE_VALUE === newValue ) {
						setForceCustom( true );
						return;
					}
					setForceCustom( false );
					onChange( newValue );
				} }
			/>
			{ showCustom && (
				<TextControl
					label={ __( 'Custom schema.org type', 'schema-plugin' ) }
					hideLabelFromVision
					value={ value }
					placeholder={ __( 'e.g. Recipe, Event, Course…', 'schema-plugin' ) }
					onChange={ onChange }
				/>
			) }
		</div>
	);
}

function TemplateEditor( { template, onChange } ) {
	const flat = Object.entries( template || {} ).filter( ( [ , v ] ) => 'string' === typeof v );
	const nestedKeys = Object.keys( template || {} ).filter(
		( k ) => 'string' !== typeof template[ k ]
	);

	const commit = ( nextFlat ) => {
		const rebuilt = {};
		nextFlat.forEach( ( [ k, v ] ) => {
			if ( k ) {
				rebuilt[ k ] = v;
			}
		} );
		nestedKeys.forEach( ( k ) => {
			rebuilt[ k ] = template[ k ];
		} );
		onChange( rebuilt );
	};

	return (
		<div className="schema-rule-template">
			<p className="schema-field-label">{ __( 'Property → token', 'schema-plugin' ) }</p>
			{ flat.map( ( [ key, val ], index ) => (
				// eslint-disable-next-line react/no-array-index-key
				<div className="schema-template-row" key={ index }>
					<TextControl
						label={ __( 'Property', 'schema-plugin' ) }
						hideLabelFromVision
						value={ key }
						placeholder={ __( 'property', 'schema-plugin' ) }
						onChange={ ( v ) => {
							const next = [ ...flat ];
							next[ index ] = [ v, next[ index ][ 1 ] ];
							commit( next );
						} }
					/>
					<TextControl
						label={ __( 'Token', 'schema-plugin' ) }
						hideLabelFromVision
						value={ val }
						placeholder="{{post_title}}"
						onChange={ ( v ) => {
							const next = [ ...flat ];
							next[ index ] = [ next[ index ][ 0 ], v ];
							commit( next );
						} }
					/>
					<Button
						icon="no-alt"
						label={ __( 'Remove property', 'schema-plugin' ) }
						aria-label={ __( 'Remove property', 'schema-plugin' ) }
						onClick={ () => {
							const next = [ ...flat ];
							next.splice( index, 1 );
							commit( next );
						} }
					/>
				</div>
			) ) }
			{ nestedKeys.length > 0 && (
				<p className="schema-template-nested-note">
					{ __(
						'This rule also sets nested properties (e.g. author, publisher) not editable here.',
						'schema-plugin'
					) }
				</p>
			) }
			<Button variant="secondary" onClick={ () => commit( [ ...flat, [ '', '' ] ] ) }>
				{ __( 'Add property', 'schema-plugin' ) }
			</Button>
		</div>
	);
}

function RuleRow( { rule, index, total, onUpdate, onRemove, onMove } ) {
	const isLastCatchAll = 'catch_all' === rule.match.type && index === total - 1;

	return (
		<div className="schema-rule-card">
			<div className="schema-rule-card-toolbar">
				<span className="schema-rule-card-number">
					{ __( 'Rule', 'schema-plugin' ) } { index + 1 }
				</span>
				<div className="schema-rule-card-actions">
					<Button
						icon="arrow-up-alt2"
						label={ __( 'Move rule up', 'schema-plugin' ) }
						aria-label={ __( 'Move rule up', 'schema-plugin' ) }
						disabled={ 0 === index }
						onClick={ () => onMove( index, -1 ) }
					/>
					<Button
						icon="arrow-down-alt2"
						label={ __( 'Move rule down', 'schema-plugin' ) }
						aria-label={ __( 'Move rule down', 'schema-plugin' ) }
						disabled={ index === total - 1 }
						onClick={ () => onMove( index, 1 ) }
					/>
					<Button
						icon="trash"
						variant="tertiary"
						isDestructive
						disabled={ isLastCatchAll }
						label={ __( 'Delete this rule', 'schema-plugin' ) }
						aria-label={ __( 'Delete this rule', 'schema-plugin' ) }
						onClick={ () => onRemove( index ) }
					/>
				</div>
			</div>

			<div className="schema-rule-sentence">
				<span className="schema-rule-sentence-word">{ __( 'When the page is…', 'schema-plugin' ) }</span>

				<SelectControl
					label={ __( 'Condition', 'schema-plugin' ) }
					hideLabelFromVision
					value={ rule.match.type }
					options={ MATCH_TYPES }
					disabled={ isLastCatchAll }
					onChange={ ( type ) => onUpdate( { ...rule, match: { type, value: '' } } ) }
				/>

				<ConditionControl
					rule={ rule }
					onChange={ ( value ) => onUpdate( { ...rule, match: { ...rule.match, value } } ) }
				/>

				<span className="schema-rule-sentence-arrow" aria-hidden="true">→</span>

				<SchemaTypeControl
					value={ rule.schema_type }
					onChange={ ( schemaType ) => onUpdate( { ...rule, schema_type: schemaType } ) }
				/>
			</div>

			<details className="schema-rule-advanced">
				<summary>
					{ rule.raw_override && rule.raw_override.trim()
						? __( 'Includes: raw JSON-LD (replaces the fields)', 'schema-plugin' )
						: <>{ __( 'Includes:', 'schema-plugin' ) } { summarizeTemplate( rule.template ) }</> }
				</summary>
				<TemplateEditor
					template={ rule.template }
					onChange={ ( template ) => onUpdate( { ...rule, template } ) }
				/>
				<RawJsonField
					label={ __( 'Raw JSON-LD for this rule (advanced)', 'schema-plugin' ) }
					help={ __(
						'If filled in, this replaces the property list above for pages this rule matches. Tokens such as {{post_title}}, {{permalink}} and {{organization_id}} still work inside it. Leave it empty to use the list.',
						'schema-plugin'
					) }
					value={ rule.raw_override || '' }
					onChange={ ( raw_override ) => onUpdate( { ...rule, raw_override } ) }
					rows={ 10 }
				/>
			</details>
		</div>
	);
}

/**
 * Default Rules manager -- CLAUDE.md Section 7 item 3, reused unmodified
 * in the wizard's Step 3. Rewritten as plain-language sentences ("When
 * the page is [a specific type of content: Products] → Tag it as
 * [Product]") rather than a raw condition/value/type grid, since most
 * WordPress site owners don't know terms like "post_type" or "taxonomy"
 * and shouldn't need to. The underlying data model and REST payload are
 * unchanged -- this only changes how it's presented.
 */
export default function DefaultRulesManager() {
	const [ rules, setRules ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ saved, setSaved ] = useState( false );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		getDefaultRules().then( ( res ) => setRules( res.rules ) );
	}, [] );

	const update = ( next ) => {
		setRules( next );
		setSaved( false );
	};

	const updateRuleAt = ( index, rule ) => {
		const next = [ ...rules ];
		next[ index ] = rule;
		update( next );
	};

	const removeAt = ( index ) => {
		const next = [ ...rules ];
		next.splice( index, 1 );
		update( next );
	};

	const moveAt = ( index, delta ) => {
		const next = [ ...rules ];
		const target = index + delta;
		if ( target < 0 || target >= next.length ) {
			return;
		}
		[ next[ index ], next[ target ] ] = [ next[ target ], next[ index ] ];
		update( next );
	};

	const addRule = () => {
		const next = [ ...rules ];
		next.splice( Math.max( next.length - 1, 0 ), 0, emptyRule() );
		update( next );
	};

	const save = () => {
		setSaving( true );
		setError( '' );
		saveDefaultRules( rules )
			.then( ( res ) => {
				setRules( res.rules );
				setSaved( true );
			} )
			.catch( ( err ) => setError( err.message || __( 'Something went wrong.', 'schema-plugin' ) ) )
			.finally( () => setSaving( false ) );
	};

	if ( ! rules ) {
		return <Spinner />;
	}

	return (
		<div className="schema-default-rules-manager">
			<p className="schema-rules-explainer">
				{ __(
					'Rules are checked from top to bottom -- the first one that matches a page decides its schema. Keep "Everything else" as your last rule so no page is left blank.',
					'schema-plugin'
				) }
			</p>

			<div className="schema-rules-toolbar">
				<Button variant="secondary" onClick={ addRule }>
					{ __( 'Add rule', 'schema-plugin' ) }
				</Button>
			</div>

			{ rules.map( ( rule, index ) => (
				<RuleRow
					key={ rule.id || index }
					rule={ rule }
					index={ index }
					total={ rules.length }
					onUpdate={ ( r ) => updateRuleAt( index, r ) }
					onRemove={ removeAt }
					onMove={ moveAt }
				/>
			) ) }

			<div className="schema-rules-save">
				<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving }>
					{ __( 'Save rules', 'schema-plugin' ) }
				</Button>
				{ saved && (
					<Notice status="success" isDismissible={ false }>
						{ __( 'Saved.', 'schema-plugin' ) }
					</Notice>
				) }
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
			</div>
		</div>
	);
}
