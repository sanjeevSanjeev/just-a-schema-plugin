import { useEffect, useRef, useState } from '@wordpress/element';
import { Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { preview as previewApi } from './api';

/**
 * Shared <LivePreview> -- CLAUDE.md Section 7: "takes draft field data as
 * props, calls /preview, and renders the returned JSON-LD in a
 * read-only block." Used identically by the post editor panel, the
 * wizard, and the Entity Settings page so preview and live output can
 * never drift out of sync (React never re-implements the Graph Builder's
 * resolution logic).
 *
 * Rendered as a real <details> element, collapsed by default -- CLAUDE.md
 * Section 10.8: "collapsible <details>-style panel... with a 'Show live
 * preview' toggle."
 *
 * @param {Object}  props
 * @param {Object}  props.payload     Body to POST to /preview.
 * @param {boolean}  [props.defaultOpen]
 * @param {boolean}  [props.collapsible] False renders without the <details> wrapper (e.g. inside a panel that is itself the disclosure).
 * @param {Function} [props.onResult]    Called with each /preview result (e.g. to show a warning count elsewhere).
 */
export default function LivePreview( {
	payload,
	defaultOpen = false,
	collapsible = true,
	onResult,
} ) {
	const [ result, setResult ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const debounceRef = useRef();
	// Guards against a slower, now-stale request's response landing after a
	// newer one and clobbering it with outdated warnings/JSON -- the
	// debounce only limits how often a request is FIRED, not the order in
	// which in-flight responses ARRIVE.
	const requestIdRef = useRef( 0 );
	const payloadKey = JSON.stringify( payload );

	useEffect( () => {
		clearTimeout( debounceRef.current );
		debounceRef.current = setTimeout( () => {
			const requestId = ++requestIdRef.current;
			setLoading( true );
			previewApi( payload )
				.then( ( res ) => {
					if ( requestId === requestIdRef.current ) {
						setResult( res );
						onResult?.( res );
					}
				} )
				.catch( () => {
					if ( requestId === requestIdRef.current ) {
						setResult( {
							node: null,
							warnings: [
								__(
									'Preview failed to load.',
									'schema-plugin'
								),
							],
						} );
					}
				} )
				.finally( () => {
					if ( requestId === requestIdRef.current ) {
						setLoading( false );
					}
				} );
		}, 400 );

		return () => clearTimeout( debounceRef.current );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ payloadKey ] );

	const warnings = result?.warnings || [];

	const body = (
		<div className="schema-live-preview-body">
			{ loading && <Spinner /> }

			{ ! loading && warnings.length > 0 && (
				<Notice status="warning" isDismissible={ false }>
					<ul>
						{ warnings.map( ( warning ) => (
							<li key={ warning }>{ warning }</li>
						) ) }
					</ul>
				</Notice>
			) }

			{ ! loading && (
				<pre className="schema-live-preview-json">
					{ JSON.stringify( result?.node ?? null, null, 2 ) }
				</pre>
			) }
		</div>
	);

	if ( ! collapsible ) {
		return <div className="schema-live-preview is-static">{ body }</div>;
	}

	return (
		<details className="schema-live-preview" open={ defaultOpen }>
			<summary>{ __( 'Show live preview', 'schema-plugin' ) }</summary>
			{ body }
		</details>
	);
}
