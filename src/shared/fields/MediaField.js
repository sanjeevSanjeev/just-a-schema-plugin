import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import withRequiredMark from './withRequiredMark';

/**
 * Shared <MediaField> -- picks an image from the WordPress media library
 * (upload or existing) instead of asking the user to paste a URL. Backed
 * by `Schema_Admin::enqueue_bundle()` calling `wp_enqueue_media()` on the
 * screens that need it (the standalone wizard and Entity Settings pages;
 * Gutenberg's own post-editor screen already loads it).
 */
export default function MediaField( { label, value, onChange, required = false } ) {
	const fieldLabel = withRequiredMark( label, required );

	const openMediaLibrary = () => {
		if ( ! window.wp?.media ) {
			return;
		}

		const frame = window.wp.media( {
			title: __( 'Select or upload an image', 'schema-plugin' ),
			button: { text: __( 'Use this image', 'schema-plugin' ) },
			multiple: false,
			library: { type: 'image' },
		} );

		frame.on( 'select', () => {
			const attachment = frame.state().get( 'selection' ).first().toJSON();
			onChange( attachment.url );
		} );

		frame.open();
	};

	return (
		<div className="schema-field schema-field-media">
			<p className="schema-field-label">{ fieldLabel }</p>

			{ value && (
				<div className="schema-media-preview">
					<img src={ value } alt="" />
					<Button variant="link" isDestructive onClick={ () => onChange( '' ) }>
						{ __( 'Remove image', 'schema-plugin' ) }
					</Button>
				</div>
			) }

			<Button variant="secondary" onClick={ openMediaLibrary }>
				{ value ? __( 'Replace image', 'schema-plugin' ) : __( 'Select image', 'schema-plugin' ) }
			</Button>
		</div>
	);
}
