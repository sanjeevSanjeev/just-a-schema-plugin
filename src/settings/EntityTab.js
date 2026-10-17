import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { Button, Notice, Spinner, ToggleControl } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { useEntitySections, filledLabel } from '../shared/EntityForm';
import { getEntity, saveEntity } from '../shared/api';
import { getWizardCopy } from '../shared/wizardCopy';
import RawJsonField from '../shared/RawJsonField';
import PreviewPanel from './PreviewPanel';

const bootstrap = window.SchemaPluginData || {};

function initialSection() {
	return new URLSearchParams( window.location.search ).get( 'section' ) || 'basics';
}

function rememberSection( key ) {
	const url = new URL( window.location.href );
	url.searchParams.set( 'section', key );
	window.history.replaceState( null, '', url );
}

/**
 * Settings → Site entity -- CLAUDE.md Section 7 item 4: "reachable at any
 * time after setup completes... Reuse the same React components and the
 * same /entity REST endpoint the wizard uses."
 *
 * Details only, for the type chosen in the wizard: the type is shown
 * read-only with a "Change type" link that re-runs the wizard (user
 * decision 2026-09-26, deviating from Section 7 item 4's type picker
 * here), so the non-destructive legacy-field handling runs through one
 * flow.
 *
 * Layout (user decision 2026-09-26): a section list with progress on the
 * left, one section at a time on the right (same sections and fields as
 * the wizard's stacked form, via useEntitySections), Previous/Next to walk
 * through them, the live preview in a slide-over panel opened from a
 * button that shows the current warning count, and a sticky save bar that
 * covers every section and warns before leaving with unsaved changes.
 */
export default function EntityTab() {
	const [ value, setValue ] = useState( null );
	const [ savedSnapshot, setSavedSnapshot ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ active, setActive ] = useState( initialSection );
	const [ previewOpen, setPreviewOpen ] = useState( false );
	const [ warningCount, setWarningCount ] = useState( 0 );
	const previewButtonRef = useRef();

	useEffect( () => {
		getEntity().then( ( res ) => {
			const loaded = {
				entity_type: res.entity_type,
				flavor: res.flavor,
				fields: res.fields,
				website_enabled_search: res.website_enabled_search,
				breadcrumbs_enabled: res.breadcrumbs_enabled,
				raw_override: res.raw_override || '',
			};
			setValue( loaded );
			setSavedSnapshot( JSON.stringify( loaded ) );
		} );
	}, [] );

	const dirty = !! value && JSON.stringify( value ) !== savedSnapshot;

	useEffect( () => {
		if ( ! dirty ) {
			return undefined;
		}
		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty ] );

	const closePreview = useCallback( () => {
		setPreviewOpen( false );
		previewButtonRef.current?.focus();
	}, [] );

	const onPreviewResult = useCallback( ( res ) => setWarningCount( ( res?.warnings || [] ).length ), [] );

	// Hooks must run on every render, so sections are computed before the
	// early returns below (they're empty until an entity type exists).
	const { sections, renderSectionBody } = useEntitySections( {
		value: value || { entity_type: '', fields: {} },
		onChange: setValue,
	} );

	if ( ! value ) {
		return <Spinner />;
	}

	if ( ! value.entity_type ) {
		return (
			<div className="schema-entity-settings schema-settings-tab">
				<h2>{ __( 'Site entity', 'schema-plugin' ) }</h2>
				<Notice status="info" isDismissible={ false }>
					{ __( 'Choose what kind of site this is first; the details depend on it.', 'schema-plugin' ) }
				</Notice>
				<p>
					<Button variant="primary" href={ bootstrap.wizardUrl }>
						{ __( 'Run setup wizard', 'schema-plugin' ) }
					</Button>
				</p>
			</div>
		);
	}

	const save = () => {
		setSaving( true );
		setError( '' );
		saveEntity( value )
			.then( ( res ) => {
				const next = {
					...value,
					fields: res.fields,
					website_enabled_search: res.website_enabled_search,
					breadcrumbs_enabled: res.breadcrumbs_enabled,
					raw_override: res.raw_override || '',
				};
				setValue( next );
				setSavedSnapshot( JSON.stringify( next ) );
			} )
			.catch( ( err ) => setError( err.message || __( 'Something went wrong.', 'schema-plugin' ) ) )
			.finally( () => setSaving( false ) );
	};

	const allSections = [
		...sections,
		{
			key: 'search',
			title: __( 'Search appearance', 'schema-plugin' ),
			status: sprintf(
				/* translators: %d: number of search-appearance options that are on (0-2). */
				__( '%d of 2 on', 'schema-plugin' ),
				( value.website_enabled_search ? 1 : 0 ) + ( value.breadcrumbs_enabled ? 1 : 0 )
			),
		},
		{
			key: 'raw',
			title: __( 'Raw JSON-LD (advanced)', 'schema-plugin' ),
			status: value.raw_override.trim() ? __( 'In use', 'schema-plugin' ) : __( 'Off', 'schema-plugin' ),
		},
	];
	const current = allSections.find( ( s ) => s.key === active ) || allSections[ 0 ];
	const index = allSections.indexOf( current );
	const prev = allSections[ index - 1 ];
	const next = allSections[ index + 1 ];

	const go = ( key ) => {
		setActive( key );
		rememberSection( key );
		document.getElementById( 'schema-entity-section-title' )?.focus();
	};

	const statusOf = ( section ) => section.status ?? `${ section.filled }/${ section.keys.length }`;
	const isComplete = ( section ) => section.keys && section.filled === section.keys.length;
	const copy = getWizardCopy( value.entity_type );

	return (
		<div className="schema-entity-settings schema-settings-tab">
			<div className="schema-entity-intro">
				<div>
					<h2>{ copy.details_heading }</h2>
					<p className="schema-wizard-step-intro">
						{ __( 'This shows up in your structured data across the whole site.', 'schema-plugin' ) }
					</p>
				</div>
				<p className="schema-entity-type-line">
					<span className="schema-dashboard-type">
						{ bootstrap.lockedTypes?.[ value.entity_type ]?.label || value.entity_type }
					</span>{ ' ' }
					<a href={ bootstrap.wizardUrl }>
						{ __( 'Change type', 'schema-plugin' ) }
						<span className="screen-reader-text">{ __( '(re-runs the setup wizard)', 'schema-plugin' ) }</span>
					</a>
				</p>
			</div>

			<div className="schema-entity-nav-layout">
				<nav className="schema-entity-nav" aria-label={ __( 'Site entity sections', 'schema-plugin' ) }>
					<ul>
						{ allSections.map( ( section ) => (
							<li key={ section.key }>
								<button
									type="button"
									className={ `schema-entity-nav-item${ section.key === current.key ? ' is-active' : '' }` }
									aria-current={ section.key === current.key ? 'step' : undefined }
									onClick={ () => go( section.key ) }
								>
									<span className="schema-entity-nav-title">{ section.title }</span>
									<span className={ `schema-entity-nav-status${ isComplete( section ) ? ' is-complete' : '' }` }>
										{ isComplete( section ) && <span aria-hidden="true">✓ </span> }
										<span aria-hidden="true">{ statusOf( section ) }</span>
										<span className="screen-reader-text">
											{ section.keys ? filledLabel( section ) : section.status }
										</span>
									</span>
								</button>
							</li>
						) ) }
					</ul>
					<Button
						ref={ previewButtonRef }
						variant="secondary"
						className="schema-entity-preview-button"
						onClick={ () => setPreviewOpen( true ) }
						aria-haspopup="dialog"
					>
						{ __( 'Preview JSON-LD', 'schema-plugin' ) }
						{ warningCount > 0 && (
							<span className="schema-entity-preview-warnings">
								{ sprintf(
									/* translators: %d: number of preview warnings. */
									_n( '%d warning', '%d warnings', warningCount, 'schema-plugin' ),
									warningCount
								) }
							</span>
						) }
					</Button>
				</nav>

				<section className="schema-entity-panel" aria-labelledby="schema-entity-section-title">
					<div className="schema-form-section-head">
						<h3 className="schema-form-section-title" id="schema-entity-section-title" tabIndex={ -1 }>
							{ current.title }
						</h3>
						{ current.keys && <span className="schema-form-section-count">{ filledLabel( current ) }</span> }
					</div>

					{ 'search' === current.key ? (
						<>
							<ToggleControl
								label={ __( 'Sitelinks search box', 'schema-plugin' ) }
								help={ __(
									'Adds SearchAction markup so Google can show a search box for your site in results.',
									'schema-plugin'
								) }
								checked={ !! value.website_enabled_search }
								onChange={ ( website_enabled_search ) =>
									setValue( { ...value, website_enabled_search } )
								}
							/>
							<ToggleControl
								label={ __( 'Breadcrumbs', 'schema-plugin' ) }
								help={ __(
									'Adds BreadcrumbList markup (Home › category › page) so Google can show the page’s place in your site. Not added to the homepage.',
									'schema-plugin'
								) }
								checked={ !! value.breadcrumbs_enabled }
								onChange={ ( breadcrumbs_enabled ) => setValue( { ...value, breadcrumbs_enabled } ) }
							/>
						</>
					) : 'raw' === current.key ? (
						<>
							{ value.raw_override.trim() && (
								<Notice status="warning" isDismissible={ false }>
									{ __(
										'In use: this replaces your site entity on every page. The fields in the other sections are kept, but not output while this is filled in.',
										'schema-plugin'
									) }
								</Notice>
							) }
							<RawJsonField
								label={ __( 'Site entity as raw JSON-LD', 'schema-plugin' ) }
								help={ __(
									'One JSON object that becomes your Organization or Person node. Leave @id out to keep the one other nodes point at (publisher, provider...). Clear the box to go back to the fields.',
									'schema-plugin'
								) }
								value={ value.raw_override }
								onChange={ ( raw_override ) => setValue( { ...value, raw_override } ) }
								rows={ 14 }
							/>
						</>
					) : (
						renderSectionBody( current )
					) }

					<div className="schema-entity-panel-nav">
						{ prev ? (
							<Button variant="tertiary" onClick={ () => go( prev.key ) }>
								{ sprintf(
									/* translators: %s: previous section title. */
									__( '← %s', 'schema-plugin' ),
									prev.title
								) }
							</Button>
						) : (
							<span />
						) }
						{ next && (
							<Button variant="secondary" onClick={ () => go( next.key ) }>
								{ sprintf(
									/* translators: %s: next section title. */
									__( 'Next: %s →', 'schema-plugin' ),
									next.title
								) }
							</Button>
						) }
					</div>
				</section>
			</div>

			<div className="schema-save-bar" role="region" aria-label={ __( 'Save', 'schema-plugin' ) }>
				<span className={ `schema-save-status${ dirty ? ' is-dirty' : '' }` } aria-live="polite">
					{ dirty ? __( 'Unsaved changes', 'schema-plugin' ) : __( 'All changes saved', 'schema-plugin' ) }
				</span>
				{ error && <span className="schema-save-error">{ error }</span> }
				<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving || ! dirty }>
					{ __( 'Save changes', 'schema-plugin' ) }
				</Button>
			</div>

			<PreviewPanel
				open={ previewOpen }
				onClose={ closePreview }
				onResult={ onPreviewResult }
				payload={ {
					entity_type: value.entity_type,
					flavor: value.flavor,
					fields: value.fields,
					...( value.raw_override.trim() ? { raw_override: value.raw_override } : {} ),
				} }
			/>
		</div>
	);
}
