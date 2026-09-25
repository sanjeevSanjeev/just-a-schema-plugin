<?php
/**
 * LinkedContentEntity
 *
 * Shared data model for every repeatable, page-linkable schema entity:
 * Services, Team members, Locations, Events, and (via auto mode) Authors.
 *
 * DEFERRED FEATURE (v1.1+) — see CLAUDE.md Section 8. This class is kept
 * present and correct so the v1.1 manager UIs (Services/Team/Locations/
 * Events) can be built against it later without a data migration. Do not
 * build manager UIs for this in the MVP.
 *
 * Storage: one row per entity in wp_options (or a dedicated table if the
 * entity count grows large). Each entity is a flat, versionable array so
 * switching schema_type never destroys prior field data (same
 * non-destructive principle used for per-post schema blocks).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema_Linked_Content_Entity {

	/** @var string Stable identifier, never reused. e.g. "svc_6821a3" */
	public string $id;

	/** @var string schema.org @type, e.g. 'Service', 'Person', 'LocalBusiness', 'Event' */
	public string $schema_type;

	/** @var array Type-specific field data, keyed by schema.org property name */
	public array $fields = array();

	/** @var string 'manual' | 'auto' */
	public string $link_mode = 'manual';

	/** @var int|null Only used when link_mode === 'manual' */
	public ?int $linked_post_id = null;

	/**
	 * Only used when link_mode === 'auto'.
	 * Identifies which resolver strategy to use — see
	 * Schema_Entity_Resolver::resolve_auto_link(). e.g. 'post_author'
	 */
	public ?string $auto_link_source = null;

	/**
	 * Only used when auto_link_source === 'post_author'.
	 * The WP user this entity is bound to. Also how Team <-> Author
	 * entities share a single @id instead of duplicating a person.
	 */
	public ?int $wp_user_id = null;

	/**
	 * How this entity nests into its parent (usually the Organization).
	 * e.g. 'employee', 'department', 'hasOfferCatalog', 'event', 'author'
	 */
	public string $parent_relationship;

	/** @var string Legacy/incompatible fields preserved on type switch, never shown by default */
	public array $legacy_fields = array();

	public function __construct( array $data = array() ) {
		foreach ( $data as $key => $value ) {
			if ( property_exists( $this, $key ) ) {
				$this->$key = $value;
			}
		}
	}

	/**
	 * Deterministic @id for this entity.
	 * Manual entities linked to a page use the page URL as the base,
	 * since that's the canonical, crawlable location for the entity.
	 * Unlinked entities fall back to a site-root fragment.
	 */
	public function get_id_uri(): string {
		if ( $this->link_mode === 'manual' && $this->linked_post_id ) {
			$permalink = get_permalink( $this->linked_post_id );
			return trailingslashit( $permalink ) . '#' . strtolower( $this->schema_type );
		}

		if ( $this->link_mode === 'auto' && $this->auto_link_source === 'post_author' && $this->wp_user_id ) {
			$archive_url = get_author_posts_url( $this->wp_user_id );
			if ( $archive_url ) {
				return trailingslashit( $archive_url ) . '#person';
			}
		}

		return home_url( '/#' . strtolower( $this->schema_type ) . '-' . $this->id );
	}

	public function has_linked_page(): bool {
		if ( $this->link_mode === 'manual' ) {
			return ! empty( $this->linked_post_id );
		}
		if ( $this->link_mode === 'auto' && $this->auto_link_source === 'post_author' ) {
			return (bool) get_author_posts_url( $this->wp_user_id );
		}
		return false;
	}
}
