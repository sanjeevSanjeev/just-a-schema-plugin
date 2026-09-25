import { useEffect, useRef } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import LivePreview from '../shared/LivePreview';

/**
 * Slide-over panel with the live JSON-LD preview, so the form keeps the
 * full width. Stays mounted while closed (hidden), so the preview keeps
 * updating and the opener can show a live warning count.
 *
 * Accessibility: a labelled dialog; focus moves to the close button on
 * open, Esc closes, and the caller returns focus to the opener.
 */
export default function PreviewPanel( { open, onClose, payload, onResult } ) {
	const closeRef = useRef();

	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}
		closeRef.current?.focus();
		const onKey = ( event ) => {
			if ( 'Escape' === event.key ) {
				onClose();
			}
		};
		window.addEventListener( 'keydown', onKey );
		return () => window.removeEventListener( 'keydown', onKey );
	}, [ open, onClose ] );

	return (
		<>
			{ open && <div className="schema-preview-scrim" onClick={ onClose } aria-hidden="true" /> }
			<div
				className={ `schema-preview-panel${ open ? ' is-open' : '' }` }
				role="dialog"
				aria-modal="true"
				aria-labelledby="schema-preview-panel-title"
				hidden={ ! open }
			>
				<div className="schema-preview-panel-head">
					<h2 id="schema-preview-panel-title">{ __( 'Live preview', 'schema-plugin' ) }</h2>
					<Button ref={ closeRef } icon="no-alt" label={ __( 'Close preview', 'schema-plugin' ) } onClick={ onClose } />
				</div>
				<p className="description">
					{ __( 'The sitewide entity exactly as it will be output, including unsaved changes.', 'schema-plugin' ) }
				</p>
				<LivePreview payload={ payload } collapsible={ false } onResult={ onResult } />
			</div>
		</>
	);
}
