<?php
/**
 * Schema_Default_Rules
 *
 * The editable Default Rules engine -- CLAUDE.md Section 3.4, the fix for
 * complaint #7 (no way to edit default schema templates without code).
 * Fully data-driven: stored config, editable in admin UI, never hardcoded
 * PHP beyond the initial per-type seed sets in Schema_Types.
 *
 * @package Schema_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema_Default_Rules {

	const OPTION = 'schema_plugin_default_rules';

	public static function get_rules(): array {
		$rules = get_option( self::OPTION, array() );
		if ( ! is_array( $rules ) || empty( $rules ) ) {
			return array(
				array(
					'id'          => 'rule_fallback_catch_all',
					'priority'    => 0,
					'match'       => array( 'type' => 'catch_all' ),
					'schema_type' => 'WebPage',
					'template'    => Schema_Types::TEMPLATE_BY_SCHEMA_TYPE['WebPage'],
				),
			);
		}
		return $rules;
	}

	private static function get_ordered_rules(): array {
		$rules = self::get_rules();
		usort(
			$rules,
			static function ( $a, $b ) {
				return ( $a['priority'] ?? 0 ) <=> ( $b['priority'] ?? 0 );
			}
		);
		return $rules;
	}

	/**
	 * First matching rule wins -- CLAUDE.md Section 3.4/4.
	 */
	public static function resolve( int $post_id ): ?array {
		$resolved = self::resolve_with_rule( $post_id );
		return $resolved ? $resolved['node'] : null;
	}

	/**
	 * Like resolve(), but also returns the rule that matched.
	 *
	 * @param int         $post_id Post ID.
	 * @param string|null $path    Request path to match url_pattern rules
	 *                             against, trimmed of slashes. Null uses the
	 *                             current request; the Completeness report
	 *                             passes each page's own path, since it runs
	 *                             inside a REST request.
	 * @return array{rule: array, node: array}|null
	 */
	public static function resolve_with_rule( int $post_id, ?string $path = null ): ?array {
		foreach ( self::get_ordered_rules() as $rule ) {
			if ( self::rule_matches( $rule, $post_id, $path ) ) {
				return array(
					'rule' => $rule,
					'node' => self::build_node( $rule, $post_id ),
				);
			}
		}
		return null;
	}

	private static function build_node( array $rule, int $post_id ): array {
		$template = isset( $rule['template'] ) && is_array( $rule['template'] ) ? $rule['template'] : array();
		$resolved = Schema_Token_Resolver::resolve_template( $template, $post_id );

		// A template resolving to nothing but a bare @id is a deliberate
		// dangling reference into a node already emitted elsewhere in the
		// same @graph (e.g. a homepage rule pointing at the sitewide
		// Organization node), not a new node.
		if ( array_keys( $resolved ) === array( '@id' ) ) {
			return $resolved;
		}

		$node = array( '@type' => $rule['schema_type'] ?? 'WebPage' );

		if ( ! isset( $resolved['@id'] ) && $post_id > 0 ) {
			// get_permalink(0) falls back to the global $post rather than
			// returning false -- guard explicitly (CLAUDE.md Section 4/3.4:
			// an archive/blog-index request has no single queried post).
			$permalink = get_permalink( $post_id );
			if ( $permalink ) {
				$node['@id'] = trailingslashit( $permalink ) . '#' . strtolower( $node['@type'] );
			}
		}

		$node = array_merge( $node, $resolved );

		// Blog index and archives have no single post, so every post token
		// resolves empty. Identify the node by the request URL instead of
		// emitting a bare type.
		if ( $post_id <= 0 && 'WebPage' === $node['@type'] ) {
			$request_path = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
			$request_url  = esc_url_raw( home_url( $request_path ) );
			if ( empty( $node['url'] ) ) {
				$node['url'] = $request_url;
			}
			if ( empty( $node['@id'] ) ) {
				$node['@id'] = trailingslashit( strtok( $request_url, '?#' ) ) . '#webpage';
			}
			if ( empty( $node['name'] ) ) {
				$title = html_entity_decode( wp_get_document_title(), ENT_QUOTES, 'UTF-8' );
				if ( '' !== $title ) {
					$node['name'] = $title;
				}
			}
		}

		return $node;
	}

	/**
	 * Whether a rule matches a post.
	 *
	 * @param array       $rule         Default rule.
	 * @param int         $post_id      Post ID (0 on archives).
	 * @param string|null $request_path Path for url_pattern rules; null uses the current request.
	 * @return bool
	 */
	private static function rule_matches( array $rule, int $post_id, ?string $request_path = null ): bool {
		$match = $rule['match'] ?? array();
		$type  = $match['type'] ?? 'catch_all';
		$value = $match['value'] ?? null;

		switch ( $type ) {
			case 'catch_all':
				return true;

			case 'post_id':
				$target = is_string( $value ) ? (int) Schema_Token_Resolver::resolve_string( $value, $post_id ) : (int) $value;
				return $target > 0 && $target === $post_id;

			case 'post_type':
				// get_post_type(0) falls back to the global $post rather than
				// returning false, which is ambiguous on archive/home
				// requests that have no single queried object -- guard
				// explicitly rather than rely on that fallback.
				return $post_id > 0 && get_post_type( $post_id ) === $value;

			case 'post_type_taxonomy':
				if ( ! is_array( $value ) || $post_id <= 0 ) {
					return false;
				}
				if ( get_post_type( $post_id ) !== ( $value['post_type'] ?? '' ) ) {
					return false;
				}
				return (bool) has_term( $value['term'] ?? '', $value['taxonomy'] ?? '', $post_id );

			case 'url_pattern':
				$path    = null !== $request_path
					? $request_path
					: trim( (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ), PHP_URL_PATH ), '/' );
				$pattern = trim( (string) $value, '/' );
				$regex   = '#^' . str_replace( '\*', '.*', preg_quote( $pattern, '#' ) ) . '$#i';
				return (bool) preg_match( $regex, $path );

			default:
				return false;
		}
	}

	/**
	 * Whole-list replace (CLAUDE.md Section 6). Renumbers priority by
	 * array order and guarantees a trailing catch_all rule always exists.
	 *
	 * @return array The saved, normalized rule list.
	 */
	public static function save_rules( array $raw_rules ): array {
		$clean = array();

		foreach ( array_values( $raw_rules ) as $index => $raw_rule ) {
			$match_type = sanitize_text_field( $raw_rule['match']['type'] ?? 'catch_all' );
			$match      = array( 'type' => $match_type );

			if ( 'post_type_taxonomy' === $match_type && is_array( $raw_rule['match']['value'] ?? null ) ) {
				$match['value'] = array(
					'post_type' => sanitize_text_field( $raw_rule['match']['value']['post_type'] ?? '' ),
					'taxonomy'  => sanitize_text_field( $raw_rule['match']['value']['taxonomy'] ?? '' ),
					'term'      => sanitize_text_field( $raw_rule['match']['value']['term'] ?? '' ),
				);
			} elseif ( 'post_id' === $match_type ) {
				$match['value'] = sanitize_text_field( (string) ( $raw_rule['match']['value'] ?? '' ) );
			} elseif ( 'catch_all' !== $match_type ) {
				$match['value'] = sanitize_text_field( (string) ( $raw_rule['match']['value'] ?? '' ) );
			}

			$clean[] = array(
				'id'          => ! empty( $raw_rule['id'] ) ? sanitize_key( $raw_rule['id'] ) : 'rule_' . strtolower( wp_generate_password( 8, false ) ),
				'priority'    => $index,
				'match'       => $match,
				'schema_type' => sanitize_text_field( $raw_rule['schema_type'] ?? 'WebPage' ),
				'template'    => self::sanitize_template( $raw_rule['template'] ?? array() ),
			);
		}

		if ( empty( $clean ) || 'catch_all' !== end( $clean )['match']['type'] ) {
			$clean[] = array(
				'id'          => 'rule_' . strtolower( wp_generate_password( 8, false ) ),
				'priority'    => count( $clean ),
				'match'       => array( 'type' => 'catch_all' ),
				'schema_type' => 'WebPage',
				'template'    => Schema_Types::TEMPLATE_BY_SCHEMA_TYPE['WebPage'],
			);
		}

		update_option( self::OPTION, $clean );
		return $clean;
	}

	private static function sanitize_template( array $template ): array {
		$clean = array();
		foreach ( $template as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$clean[ $key ] = self::sanitize_template( $value );
			} elseif ( is_string( $value ) ) {
				$clean[ $key ] = sanitize_text_field( $value );
			}
		}
		return $clean;
	}

	/**
	 * Seeds the stock rule set for a locked type -- CLAUDE.md Section 9:
	 * only if no rules exist yet, unless $force is explicitly requested
	 * (the "Reset to default rules for this type" action, Section 7 item 6).
	 */
	public static function seed_defaults( string $locked_type_key, bool $force = false ): array {
		if ( ! $force && is_array( get_option( self::OPTION, null ) ) ) {
			return self::get_rules();
		}
		$rules = Schema_Types::get_default_rule_set( $locked_type_key );
		update_option( self::OPTION, $rules );
		return $rules;
	}

	/**
	 * Re-seeds rules for the just-chosen type ONLY while the wizard has
	 * never been completed before -- never as a side effect of re-running
	 * the wizard on an already-configured site (CLAUDE.md Section 7 item 6).
	 */
	public static function seed_for_type_if_pre_completion( string $locked_type_key ): void {
		if ( Schema_Settings::get_wizard_completed_at() ) {
			return;
		}
		update_option( self::OPTION, Schema_Types::get_default_rule_set( $locked_type_key ) );
	}
}
