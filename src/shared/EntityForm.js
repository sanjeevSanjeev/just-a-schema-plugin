import { useState } from '@wordpress/element';
import { TextControl, Button, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import UrlField from './fields/UrlField';
import TextareaField from './fields/TextareaField';
import SameAsBuilder from './fields/SameAsBuilder';
import TypedField from './fields/TypedField';
import withRequiredMark from './fields/withRequiredMark';
import LivePreview from './LivePreview';
import { getWizardCopy, getTypeIcon } from './wizardCopy';

const bootstrap = window.SchemaPluginData || {};
const lockedTypes = bootstrap.lockedTypes || {};
const fieldDefsConfig = bootstrap.fieldDefs || {};
const TIER1_KEYS = [ 'name', 'url', 'description', 'sameAs' ];

function getFieldDefsFor( typeIdentifier ) {
	if ( ! typeIdentifier ) {
		return {};
	}
	if ( 'local_business' === typeIdentifier || 'LocalBusiness' === typeIdentifier ) {
		return { ...fieldDefsConfig.localBusiness };
	}
	if ( lockedTypes[ typeIdentifier ] ) {
		return { ...( fieldDefsConfig.entityExtra?.[ typeIdentifier ] || {} ) };
	}
	return {}; // custom "Show more" type -- Tier 1 fields only, per CLAUDE.md complaint #4.
}

export function badgeLabel( color ) {
	if ( 'green' === color ) {
		return __( 'Google Rich Result eligible', 'schema-plugin' );
	}
	if ( 'blue' === color ) {
		return __( 'Structured Data Only', 'schema-plugin' );
	}
	return __( 'Schema.org Valid', 'schema-plugin' );
}

export function resolveBadgeColor( typeIdentifier ) {
	const locked = lockedTypes[ typeIdentifier ];
	if ( locked ) {
		return locked.google_status;
	}
	const schemaType = typeIdentifier;
	const entry = bootstrap.typeStatus?.[ schemaType ];
	if ( ! entry ) {
		return 'white';
	}
	if ( entry.google_rich_result ) {
		return 'green';
	}
	if ( entry.geo_relevant ) {
		return 'blue';
	}
	return 'white';
}

/**
 * The type-selection screen -- composition suggestion chip, the 5 locked
 * type cards + Show More, and the post-selection disambiguation nudge
 * (CLAUDE.md Section 2/7). Split out from the field form so the wizard can
 * present type selection and site details as separate, more focused
 * screens; Entity Settings still composes both via <EntityForm> below.
 */
export function TypePicker( { value, onChange } ) {
	const { entity_type: entityType } = value;
	const [ showMore, setShowMore ] = useState( !! entityType && ! lockedTypes[ entityType ] );
	const [ customTypeQuery, setCustomTypeQuery ] = useState(
		entityType && ! lockedTypes[ entityType ] ? entityType : ''
	);
	const [ compositionDismissed, setCompositionDismissed ] = useState( false );

	const selectLockedType = ( key ) => {
		onChange( {
			...value,
			entity_type: key,
			flavor: lockedTypes[ key ]?.flavor || null,
		} );
		setShowMore( false );
		setCustomTypeQuery( '' );
	};

	const selectCustomType = ( typeString ) => {
		onChange( { ...value, entity_type: typeString, flavor: null } );
	};

	const composition = bootstrap.compositionSuggestion;
	const nudgeRule = entityType ? bootstrap.disambiguationRules?.[ entityType ] : null;

	return (
		<div className="schema-type-picker-screen">
			{ composition && ! compositionDismissed && ! entityType && (
				<Notice status="info" onRemove={ () => setCompositionDismissed( true ) }>
					{ sprintf(
						/* translators: 1: detected plugin file, 2: suggested type label. */
						__( 'We noticed %1$s is active -- this is often a %2$s site.', 'schema-plugin' ),
						composition.plugin,
						lockedTypes[ composition.suggested_type ]?.label || composition.suggested_type
					) }{ ' ' }
					<Button variant="link" onClick={ () => selectLockedType( composition.suggested_type ) }>
						{ __( 'Use this suggestion', 'schema-plugin' ) }
					</Button>
				</Notice>
			) }

			<fieldset className="schema-type-picker">
				<legend className="screen-reader-text">{ __( 'What kind of site is this?', 'schema-plugin' ) }</legend>

				<div className="schema-type-options">
					{ Object.entries( lockedTypes ).map( ( [ key, type ] ) => (
						<label
							key={ key }
							className={ `schema-type-option${ entityType === key ? ' is-selected' : '' }` }
						>
							<input
								type="radio"
								name="schema-entity-type"
								value={ key }
								checked={ entityType === key }
								onChange={ () => selectLockedType( key ) }
							/>
							<span className={ `schema-type-option-icon dashicons ${ getTypeIcon( key ) }` } aria-hidden="true" />
							<span className="schema-type-option-label">{ type.label }</span>
							<span className="schema-type-option-description">{ type.description }</span>
							<span className={ `schema-badge schema-badge-${ type.google_status }` }>
								{ badgeLabel( type.google_status ) }
							</span>
						</label>
					) ) }
				</div>

				<Button
					variant="tertiary"
					onClick={ () => setShowMore( ! showMore ) }
					aria-expanded={ showMore }
				>
					{ __( 'Show more types…', 'schema-plugin' ) }
				</Button>

				{ showMore && (
					<div className="schema-type-custom">
						<TextControl
							label={ __( 'Search schema.org types (e.g. "Corporation")', 'schema-plugin' ) }
							value={ customTypeQuery }
							onChange={ ( v ) => {
								setCustomTypeQuery( v );
								selectCustomType( v );
							} }
						/>
						{ customTypeQuery && (
							<span className="schema-badge schema-badge-white">
								{ __( 'Schema.org Valid', 'schema-plugin' ) }
							</span>
						) }
					</div>
				) }
			</fieldset>

			{ nudgeRule && (
				<Notice status="info" isDismissible={ false }>
					{ nudgeRule.message }{ ' ' }
					<Button variant="link" onClick={ () => selectLockedType( nudgeRule.suggest ) }>
						{ sprintf(
							/* translators: %s: suggested type label. */
							__( 'Switch to %s', 'schema-plugin' ),
							lockedTypes[ nudgeRule.suggest ]?.label
						) }
					</Button>
				</Notice>
			) }
		</div>
	);
}

/**
 * The site-details field screen -- Tier 1 fields plus type-specific
 * extras (LocalBusiness Tier 1.5, or the 2-3 entity extras per other
 * locked type). Labels personalize to the chosen type via
 * getWizardCopy() -- "Business name" rather than generic "Name" once
 * "Local or Small Business" is picked.
 */
export function EntityFields( { value, onChange } ) {
	const { entity_type: entityType, fields = {} } = value;
	const copy = getWizardCopy( entityType );
	const fieldDefs = getFieldDefsFor( entityType );

	const setField = ( key, fieldValue ) => {
		onChange( { ...value, fields: { ...fields, [ key ]: fieldValue } } );
	};

	if ( ! entityType ) {
		return null;
	}

	return (
		<>
			<div className="schema-field-group">
				<TextControl
					// Required for Person/LocalBusiness; Google only recommends it for Organization.
					label={ withRequiredMark( copy.name_label, fieldDefs.name?.required ?? true ) }
					value={ fields.name || '' }
					onChange={ ( v ) => setField( 'name', v ) }
				/>
				<UrlField
					label={ __( 'URL', 'schema-plugin' ) }
					value={ fields.url }
					onChange={ ( v ) => setField( 'url', v ) }
					help={ __( 'Defaults to your homepage if left blank.', 'schema-plugin' ) }
				/>
				<TextareaField
					label={ copy.description_label }
					value={ fields.description }
					onChange={ ( v ) => setField( 'description', v ) }
					help={ copy.description_help }
				/>
				<SameAsBuilder value={ fields.sameAs } onChange={ ( v ) => setField( 'sameAs', v ) } />
			</div>

			{ Object.keys( fieldDefs ).length > 0 && (
				<div className="schema-field-group">
					{ Object.entries( fieldDefs )
						.filter( ( [ key ] ) => ! TIER1_KEYS.includes( key ) )
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
		</>
	);
}

/**
 * Composed type picker + field form -- CLAUDE.md Section 7 item 4: "the
 * SAME field form used in wizard Step 1... Reuse the same React
 * components... do not build a second, separate implementation." The
 * wizard uses <TypePicker> and <EntityFields> as separate screens; Entity
 * Settings uses this composed default export as one ongoing edit screen.
 * Controlled component: parent owns { entity_type, flavor, fields }.
 */
export default function EntityForm( { value, onChange, showLivePreview = true } ) {
	const { entity_type: entityType, flavor, fields = {} } = value;

	return (
		<div className="schema-entity-form">
			<TypePicker value={ value } onChange={ onChange } />
			<EntityFields value={ value } onChange={ onChange } />

			{ showLivePreview && entityType && (
				<LivePreview payload={ { entity_type: entityType, flavor, fields } } />
			) }
		</div>
	);
}
