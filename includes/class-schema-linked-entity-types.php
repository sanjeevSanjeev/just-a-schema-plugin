<?php
/**
 * Schema_Linked_Entity_Types
 *
 * v1.1 Phase 1 (Generic Linked Entity Manager) -- config-driven registry
 * for every repeatable, page-linkable entity type (Services, Locations,
 * Team, Events, ...), the same "one manager, many configs" pattern used
 * for the wizard's locked type list. Adding a new entity type is a config
 * entry here plus nothing else: Schema_Admin registers one submenu page
 * per entry automatically.
 *
 * Field definitions reuse the same shape Schema_Types::get_field_defs()
 * returns (plus an optional human `label`), so Schema_Sanitizer/TypedField
 * work unmodified.
 *
 * @package Schema_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema_Linked_Entity_Types {

	/**
	 * `parent_relationship` is the schema.org property on the CHILD node
	 * that points back at the sitewide Organization/Person @id (see
	 * Schema_Entity_Registry::render_entity_node()). Reverse references
	 * are used because the forward properties don't fit: Organization's
	 * `makesOffer` expects an Offer and `hasOfferCatalog` an OfferCatalog,
	 * not a Service, whereas Service.provider, LocalBusiness.parentOrganization
	 * and Event.organizer take the Organization directly.
	 *
	 * A type whose `link_modes` includes 'auto' can be bound to a WP user
	 * instead of a page (Team only, today); see Schema_Entity_Resolver.
	 */
	public static function get_registry(): array {
		return array(
			'service'  => array(
				'label'               => __( 'Services', 'schema-plugin' ),
				'singular_label'      => __( 'Service', 'schema-plugin' ),
				'schema_type'         => 'Service',
				'link_modes'          => array( 'manual' ),
				'parent_relationship' => 'provider',
				'fields'              => array(
					'name'        => array(
						'label'    => __( 'Name', 'schema-plugin' ),
						'required' => true,
						'type'     => 'text',
					),
					'description' => array(
						'label'    => __( 'Description', 'schema-plugin' ),
						'required' => false,
						'type'     => 'textarea',
					),
					'url'         => array(
						'label'    => __( 'URL', 'schema-plugin' ),
						'required' => false,
						'type'     => 'url',
					),
					'image'       => array(
						'label'    => __( 'Image', 'schema-plugin' ),
						'required' => false,
						'type'     => 'media',
					),
				),
			),
			// Manual: a person with their own page (e.g. /team/jane/).
			// Auto: bound to a WP user; emits on every post they author and
			// shares the author @id, with blank fields filled from their
			// profile (Schema_Author_Profile).
			'team'     => array(
				'label'               => __( 'Team', 'schema-plugin' ),
				'singular_label'      => __( 'Team member', 'schema-plugin' ),
				'schema_type'         => 'Person',
				'link_modes'          => array( 'manual', 'auto' ),
				'parent_relationship' => 'worksFor',
				'fields'              => array(
					'name'        => array(
						'label'    => __( 'Name', 'schema-plugin' ),
						'required' => true,
						'type'     => 'text',
					),
					'jobTitle'    => array(
						'label'    => __( 'Job title', 'schema-plugin' ),
						'required' => false,
						'type'     => 'text',
					),
					'description' => array(
						'label'       => __( 'Bio', 'schema-plugin' ),
						'required'    => false,
						'recommended' => true,
						'type'        => 'textarea',
					),
					'image'       => array(
						'label'       => __( 'Photo', 'schema-plugin' ),
						'required'    => false,
						'recommended' => true,
						'type'        => 'media',
					),
					'url'         => array(
						'label'    => __( 'URL', 'schema-plugin' ),
						'required' => false,
						'type'     => 'url',
					),
					'email'       => array(
						'label'    => __( 'Email', 'schema-plugin' ),
						'required' => false,
						'type'     => 'email',
					),
					'sameAs'      => array(
						'label'       => __( 'Social profiles', 'schema-plugin' ),
						'required'    => false,
						'recommended' => true,
						'type'        => 'sameAs_list',
					),
				),
			),
			// Branch locations, per Google's LocalBusiness guidance: each
			// location is its own LocalBusiness node linked to the parent
			// brand. Field set mirrors Schema_Types::LOCAL_BUSINESS_FIELDS
			// (the Google-verified list), minus the review fields.
			// TODO(v1.1): let a location pick a LocalBusiness subtype.
			'location' => array(
				'label'               => __( 'Locations', 'schema-plugin' ),
				'singular_label'      => __( 'Location', 'schema-plugin' ),
				'schema_type'         => 'LocalBusiness',
				'link_modes'          => array( 'manual' ),
				'parent_relationship' => 'parentOrganization',
				'fields'              => array(
					'name'                      => array(
						'label'    => __( 'Name', 'schema-plugin' ),
						'required' => true,
						'type'     => 'text',
					),
					'address'                   => array(
						'required' => true,
						'type'     => 'address',
					),
					'telephone'                 => array(
						'label'       => __( 'Telephone', 'schema-plugin' ),
						'required'    => false,
						'recommended' => true,
						'type'        => 'tel',
					),
					'url'                       => array(
						'label'       => __( 'URL', 'schema-plugin' ),
						'required'    => false,
						'recommended' => true,
						'type'        => 'url',
					),
					'image'                     => array(
						'label'    => __( 'Image', 'schema-plugin' ),
						'required' => false,
						'type'     => 'media',
					),
					'priceRange'                => array(
						'label'       => __( 'Price range', 'schema-plugin' ),
						'required'    => false,
						'recommended' => true,
						'type'        => 'select',
						'options'     => array( '$', '$$', '$$$', '$$$$' ),
					),
					'geo'                       => array(
						'required'    => false,
						'recommended' => true,
						'type'        => 'lat_long',
					),
					'openingHoursSpecification' => array(
						'required'    => false,
						'recommended' => true,
						'type'        => 'repeatable_hours',
					),
				),
			),
			// Required/recommended split follows Google's Event structured
			// data docs: name, startDate and location are required.
			// TODO(v1.1): offers, performer.
			'event'    => array(
				'label'               => __( 'Events', 'schema-plugin' ),
				'singular_label'      => __( 'Event', 'schema-plugin' ),
				'schema_type'         => 'Event',
				'link_modes'          => array( 'manual' ),
				'parent_relationship' => 'organizer',
				'fields'              => array(
					'name'                => array(
						'label'    => __( 'Name', 'schema-plugin' ),
						'required' => true,
						'type'     => 'text',
					),
					'startDate'           => array(
						'label'    => __( 'Start date and time', 'schema-plugin' ),
						'required' => true,
						'type'     => 'datetime',
					),
					'endDate'             => array(
						'label'       => __( 'End date and time', 'schema-plugin' ),
						'required'    => false,
						'recommended' => true,
						'type'        => 'datetime',
					),
					'eventAttendanceMode' => array(
						'label'    => __( 'Attendance mode', 'schema-plugin' ),
						'required' => false,
						'type'     => 'select',
						'options'  => array(
							array(
								'label' => __( 'In person', 'schema-plugin' ),
								'value' => 'https://schema.org/OfflineEventAttendanceMode',
							),
							array(
								'label' => __( 'Online', 'schema-plugin' ),
								'value' => 'https://schema.org/OnlineEventAttendanceMode',
							),
							array(
								'label' => __( 'Mixed (in person and online)', 'schema-plugin' ),
								'value' => 'https://schema.org/MixedEventAttendanceMode',
							),
						),
					),
					'eventStatus'         => array(
						'label'       => __( 'Status', 'schema-plugin' ),
						'required'    => false,
						'recommended' => true,
						'type'        => 'select',
						'options'     => array(
							array(
								'label' => __( 'Scheduled', 'schema-plugin' ),
								'value' => 'https://schema.org/EventScheduled',
							),
							array(
								'label' => __( 'Rescheduled', 'schema-plugin' ),
								'value' => 'https://schema.org/EventRescheduled',
							),
							array(
								'label' => __( 'Postponed', 'schema-plugin' ),
								'value' => 'https://schema.org/EventPostponed',
							),
							array(
								'label' => __( 'Moved online', 'schema-plugin' ),
								'value' => 'https://schema.org/EventMovedOnline',
							),
							array(
								'label' => __( 'Cancelled', 'schema-plugin' ),
								'value' => 'https://schema.org/EventCancelled',
							),
						),
					),
					'location'            => array(
						'required' => true,
						'type'     => 'event_location',
					),
					'description'         => array(
						'label'       => __( 'Description', 'schema-plugin' ),
						'required'    => false,
						'recommended' => true,
						'type'        => 'textarea',
					),
					'image'               => array(
						'label'       => __( 'Image', 'schema-plugin' ),
						'required'    => false,
						'recommended' => true,
						'type'        => 'media',
					),
					'url'                 => array(
						'label'    => __( 'URL', 'schema-plugin' ),
						'required' => false,
						'type'     => 'url',
					),
				),
			),
		);
	}

	/**
	 * Only the types whose feature is switched on (Schema_Features). Use
	 * this for anything that emits, lists or scores entities; get_config()
	 * stays unfiltered for plain config lookups.
	 */
	public static function get_enabled_registry(): array {
		return array_intersect_key( self::get_registry(), array_flip( Schema_Features::get_enabled() ) );
	}

	public static function get_config( string $entity_type ): ?array {
		$registry = self::get_registry();
		return $registry[ $entity_type ] ?? null;
	}

	public static function get_field_defs( string $entity_type ): array {
		$config = self::get_config( $entity_type );
		return $config['fields'] ?? array();
	}
}
