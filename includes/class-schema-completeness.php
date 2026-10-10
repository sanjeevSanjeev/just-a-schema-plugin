<?php
/**
 * Schema_Completeness
 *
 * v1.1 Phase 6 -- the Completeness Dashboard. Scores the sitewide entity
 * and every linked entity (Services, Team, Locations, Events) against the
 * fields Google documents as required or recommended for their type
 * (the `required` / `recommended` flags in Schema_Types and
 * Schema_Linked_Entity_Types, verified 2026-09 in Phase 5), and lists
 * concrete issues that stop an entry being useful.
 *
 * Pages with a customized schema block (post meta) are scored too, as a
 * "Customized pages" group, against the verified per-type field sets
 * (Schema_Types::get_field_defs(): Article, Product, LocalBusiness...).
 * Pages on the site default aren't scored, and neither are pages using a
 * raw JSON-LD override (there's no field set to score against); those are
 * counted in the group note.
 *
 * Scoring reads the node exactly as it is emitted (defaults, Network
 * Identity and auto-linked profile data included), not the raw form
 * input, so the score describes what search engines actually receive.
 *
 * Each issue is { text, severity }: 'error' when it stops Google using
 * the entry (invalid URL, event with no venue, not set up), 'warning' when
 * it's worth a look (appears nowhere, past event, incomplete policy). The
 * Dashboard colors alerts by it.
 *
 * Score = (2 x required filled + recommended filled) /
 *         (2 x required total  + recommended total), as a percentage.
 * It's this plugin's heuristic, not a Google metric; Google has no
 * completeness score. Informational only, never gates anything.
 *
 * @package Schema_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema_Completeness {

	/**
	 * Tier 1 fields (name/url/description/sameAs) carry no flags because
	 * they're shared by every type; which of them Google recommends
	 * depends on the type (Organization, ProfilePage and LocalBusiness
	 * docs, updated 2026-09-08).
	 */
	const TIER1_RECOMMENDED = array(
		'organization'   => array( 'url', 'description', 'sameAs' ),
		'individual'     => array( 'description', 'sameAs' ),
		'local_business' => array( 'url' ),
	);

	/** Most customized pages scored per report; the rest are counted in the note. */
	const MAX_PAGES = 50;

	public static function get_report(): array {
		$entity = self::score_entity();

		$groups     = array();
		$all_scores = array();
		foreach ( Schema_Linked_Entity_Types::get_enabled_registry() as $entity_type => $config ) {
			$items = array();
			foreach ( Schema_Linked_Entity_Store::get_all( $entity_type ) as $row ) {
				$item         = self::score_linked( new Schema_Linked_Content_Entity( $row ), $entity_type, $config );
				$items[]      = $item;
				$all_scores[] = $item['score'];
			}
			$groups[] = array(
				'entity_type' => $entity_type,
				'label'       => $config['label'],
				'edit_url'    => Schema_Admin::linked_entity_page_url( $entity_type ),
				'note'        => 'service' === $entity_type
					? __( 'Google has no rich result for services, so only required fields are scored.', 'schema-plugin' )
					: '',
				'items'       => $items,
			);
		}

		$pages = self::score_pages();
		if ( $pages ) {
			foreach ( $pages['items'] as $item ) {
				$all_scores[] = $item['score'];
			}
			$groups[] = $pages;
		}

		// The sitewide entity counts as much as all linked entries together.
		$overall = $all_scores
			? (int) round( ( $entity['score'] + array_sum( $all_scores ) / count( $all_scores ) ) / 2 )
			: $entity['score'];

		return array(
			'overall' => $overall,
			'entity'  => $entity,
			'groups'  => $groups,
		);
	}

	private static function score_entity(): array {
		$type     = Schema_Entity_Registry::get_entity_type();
		// admin_url(), not menu_page_url(): menus aren't registered in REST requests.
		$edit_url = Schema_Admin::page_url( Schema_Admin::PAGE_SETTINGS, 'entity' );

		if ( '' === $type ) {
			return array(
				'label'               => __( 'Site entity', 'schema-plugin' ),
				'score'               => 0,
				'missing_required'    => array(),
				'missing_recommended' => array(),
				'issues'              => array( self::issue( __( 'Not set up yet. Run the setup wizard to choose a site type.', 'schema-plugin' ), 'error' ) ),
				'edit_url'            => $edit_url,
			);
		}

		$defs      = Schema_Types::get_field_defs( $type );
		$tier1_key = in_array( $type, array( 'individual', 'local_business' ), true ) ? $type : 'organization';
		foreach ( self::TIER1_RECOMMENDED[ $tier1_key ] as $key ) {
			if ( isset( $defs[ $key ] ) && empty( $defs[ $key ]['required'] ) ) {
				$defs[ $key ]['recommended'] = true;
			}
		}

		$node   = Schema_Entity_Registry::get_organization_node();
		$scored = self::score_node( $node, $defs );
		$locked = Schema_Types::get_locked_types();

		return array_merge(
			$scored,
			array(
				'label'    => $locked[ $type ]['label'] ?? $type,
				'issues'   => self::field_issues( $node, $defs ),
				'edit_url' => $edit_url,
			)
		);
	}

	private static function score_linked( Schema_Linked_Content_Entity $entity, string $entity_type, array $config ): array {
		$node   = Schema_Entity_Registry::render_entity_node( $entity );
		$defs   = $config['fields'];
		$scored = self::score_node( $node, $defs );
		$issues = self::field_issues( $node, $defs );

		if ( Schema_Entity_Resolver::is_unlinked( $entity ) ) {
			$issues[] = self::issue(
				'auto' === $entity->link_mode
					? __( "Not appearing anywhere yet: the linked user hasn't published any posts.", 'schema-plugin' )
					: __( 'Not appearing anywhere: choose the page it belongs to.', 'schema-plugin' ),
				'warning'
			);
		}

		if ( 'event' === $entity_type ) {
			$issues = array_merge( $issues, self::event_issues( $node ) );
		}

		$name = $node['name'] ?? '';
		return array_merge(
			$scored,
			array(
				'id'     => $entity->id,
				'name'   => is_string( $name ) && '' !== $name ? $name : __( '(untitled)', 'schema-plugin' ),
				'issues' => $issues,
			)
		);
	}

	/**
	 * The "Customized pages" group, or null when no page has a customized
	 * block. One query for posts with the meta key, capped at MAX_PAGES.
	 */
	private static function score_pages(): ?array {
		$query = new WP_Query(
			array(
				'post_type'              => 'any',
				'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'meta_key'               => Schema_Post_Meta::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_compare'           => 'EXISTS',
				'posts_per_page'         => self::MAX_PAGES,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'update_post_term_cache' => false,
			)
		);
		if ( ! $query->posts ) {
			return null;
		}

		$items = array();
		$raw   = 0;
		foreach ( $query->posts as $post_id ) {
			$blocks = Schema_Post_Meta::get_blocks( $post_id );
			if ( empty( $blocks ) ) {
				continue;
			}
			if ( ! empty( $blocks[0]['raw_override'] ) ) {
				++$raw;
				continue;
			}
			$items[] = self::score_page( (int) $post_id, (string) ( $blocks[0]['schema_type'] ?? 'WebPage' ) );
		}

		$notes = array();
		if ( $raw ) {
			$notes[] = sprintf(
				/* translators: %d: number of pages. */
				_n( '%d page uses a raw JSON-LD override and is not scored.', '%d pages use a raw JSON-LD override and are not scored.', $raw, 'schema-plugin' ),
				$raw
			);
		}
		if ( $query->found_posts > self::MAX_PAGES ) {
			$notes[] = sprintf(
				/* translators: %d: number of pages shown. */
				__( 'Showing the %d most recently edited customized pages.', 'schema-plugin' ),
				self::MAX_PAGES
			);
		}

		if ( ! $items && ! $raw ) {
			return null;
		}

		return array(
			'entity_type' => 'page_block',
			'label'       => __( 'Customized pages', 'schema-plugin' ),
			'edit_url'    => '',
			'note'        => implode( ' ', $notes ),
			'items'       => $items,
		);
	}

	private static function score_page( int $post_id, string $schema_type ): array {
		$node   = Schema_Post_Meta::get_explicit_block( $post_id ) ?? array();
		$defs   = Schema_Types::get_field_defs( $schema_type );
		// The entity labels ("Photo", "Logo") don't fit a page's fields.
		foreach ( self::block_labels() as $key => $label ) {
			if ( isset( $defs[ $key ] ) && empty( $defs[ $key ]['label'] ) ) {
				$defs[ $key ]['label'] = $label;
			}
		}
		$scored = self::score_node( $node, $defs );
		$issues = self::field_issues( $node, $defs );

		if ( 'publish' !== get_post_status( $post_id ) ) {
			$issues[] = self::issue( __( "This page isn't published yet, so its schema isn't live.", 'schema-plugin' ), 'warning' );
		}

		$title = get_the_title( $post_id );
		return array_merge(
			$scored,
			array(
				'id'       => (string) $post_id,
				'name'     => '' !== $title ? sprintf( '%1$s (%2$s)', $title, $schema_type ) : sprintf( '%s (%s)', __( '(untitled)', 'schema-plugin' ), $schema_type ),
				'issues'   => $issues,
				'edit_url' => (string) get_edit_post_link( $post_id, 'raw' ),
			)
		);
	}

	/** @return array<string, string> */
	private static function block_labels(): array {
		return array(
			'headline'      => __( 'Headline', 'schema-plugin' ),
			'image'         => __( 'Image', 'schema-plugin' ),
			'datePublished' => __( 'Date published', 'schema-plugin' ),
			'dateModified'  => __( 'Date modified', 'schema-plugin' ),
			'offers'        => __( 'Price and availability (or a review or rating)', 'schema-plugin' ),
		);
	}

	/**
	 * @return array{score:int, missing_required:string[], missing_recommended:string[]}
	 */
	private static function score_node( array $node, array $defs ): array {
		$required    = array();
		$recommended = array();
		foreach ( $defs as $key => $def ) {
			if ( ! empty( $def['one_of'] ) ) {
				// Required as a group (Product: offers, review or aggregateRating).
				$required[ $key ] = (bool) array_filter( (array) $def['one_of'], static fn( $alt ) => self::is_filled( $node[ $alt ] ?? null ) );
			} elseif ( ! empty( $def['required'] ) ) {
				$required[ $key ] = self::is_filled( $node[ $key ] ?? null );
			} elseif ( ! empty( $def['recommended'] ) ) {
				$recommended[ $key ] = self::is_filled( $node[ $key ] ?? null );
			}
		}

		$total  = 2 * count( $required ) + count( $recommended );
		$filled = 2 * count( array_filter( $required ) ) + count( array_filter( $recommended ) );

		return array(
			'score'               => $total ? (int) round( 100 * $filled / $total ) : 100,
			'missing_required'    => self::labels( array_keys( array_diff_key( $required, array_filter( $required ) ) ), $defs ),
			'missing_recommended' => self::labels( array_keys( array_diff_key( $recommended, array_filter( $recommended ) ) ), $defs ),
		);
	}

	/**
	 * @param mixed $value
	 */
	private static function is_filled( $value ): bool {
		return null !== $value && '' !== $value && array() !== $value;
	}

	/**
	 * Problems with values that are present: invalid URLs and incomplete
	 * store policies (the same checks the live preview lint runs).
	 *
	 * @return array[] Issues ({ text, severity }).
	 */
	private static function field_issues( array $node, array $defs ): array {
		$issues = array();
		foreach ( $defs as $key => $def ) {
			$value = $node[ $key ] ?? null;
			if ( ! self::is_filled( $value ) ) {
				continue;
			}
			$type = $def['type'] ?? 'text';
			if ( in_array( $type, array( 'url', 'media' ), true ) && is_string( $value ) && ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
				$issues[] = self::issue(
					sprintf(
						/* translators: %s: field label. */
						__( "%s doesn't look like a valid URL.", 'schema-plugin' ),
						self::label( $key, $def )
					),
					'error'
				);
			}
			foreach ( Schema_Sanitizer::lint_commerce_value( $value, $type ) as $text ) {
				$issues[] = self::issue( $text, 'warning' );
			}
		}
		return $issues;
	}

	/**
	 * Google's Event docs (updated 2026-09-08): location must be a Place
	 * with an address; purely virtual events aren't supported. Past events
	 * still emit but are no longer useful.
	 *
	 * @return array[] Issues ({ text, severity }).
	 */
	private static function event_issues( array $node ): array {
		$issues    = array();
		$locations = isset( $node['location']['@type'] ) ? array( $node['location'] ) : (array) ( $node['location'] ?? array() );
		$has_venue = false;
		foreach ( $locations as $location ) {
			if ( 'Place' === ( $location['@type'] ?? '' ) && ! empty( $location['address'] ) ) {
				$has_venue = true;
			}
		}
		if ( $locations && ! $has_venue ) {
			$issues[] = self::issue( __( "Google's event results need a physical venue with an address. Online-only events aren't eligible.", 'schema-plugin' ), 'error' );
		}

		$last = $node['endDate'] ?? ( $node['startDate'] ?? '' );
		if ( is_string( $last ) && '' !== $last ) {
			$timestamp = strtotime( $last );
			if ( $timestamp && $timestamp < time() ) {
				$issues[] = self::issue( __( 'This event is in the past. Consider removing it or updating the dates.', 'schema-plugin' ), 'warning' );
			}
		}

		return $issues;
	}

	/**
	 * @return array{text:string, severity:string}
	 */
	private static function issue( string $text, string $severity ): array {
		return array(
			'text'     => $text,
			'severity' => $severity,
		);
	}

	/**
	 * @param string[] $keys
	 * @return string[]
	 */
	private static function labels( array $keys, array $defs ): array {
		return array_map( static fn( $key ) => self::label( $key, $defs[ $key ] ?? array() ), $keys );
	}

	private static function label( string $key, array $def ): string {
		if ( ! empty( $def['label'] ) ) {
			return $def['label'];
		}
		$extra = Schema_Types::get_entity_extra_fields();
		foreach ( $extra as $type_defs ) {
			if ( ! empty( $type_defs[ $key ]['label'] ) ) {
				return $type_defs[ $key ]['label'];
			}
		}
		$labels = array(
			'name'                      => __( 'Name', 'schema-plugin' ),
			'url'                       => __( 'URL', 'schema-plugin' ),
			'description'               => __( 'Description', 'schema-plugin' ),
			'sameAs'                    => __( 'Social profiles', 'schema-plugin' ),
			'address'                   => __( 'Address', 'schema-plugin' ),
			'telephone'                 => __( 'Telephone', 'schema-plugin' ),
			'priceRange'                => __( 'Price range', 'schema-plugin' ),
			'openingHoursSpecification' => __( 'Opening hours', 'schema-plugin' ),
			'geo'                       => __( 'Map coordinates', 'schema-plugin' ),
			'department'                => __( 'Departments', 'schema-plugin' ),
			'location'                  => __( 'Location', 'schema-plugin' ),
		);
		return $labels[ $key ] ?? $key;
	}
}
