import { useState } from '@wordpress/element';
import { Button, CheckboxControl, Notice } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { saveFeatures } from '../shared/api';

const bootstrap = window.SchemaPluginData || {};

/**
 * Optional features (Schema_Features). Saving reloads the page so the
 * admin menu (Content page, Network identity tab) reflects the change.
 * Turning off a feature that has entries asks first; the data is kept.
 */
export default function FeaturesCard() {
	const features = bootstrap.features || [];
	const [ enabled, setEnabled ] = useState( features.filter( ( f ) => f.enabled ).map( ( f ) => f.key ) );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( '' );

	const initial = features.filter( ( f ) => f.enabled ).map( ( f ) => f.key );
	const changed = enabled.length !== initial.length || enabled.some( ( key ) => ! initial.includes( key ) );

	const toggle = ( key, on ) => setEnabled( on ? [ ...enabled, key ] : enabled.filter( ( k ) => k !== key ) );

	const save = () => {
		const losing = features.filter( ( f ) => f.enabled && f.entry_count > 0 && ! enabled.includes( f.key ) );
		if ( losing.length ) {
			const list = losing
				.map( ( f ) =>
					sprintf(
						/* translators: 1: feature name, 2: number of entries. */
						_n( '%1$s (%2$d entry)', '%1$s (%2$d entries)', f.entry_count, 'schema-plugin' ),
						f.label,
						f.entry_count
					)
				)
				.join( ', ' );
			// eslint-disable-next-line no-alert
			const ok = window.confirm(
				sprintf(
					/* translators: %s: list of features with entry counts. */
					__(
						"Turning off %s stops it appearing in your structured data. Nothing is deleted: turn it back on and it's restored. Continue?",
						'schema-plugin'
					),
					list
				)
			);
			if ( ! ok ) {
				return;
			}
		}

		setSaving( true );
		setError( '' );
		saveFeatures( enabled )
			.then( () => window.location.reload() )
			.catch( ( err ) => {
				setError( err.message || __( 'Something went wrong.', 'schema-plugin' ) );
				setSaving( false );
			} );
	};

	return (
		<section className="schema-features-card" aria-labelledby="schema-features-heading">
			<h2 id="schema-features-heading">{ __( 'Features', 'schema-plugin' ) }</h2>
			<p className="description">
				{ __( 'Turn on only what your site uses. Features that are off add nothing to your site.', 'schema-plugin' ) }
			</p>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ features.map( ( feature ) => (
				<div className="schema-feature-row" key={ feature.key }>
					<CheckboxControl
						label={ feature.label }
						help={
							feature.entry_count > 0 && ! enabled.includes( feature.key )
								? sprintf(
										/* translators: 1: feature description, 2: number of saved entries. */
										_n(
											'%1$s Has %2$d saved entry, kept while off.',
											'%1$s Has %2$d saved entries, kept while off.',
											feature.entry_count,
											'schema-plugin'
										),
										feature.description,
										feature.entry_count
								  )
								: feature.description
						}
						checked={ enabled.includes( feature.key ) }
						onChange={ ( on ) => toggle( feature.key, on ) }
					/>
				</div>
			) ) }

			<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving || ! changed }>
				{ __( 'Save features', 'schema-plugin' ) }
			</Button>
		</section>
	);
}
