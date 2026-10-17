<?php
/**
 * Schema_Breadcrumbs
 *
 * Builds the BreadcrumbList node for the current request. Singular pages,
 * taxonomy / post type / author / date archives and the static posts page
 * get one; the front page, search and 404 do not (a one-item trail says
 * nothing). The last crumb is the current page and carries no URL, which
 * Google allows and Yoast and Slim SEO both do.
 *
 * The trail is a plain list of `name` + optional `url` items, so the
 * `schema_plugin_breadcrumb_items` filter can change it without touching the
 * node format.
 *
 * @package Schema_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the sitewide breadcrumb trail as a schema.org BreadcrumbList.
 */
class Schema_Breadcrumbs {

	/**
	 * Page types that accept a `breadcrumb` property (schema.org WebPage and
	 * its subtypes). Article, Product and similar do not.
	 */
	const PAGE_TYPES = array(
		'WebPage',
		'CollectionPage',
		'ProfilePage',
		'AboutPage',
		'ContactPage',
		'ItemPage',
		'FAQPage',
		'QAPage',
		'SearchResultsPage',
		'CheckoutPage',
	);

	/**
	 * Whether the trail is switched on (it is, until the user turns it off).
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return '0' !== (string) get_option( Schema_Settings::OPTION_BREADCRUMBS, '1' );
	}

	/**
	 * Whether a node of this type can carry a `breadcrumb` reference.
	 *
	 * @param mixed $type The node's @type.
	 * @return bool
	 */
	public static function is_page_type( $type ): bool {
		return is_string( $type ) && in_array( $type, self::PAGE_TYPES, true );
	}

	/**
	 * The BreadcrumbList node for this request, or null when there is none.
	 *
	 * @param array|null $primary_node The page's own node, used to match its @id.
	 * @return array|null
	 */
	public static function build_node( ?array $primary_node ): ?array {
		if ( ! self::is_enabled() || self::other_plugin_prints_trail() ) {
			return null;
		}

		/**
		 * Filter: the trail as a list of `array( 'name' => string, 'url' => string )`
		 * items, `url` optional. Return an empty array for no breadcrumbs.
		 *
		 * @param array $items Trail, home first and current page last.
		 */
		$items = apply_filters( 'schema_plugin_breadcrumb_items', self::get_items() );
		$items = self::clean_items( $items );
		if ( count( $items ) < 2 ) {
			return null;
		}

		$list     = array();
		$position = 1;
		foreach ( $items as $item ) {
			$entry = array(
				'@type'    => 'ListItem',
				'position' => $position,
				'name'     => $item['name'],
			);
			if ( '' !== $item['url'] ) {
				$entry['item'] = $item['url'];
			}
			$list[] = $entry;
			++$position;
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => self::get_node_id( $primary_node ),
			'itemListElement' => $list,
		);
	}

	/**
	 * In "both" mode Yoast and Rank Math print their own trail next to ours;
	 * two BreadcrumbLists for one page is what that mode already warns about,
	 * so ours stands down.
	 *
	 * @return bool
	 */
	private static function other_plugin_prints_trail(): bool {
		return Schema_Compat::MODE_BOTH === Schema_Compat::get_mode()
			&& ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) );
	}

	/**
	 * Same page URL as the WebPage node, with a #breadcrumb fragment, so the
	 * two always agree.
	 *
	 * @param array|null $primary_node The page's own node.
	 * @return string
	 */
	private static function get_node_id( ?array $primary_node ): string {
		$base = '';
		if ( $primary_node && ! empty( $primary_node['@id'] ) && is_string( $primary_node['@id'] ) ) {
			$base = strtok( $primary_node['@id'], '#' );
		}
		if ( ! $base ) {
			$queried = get_queried_object();
			$base    = $queried instanceof WP_Post ? (string) get_permalink( $queried ) : '';
		}
		if ( ! $base ) {
			$path = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
			$base = (string) strtok( esc_url_raw( home_url( $path ) ), '?#' );
		}
		return trailingslashit( $base ) . '#breadcrumb';
	}

	/**
	 * The trail for the current request, home first, current page last.
	 *
	 * @return array<int, array{name: string, url?: string}>
	 */
	private static function get_items(): array {
		if ( is_front_page() || is_search() || is_404() ) {
			return array();
		}

		$items = array(
			array(
				'name' => __( 'Home', 'schema-plugin' ),
				'url'  => home_url( '/' ),
			),
		);

		if ( is_home() ) {
			// The static posts page.
			$items[] = array( 'name' => single_post_title( '', false ) );
		} elseif ( is_singular() ) {
			$items = array_merge( $items, self::get_singular_items() );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$items = array_merge( $items, self::get_term_archive_items() );
		} elseif ( is_post_type_archive() ) {
			$items[] = array( 'name' => post_type_archive_title( '', false ) );
		} elseif ( is_author() ) {
			$author = get_queried_object();
			if ( ! $author instanceof WP_User ) {
				return array();
			}
			$items[] = array( 'name' => $author->display_name );
		} elseif ( is_date() ) {
			$items = array_merge( $items, self::get_date_items() );
		} else {
			return array();
		}

		return $items;
	}

	/**
	 * Archive link, ancestors or category, then the page itself.
	 *
	 * @return array
	 */
	private static function get_singular_items(): array {
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return array();
		}

		$items = self::get_post_type_archive_items( $post->post_type );

		if ( is_post_type_hierarchical( $post->post_type ) ) {
			foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor_id ) {
				$items[] = array(
					'name' => get_the_title( $ancestor_id ),
					'url'  => (string) get_permalink( $ancestor_id ),
				);
			}
		} else {
			$term = self::get_primary_term( $post );
			if ( $term ) {
				$items = array_merge( $items, self::get_term_trail( $term ) );
			}
		}

		$items[] = array( 'name' => get_the_title( $post ) );
		return $items;
	}

	/**
	 * Ancestors and the term itself (no URL on the last one), after the
	 * archive link when the taxonomy belongs to one post type.
	 *
	 * @return array
	 */
	private static function get_term_archive_items(): array {
		$term = get_queried_object();
		if ( ! $term instanceof WP_Term ) {
			return array();
		}

		$items    = array();
		$taxonomy = get_taxonomy( $term->taxonomy );
		if ( $taxonomy && 1 === count( (array) $taxonomy->object_type ) ) {
			$items = self::get_post_type_archive_items( reset( $taxonomy->object_type ) );
		}

		foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, $term->taxonomy );
			$link     = $ancestor instanceof WP_Term ? get_term_link( $ancestor ) : null;
			if ( $ancestor instanceof WP_Term && is_string( $link ) ) {
				$items[] = array(
					'name' => $ancestor->name,
					'url'  => $link,
				);
			}
		}

		$items[] = array( 'name' => single_term_title( '', false ) );
		return $items;
	}

	/**
	 * Year, month and day archives: each level links to the one above.
	 *
	 * @return array
	 */
	private static function get_date_items(): array {
		$year  = (int) get_query_var( 'year' );
		$month = (int) get_query_var( 'monthnum' );
		$day   = (int) get_query_var( 'day' );
		if ( ! $year ) {
			return array();
		}

		if ( is_year() ) {
			return array( array( 'name' => (string) $year ) );
		}

		$items = array(
			array(
				'name' => (string) $year,
				'url'  => get_year_link( $year ),
			),
		);

		global $wp_locale;
		$month_name = $wp_locale->get_month( $month );
		if ( is_month() ) {
			$items[] = array( 'name' => $month_name );
			return $items;
		}

		$items[] = array(
			'name' => $month_name,
			'url'  => get_month_link( $year, $month ),
		);
		$items[] = array( 'name' => zeroise( $day, 2 ) );
		return $items;
	}

	/**
	 * The blog page (for posts, when one is set) or the post type's archive.
	 *
	 * @param string $post_type Post type slug.
	 * @return array
	 */
	private static function get_post_type_archive_items( string $post_type ): array {
		if ( 'post' === $post_type ) {
			$blog_page = (int) get_option( 'page_for_posts' );
			if ( 'page' === get_option( 'show_on_front' ) && $blog_page && 'publish' === get_post_status( $blog_page ) ) {
				return array(
					array(
						'name' => get_the_title( $blog_page ),
						'url'  => (string) get_permalink( $blog_page ),
					),
				);
			}
			return array();
		}

		$object = get_post_type_object( $post_type );
		$link   = $object && $object->has_archive ? get_post_type_archive_link( $post_type ) : false;
		if ( ! $link ) {
			return array();
		}
		return array(
			array(
				'name' => $object->labels->name,
				'url'  => $link,
			),
		);
	}

	/**
	 * The term whose trail represents a post: the primary term set in Yoast
	 * or Rank Math if there is one (so the trail matches theirs), otherwise
	 * the first term of `category` (posts) or of the post type's first
	 * public hierarchical taxonomy.
	 *
	 * @param WP_Post $post The post.
	 * @return WP_Term|null
	 */
	private static function get_primary_term( WP_Post $post ): ?WP_Term {
		$taxonomy = 'category';
		if ( 'post' !== $post->post_type ) {
			$taxonomy = '';
			foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $candidate ) {
				if ( $candidate->public && $candidate->hierarchical ) {
					$taxonomy = $candidate->name;
					break;
				}
			}
		}
		if ( '' === $taxonomy ) {
			return null;
		}

		$terms = get_the_terms( $post, $taxonomy );
		if ( ! is_array( $terms ) || ! $terms ) {
			return null;
		}

		foreach ( array( '_yoast_wpseo_primary_' . $taxonomy, 'rank_math_primary_' . $taxonomy ) as $meta_key ) {
			$primary_id = (int) get_post_meta( $post->ID, $meta_key, true );
			foreach ( $terms as $term ) {
				if ( $primary_id && $term->term_id === $primary_id ) {
					return $term;
				}
			}
		}

		return reset( $terms );
	}

	/**
	 * A term's ancestors (top first) followed by the term, each with its URL.
	 *
	 * @param WP_Term $term The term.
	 * @return array
	 */
	private static function get_term_trail( WP_Term $term ): array {
		$items = array();
		$terms = array();
		foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
			$terms[] = get_term( $ancestor_id, $term->taxonomy );
		}
		$terms[] = $term;

		foreach ( $terms as $item ) {
			$link = $item instanceof WP_Term ? get_term_link( $item ) : null;
			if ( $item instanceof WP_Term && is_string( $link ) ) {
				$items[] = array(
					'name' => $item->name,
					'url'  => $link,
				);
			}
		}
		return $items;
	}

	/**
	 * Plain-text names, absolute http(s) URLs, nothing malformed.
	 *
	 * @param mixed $items Raw items, possibly from the filter.
	 * @return array<int, array{name: string, url: string}>
	 */
	private static function clean_items( $items ): array {
		$clean = array();
		foreach ( (array) $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$name = trim( html_entity_decode( wp_strip_all_tags( (string) ( $item['name'] ?? '' ) ), ENT_QUOTES, 'UTF-8' ) );
			if ( '' === $name ) {
				continue;
			}
			$clean[] = array(
				'name' => $name,
				'url'  => isset( $item['url'] ) ? (string) esc_url_raw( (string) $item['url'], array( 'http', 'https' ) ) : '',
			);
		}

		// Google needs a URL on every crumb except the last.
		$last = count( $clean ) - 1;
		foreach ( $clean as $index => $item ) {
			if ( '' === $item['url'] && $index < $last ) {
				unset( $clean[ $index ] );
			}
		}
		return array_values( $clean );
	}
}
