import { useState } from '@wordpress/element';
import { TextControl, Button, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import UrlField from './fields/UrlField';
import TextareaField from './fields/TextareaField';
import SameAsBuilder from './fields/SameAsBuilder';
import TypedField from './fields/TypedField';
import withRequiredMark from './fields/withRequiredMark';
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
 * Sections, in priority order: what identifies you first, optional detail
 * last. Keys not listed land in "Other details" so a new field is never
 * hidden. Collapsed sections hold rarely needed fields.
 */
const SECTIONS = [
	{
		key: 'basics',
		title: __( 'Basics', 'schema-plugin' ),
		keys: [ 'name', 'logo', 'image', 'url', 'description', 'jobTitle' ],
	},
	{
		key: 'contact',
		title: __( 'Contact & location', 'schema-plugin' ),
		keys: [ 'email', 'telephone', 'address', 'geo' ],
	},
	{ key: 'social', title: __( 'Social profiles', 'schema-plugin' ), keys: [ 'sameAs' ] },
	{
		key: 'business',
		title: __( 'Business details', 'schema-plugin' ),
		keys: [ 'openingHoursSpecification', 'priceRange', 'department' ],
	},
	{
		key: 'store',
		title: __( 'Store policies', 'schema-plugin' ),
		keys: [ 'hasMerchantReturnPolicy', 'hasShippingService' ],
	},
	{
		key: 'identity',
		title: __( 'Legal & identifiers', 'schema-plugin' ),
		keys: [ 'alternateName', 'legalName', 'vatID', 'taxID', 'foundingDate', 'founder' ],
		collapsed: true,
	},
	{
		key: 'reviews',
		title: __( 'Reviews of other businesses', 'schema-plugin' ),
		keys: [ 'aggregateRating', 'review' ],
		collapsed: true,
	},
];

// Short fields shown two to a row on wide screens.
const PAIRED = [ 'email', 'telephone', 'alternateName', 'legalName', 'vatID', 'taxID', 'foundingDate', 'founder' ];

function isFilled( fieldValue ) {
	if ( Array.isArray( fieldValue ) ) {
		return fieldValue.some( ( v ) => '' !== v && null !== v );
	}
	if ( fieldValue && 'object' === typeof fieldValue ) {
		return Object.entries( fieldValue ).some( ( [ k, v ] ) => '@type' !== k && '' !== v && null !== v && undefined !== v );
	}
	return '' !== ( fieldValue ?? '' );
}

/**
 * The site-details form's building blocks -- Tier 1 fields plus
 * type-specific extras, grouped into prioritized sections, shared by the
 * wizard's Details step (<EntityFields>, stacked) and Settings → Site
 * entity (section list + one section at a time). Labels personalize to the
 * chosen type via getWizardCopy() -- "Business name" rather than generic
 * "Name" once "Local or Small Business" is picked.
 *
 * A "Recommended" badge shows only while a field is empty, so the badges
 * double as a to-do list; each section reports how many fields are filled.
 */
export function useEntitySections( { value, onChange } ) {
	const { entity_type: entityType, fields = {} } = value;
	const copy = getWizardCopy( entityType );
	const fieldDefs = getFieldDefsFor( entityType );

	const setField = ( key, fieldValue ) => {
		onChange( { ...value, fields: { ...fields, [ key ]: fieldValue } } );
	};

	const available = entityType
		? [ ...TIER1_KEYS, ...Object.keys( fieldDefs ).filter( ( key ) => ! TIER1_KEYS.includes( key ) ) ]
		: [];
	const placed = SECTIONS.flatMap( ( section ) => section.keys );
	const sections = [
		...SECTIONS,
		{ key: 'other', title: __( 'Other details', 'schema-plugin' ), keys: available.filter( ( k ) => ! placed.includes( k ) ) },
	]
		.map( ( section ) => {
			const keys = section.keys.filter( ( key ) => available.includes( key ) );
			return { ...section, keys, filled: keys.filter( ( key ) => isFilled( fields[ key ] ) ).length };
		} )
		.filter( ( section ) => section.keys.length );

	const renderField = ( key ) => {
		switch ( key ) {
			case 'name':
				return (
					<TextControl
						// Required for Person/LocalBusiness; Google only recommends it for Organization.
						label={ withRequiredMark( copy.name_label, fieldDefs.name?.required ?? true ) }
						value={ fields.name || '' }
						onChange={ ( v ) => setField( 'name', v ) }
					/>
				);
			case 'url':
				return (
					<UrlField
						label={ __( 'URL', 'schema-plugin' ) }
						value={ fields.url }
						onChange={ ( v ) => setField( 'url', v ) }
						help={ __( 'Defaults to your homepage if left blank.', 'schema-plugin' ) }
					/>
				);
			case 'description':
				return (
					<TextareaField
						label={ copy.description_label }
						value={ fields.description }
						onChange={ ( v ) => setField( 'description', v ) }
						help={ copy.description_help }
					/>
				);
			case 'sameAs':
				return <SameAsBuilder value={ fields.sameAs } onChange={ ( v ) => setField( 'sameAs', v ) } />;
			default: {
				const def = fieldDefs[ key ] || {};
				return (
					<TypedField
						fieldKey={ key }
						def={ { ...def, recommended: !! def.recommended && ! isFilled( fields[ key ] ) } }
						value={ fields[ key ] }
						onChange={ ( v ) => setField( key, v ) }
					/>
				);
			}
		}
	};

	const renderSectionBody = ( section ) => {
		const rows = [];
		section.keys.forEach( ( key ) => {
			const last = rows[ rows.length - 1 ];
			if ( PAIRED.includes( key ) && last?.paired && 1 === last.keys.length ) {
				last.keys.push( key );
			} else {
				rows.push( { paired: PAIRED.includes( key ), keys: [ key ] } );
			}
		} );
		return rows.map( ( row ) => (
			<div className={ row.paired ? 'schema-form-row is-paired' : 'schema-form-row' } key={ row.keys.join( '-' ) }>
				{ row.keys.map( ( key ) => (
					<div className="schema-form-cell" key={ key }>
						{ renderField( key ) }
					</div>
				) ) }
			</div>
		) );
	};

	return { sections, renderSectionBody };
}

/** "N of M filled" for a section from useEntitySections(). */
export function filledLabel( section ) {
	return sprintf(
		/* translators: 1: number of filled fields, 2: number of fields in the section. */
		__( '%1$d of %2$d filled', 'schema-plugin' ),
		section.filled,
		section.keys.length
	);
}

/**
 * Stacked layout of the site-details form: every section in priority
 * order down one column (the wizard's Details step, a linear flow in a
 * 720px column). Settings → Site entity shows the same sections one at a
 * time with a section list instead (see EntityTab).
 */
export function EntityFields( { value, onChange } ) {
	const { sections, renderSectionBody } = useEntitySections( { value, onChange } );

	if ( ! value.entity_type ) {
		return null;
	}

	return (
		<div className="schema-entity-sections">
			{ sections.map( ( section ) => {
				const heading = (
					<>
						<h3 className="schema-form-section-title">{ section.title }</h3>
						<span className="schema-form-section-count">{ filledLabel( section ) }</span>
					</>
				);

				return section.collapsed ? (
					<details className="schema-form-section is-collapsible" key={ section.key } open={ section.filled > 0 }>
						<summary className="schema-form-section-head">{ heading }</summary>
						{ renderSectionBody( section ) }
					</details>
				) : (
					<section className="schema-form-section" key={ section.key }>
						<div className="schema-form-section-head">{ heading }</div>
						{ renderSectionBody( section ) }
					</section>
				);
			} ) }
		</div>
	);
}
