import { createRoot, useEffect, useState } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import DefaultRulesManager from '../shared/DefaultRulesManager';
import { getEntity, resetDefaultRulesToType } from '../shared/api';
import { getWizardCopy } from '../shared/wizardCopy';
import '../shared/style.scss';

const bootstrap = window.SchemaPluginData || {};

/**
 * Default Rules manager page -- CLAUDE.md Section 7 item 3. The intro
 * copy personalizes to the site's configured type, same as the wizard's
 * Step 3 (Section 7 item 2), and offers the same explicit "Reset to
 * default rules for this type" action here too rather than only inside
 * the wizard's re-run flow.
 */
function DefaultRulesPage() {
	const [ entityType, setEntityType ] = useState( undefined );
	const [ resetKey, setResetKey ] = useState( 0 );

	useEffect( () => {
		getEntity().then( ( res ) => setEntityType( res.entity_type || '' ) );
	}, [] );

	if ( undefined === entityType ) {
		return <Spinner />;
	}

	const copy = getWizardCopy( entityType );
	const typeLabel = bootstrap.lockedTypes?.[ entityType ]?.label;

	const resetToStock = () => {
		// eslint-disable-next-line no-alert
		if (
			! window.confirm(
				__(
					'Replace the current rules with the stock defaults for this type? This cannot be undone.',
					'schema-plugin'
				)
			)
		) {
			return;
		}
		resetDefaultRulesToType( entityType ).then( () => setResetKey( ( k ) => k + 1 ) );
	};

	return (
		<div className="schema-default-rules-page schema-page schema-page-wide schema-card">
			<p className="schema-breadcrumb">
				<a href={ bootstrap.dashboardUrl }>{ __( '← Dashboard', 'schema-plugin' ) }</a>
			</p>
			<h1>{ __( 'Default Rules', 'schema-plugin' ) }</h1>
			<p className="schema-wizard-step-intro">
				{ entityType
					? copy.rules_intro
					: __(
						'The first matching rule wins. Every site should end with a catch-all rule so every page gets some schema.',
						'schema-plugin'
					) }
			</p>

			{ typeLabel && (
				<p>
					<Button variant="secondary" isDestructive onClick={ resetToStock }>
						{ __( 'Reset to default rules for this type', 'schema-plugin' ) }
					</Button>
				</p>
			) }

			<DefaultRulesManager key={ resetKey } />
		</div>
	);
}

const container = document.getElementById( 'schema-plugin-rules-root' );
if ( container ) {
	createRoot( container ).render( <DefaultRulesPage /> );
}
