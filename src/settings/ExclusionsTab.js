import { useEffect, useState } from '@wordpress/element';
import { Button, TextControl, ToggleControl, Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { getExclusions, saveExclusions } from '../shared/api';

/**
 * Exclusions settings page (new -- CLAUDE.md Section 3.5's backend
 * exclusion list and the three toggles existed in PHP since the MVP
 * build, but had no admin UI to manage them until now). System
 * exclusions (wp-admin, feeds, search bots, etc.) are fixed and not
 * shown here -- CLAUDE.md Section 3.5 keeps those hardcoded, never
 * editable UI.
 */
export default function ExclusionsTab() {
	const [ data, setData ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ saved, setSaved ] = useState( false );

	useEffect( () => {
		getExclusions().then( setData );
	}, [] );

	if ( ! data ) {
		return <Spinner />;
	}

	const patterns = data.backend_exclusions;

	const updatePatternAt = ( index, patch ) => {
		const next = [ ...patterns ];
		next[ index ] = { ...next[ index ], ...patch };
		setData( { ...data, backend_exclusions: next } );
		setSaved( false );
	};

	const removeAt = ( index ) => {
		const next = [ ...patterns ];
		next.splice( index, 1 );
		setData( { ...data, backend_exclusions: next } );
		setSaved( false );
	};

	const addPattern = () => {
		setData( {
			...data,
			backend_exclusions: [ ...patterns, { pattern: '', enabled: true, source: 'custom' } ],
		} );
		setSaved( false );
	};

	const save = () => {
		setSaving( true );
		saveExclusions( data ).then( ( res ) => {
			setData( res );
			setSaving( false );
			setSaved( true );
		} );
	};

	return (
		<div className="schema-exclusions-page schema-settings-tab">
			<h2>{ __( 'Exclusions', 'schema-plugin' ) }</h2>
			<p className="schema-wizard-step-intro">
				{ __(
					'Pages matching any of these patterns never get schema output. wp-admin, feeds, and similar system pages are always excluded and not shown here.',
					'schema-plugin'
				) }
			</p>

			<div className="schema-field-group">
				{ patterns.map( ( rule, index ) => (
					// eslint-disable-next-line react/no-array-index-key
					<div className="schema-exclusion-row" key={ index }>
						<ToggleControl
							label={ __( 'Enabled', 'schema-plugin' ) }
							hideLabelFromVision
							checked={ !! rule.enabled }
							onChange={ ( enabled ) => updatePatternAt( index, { enabled } ) }
						/>
						<TextControl
							label={ __( 'Pattern', 'schema-plugin' ) }
							hideLabelFromVision
							value={ rule.pattern }
							placeholder="/cart/*"
							onChange={ ( pattern ) => updatePatternAt( index, { pattern } ) }
						/>
						{ 'default' === rule.source && (
							<span className="schema-badge schema-badge-white">{ __( 'Default', 'schema-plugin' ) }</span>
						) }
						<Button
							icon="trash"
							variant="tertiary"
							isDestructive
							label={ __( 'Remove this pattern', 'schema-plugin' ) }
							aria-label={ __( 'Remove this pattern', 'schema-plugin' ) }
							onClick={ () => removeAt( index ) }
						/>
					</div>
				) ) }

				<Button variant="secondary" onClick={ addPattern }>
					{ __( 'Add pattern', 'schema-plugin' ) }
				</Button>
			</div>

			<div className="schema-field-group">
				<ToggleControl
					label={ __( 'Exclude search results pages', 'schema-plugin' ) }
					checked={ !! data.exclude_search }
					onChange={ ( exclude_search ) => {
						setData( { ...data, exclude_search } );
						setSaved( false );
					} }
				/>
				<ToggleControl
					label={ __( 'Exclude 404 (not found) pages', 'schema-plugin' ) }
					checked={ !! data.exclude_404 }
					onChange={ ( exclude_404 ) => {
						setData( { ...data, exclude_404 } );
						setSaved( false );
					} }
				/>
				<ToggleControl
					label={ __( 'Exclude pages marked "noindex" by your SEO plugin', 'schema-plugin' ) }
					checked={ !! data.exclude_noindex }
					onChange={ ( exclude_noindex ) => {
						setData( { ...data, exclude_noindex } );
						setSaved( false );
					} }
				/>
			</div>

			<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving }>
				{ __( 'Save', 'schema-plugin' ) }
			</Button>
			{ saved && (
				<Notice status="success" isDismissible={ false } onRemove={ () => setSaved( false ) }>
					{ __( 'Saved.', 'schema-plugin' ) }
				</Notice>
			) }
		</div>
	);
}
