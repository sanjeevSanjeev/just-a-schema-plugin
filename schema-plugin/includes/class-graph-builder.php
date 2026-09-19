<?php
/**
 * Schema_Graph_Builder
 *
 * MVP CORE — see CLAUDE.md Section 4 for the required render order.
 * This is the single entry point that assembles the final JSON-LD @graph
 * for the current request and outputs it in wp_head.
 *
 * Depends on four classes that must be built as part of the MVP
 * (see CLAUDE.md Sections 3.1, 3.2, 3.4, 3.5):
 *   - Schema_Entity_Registry  (Organization/Person + WebSite singleton nodes)
 *   - Schema_Post_Meta        (reads/writes _schema_plugin_blocks)
 *   - Schema_Default_Rules    (resolves the priority rule list)
 *   - Schema_Settings         (exclusion lists, toggles)
 *
 * Do not change the resolution order below without updating CLAUDE.md —
 * it is deliberately: system exclusions -> backend exclusions ->
 * singleton nodes -> per-post override or default rule -> linked
 * entities (deferred, v1.1) -> graph filter -> output.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema_Graph_Builder {

	public static function init(): void {
		add_action( 'wp_head', array( __CLASS__, 'output' ) );
	}

	public static function output(): void {
		$graph = self::build_for_current_request();
		if ( empty( $graph ) ) {
			return;
		}

		$payload = count( $graph ) === 1
			? $graph[0]
			: array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			);

		if ( count( $graph ) === 1 && ! isset( $payload['@context'] ) ) {
			$payload['@context'] = 'https://schema.org';
		}

		echo '<script type="application/ld+json">'
			. wp_json_encode( $payload, JSON_UNESCAPED_SLASHES )
			. '</script>' . "\n";
	}

	public static function build_for_current_request(): ?array {
		if ( self::is_system_excluded() ) {
			return null;
		}

		if ( self::is_backend_excluded() ) {
			return null;
		}

		$post_id = get_queried_object_id();
		$graph   = array();

		// 1. Sitewide singleton nodes — present on every emitting page.
		$graph[] = Schema_Entity_Registry::get_organization_node();
		$graph[] = Schema_Entity_Registry::get_website_node();

		// 2. Page-specific primary node: explicit override wins, else default rules.
		$primary_node = Schema_Post_Meta::get_explicit_block( $post_id );
		if ( ! $primary_node ) {
			$primary_node = Schema_Default_Rules::resolve( $post_id );
		}
		if ( $primary_node ) {
			$graph[] = $primary_node;
		}

		// 3. Linked entities (Team/Services/Locations/Events/Authors) — DEFERRED,
		// v1.1+. Schema_Entity_Registry::get_all_linked_entities() should return
		// an empty array in MVP so this loop is a safe no-op until v1.1 builds
		// the manager UIs referenced in CLAUDE.md Section 8.
		if ( method_exists( 'Schema_Entity_Registry', 'get_all_linked_entities' ) ) {
			foreach ( Schema_Entity_Registry::get_all_linked_entities() as $entity ) {
				if ( Schema_Entity_Resolver::emits_on( $entity, $post_id ) ) {
					$graph[] = Schema_Entity_Registry::render_entity_node( $entity );
				}
			}
		}

		/**
		 * Filter: allow advanced/manual adjustment of the graph via code —
		 * the supported extension point for anything not covered by the UI
		 * (mirrors the role RankMath's undocumented filter hack played,
		 * but as a documented, intentional hook).
		 */
		return apply_filters( 'schema_plugin_graph', array_filter( $graph ), $post_id );
	}

	private static function is_system_excluded(): bool {
		return is_admin()
			|| wp_doing_ajax()
			|| wp_doing_cron()
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			|| is_feed()
			|| is_robots()
			|| is_trackback();
	}

	private static function is_backend_excluded(): bool {
		$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );

		foreach ( Schema_Settings::get_backend_exclusions() as $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}
			if ( self::path_matches_pattern( $path, $rule['pattern'] ) ) {
				return true;
			}
		}

		if ( Schema_Settings::get( 'exclude_search', true ) && is_search() ) {
			return true;
		}
		if ( Schema_Settings::get( 'exclude_404', true ) && is_404() ) {
			return true;
		}
		if ( Schema_Settings::get( 'exclude_noindex', true ) && self::is_marked_noindex() ) {
			return true;
		}

		return false;
	}

	private static function path_matches_pattern( string $path, string $pattern ): bool {
		$pattern = trim( $pattern, '/' );
		$regex   = '#^' . str_replace( '\*', '.*', preg_quote( $pattern, '#' ) ) . '$#i';
		return (bool) preg_match( $regex, $path );
	}

	private static function is_marked_noindex(): bool {
		// Reads existing robots meta regardless of which SEO plugin (if any) set it.
		return (bool) apply_filters( 'schema_plugin_page_is_noindex', false );
	}
}
