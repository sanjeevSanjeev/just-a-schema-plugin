<?php
/**
 * Schema_Network_Identity
 *
 * v1.1 Phase 4 -- fixes CLAUDE.md complaint #1 (no shared identity across
 * subdomain/subdirectory installs). A site is either standalone (the MVP
 * behavior) or a member of a parent brand's web presence:
 *
 * - relationship 'same': this site belongs to the same organization or
 *   person as the parent (e.g. blog.example.com for Example Inc.). The
 *   sitewide entity takes the parent's @id, so every reference on this site
 *   (publisher, provider, organizer, worksFor, ...) points at one shared
 *   entity instead of a per-install duplicate.
 * - relationship 'sub': this site is a separate brand or division. Its
 *   entity keeps its own @id and gains parentOrganization (or memberOf for
 *   a Person) pointing at the parent.
 *
 * Either way, the WebSite node gains isPartOf -> the parent's WebSite.
 *
 * Parent @ids default to "{parent_url}/#organization|#person" and
 * "{parent_url}/#website" (the convention used by this plugin, Yoast and
 * Rank Math), with overrides for parents that use something else. Works
 * the same for separate installs and multisite subsites; nothing is read
 * from the parent site at runtime.
 *
 * TODO(v1.1): a "parent" role that lists member sites as subOrganization.
 *
 * @package Schema_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema_Network_Identity {

	const OPTION = 'schema_plugin_network_identity';

	/**
	 * Draft settings used by /preview in place of the saved ones.
	 *
	 * @var array|null
	 */
	private static ?array $preview_settings = null;

	public static function defaults(): array {
		return array(
			'role'              => 'standalone',
			'relationship'      => 'same',
			'parent_url'        => '',
			'parent_name'       => '',
			'parent_entity_id'  => '',
			'parent_website_id' => '',
		);
	}

	public static function get_settings(): array {
		if ( null !== self::$preview_settings ) {
			return self::$preview_settings;
		}
		// Feature off: behave as standalone, without reading the option.
		if ( ! Schema_Features::is_enabled( 'network' ) ) {
			return self::defaults();
		}
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Runs $callback with draft settings applied, so /preview renders
	 * through exactly the same code path as the live output.
	 *
	 * @return mixed The callback's return value.
	 */
	public static function with_preview_settings( array $settings, callable $callback ) {
		self::$preview_settings = $settings;
		try {
			return $callback();
		} finally {
			self::$preview_settings = null;
		}
	}

	public static function sanitize( array $raw ): array {
		$clean = self::defaults();

		$clean['role']         = in_array( $raw['role'] ?? '', array( 'standalone', 'member' ), true ) ? $raw['role'] : 'standalone';
		$clean['relationship'] = in_array( $raw['relationship'] ?? '', array( 'same', 'sub' ), true ) ? $raw['relationship'] : 'same';
		$clean['parent_name']  = sanitize_text_field( (string) ( $raw['parent_name'] ?? '' ) );

		// Invalid URLs are kept as plain text (not "fixed" by esc_url_raw()
		// into http://not%20a%20url) so validate() can quote what the user
		// actually typed; save() rejects them either way.
		foreach ( array( 'parent_url', 'parent_entity_id', 'parent_website_id' ) as $key ) {
			$value         = trim( sanitize_text_field( (string) ( $raw[ $key ] ?? '' ) ) );
			$clean[ $key ] = filter_var( $value, FILTER_VALIDATE_URL ) ? esc_url_raw( $value ) : $value;
		}

		return $clean;
	}

	/**
	 * Save-blocking problems. Only settings that would produce a broken
	 * identity are rejected; everything else is a preview warning.
	 *
	 * @return string[]
	 */
	public static function validate( array $settings ): array {
		if ( 'member' !== $settings['role'] ) {
			return array();
		}

		$errors = array();
		if ( ! filter_var( $settings['parent_url'], FILTER_VALIDATE_URL ) ) {
			$errors[] = __( "Enter the main site's full address, e.g. https://example.com/", 'schema-plugin' );
		} elseif ( self::same_address( $settings['parent_url'], home_url( '/' ) ) ) {
			$errors[] = __( "The main site's address is this site's own address. Enter the parent brand's site instead.", 'schema-plugin' );
		}

		foreach ( array( 'parent_entity_id', 'parent_website_id' ) as $key ) {
			if ( '' !== $settings[ $key ] && ! filter_var( $settings[ $key ], FILTER_VALIDATE_URL ) ) {
				$errors[] = sprintf(
					/* translators: %s: the value the user entered. */
					__( '"%s" is not a full URL. An @id must be an absolute URL.', 'schema-plugin' ),
					$settings[ $key ]
				);
			}
		}

		return $errors;
	}

	/**
	 * @return array|WP_Error The saved settings.
	 */
	public static function save( array $raw ) {
		$settings = self::sanitize( $raw );
		$errors   = self::validate( $settings );
		if ( $errors ) {
			return new WP_Error( 'schema_plugin_invalid_network_identity', implode( ' ', $errors ), array( 'status' => 400 ) );
		}
		update_option( self::OPTION, $settings );
		return $settings;
	}

	private static function same_address( string $a, string $b ): bool {
		$normalize = static fn( string $url ) => untrailingslashit( strtolower( (string) preg_replace( '#^https?://(www\.)?#i', '', $url ) ) );
		return $normalize( $a ) === $normalize( $b );
	}

	private static function is_member( array $settings ): bool {
		return 'member' === $settings['role'] && filter_var( $settings['parent_url'], FILTER_VALIDATE_URL )
			&& ! self::same_address( $settings['parent_url'], home_url( '/' ) );
	}

	/**
	 * Parent @ids as they would be used, override or derived.
	 */
	public static function get_parent_ids( array $settings, string $resolved_type ): array {
		$base     = trailingslashit( $settings['parent_url'] );
		$fragment = 'Person' === $resolved_type ? 'person' : 'organization';
		$valid = static fn( string $url ) => (bool) filter_var( $url, FILTER_VALIDATE_URL );
		return array(
			'entity'  => $valid( $settings['parent_entity_id'] ) ? $settings['parent_entity_id'] : $base . '#' . $fragment,
			'website' => $valid( $settings['parent_website_id'] ) ? $settings['parent_website_id'] : $base . '#website',
		);
	}

	/**
	 * The parent's entity @id when this site shares it ('same'), else null.
	 */
	public static function get_shared_entity_id( string $resolved_type ): ?string {
		$settings = self::get_settings();
		if ( ! self::is_member( $settings ) || 'same' !== $settings['relationship'] ) {
			return null;
		}
		return self::get_parent_ids( $settings, $resolved_type )['entity'];
	}

	/**
	 * In 'same' mode the entity's canonical url is the parent site, and a
	 * blank name falls back to the parent's name.
	 */
	public static function apply_entity_defaults( array $fields ): array {
		$settings = self::get_settings();
		if ( ! self::is_member( $settings ) || 'same' !== $settings['relationship'] ) {
			return $fields;
		}
		if ( empty( $fields['url'] ) || self::same_address( (string) $fields['url'], home_url( '/' ) ) ) {
			$fields['url'] = trailingslashit( $settings['parent_url'] );
		}
		if ( empty( $fields['name'] ) && $settings['parent_name'] ) {
			$fields['name'] = $settings['parent_name'];
		}
		return $fields;
	}

	public static function apply_to_entity_node( array $node, string $resolved_type ): array {
		$settings = self::get_settings();
		if ( ! self::is_member( $settings ) || 'sub' !== $settings['relationship'] ) {
			return $node;
		}
		// parentOrganization is Organization-only; Person uses memberOf.
		$property          = 'Person' === $resolved_type ? 'memberOf' : 'parentOrganization';
		$node[ $property ] = array( '@id' => self::get_parent_ids( $settings, $resolved_type )['entity'] );
		return $node;
	}

	public static function apply_to_website_node( array $node, string $resolved_type ): array {
		$settings = self::get_settings();
		if ( ! self::is_member( $settings ) ) {
			return $node;
		}
		$node['isPartOf'] = array( '@id' => self::get_parent_ids( $settings, $resolved_type )['website'] );
		return $node;
	}

	/**
	 * A likely parent address, offered in the UI but never applied
	 * automatically: the network's main site on a multisite subsite, or
	 * the domain root when this install lives in a subdirectory. Subdomain
	 * installs get no guess (the registrable domain isn't reliably
	 * derivable, e.g. example.co.uk).
	 */
	public static function get_suggested_parent_url(): string {
		if ( is_multisite() && ! is_main_site() ) {
			return trailingslashit( get_home_url( get_main_site_id() ) );
		}
		$parts = wp_parse_url( home_url( '/' ) );
		if ( ! empty( $parts['host'] ) && ! empty( $parts['path'] ) && '/' !== $parts['path'] ) {
			return ( $parts['scheme'] ?? 'https' ) . '://' . $parts['host'] . '/';
		}
		return '';
	}
}
