import { __, sprintf } from '@wordpress/i18n';

const bootstrap = window.SchemaPluginData || {};
const COPY = bootstrap.wizardCopy || {};

/**
 * Per-type personalized copy (headings, field labels) for the wizard and
 * anywhere else EntityFields/EntityForm renders -- e.g. "Business name"
 * instead of a generic "Name" once "Local or Small Business" is chosen.
 * Falls back to generic copy for a custom "Show more" type or before any
 * type is chosen yet.
 */
export function getWizardCopy( entityType ) {
	const fallback = COPY._default || {};
	const specific = ( entityType && COPY[ entityType ] ) || {};
	return { ...fallback, ...specific };
}

/** Presentational only -- dashicons already ship with wp-admin, no extra asset needed. */
export const TYPE_ICONS = {
	individual: 'dashicons-admin-users',
	local_business: 'dashicons-store',
	online_store: 'dashicons-cart',
	general_business: 'dashicons-building',
	blog_publication: 'dashicons-admin-post',
};

export function getTypeIcon( key ) {
	return TYPE_ICONS[ key ] || 'dashicons-admin-site-alt3';
}

export function continueAsLabel( lockedTypes, entityType ) {
	const label = lockedTypes?.[ entityType ]?.label;
	if ( ! label ) {
		return __( 'Continue', 'schema-plugin' );
	}
	return sprintf(
		/* translators: %s: chosen site type, e.g. "Local or Small Business". */
		__( 'Continue as %s', 'schema-plugin' ),
		label
	);
}
