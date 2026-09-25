import { TextControl, Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Known platforms -- clicking the icon adds a row seeded with the bare
 * prefix (empty username); `match` recognizes that same prefix (with or
 * without a username) so a row always renders in "just type your
 * username" mode instead of asking the user to know their own full
 * profile URL.
 */
const PLATFORMS = [
	{
		key: 'twitter',
		label: __( 'X / Twitter', 'schema-plugin' ),
		glyph: 'X',
		prefix: 'https://x.com/',
		match: /^https?:\/\/(?:www\.)?(?:x|twitter)\.com\/([^/?]*)$/i,
	},
	{
		key: 'facebook',
		label: __( 'Facebook', 'schema-plugin' ),
		glyph: 'f',
		prefix: 'https://www.facebook.com/',
		match: /^https?:\/\/(?:www\.)?facebook\.com\/([^/?]*)$/i,
	},
	{
		key: 'instagram',
		label: __( 'Instagram', 'schema-plugin' ),
		glyph: 'IG',
		prefix: 'https://www.instagram.com/',
		match: /^https?:\/\/(?:www\.)?instagram\.com\/([^/?]*)$/i,
	},
	{
		key: 'linkedin',
		label: __( 'LinkedIn', 'schema-plugin' ),
		glyph: 'in',
		prefix: 'https://www.linkedin.com/in/',
		match: /^https?:\/\/(?:www\.)?linkedin\.com\/in\/([^/?]*)$/i,
	},
	{
		key: 'youtube',
		label: __( 'YouTube', 'schema-plugin' ),
		glyph: 'YT',
		prefix: 'https://www.youtube.com/@',
		match: /^https?:\/\/(?:www\.)?youtube\.com\/@([^/?]*)$/i,
	},
];

function detectPlatform( url ) {
	for ( const platform of PLATFORMS ) {
		const found = url.match( platform.match );
		if ( found ) {
			return { platform, username: found[ 1 ] };
		}
	}
	return null;
}

function SameAsRow( { url, onChange, onRemove } ) {
	const detected = detectPlatform( url );

	if ( detected ) {
		const { platform, username } = detected;
		return (
			<div className="schema-sameas-row schema-sameas-row-platform">
				<span className="schema-sameas-prefix">{ platform.prefix.replace( /^https?:\/\//, '' ) }</span>
				<TextControl
					label={ sprintf(
						/* translators: %s: platform name, e.g. "Facebook". */
						__( '%s username', 'schema-plugin' ),
						platform.label
					) }
					hideLabelFromVision
					value={ username }
					placeholder={ __( 'username', 'schema-plugin' ) }
					onChange={ ( newUsername ) => onChange( platform.prefix + newUsername.trim().replace( /^@/, '' ) ) }
				/>
				<Button
					icon="no-alt"
					label={ __( 'Remove this profile', 'schema-plugin' ) }
					aria-label={ __( 'Remove this profile', 'schema-plugin' ) }
					onClick={ onRemove }
				/>
			</div>
		);
	}

	// Fallback: any other site -- a plain full-URL field, same as before.
	return (
		<div className="schema-sameas-row">
			<TextControl
				type="url"
				label={ __( 'Profile URL', 'schema-plugin' ) }
				hideLabelFromVision
				value={ url }
				placeholder={ __( 'https://example.com/your-profile', 'schema-plugin' ) }
				onChange={ onChange }
			/>
			<Button
				icon="no-alt"
				label={ __( 'Remove this URL', 'schema-plugin' ) }
				aria-label={ __( 'Remove this URL', 'schema-plugin' ) }
				onClick={ onRemove }
			/>
		</div>
	);
}

/**
 * Shared <SameAsBuilder> -- icon-grid quick-add + a fallback free-form URL
 * list, reused identically wherever `sameAs` is edited (CLAUDE.md Section
 * 10.6.A). For a recognized platform, the user only ever types their
 * username; the full profile URL is composed automatically, so nobody
 * needs to know or paste their own full profile link.
 */
export default function SameAsBuilder( { value, onChange } ) {
	const urls = Array.isArray( value ) ? value : [];

	const updateAt = ( index, url ) => {
		const next = [ ...urls ];
		next[ index ] = url;
		onChange( next );
	};

	const removeAt = ( index ) => {
		const next = [ ...urls ];
		next.splice( index, 1 );
		onChange( next );
	};

	const addPlatform = ( platform ) => onChange( [ ...urls, platform.prefix ] );
	const addBlank = () => onChange( [ ...urls, '' ] );

	return (
		<div className="schema-field schema-sameas-builder">
			<p className="schema-field-label">{ __( 'Social / profile links (sameAs)', 'schema-plugin' ) }</p>

			<div
				className="schema-sameas-grid"
				role="group"
				aria-label={ __( 'Quick-add a social platform', 'schema-plugin' ) }
			>
				{ PLATFORMS.map( ( platform ) => (
					<button
						key={ platform.key }
						type="button"
						className="schema-sameas-icon"
						aria-label={ sprintf(
							/* translators: %s: platform name, e.g. "Facebook". */
							__( 'Add your %s username', 'schema-plugin' ),
							platform.label
						) }
						onClick={ () => addPlatform( platform ) }
					>
						<span aria-hidden="true">{ platform.glyph }</span>
					</button>
				) ) }
			</div>

			{ urls.map( ( url, index ) => (
				// eslint-disable-next-line react/no-array-index-key
				<SameAsRow key={ index } url={ url } onChange={ ( v ) => updateAt( index, v ) } onRemove={ () => removeAt( index ) } />
			) ) }

			<Button variant="secondary" onClick={ addBlank }>
				{ __( 'Add a different website', 'schema-plugin' ) }
			</Button>
		</div>
	);
}
