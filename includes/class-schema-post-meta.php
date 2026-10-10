<?php
/**
 * Schema_Post_Meta
 *
 * Per-post schema blocks -- CLAUDE.md Section 3.2, the fix for complaint
 * #3 (switching a type must never wipe previously entered field data).
 *
 * MVP UI manages a single active block per post (Section 7 item 1's
 * post-editor panel shows one structured form + one raw override
 * textarea); storage stays an array so v1.1 can support multiple
 * simultaneous blocks without a data migration.
 *
 * @package Schema_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema_Post_Meta {

	const META_KEY = '_schema_plugin_blocks';

	public static function get_blocks( int $post_id ): array {
		$blocks = get_post_meta( $post_id, self::META_KEY, true );
		return is_array( $blocks ) ? $blocks : array();
	}

	public static function has_blocks( int $post_id ): bool {
		return ! empty( self::get_blocks( $post_id ) );
	}

	/**
	 * Resolves the primary node for a post's explicit block, or null if
	 * the post has no blocks (render should fall through to Default
	 * Rules -- CLAUDE.md Section 4, step 5).
	 */
	public static function get_explicit_block( int $post_id ): ?array {
		$blocks = self::get_blocks( $post_id );
		if ( empty( $blocks ) ) {
			return null;
		}

		$block = $blocks[0];

		if ( ! empty( $block['raw_override'] ) ) {
			$decoded = json_decode( $block['raw_override'], true );
			return is_array( $decoded ) ? $decoded : null;
		}

		return self::build_block_node( $post_id, (string) ( $block['schema_type'] ?? 'WebPage' ), (array) ( $block['fields'] ?? array() ) );
	}

	/**
	 * The node for a structured block. Shared by the live graph and the
	 * /preview endpoint so the two can't drift.
	 *
	 * Article and BlogPosting also get `author` and `publisher` as @id
	 * references (the same ones the default rule uses) unless the block
	 * sets them. Without this, customizing a post's Article would silently
	 * drop both, which Google recommends for articles.
	 */
	public static function build_block_node( int $post_id, string $schema_type, array $fields ): array {
		$node      = array( '@type' => $schema_type );
		$permalink = $post_id > 0 ? get_permalink( $post_id ) : '';
		if ( $permalink ) {
			$node['@id'] = trailingslashit( $permalink ) . '#' . strtolower( $schema_type );
		}

		foreach ( $fields as $key => $value ) {
			if ( '' === $value || array() === $value || null === $value ) {
				continue;
			}
			$node[ $key ] = $value;
		}

		if ( in_array( $schema_type, array( 'Article', 'BlogPosting' ), true ) ) {
			$refs = Schema_Token_Resolver::resolve_template(
				array(
					'author'    => array( '@id' => '{{post_author_id}}' ),
					'publisher' => array( '@id' => '{{organization_id}}' ),
				),
				$post_id
			);
			foreach ( $refs as $key => $ref ) {
				if ( ! isset( $node[ $key ] ) ) {
					$node[ $key ] = $ref;
				}
			}
		}

		return $node;
	}

	/**
	 * @return array|WP_Error The saved (sanitized) blocks, or a WP_Error
	 *                        if a raw_override failed to decode as JSON.
	 */
	public static function save_blocks( int $post_id, array $raw_blocks ) {
		$existing = self::get_blocks( $post_id );
		$clean    = array();

		foreach ( $raw_blocks as $raw_block ) {
			$block_id    = ! empty( $raw_block['block_id'] ) ? sanitize_key( $raw_block['block_id'] ) : self::generate_block_id();
			$schema_type = ! empty( $raw_block['schema_type'] ) ? sanitize_text_field( $raw_block['schema_type'] ) : 'WebPage';

			$previous        = self::find_block( $existing, $block_id );
			$field_defs      = Schema_Types::get_field_defs( $schema_type );
			$incoming_fields = Schema_Sanitizer::sanitize_fields( (array) ( $raw_block['fields'] ?? array() ), $field_defs );

			$migrated = Schema_Field_Migrator::migrate(
				$previous ? (array) $previous['fields'] : array(),
				$previous ? (array) $previous['legacy_fields'] : array(),
				$schema_type,
				$incoming_fields,
				$previous ? (string) $previous['schema_type'] : null
			);

			$raw_override = null;
			if ( isset( $raw_block['raw_override'] ) && '' !== trim( (string) $raw_block['raw_override'] ) ) {
				$decoded = json_decode( (string) $raw_block['raw_override'], true );
				if ( ! is_array( $decoded ) ) {
					return new WP_Error(
						'schema_plugin_invalid_json',
						__( 'The raw JSON-LD override is not valid JSON.', 'schema-plugin' ),
						array( 'status' => 400 )
					);
				}
				$raw_override = wp_json_encode( $decoded );
			}

			$clean[] = array(
				'block_id'      => $block_id,
				'schema_type'   => $schema_type,
				'fields'        => $migrated['fields'],
				'legacy_fields' => $migrated['legacy_fields'],
				'raw_override'  => $raw_override,
			);
		}

		update_post_meta( $post_id, self::META_KEY, $clean );
		return $clean;
	}

	public static function delete_blocks( int $post_id ): void {
		delete_post_meta( $post_id, self::META_KEY );
	}

	private static function find_block( array $blocks, string $block_id ): ?array {
		foreach ( $blocks as $block ) {
			if ( ( $block['block_id'] ?? '' ) === $block_id ) {
				return $block;
			}
		}
		return null;
	}

	private static function generate_block_id(): string {
		// Lowercased: block_id is later matched via sanitize_key(), which
		// always lowercases -- wp_generate_password()'s default charset
		// includes uppercase letters, so an unlowercased id would fail to
		// match itself on the very next save, silently dropping the
		// non-destructive type-switch migration's "previous" lookup.
		return 'blk_' . strtolower( wp_generate_password( 8, false ) );
	}
}
