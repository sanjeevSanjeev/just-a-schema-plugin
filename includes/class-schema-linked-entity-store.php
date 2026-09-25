<?php
/**
 * Schema_Linked_Entity_Store
 *
 * v1.1 Phase 1 (Generic Linked Entity Manager) -- generic CRUD storage
 * for LinkedContentEntity records, one wp_option per entity_type (e.g.
 * "service"), matching the storage note already in
 * class-linked-content-entity.php's own docblock ("one row per entity in
 * wp_options"). Team also supports auto mode (bound to a WP user). No
 * type-switching UI exists for these entities, so no non-destructive
 * field-migration logic is needed the way it is for the sitewide entity
 * or per-post blocks.
 *
 * @package Schema_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema_Linked_Entity_Store {

	const OPTION_PREFIX = 'schema_plugin_entities_';

	public static function get_all( string $entity_type ): array {
		$rows = get_option( self::option_name( $entity_type ), array() );
		return is_array( $rows ) ? $rows : array();
	}

	public static function get( string $entity_type, string $id ): ?array {
		foreach ( self::get_all( $entity_type ) as $row ) {
			if ( ( $row['id'] ?? '' ) === $id ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * @return array|WP_Error The saved record.
	 */
	public static function save( string $entity_type, array $data ) {
		$config = Schema_Linked_Entity_Types::get_config( $entity_type );
		if ( ! $config ) {
			return new WP_Error( 'schema_plugin_unknown_entity_type', __( 'Unknown entity type.', 'schema-plugin' ), array( 'status' => 404 ) );
		}

		$rows = self::get_all( $entity_type );
		$id   = ! empty( $data['id'] ) ? sanitize_key( $data['id'] ) : self::generate_id( $entity_type );

		$clean_fields = Schema_Sanitizer::sanitize_fields( (array) ( $data['fields'] ?? array() ), $config['fields'] );

		$link_mode = ( $data['link_mode'] ?? '' );
		if ( ! in_array( $link_mode, $config['link_modes'], true ) ) {
			$link_mode = $config['link_modes'][0];
		}

		$linked_post_id = ! empty( $data['linked_post_id'] ) ? absint( $data['linked_post_id'] ) : null;
		$wp_user_id     = ! empty( $data['wp_user_id'] ) ? absint( $data['wp_user_id'] ) : null;

		// Each mode keeps only its own link target, so a record switched
		// from auto to manual can't keep emitting on the old author's posts.
		if ( 'auto' === $link_mode ) {
			$linked_post_id = null;
			if ( ! $wp_user_id || ! get_userdata( $wp_user_id ) ) {
				return new WP_Error( 'schema_plugin_user_required', __( 'Choose the WordPress user this entry is linked to.', 'schema-plugin' ), array( 'status' => 400 ) );
			}
			foreach ( $rows as $row ) {
				if ( ( $row['id'] ?? '' ) !== $id && 'auto' === ( $row['link_mode'] ?? '' ) && (int) ( $row['wp_user_id'] ?? 0 ) === $wp_user_id ) {
					return new WP_Error( 'schema_plugin_user_taken', __( 'Another entry is already linked to this user. Edit that one instead.', 'schema-plugin' ), array( 'status' => 400 ) );
				}
			}
		} else {
			$wp_user_id = null;
		}

		$existing_index = null;
		$legacy_fields  = array();
		foreach ( $rows as $index => $row ) {
			if ( ( $row['id'] ?? '' ) === $id ) {
				$existing_index = $index;
				$legacy_fields  = (array) ( $row['legacy_fields'] ?? array() );
				break;
			}
		}

		$record = array(
			'id'                  => $id,
			'entity_type'         => $entity_type,
			'schema_type'         => $config['schema_type'],
			'fields'              => $clean_fields,
			'link_mode'           => $link_mode,
			'linked_post_id'      => $linked_post_id,
			// post_author is the only auto strategy Schema_Entity_Resolver supports.
			'auto_link_source'    => 'auto' === $link_mode ? 'post_author' : null,
			'wp_user_id'          => $wp_user_id,
			'parent_relationship' => $config['parent_relationship'], // fixed per type, never client-supplied.
			'legacy_fields'       => $legacy_fields,
		);

		if ( null !== $existing_index ) {
			$rows[ $existing_index ] = $record;
		} else {
			$rows[] = $record;
		}

		update_option( self::option_name( $entity_type ), $rows );
		return $record;
	}

	public static function delete( string $entity_type, string $id ): void {
		$rows = array_values(
			array_filter(
				self::get_all( $entity_type ),
				static fn( $row ) => ( $row['id'] ?? '' ) !== $id
			)
		);
		update_option( self::option_name( $entity_type ), $rows );
	}

	private static function generate_id( string $entity_type ): string {
		$prefix = substr( sanitize_key( $entity_type ), 0, 3 );
		// Lowercased: an id is looked up later via sanitize_key(), which
		// always lowercases -- wp_generate_password()'s default charset
		// includes uppercase letters, so an unlowercased id would
		// silently fail to match itself on the very next update/delete.
		return ( $prefix ?: 'ent' ) . '_' . strtolower( wp_generate_password( 8, false ) );
	}

	private static function option_name( string $entity_type ): string {
		return self::OPTION_PREFIX . sanitize_key( $entity_type );
	}
}
