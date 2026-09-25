<?php
/**
 * Schema_Linked_Entity_Rest_Controller
 *
 * v1.1 Phase 1 (Generic Linked Entity Manager) -- one generic REST
 * resource, parameterized by entity_type, backing every repeatable
 * page-linkable entity (Services now; Locations/Events/Team follow in
 * v1.1 Phase 2/3 as additional Schema_Linked_Entity_Types config entries,
 * no new endpoints needed).
 *
 * @package Schema_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema_Linked_Entity_Rest_Controller {

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes(): void {
		$namespace = Schema_Rest_Controller::NAMESPACE_V1;

		register_rest_route(
			$namespace,
			'/entities/(?P<type>[a-z_]+)/config',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_config' ),
				'permission_callback' => array( 'Schema_Rest_Controller', 'can_manage_options' ),
			)
		);

		register_rest_route(
			$namespace,
			'/entities/(?P<type>[a-z_]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_entities' ),
					'permission_callback' => array( 'Schema_Rest_Controller', 'can_manage_options' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_entity' ),
					'permission_callback' => array( 'Schema_Rest_Controller', 'can_manage_options' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/entities/(?P<type>[a-z_]+)/(?P<id>[a-zA-Z0-9_]+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'update_entity' ),
					'permission_callback' => array( 'Schema_Rest_Controller', 'can_manage_options' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_entity' ),
					'permission_callback' => array( 'Schema_Rest_Controller', 'can_manage_options' ),
				),
			)
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_config( WP_REST_Request $request ) {
		$config = self::enabled_config( sanitize_key( $request['type'] ) );
		if ( ! $config ) {
			return self::unknown_type_error();
		}
		return new WP_REST_Response( $config );
	}

	/**
	 * Config for a type whose feature is on; null otherwise, so a
	 * disabled feature's routes answer 404.
	 */
	private static function enabled_config( string $type ): ?array {
		return isset( Schema_Linked_Entity_Types::get_enabled_registry()[ $type ] ) ? Schema_Linked_Entity_Types::get_config( $type ) : null;
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function list_entities( WP_REST_Request $request ) {
		$type = sanitize_key( $request['type'] );
		if ( ! self::enabled_config( $type ) ) {
			return self::unknown_type_error();
		}
		return new WP_REST_Response(
			array( 'entities' => Schema_Linked_Entity_Store::get_all( $type ) )
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_entity( WP_REST_Request $request ) {
		$type = sanitize_key( $request['type'] );
		if ( ! self::enabled_config( $type ) ) {
			return self::unknown_type_error();
		}

		$data = (array) $request->get_json_params();
		unset( $data['id'] ); // creation always generates a fresh id.

		$record = Schema_Linked_Entity_Store::save( $type, $data );
		return is_wp_error( $record ) ? $record : new WP_REST_Response( $record );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_entity( WP_REST_Request $request ) {
		$type = sanitize_key( $request['type'] );
		$id   = sanitize_key( $request['id'] );

		if ( ! self::enabled_config( $type ) ) {
			return self::unknown_type_error();
		}
		if ( ! Schema_Linked_Entity_Store::get( $type, $id ) ) {
			return new WP_Error( 'schema_plugin_entity_not_found', __( 'Entity not found.', 'schema-plugin' ), array( 'status' => 404 ) );
		}

		$data       = (array) $request->get_json_params();
		$data['id'] = $id;

		$record = Schema_Linked_Entity_Store::save( $type, $data );
		return is_wp_error( $record ) ? $record : new WP_REST_Response( $record );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_entity( WP_REST_Request $request ) {
		if ( ! self::enabled_config( sanitize_key( $request['type'] ) ) ) {
			return self::unknown_type_error();
		}
		Schema_Linked_Entity_Store::delete( sanitize_key( $request['type'] ), sanitize_key( $request['id'] ) );
		return new WP_REST_Response( array( 'deleted' => true ) );
	}

	private static function unknown_type_error(): WP_Error {
		return new WP_Error( 'schema_plugin_unknown_entity_type', __( 'Unknown entity type.', 'schema-plugin' ), array( 'status' => 404 ) );
	}
}
