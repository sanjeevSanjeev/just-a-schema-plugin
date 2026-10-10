<?php
/**
 * Schema_Entity_Resolver
 *
 * DEFERRED FEATURE (v1.1+) — see CLAUDE.md Section 8. Kept present so
 * v1.1 manager UIs can be built against it without redesigning the
 * linking mechanism. Do not build manager UIs against this in the MVP.
 *
 * Answers one question: "for the page currently being rendered, which
 * LinkedContentEntity records should emit their full entity here?"
 *
 * This is the single branch point between manual-mode (Team, Locations,
 * Services, Events) and auto-mode (Authors) entities. Everything
 * downstream reads off resolve_for_post()/emits_on(), so new
 * auto_link_source strategies only require adding a case here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema_Entity_Resolver {

	/**
	 * @param Schema_Linked_Content_Entity $entity
	 * @param int                          $current_post_id
	 * @return bool  True if this entity should render its full node on this page.
	 */
	public static function emits_on( Schema_Linked_Content_Entity $entity, int $current_post_id ): bool {
		if ( $entity->link_mode === 'manual' ) {
			return $entity->linked_post_id === $current_post_id;
		}

		if ( $entity->link_mode === 'auto' ) {
			return self::resolve_auto_link( $entity, $current_post_id );
		}

		return false;
	}

	/**
	 * Whether an entity is the auto-linked (post author) entry for a user.
	 * Used on author archives, where there's no post to check emits_on()
	 * against.
	 *
	 * @param Schema_Linked_Content_Entity $entity  Entity to check.
	 * @param int                          $user_id WordPress user ID.
	 * @return bool
	 */
	public static function is_author_entry( Schema_Linked_Content_Entity $entity, int $user_id ): bool {
		return 'auto' === $entity->link_mode
			&& 'post_author' === $entity->auto_link_source
			&& $user_id > 0
			&& (int) $entity->wp_user_id === $user_id;
	}

	/**
	 * Auto-link strategies. Add new cases here as new sources are supported
	 * (e.g. 'post_tag', 'custom_field') without touching manual-mode logic
	 * or any individual manager (Team, Services, etc.).
	 */
	private static function resolve_auto_link( Schema_Linked_Content_Entity $entity, int $current_post_id ): bool {
		switch ( $entity->auto_link_source ) {

			case 'post_author':
				if ( ! $entity->wp_user_id ) {
					return false;
				}
				$post_author_id = (int) get_post_field( 'post_author', $current_post_id );
				return $post_author_id === (int) $entity->wp_user_id;

			default:
				return false;
		}
	}

	/**
	 * Every post/page a given entity currently appears on.
	 * Used for admin summaries (e.g. "Auto: 12 authored posts") — never
	 * used for emission itself, which stays a live, per-request check
	 * via emits_on().
	 */
	public static function get_linked_post_ids( Schema_Linked_Content_Entity $entity ): array {
		if ( $entity->link_mode === 'manual' ) {
			return $entity->linked_post_id ? array( $entity->linked_post_id ) : array();
		}

		if ( $entity->link_mode === 'auto' && $entity->auto_link_source === 'post_author' && $entity->wp_user_id ) {
			return get_posts(
				array(
					'author'         => $entity->wp_user_id,
					'post_type'      => 'any',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);
		}

		return array();
	}

	/**
	 * Whether this entity should show the "not yet linked to any page"
	 * warning in the admin list table. Same treatment regardless of
	 * why — manual entities with no page picked, or auto entities
	 * whose WP user hasn't published anything (or has no archive URL).
	 */
	public static function is_unlinked( Schema_Linked_Content_Entity $entity ): bool {
		return ! $entity->has_linked_page() && empty( self::get_linked_post_ids( $entity ) );
	}
}
