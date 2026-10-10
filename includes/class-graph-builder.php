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
 * entities + author fallback -> graph filter -> output.
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

		$json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES );
		// SECURITY (CLAUDE.md Section 10.5.B, blocking for MVP): wp_json_encode()
		// does not stop a field value containing the literal string
		// "</script>" from breaking out of this tag.
		$json = str_replace( '</', '<\/', $json );

		wp_print_inline_script_tag( $json, array( 'type' => 'application/ld+json' ) );
	}

	/**
	 * Built once per request: wp_head output and the SEO-plugin
	 * suppression check (Schema_Compat) both need the same graph.
	 */
	public static function build_for_current_request(): ?array {
		static $memo = false;
		if ( false === $memo ) {
			$memo = self::build_graph();
		}
		return $memo;
	}

	/**
	 * Builds the graph for the current request, or null when excluded.
	 *
	 * @return array|null
	 */
	private static function build_graph(): ?array {
		if ( self::is_system_excluded() ) {
			return null;
		}

		if ( self::is_backend_excluded() ) {
			return null;
		}

		// Only a post (singular, or the static posts page) has a post ID.
		// get_queried_object_id() would return a user ID on author archives
		// and a term ID on taxonomy archives, which the per-post lookups
		// below would then misread as a post ID.
		$queried = get_queried_object();
		$post_id = $queried instanceof WP_Post ? $queried->ID : 0;
		$graph   = array();

		// 1. Sitewide singleton nodes — present on every emitting page.
		$graph[] = Schema_Entity_Registry::get_organization_node();
		$graph[] = Schema_Entity_Registry::get_website_node();

		// 2. Page-specific primary node: explicit override wins, else default rules.
		$primary_node = Schema_Post_Meta::get_explicit_block( $post_id );
		if ( ! $primary_node ) {
			$primary_node = Schema_Default_Rules::resolve( $post_id );
		}
		$primary_index = null;
		if ( $primary_node ) {
			$graph[]       = $primary_node;
			$primary_index = count( $graph ) - 1;
		}

		// 3. Linked entities (Services/Team/Locations/Events) that emit on
		// this page.
		if ( $post_id ) {
			foreach ( Schema_Entity_Registry::get_all_linked_entities() as $entity ) {
				if ( Schema_Entity_Resolver::emits_on( $entity, $post_id ) ) {
					$graph[] = Schema_Entity_Registry::render_entity_node( $entity );
				}
			}
			$author_node = self::get_unresolved_author_node( $graph, $post_id );
			if ( $author_node ) {
				$graph[] = $author_node;
			}
		}

		// 4. Author archive: describe the author as a ProfilePage.
		if ( 0 === $post_id && null !== $primary_index && is_author() ) {
			$graph = self::add_author_archive_nodes( $graph, $primary_index );
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

	/**
	 * Author archives: when the page's own node is still the plain default
	 * WebPage, make it a ProfilePage about the author (Google's ProfilePage
	 * needs a mainEntity Person with a name) and emit that Person: the
	 * author's auto-linked Team entry if they have one, else the
	 * profile-only fallback. A page matched by a custom rule keeps its
	 * node; the author is only described when the page says it's a profile.
	 *
	 * @param array $graph         Graph so far.
	 * @param int   $primary_index Index of the page's own node.
	 * @return array
	 */
	private static function add_author_archive_nodes( array $graph, int $primary_index ): array {
		$user = get_queried_object();
		if ( ! $user instanceof WP_User || 'WebPage' !== ( $graph[ $primary_index ]['@type'] ?? '' ) ) {
			return $graph;
		}

		$person = null;
		foreach ( Schema_Entity_Registry::get_all_linked_entities() as $entity ) {
			if ( Schema_Entity_Resolver::is_author_entry( $entity, (int) $user->ID ) ) {
				$person = Schema_Entity_Registry::render_entity_node( $entity );
				break;
			}
		}
		if ( ! $person ) {
			$person = Schema_Author_Profile::build_person_node( (int) $user->ID );
		}
		if ( ! $person || empty( $person['@id'] ) || empty( $person['name'] ) ) {
			return $graph;
		}

		$page          = $graph[ $primary_index ];
		$page['@type'] = 'ProfilePage';
		if ( ! empty( $page['@id'] ) ) {
			$page['@id'] = str_replace( '#webpage', '#profilepage', (string) $page['@id'] );
		}
		$page['mainEntity']      = array( '@id' => $person['@id'] );
		$graph[ $primary_index ] = $page;

		foreach ( $graph as $node ) {
			if ( ( $node['@id'] ?? '' ) === $person['@id'] ) {
				return $graph; // already defined.
			}
		}
		$graph[] = $person;
		return $graph;
	}

	/**
	 * Author auto-link fallback: when the graph references the post
	 * author's @id (e.g. an Article's `author`) but no node defines it --
	 * the author has no auto-linked Team entry -- emit a profile-only
	 * Person so the reference resolves.
	 */
	private static function get_unresolved_author_node( array $graph, int $post_id ): ?array {
		$id_uri = Schema_Author_Profile::get_person_id_uri( (int) get_post_field( 'post_author', $post_id ) );
		if ( ! $id_uri ) {
			return null;
		}

		$referenced = false;
		foreach ( $graph as $node ) {
			if ( ( $node['@id'] ?? '' ) === $id_uri ) {
				return null; // already defined.
			}
			if ( ! $referenced && str_contains( (string) wp_json_encode( $node, JSON_UNESCAPED_SLASHES ), '"' . $id_uri . '"' ) ) {
				$referenced = true;
			}
		}

		return $referenced ? Schema_Author_Profile::build_person_node( (int) get_post_field( 'post_author', $post_id ) ) : null;
	}

	private static function is_backend_excluded(): bool {
		$path = trim( (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ), PHP_URL_PATH ), '/' );

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
