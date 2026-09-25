<?php
/**
 * Schema_Field_Migrator
 *
 * The non-destructive type-switch logic behind complaint #3 (CLAUDE.md
 * Section 1 and 3.2): changing a type must never delete previously entered
 * field data. Shared by the sitewide entity (Section 3.1 + Section 7 item 6)
 * and per-post schema blocks (Section 3.2) so both behave identically.
 *
 * @package Schema_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema_Field_Migrator {

	/**
	 * Partitions a pool of field values into the active set for
	 * $new_type_identifier and everything else (legacy).
	 */
	public static function partition( array $pool, string $new_type_identifier ): array {
		$valid_keys = array_keys( Schema_Types::get_field_defs( $new_type_identifier ) );
		$active     = array();
		$legacy     = array();

		foreach ( $pool as $key => $value ) {
			if ( in_array( $key, $valid_keys, true ) ) {
				$active[ $key ] = $value;
			} else {
				$legacy[ $key ] = $value;
			}
		}

		return array(
			'fields'        => $active,
			'legacy_fields' => $legacy,
		);
	}

	/**
	 * Computes the fields to store after a save.
	 *
	 * - Submitted values are authoritative for fields valid under the new
	 *   type: the editing forms always send every field they show, so a
	 *   valid field missing from $incoming_fields was cleared by the user
	 *   and is dropped. (Previously it was merged back in from the stored
	 *   values, so no field could ever be cleared.)
	 * - Fields not valid for the new type, from any source, move to
	 *   legacy_fields rather than being deleted.
	 * - Only when the type actually changes, legacy fields valid for the
	 *   new type are restored (switching back to a prior type), since the
	 *   form couldn't have shown them under the old type. Without a type
	 *   change, restorable legacy fields are already shown in the form via
	 *   get_editable(), so their absence also means "cleared".
	 *
	 * @param string|null $previous_type_identifier Null for a first save.
	 */
	public static function migrate( array $current_fields, array $legacy_fields, string $new_type_identifier, array $incoming_fields = array(), ?string $previous_type_identifier = null ): array {
		$valid = array_flip( array_keys( Schema_Types::get_field_defs( $new_type_identifier ) ) );

		$active = array_intersect_key( $incoming_fields, $valid );
		if ( null !== $previous_type_identifier && $previous_type_identifier !== $new_type_identifier ) {
			$active += array_intersect_key( $legacy_fields, $valid );
		}

		return array(
			'fields'        => $active,
			'legacy_fields' => array_diff_key( array_merge( $legacy_fields, $current_fields, $incoming_fields ), $valid ),
		);
	}

	/**
	 * What an editing form should load: the active fields plus any legacy
	 * fields that are valid for the current type (e.g. after a type gains
	 * a field the user once entered under another type). Showing them
	 * means nothing reappears on save without the user having seen it.
	 */
	public static function get_editable( array $current_fields, array $legacy_fields, string $type_identifier ): array {
		$restorable = self::partition( $legacy_fields, $type_identifier );
		return array(
			'fields'        => array_merge( $restorable['fields'], $current_fields ),
			'legacy_fields' => $restorable['legacy_fields'],
		);
	}
}
