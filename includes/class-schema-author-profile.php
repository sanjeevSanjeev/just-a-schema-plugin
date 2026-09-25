<?php
/**
 * Schema_Author_Profile
 *
 * v1.1 Phase 3 (Author auto-link) -- schema fields on the native WP user
 * profile screen, and the Person node built from a user's profile.
 *
 * Article/BlogPosting default rules already reference the author as
 * `{"@id": "<author archive>/#person"}` via {{post_author_id}}. A Team
 * member in auto mode shares that exact @id (see
 * Schema_Linked_Content_Entity::get_id_uri()), and authors without a Team
 * entry get a profile-only Person node, so the reference always resolves
 * to a real node instead of dangling.
 *
 * @package Schema_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema_Author_Profile {

	const META_JOB_TITLE = 'schema_plugin_job_title';
	const META_SAME_AS   = 'schema_plugin_same_as';
	const META_PHOTO     = 'schema_plugin_photo';

	const NONCE_ACTION = 'schema_plugin_author_profile';
	const NONCE_FIELD  = 'schema_plugin_author_profile_nonce';

	public static function init(): void {
		add_action( 'show_user_profile', array( __CLASS__, 'render_fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_fields' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_fields' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_fields' ) );
	}

	/**
	 * @return string[] Every user meta key this plugin writes (for uninstall).
	 */
	public static function meta_keys(): array {
		return array( self::META_JOB_TITLE, self::META_SAME_AS, self::META_PHOTO );
	}

	public static function render_fields( WP_User $user ): void {
		$same_as = self::get_same_as( $user->ID );
		?>
		<h2><?php esc_html_e( 'Schema (structured data)', 'schema-plugin' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Used in the Person structured data emitted on posts this user writes. Name, bio and website come from the fields above.', 'schema-plugin' ); ?>
		</p>
		<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="schema_plugin_job_title"><?php esc_html_e( 'Job title', 'schema-plugin' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="schema_plugin_job_title" name="schema_plugin_job_title" value="<?php echo esc_attr( (string) get_user_meta( $user->ID, self::META_JOB_TITLE, true ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th><label for="schema_plugin_photo"><?php esc_html_e( 'Photo URL', 'schema-plugin' ); ?></label></th>
				<td>
					<input type="url" class="regular-text code" id="schema_plugin_photo" name="schema_plugin_photo" value="<?php echo esc_attr( (string) get_user_meta( $user->ID, self::META_PHOTO, true ) ); ?>" aria-describedby="schema_plugin_photo_description" />
					<p class="description" id="schema_plugin_photo_description">
						<?php esc_html_e( 'A full URL to a headshot, used in structured data. Leave blank to emit no image; the Gravatar is not used because it may be a generic placeholder.', 'schema-plugin' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th><label for="schema_plugin_same_as"><?php esc_html_e( 'Social profiles', 'schema-plugin' ); ?></label></th>
				<td>
					<textarea class="large-text code" rows="4" id="schema_plugin_same_as" name="schema_plugin_same_as" aria-describedby="schema_plugin_same_as_description"><?php echo esc_textarea( implode( "\n", $same_as ) ); ?></textarea>
					<p class="description" id="schema_plugin_same_as_description">
						<?php esc_html_e( 'One full URL per line, e.g. https://www.linkedin.com/in/your-name. Lines that are not full URLs are removed on save.', 'schema-plugin' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	public static function save_fields( int $user_id ): void {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$job_title = isset( $_POST['schema_plugin_job_title'] ) ? sanitize_text_field( wp_unslash( $_POST['schema_plugin_job_title'] ) ) : '';
		$photo     = isset( $_POST['schema_plugin_photo'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['schema_plugin_photo'] ) ) ) : '';
		$photo     = filter_var( $photo, FILTER_VALIDATE_URL ) ? esc_url_raw( $photo ) : '';
		$same_as   = isset( $_POST['schema_plugin_same_as'] ) ? sanitize_textarea_field( wp_unslash( $_POST['schema_plugin_same_as'] ) ) : '';
		// This plain PHP screen has no inline URL check, so lines that
		// aren't full URLs are dropped rather than "fixed" by esc_url_raw()
		// into something like http://not%20a%20url.
		$same_as = array_filter( array_map( 'trim', preg_split( '/\R/', $same_as ) ), static fn( $url ) => (bool) filter_var( $url, FILTER_VALIDATE_URL ) );
		$same_as = Schema_Sanitizer::sanitize_value( $same_as, 'sameAs_list' );

		self::update_or_delete( $user_id, self::META_JOB_TITLE, $job_title );
		self::update_or_delete( $user_id, self::META_PHOTO, $photo );
		self::update_or_delete( $user_id, self::META_SAME_AS, $same_as );
	}

	/**
	 * @param mixed $value
	 */
	private static function update_or_delete( int $user_id, string $key, $value ): void {
		if ( '' === $value || array() === $value ) {
			delete_user_meta( $user_id, $key );
		} else {
			update_user_meta( $user_id, $key, $value );
		}
	}

	/**
	 * get_user_meta() returns '' when unset, and (array) '' is [''].
	 *
	 * @return string[]
	 */
	private static function get_same_as( int $user_id ): array {
		$urls = get_user_meta( $user_id, self::META_SAME_AS, true );
		return is_array( $urls ) ? array_values( array_filter( $urls, static fn( $url ) => is_string( $url ) && '' !== $url ) ) : array();
	}

	/**
	 * Same convention as the {{post_author_id}} token.
	 */
	public static function get_person_id_uri( int $user_id ): string {
		$archive_url = $user_id ? get_author_posts_url( $user_id ) : '';
		return $archive_url ? trailingslashit( $archive_url ) . '#person' : '';
	}

	/**
	 * Person properties derived from the user's profile, keyed by
	 * schema.org property. Empty values are omitted (CLAUDE.md 10.5.C).
	 */
	public static function get_profile_fields( int $user_id ): array {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array();
		}

		// Profile schema fields belong to the "Team & authors" feature; with
		// it off, the fallback Person uses core profile data only.
		$team    = Schema_Features::is_enabled( 'team' );
		$same_as = $team ? self::get_same_as( $user_id ) : array();
		if ( $user->user_url ) {
			array_unshift( $same_as, $user->user_url );
		}

		$fields = array(
			'name'        => $user->display_name,
			'url'         => get_author_posts_url( $user_id ),
			'description' => wp_strip_all_tags( (string) $user->description ),
			'jobTitle'    => $team ? (string) get_user_meta( $user_id, self::META_JOB_TITLE, true ) : '',
			'image'       => $team ? (string) get_user_meta( $user_id, self::META_PHOTO, true ) : '',
			'sameAs'      => array_values( array_unique( $same_as ) ),
		);

		return array_filter( $fields, static fn( $value ) => '' !== $value && array() !== $value );
	}

	/**
	 * Profile-only Person node for an author with no Team entry.
	 */
	public static function build_person_node( int $user_id ): ?array {
		$id_uri = self::get_person_id_uri( $user_id );
		$fields = self::get_profile_fields( $user_id );
		if ( ! $id_uri || empty( $fields['name'] ) ) {
			return null;
		}
		return array_merge(
			array(
				'@type' => 'Person',
				'@id'   => $id_uri,
			),
			$fields
		);
	}
}
