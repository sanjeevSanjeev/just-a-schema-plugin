import { useEffect, useState } from '@wordpress/element';
import { Button, Notice, Spinner, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { EntityFields } from '../shared/EntityForm';
import LivePreview from '../shared/LivePreview';
import { getEntity, saveEntity } from '../shared/api';
import { getWizardCopy } from '../shared/wizardCopy';

const bootstrap = window.SchemaPluginData || {};

/**
 * Settings → Site entity -- CLAUDE.md Section 7 item 4: "reachable at any
 * time after setup completes... Reuse the same React components and the
 * same /entity REST endpoint the wizard uses." Two-column layout with a
 * sticky live preview at >=1200px (Section 10.8.B), open by default there.
 *
 * Details only, for the type chosen in the wizard: the type is shown
 * read-only with a "Change type" link that re-runs the wizard (user
 * decision 2026-09-26, deviating from Section 7 item 4's type picker
 * here), so the non-destructive legacy-field handling runs through one
 * flow.
 *
 * Fields come grouped by priority (<EntityFields>); the sitelinks toggle
 * is a WebSite setting, so it gets its own "Search appearance" section.
 * A sticky save bar shows whether there are unsaved changes, and leaving
 * the page with unsaved changes asks first.
 */
export default function EntityTab() {
	const [ value, setValue ] = useState( null );
	const [ savedSnapshot, setSavedSnapshot ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		getEntity().then( ( res ) => {
			const loaded = {
				entity_type: res.entity_type,
				flavor: res.flavor,
				fields: res.fields,
				website_enabled_search: res.website_enabled_search,
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
				const next = { ...value, fields: res.fields, website_enabled_search: res.website_enabled_search };
				setValue( next );
				setSavedSnapshot( JSON.stringify( next ) );
			} )
			.catch( ( err ) => setError( err.message || __( 'Something went wrong.', 'schema-plugin' ) ) )
			.finally( () => setSaving( false ) );
	};

	const copy = getWizardCopy( value.entity_type );
	const wide = window.matchMedia?.( '(min-width: 1200px)' ).matches;

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

			<div className="schema-entity-settings-layout">
				<div className="schema-entity-settings-form">
					<EntityFields value={ value } onChange={ setValue } />

					<section className="schema-form-section">
						<div className="schema-form-section-head">
							<h3 className="schema-form-section-title">{ __( 'Search appearance', 'schema-plugin' ) }</h3>
						</div>
						<ToggleControl
							label={ __( 'Sitelinks search box', 'schema-plugin' ) }
							help={ __(
								'Adds SearchAction markup so Google can show a search box for your site in results.',
								'schema-plugin'
							) }
							checked={ !! value.website_enabled_search }
							onChange={ ( website_enabled_search ) => setValue( { ...value, website_enabled_search } ) }
						/>
					</section>

					<div className="schema-save-bar" role="region" aria-label={ __( 'Save', 'schema-plugin' ) }>
						<span className={ `schema-save-status${ dirty ? ' is-dirty' : '' }` } aria-live="polite">
							{ dirty ? __( 'Unsaved changes', 'schema-plugin' ) : __( 'All changes saved', 'schema-plugin' ) }
						</span>
						{ error && <span className="schema-save-error">{ error }</span> }
						<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving || ! dirty }>
							{ __( 'Save changes', 'schema-plugin' ) }
						</Button>
					</div>
				</div>

				<div className="schema-entity-settings-preview">
					<LivePreview
						defaultOpen={ wide }
						payload={ { entity_type: value.entity_type, flavor: value.flavor, fields: value.fields } }
					/>
				</div>
			</div>
		</div>
	);
}
