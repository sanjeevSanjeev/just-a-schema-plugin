<?php
/**
 * Schema_Entity_Registry
 *
 * The sitewide Organization/Person + WebSite singleton -- CLAUDE.md
 * Section 3.1. Also the home of the (deferred, v1.1) LinkedContentEntity
 * accessors referenced by Schema_Graph_Builder.
 *
 * @package Schema_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema_Entity_Registry {

	const OPTION_TYPE   = 'schema_plugin_entity_type';
	const OPTION_FLAVOR = 'schema_plugin_entity_flavor';
	const OPTION_FIELDS = 'schema_plugin_entity_fields';
	const OPTION_LEGACY = 'schema_plugin_entity_legacy_fields';
	const OPTION_SEARCH = 'schema_plugin_website_enabled_search';

	public static function get_entity_type(): string {
		return (string) get_option( self::OPTION_TYPE, '' );
	}

	public static function get_entity_flavor(): ?string {
		$flavor = get_option( self::OPTION_FLAVOR, '' );
		return '' !== $flavor ? $flavor : null;
	}

	public static function get_entity_fields(): array {
		$fields = get_option( self::OPTION_FIELDS, array() );
		return is_array( $fields ) ? $fields : array();
	}

	public static function get_entity_legacy_fields(): array {
		$fields = get_option( self::OPTION_LEGACY, array() );
		return is_array( $fields ) ? $fields : array();
	}

	public static function get_website_search_enabled(): bool {
		return (bool) get_option( self::OPTION_SEARCH, false );
	}

	public static function set_website_search_enabled( bool $enabled ): void {
		update_option( self::OPTION_SEARCH, $enabled );
	}

	/** Resolves the literal schema.org @type for the current entity selection. */
	public static function get_resolved_schema_type(): string {
		$type = self::get_entity_type();
		return $type ? self::resolve_schema_type( $type ) : 'Organization';
	}

	/**
	 * Locked type keys resolve through Schema_Types; a "Show more"
	 * selection stores the literal schema.org type string directly.
	 */
	public static function resolve_schema_type( string $type_identifier ): string {
		$locked = Schema_Types::get_locked_types();
		if ( isset( $locked[ $type_identifier ] ) ) {
			return $locked[ $type_identifier ]['schema_type'];
		}
		return $type_identifier ?: 'Organization';
	}

	/**
	 * @id generation is deterministic, never free-form input -- CLAUDE.md
	 * Section 3.1. The one exception is Network Identity's 'same'
	 * relationship, where this site deliberately shares its parent
	 * brand's @id (v1.1 Phase 4).
	 *
	 * @param string|null $resolved_type Draft type for previews; defaults to the saved one.
	 */
	public static function get_entity_id_uri( ?string $resolved_type = null ): string {
		$resolved_type = $resolved_type ?? self::get_resolved_schema_type();

		$shared = Schema_Network_Identity::get_shared_entity_id( $resolved_type );
		if ( $shared ) {
			return $shared;
		}

		$fragment = ( 'Person' === $resolved_type ) ? 'person' : 'organization';
		return home_url( '/#' . $fragment );
	}

	public static function get_website_id_uri(): string {
		return home_url( '/#website' );
	}

	public static function get_organization_node(): array {
		return self::build_organization_node( self::get_resolved_schema_type(), self::get_entity_fields() );
	}

	/**
	 * Single builder for the sitewide entity node, shared by the live
	 * output and /preview (which passes draft type + fields), so the two
	 * can't drift.
	 */
	public static function build_organization_node( string $resolved_type, array $fields ): array {
		$fields = Schema_Network_Identity::apply_entity_defaults( $fields );

		if ( empty( $fields['url'] ) ) {
			$fields['url'] = home_url( '/' );
		}
		if ( empty( $fields['inLanguage'] ) ) {
			$fields['inLanguage'] = get_locale();
		}

		$node = array(
			'@type' => $resolved_type,
			'@id'   => self::get_entity_id_uri( $resolved_type ),
		);

		foreach ( $fields as $key => $value ) {
			if ( '' === $value || array() === $value || null === $value ) {
				continue; // an absent property is valid schema; an empty one often isn't.
			}
			$node[ $key ] = $value;
		}

		return Schema_Network_Identity::apply_to_entity_node( $node, $resolved_type );
	}

	public static function get_website_node(): array {
		$node = array(
			'@type' => 'WebSite',
			'@id'   => self::get_website_id_uri(),
			'url'   => home_url( '/' ),
			'name'  => get_bloginfo( 'name' ),
		);

		if ( self::get_website_search_enabled() ) {
			$node['potentialAction'] = array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			);
		}

		return Schema_Network_Identity::apply_to_website_node( $node, self::get_resolved_schema_type() );
	}

	/**
	 * Saves the entity type + fields, applying the same non-destructive
	 * legacy-field handling used for per-post blocks (CLAUDE.md Section
	 * 3.2, reused per Section 7 item 6) whenever the type changes.
	 */
	public static function save_entity( string $type_identifier, ?string $flavor, array $raw_fields ): array {
		$field_defs   = Schema_Types::get_field_defs( $type_identifier );
		$clean_fields = Schema_Sanitizer::sanitize_fields( $raw_fields, $field_defs );

		$migrated = Schema_Field_Migrator::migrate(
			self::get_entity_fields(),
			self::get_entity_legacy_fields(),
			$type_identifier,
			$clean_fields,
			self::get_entity_type() ? self::get_entity_type() : null
		);

		update_option( self::OPTION_TYPE, sanitize_text_field( $type_identifier ) );
		update_option( self::OPTION_FLAVOR, $flavor ? sanitize_text_field( $flavor ) : '' );
		update_option( self::OPTION_FIELDS, $migrated['fields'] );
		update_option( self::OPTION_LEGACY, $migrated['legacy_fields'] );

		return array(
			'entity_type'   => $type_identifier,
			'flavor'        => $flavor,
			'fields'        => $migrated['fields'],
			'legacy_fields' => $migrated['legacy_fields'],
		);
	}

	/**
	 * v1.1 Phase 1 (Generic Linked Entity Manager): returns every
	 * persisted entity across every registered type (currently just
	 * "service" -- Locations/Events/Team follow in Phase 2/3 as more
	 * Schema_Linked_Entity_Types config entries, no changes needed here).
	 *
	 * @return Schema_Linked_Content_Entity[]
	 */
	public static function get_all_linked_entities(): array {
		$entities = array();
		// Disabled features' options aren't even read.
		foreach ( array_keys( Schema_Linked_Entity_Types::get_enabled_registry() ) as $entity_type ) {
			foreach ( Schema_Linked_Entity_Store::get_all( $entity_type ) as $row ) {
				$entities[] = new Schema_Linked_Content_Entity( $row );
			}
		}
		return $entities;
	}

	/**
	 * Builds the full node for a linked entity from its own fields, plus a
	 * reverse reference back to the sitewide entity under the property
	 * named by `parent_relationship` (see Schema_Linked_Entity_Types).
	 */
	public static function render_entity_node( Schema_Linked_Content_Entity $entity ): array {
		$node = array(
			'@type' => $entity->schema_type,
			'@id'   => $entity->get_id_uri(),
		);

		// An auto-linked person's blank fields fall back to their WP profile.
		$fields = $entity->fields;
		if ( 'auto' === $entity->link_mode && 'post_author' === $entity->auto_link_source && $entity->wp_user_id ) {
			$fields = array_merge(
				Schema_Author_Profile::get_profile_fields( (int) $entity->wp_user_id ),
				array_filter( $fields, static fn( $value ) => '' !== $value && array() !== $value && null !== $value )
			);
		}

		foreach ( $fields as $key => $value ) {
			if ( '' === $value || array() === $value || null === $value ) {
				continue;
			}
			$node[ $key ] = $value;
		}

		// Services saved during Phase 1 stored the provisional forward
		// property names; both mean "provided by the sitewide entity".
		$relationship = in_array( $entity->parent_relationship, array( 'hasOfferCatalog', 'makesOffer' ), true )
			? 'provider'
			: $entity->parent_relationship;

		// parentOrganization and worksFor only accept an Organization, so a
		// Person site's locations/team are emitted without the back-reference.
		$skip = in_array( $relationship, array( 'parentOrganization', 'worksFor' ), true ) && 'Person' === self::get_resolved_schema_type();

		if ( ! $skip && in_array( $relationship, array( 'provider', 'parentOrganization', 'organizer', 'worksFor' ), true ) ) {
			$node[ $relationship ] = array( '@id' => self::get_entity_id_uri() );
		}

		return $node;
	}
}
