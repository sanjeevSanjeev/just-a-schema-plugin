<?php
/**
 * Schema_Rest_Controller
 *
 * REST API -- CLAUDE.md Section 6, namespace schema-plugin/v1. React never
 * independently replicates the Graph Builder's resolution logic in JS; it
 * always calls /preview and renders whatever PHP returns, so preview and
 * live output can never drift out of sync.
 *
 * @package Schema_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema_Rest_Controller {

	const NAMESPACE_V1 = 'schema-plugin/v1';

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE_V1,
			'/entity',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_entity' ),
					'permission_callback' => array( __CLASS__, 'can_manage_options' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'save_entity' ),
					'permission_callback' => array( __CLASS__, 'can_manage_options' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/default-rules',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_default_rules' ),
					'permission_callback' => array( __CLASS__, 'can_manage_options' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'save_default_rules' ),
					'permission_callback' => array( __CLASS__, 'can_manage_options' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/posts/(?P<id>\d+)/schema',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_post_schema' ),
					'permission_callback' => array( __CLASS__, 'can_edit_post' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'save_post_schema' ),
					'permission_callback' => array( __CLASS__, 'can_edit_post' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_post_schema' ),
					'permission_callback' => array( __CLASS__, 'can_edit_post' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'preview' ),
				'permission_callback' => array( __CLASS__, 'can_edit_posts' ),
			)
		);

		// Backs the Default Rules manager's plain-language "choose a page"
		// picker (CLAUDE.md Section 3.4's post_id match type) -- lets a
		// non-technical user search for a page by title instead of typing
		// a raw numeric ID.
		register_rest_route(
			self::NAMESPACE_V1,
			'/post-search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'post_search' ),
				'permission_callback' => array( __CLASS__, 'can_edit_posts' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/exclusions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_exclusions' ),
					'permission_callback' => array( __CLASS__, 'can_manage_options' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'save_exclusions' ),
					'permission_callback' => array( __CLASS__, 'can_manage_options' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/features',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_features' ),
					'permission_callback' => array( __CLASS__, 'can_manage_options' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'save_features' ),
					'permission_callback' => array( __CLASS__, 'can_manage_options' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/compat',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_compat' ),
					'permission_callback' => array( __CLASS__, 'can_manage_options' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'save_compat' ),
					'permission_callback' => array( __CLASS__, 'can_manage_options' ),
				),
			)
		);

		// Only registered while the Network identity feature is on.
		if ( Schema_Features::is_enabled( 'network' ) ) {
			register_rest_route(
				self::NAMESPACE_V1,
				'/network-identity',
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( __CLASS__, 'get_network_identity' ),
						'permission_callback' => array( __CLASS__, 'can_manage_options' ),
					),
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( __CLASS__, 'save_network_identity' ),
						'permission_callback' => array( __CLASS__, 'can_manage_options' ),
					),
				)
			);
		}

		register_rest_route(
			self::NAMESPACE_V1,
			'/dashboard/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_dashboard_status' ),
				'permission_callback' => array( __CLASS__, 'can_manage_options' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/dashboard/completeness',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_completeness' ),
				'permission_callback' => array( __CLASS__, 'can_manage_options' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/dashboard/recommendations/(?P<key>[a-z_]+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'update_recommendation' ),
				'permission_callback' => array( __CLASS__, 'can_manage_options' ),
			)
		);
	}

	// -- Permission callbacks --------------------------------------------------

	public static function can_edit_posts(): bool {
		return current_user_can( 'edit_posts' );
	}

	public static function can_manage_options(): bool {
		return current_user_can( 'manage_options' );
	}

	public static function can_edit_post( WP_REST_Request $request ): bool {
		$post_id = (int) $request['id'];
		return current_user_can( 'edit_post', $post_id );
	}

	// -- /entity -----------------------------------------------------------

	public static function get_entity(): WP_REST_Response {
		$type     = Schema_Entity_Registry::get_entity_type();
		$editable = Schema_Field_Migrator::get_editable( Schema_Entity_Registry::get_entity_fields(), Schema_Entity_Registry::get_entity_legacy_fields(), $type );

		return new WP_REST_Response(
			array(
				'entity_type'           => $type,
				'flavor'                => Schema_Entity_Registry::get_entity_flavor(),
				// Includes legacy values valid for the current type, so the
				// form shows everything a save would keep.
				'fields'                => $editable['fields'],
				'legacy_fields'         => $editable['legacy_fields'],
				'website_enabled_search' => Schema_Entity_Registry::get_website_search_enabled(),
				'wizard_completed_at'   => Schema_Settings::get_wizard_completed_at(),
			)
		);
	}

	public static function save_entity( WP_REST_Request $request ) {
		$type_identifier = sanitize_text_field( (string) $request->get_param( 'entity_type' ) );
		if ( '' === $type_identifier ) {
			return new WP_Error( 'schema_plugin_missing_type', __( 'entity_type is required.', 'schema-plugin' ), array( 'status' => 400 ) );
		}

		$flavor = $request->get_param( 'flavor' );
		$flavor = $flavor ? sanitize_text_field( (string) $flavor ) : null;
		$fields = (array) $request->get_param( 'fields' );

		$result = Schema_Entity_Registry::save_entity( $type_identifier, $flavor, $fields );

		if ( null !== $request->get_param( 'website_enabled_search' ) ) {
			Schema_Entity_Registry::set_website_search_enabled( (bool) $request->get_param( 'website_enabled_search' ) );
		}

		$locked_types = Schema_Types::get_locked_types();
		$first_run    = false;

		if ( $request->get_param( 'complete_wizard' ) ) {
			if ( isset( $locked_types[ $type_identifier ] ) ) {
				Schema_Default_Rules::seed_for_type_if_pre_completion( $type_identifier );
			}
			$first_run = Schema_Settings::mark_wizard_completed();
		}

		$result['website_enabled_search'] = Schema_Entity_Registry::get_website_search_enabled();
		$result['first_run']              = $first_run;

		return new WP_REST_Response( $result );
	}

	// -- /default-rules ------------------------------------------------------

	public static function get_default_rules(): WP_REST_Response {
		return new WP_REST_Response( array( 'rules' => Schema_Default_Rules::get_rules() ) );
	}

	public static function save_default_rules( WP_REST_Request $request ): WP_REST_Response {
		if ( $request->get_param( 'reset_to_type_default' ) ) {
			$type_key = sanitize_text_field( (string) $request->get_param( 'reset_to_type_default' ) );
			$rules    = Schema_Default_Rules::seed_defaults( $type_key, true );
			return new WP_REST_Response( array( 'rules' => $rules ) );
		}

		// Wizard Step 1 -> Step 2 transition: show the just-chosen type's
		// stock rule set, but ONLY while the wizard has never completed
		// before (CLAUDE.md Section 7 item 6 -- never a side effect of a
		// re-run on an already-configured site).
		if ( $request->get_param( 'seed_for_type' ) ) {
			$type_key = sanitize_text_field( (string) $request->get_param( 'seed_for_type' ) );
			Schema_Default_Rules::seed_for_type_if_pre_completion( $type_key );
			return new WP_REST_Response( array( 'rules' => Schema_Default_Rules::get_rules() ) );
		}

		$rules = (array) $request->get_param( 'rules' );
		return new WP_REST_Response( array( 'rules' => Schema_Default_Rules::save_rules( $rules ) ) );
	}

	// -- /posts/{id}/schema --------------------------------------------------

	public static function get_post_schema( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request['id'];
		// Same editable view as GET /entity: legacy values valid for the
		// block's current type are shown in the form.
		$blocks = array_map(
			static function ( array $block ): array {
				$editable = Schema_Field_Migrator::get_editable( (array) ( $block['fields'] ?? array() ), (array) ( $block['legacy_fields'] ?? array() ), (string) ( $block['schema_type'] ?? '' ) );
				return array_merge( $block, $editable );
			},
			Schema_Post_Meta::get_blocks( $post_id )
		);

		if ( ! empty( $blocks ) ) {
			$resolved = Schema_Post_Meta::get_explicit_block( $post_id );
			$source   = 'custom';
		} else {
			$resolved = Schema_Default_Rules::resolve( $post_id );
			$source   = 'default';
		}

		return new WP_REST_Response(
			array(
				'source'           => $source,
				'blocks'           => $blocks,
				'resolved_preview' => $resolved,
			)
		);
	}

	public static function save_post_schema( WP_REST_Request $request ) {
		$post_id = (int) $request['id'];
		$blocks  = (array) $request->get_param( 'blocks' );

		$saved = Schema_Post_Meta::save_blocks( $post_id, $blocks );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return new WP_REST_Response(
			array(
				'blocks'           => $saved,
				'resolved_preview' => Schema_Post_Meta::get_explicit_block( $post_id ),
			)
		);
	}

	public static function delete_post_schema( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request['id'];
		Schema_Post_Meta::delete_blocks( $post_id );

		return new WP_REST_Response(
			array(
				'source'           => 'default',
				'blocks'           => array(),
				'resolved_preview' => Schema_Default_Rules::resolve( $post_id ),
			)
		);
	}

	// -- /preview --------------------------------------------------------------

	/**
	 * Resolves arbitrary draft data WITHOUT saving anything -- used by both
	 * the wizard/entity settings form and the post editor panel, so preview
	 * and live output can never drift out of sync (CLAUDE.md, Architecture).
	 */
	public static function preview( WP_REST_Request $request ): WP_REST_Response {
		$entity_type  = $request->get_param( 'entity_type' );
		$schema_type  = $request->get_param( 'schema_type' );
		$raw_override = $request->get_param( 'raw_override' );
		$fields       = (array) $request->get_param( 'fields' );

		if ( is_array( $request->get_param( 'network_identity' ) ) ) {
			return self::preview_network_identity( (array) $request->get_param( 'network_identity' ) );
		}

		if ( $raw_override && '' !== trim( (string) $raw_override ) ) {
			$decoded = json_decode( (string) $raw_override, true );
			if ( ! is_array( $decoded ) ) {
				return new WP_REST_Response(
					array(
						'node'     => null,
						'warnings' => array( __( 'The raw JSON-LD override is not valid JSON.', 'schema-plugin' ) ),
					)
				);
			}
			return new WP_REST_Response(
				array(
					'node'     => $decoded,
					'warnings' => array(),
				)
			);
		}

		if ( $entity_type ) {
			$type_identifier = sanitize_text_field( (string) $entity_type );
			$field_defs      = Schema_Types::get_field_defs( $type_identifier );
			$clean_fields    = Schema_Sanitizer::sanitize_fields( $fields, $field_defs );
			// Saving moves fields outside the type's defs to legacy (not
			// emitted), so the preview must drop them too.
			$clean_fields = array_intersect_key( $clean_fields, $field_defs );

			$resolved_type = Schema_Entity_Registry::resolve_schema_type( $type_identifier );
			$node          = Schema_Entity_Registry::build_organization_node( $resolved_type, $clean_fields );

			$locked_types = Schema_Types::get_locked_types();
			$type_label   = $locked_types[ $type_identifier ]['label'] ?? $resolved_type;

			return new WP_REST_Response(
				array(
					'node'     => $node,
					// Lint what will be emitted: Network Identity may supply the name/url.
					'warnings' => Schema_Sanitizer::lint_fields( Schema_Network_Identity::apply_entity_defaults( $clean_fields ), $field_defs, $type_label ),
					'badge'    => Schema_Types::get_badge_for_schema_type( $resolved_type ),
				)
			);
		}

		if ( $schema_type ) {
			$schema_type  = sanitize_text_field( (string) $schema_type );
			$field_defs   = Schema_Types::get_field_defs( $schema_type );
			// Saving moves fields the type doesn't define into legacy, so the
			// preview drops them too and matches what goes live.
			$clean_fields = array_intersect_key( Schema_Sanitizer::sanitize_fields( $fields, $field_defs ), $field_defs );

			$post_id = absint( $request->get_param( 'post_id' ) );
			$node    = Schema_Post_Meta::build_block_node( $post_id, $schema_type, $clean_fields );

			return new WP_REST_Response(
				array(
					'node'     => $node,
					'warnings' => Schema_Sanitizer::lint_fields( $clean_fields, $field_defs, $schema_type ),
					'badge'    => Schema_Types::get_badge_for_schema_type( $schema_type ),
				)
			);
		}

		return new WP_REST_Response(
			array(
				'node'     => null,
				'warnings' => array(),
			)
		);
	}

	// -- /post-search ------------------------------------------------------

	/**
	 * @return WP_REST_Response Array of { id, title, type } -- either the
	 *                           posts matching a text search, or (when
	 *                           `include` is given) the specific posts by
	 *                           ID, used to resolve an already-saved
	 *                           post_id rule back to a human-readable title.
	 */
	public static function post_search( WP_REST_Request $request ): WP_REST_Response {
		$search  = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$include = array_filter( array_map( 'absint', explode( ',', (string) $request->get_param( 'include' ) ) ) );

		$args = array(
			'post_type'      => 'any',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);

		if ( ! empty( $include ) ) {
			$args['post__in']      = $include;
			$args['posts_per_page'] = count( $include );
			unset( $args['orderby'], $args['order'] );
		} elseif ( '' !== $search ) {
			$args['s'] = $search;
		}

		$query   = new WP_Query( $args );
		$results = array();

		foreach ( $query->posts as $post ) {
			$type_object = get_post_type_object( $post->post_type );
			$results[]   = array(
				'id'    => $post->ID,
				'title' => get_the_title( $post ) ?: __( '(no title)', 'schema-plugin' ),
				'type'  => $type_object ? $type_object->labels->singular_name : $post->post_type,
			);
		}

		return new WP_REST_Response( $results );
	}

	// -- /features --------------------------------------------------------------

	public static function get_features(): WP_REST_Response {
		return new WP_REST_Response( Schema_Features::get_status() );
	}

	public static function save_features( WP_REST_Request $request ): WP_REST_Response {
		Schema_Features::set_enabled( (array) $request->get_param( 'enabled' ) );
		return self::get_features();
	}

	// -- /compat ----------------------------------------------------------------

	/**
	 * Current mode and detected plugins.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_compat(): WP_REST_Response {
		return new WP_REST_Response( Schema_Compat::get_status() );
	}

	/**
	 * Saves the mode.
	 *
	 * @param WP_REST_Request $request Request with a `mode` param.
	 * @return WP_REST_Response
	 */
	public static function save_compat( WP_REST_Request $request ): WP_REST_Response {
		Schema_Compat::set_mode( sanitize_key( (string) $request->get_param( 'mode' ) ) );
		return self::get_compat();
	}

	// -- /network-identity ----------------------------------------------------

	public static function get_network_identity(): WP_REST_Response {
		$settings = Schema_Network_Identity::get_settings();
		return new WP_REST_Response(
			array(
				'settings'             => $settings,
				'derived_ids'          => Schema_Network_Identity::get_parent_ids( $settings, Schema_Entity_Registry::get_resolved_schema_type() ),
				'suggested_parent_url' => Schema_Network_Identity::get_suggested_parent_url(),
				'home_url'             => home_url( '/' ),
				'entity_is_person'     => 'Person' === Schema_Entity_Registry::get_resolved_schema_type(),
			)
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function save_network_identity( WP_REST_Request $request ) {
		$result = Schema_Network_Identity::save( (array) $request->get_json_params() );
		return is_wp_error( $result ) ? $result : self::get_network_identity();
	}

	/**
	 * The sitewide Organization/Person + WebSite nodes as they would render
	 * with the draft settings, through the same builders as wp_head.
	 */
	private static function preview_network_identity( array $raw ): WP_REST_Response {
		$settings = Schema_Network_Identity::sanitize( $raw );

		$graph = Schema_Network_Identity::with_preview_settings(
			$settings,
			static fn() => array(
				Schema_Entity_Registry::get_organization_node(),
				Schema_Entity_Registry::get_website_node(),
			)
		);

		return new WP_REST_Response(
			array(
				'node'     => $graph,
				'warnings' => Schema_Network_Identity::validate( $settings ),
			)
		);
	}

	// -- /exclusions ---------------------------------------------------------

	public static function get_exclusions(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'backend_exclusions' => Schema_Settings::get_backend_exclusions(),
				'exclude_search'     => (bool) Schema_Settings::get( 'exclude_search', true ),
				'exclude_404'        => (bool) Schema_Settings::get( 'exclude_404', true ),
				'exclude_noindex'    => (bool) Schema_Settings::get( 'exclude_noindex', true ),
			)
		);
	}

	public static function save_exclusions( WP_REST_Request $request ): WP_REST_Response {
		Schema_Settings::save_backend_exclusions( (array) $request->get_param( 'backend_exclusions' ) );

		foreach ( array( 'exclude_search', 'exclude_404', 'exclude_noindex' ) as $toggle ) {
			if ( null !== $request->get_param( $toggle ) ) {
				Schema_Settings::set( $toggle, (bool) $request->get_param( $toggle ) );
			}
		}

		return self::get_exclusions();
	}

	// -- /dashboard ------------------------------------------------------------

	public static function get_dashboard_status(): WP_REST_Response {
		return new WP_REST_Response( Schema_Dashboard::get_status() );
	}

	public static function get_completeness(): WP_REST_Response {
		return new WP_REST_Response( Schema_Completeness::get_report() );
	}

	public static function update_recommendation( WP_REST_Request $request ) {
		$key    = sanitize_key( $request['key'] );
		$status = sanitize_text_field( (string) $request->get_param( 'status' ) );

		$result = Schema_Dashboard::set_recommendation_status( Schema_Entity_Registry::get_entity_type(), $key, $status );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'key'    => $key,
				'status' => $status,
			)
		);
	}
}
