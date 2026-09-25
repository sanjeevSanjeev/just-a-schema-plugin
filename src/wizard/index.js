import { createRoot, useEffect, useState } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import Step1TypePicker from './Step1TypePicker';
import Step2Details from './Step2Details';
import Step3Rules from './Step3Rules';
import Step4Review from './Step4Review';
import { getEntity } from '../shared/api';
import '../shared/style.scss';

const TOTAL_STEPS = 4;

/**
 * Setup wizard -- CLAUDE.md Section 7 item 2. Pre-fills from the CURRENT
 * saved entity (never blank), so re-running the wizard on an
 * already-configured site works per Section 7 item 6. Four focused
 * screens (type -> details -> rules -> review) rather than one long form,
 * with copy and field labels personalized to the chosen type.
 */
function Wizard() {
	const [ step, setStep ] = useState( 1 );
	const [ entity, setEntity ] = useState( null );

	useEffect( () => {
		getEntity().then( ( res ) => {
			setEntity( {
				entity_type: res.entity_type,
				flavor: res.flavor,
				fields: res.fields,
				website_enabled_search: res.website_enabled_search,
			} );
		} );
	}, [] );

	if ( ! entity ) {
		return <Spinner />;
	}

	return (
		<div className="schema-wizard schema-page">
			<div className="schema-wizard-progress">
				<p className="schema-wizard-steps">
					{ sprintf(
						/* translators: 1: current step number, 2: total step count. */
						__( 'Step %1$d of %2$d', 'schema-plugin' ),
						step,
						TOTAL_STEPS
					) }
				</p>
				<div className="schema-wizard-progress-bar">
					<div
						className="schema-wizard-progress-fill"
						style={ { width: `${ ( step / TOTAL_STEPS ) * 100 }%` } }
					/>
				</div>
			</div>

			<div className="schema-card">
				{ 1 === step && (
					<Step1TypePicker entity={ entity } onChange={ setEntity } onContinue={ () => setStep( 2 ) } />
				) }

				{ 2 === step && (
					<Step2Details
						entity={ entity }
						onChange={ setEntity }
						onBack={ () => setStep( 1 ) }
						onContinue={ () => setStep( 3 ) }
					/>
				) }

				{ 3 === step && (
					<Step3Rules entity={ entity } onBack={ () => setStep( 2 ) } onContinue={ () => setStep( 4 ) } />
				) }

				{ 4 === step && <Step4Review entity={ entity } onBack={ () => setStep( 3 ) } /> }
			</div>
		</div>
	);
}

const container = document.getElementById( 'schema-plugin-wizard-root' );
if ( container ) {
	createRoot( container ).render( <Wizard /> );
}
