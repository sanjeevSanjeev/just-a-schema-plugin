import apiFetch from '@wordpress/api-fetch';

/**
 * Thin wrapper around the schema-plugin/v1 REST API (CLAUDE.md Section 6).
 * Every React surface goes through this file rather than calling apiFetch
 * directly, so the request shapes stay in one place.
 */
const NAMESPACE = '/schema-plugin/v1';

export function getEntity() {
	return apiFetch( { path: `${ NAMESPACE }/entity` } );
}

export function saveEntity( data ) {
	return apiFetch( { path: `${ NAMESPACE }/entity`, method: 'POST', data } );
}

export function getDefaultRules() {
	return apiFetch( { path: `${ NAMESPACE }/default-rules` } );
}

export function saveDefaultRules( rules ) {
	return apiFetch( {
		path: `${ NAMESPACE }/default-rules`,
		method: 'POST',
		data: { rules },
	} );
}

export function resetDefaultRulesToType( typeKey ) {
	return apiFetch( {
		path: `${ NAMESPACE }/default-rules`,
		method: 'POST',
		data: { reset_to_type_default: typeKey },
	} );
}

/**
 * Wizard Step 1 -> Step 2: pre-fill the just-chosen type's stock rules,
 * but only while the wizard has never completed before -- enforced
 * server-side by Schema_Default_Rules::seed_for_type_if_pre_completion().
 */
export function seedDefaultRulesForType( typeKey ) {
	return apiFetch( {
		path: `${ NAMESPACE }/default-rules`,
		method: 'POST',
		data: { seed_for_type: typeKey },
	} );
}

export function getPostSchema( postId ) {
	return apiFetch( { path: `${ NAMESPACE }/posts/${ postId }/schema` } );
}

export function savePostSchema( postId, blocks ) {
	return apiFetch( {
		path: `${ NAMESPACE }/posts/${ postId }/schema`,
		method: 'POST',
		data: { blocks },
	} );
}

export function deletePostSchema( postId ) {
	return apiFetch( {
		path: `${ NAMESPACE }/posts/${ postId }/schema`,
		method: 'DELETE',
	} );
}

export function preview( body ) {
	return apiFetch( { path: `${ NAMESPACE }/preview`, method: 'POST', data: body } );
}

/** Backs the Default Rules manager's plain-language "choose a page" picker. */
export function searchPosts( search ) {
	return apiFetch( { path: `${ NAMESPACE }/post-search?search=${ encodeURIComponent( search ) }` } );
}

export function getPostsByIds( ids ) {
	return apiFetch( { path: `${ NAMESPACE }/post-search?include=${ ids.join( ',' ) }` } );
}

export function getExclusions() {
	return apiFetch( { path: `${ NAMESPACE }/exclusions` } );
}

export function saveExclusions( data ) {
	return apiFetch( { path: `${ NAMESPACE }/exclusions`, method: 'POST', data } );
}

export function getDashboardStatus() {
	return apiFetch( { path: `${ NAMESPACE }/dashboard/status` } );
}

export function updateRecommendation( key, status ) {
	return apiFetch( {
		path: `${ NAMESPACE }/dashboard/recommendations/${ key }`,
		method: 'POST',
		data: { status },
	} );
}

/** Generic Linked Entity Manager (v1.1 Phase 1) -- Services now, Locations/Events/Team follow as more entity_type configs. */
export function getLinkedEntityConfig( entityType ) {
	return apiFetch( { path: `${ NAMESPACE }/entities/${ entityType }/config` } );
}

export function getLinkedEntities( entityType ) {
	return apiFetch( { path: `${ NAMESPACE }/entities/${ entityType }` } );
}

export function createLinkedEntity( entityType, data ) {
	return apiFetch( { path: `${ NAMESPACE }/entities/${ entityType }`, method: 'POST', data } );
}

export function updateLinkedEntity( entityType, id, data ) {
	return apiFetch( { path: `${ NAMESPACE }/entities/${ entityType }/${ id }`, method: 'POST', data } );
}

export function deleteLinkedEntity( entityType, id ) {
	return apiFetch( { path: `${ NAMESPACE }/entities/${ entityType }/${ id }`, method: 'DELETE' } );
}

/**
 * Core endpoint, not schema-plugin/v1: the user picker for auto-linked
 * (author) entities. context=edit lists every user, not only those with
 * published posts; the manager page already requires manage_options.
 */
export function getUsers() {
	return apiFetch( { path: '/wp/v2/users?context=edit&per_page=100&orderby=name&_fields=id,name' } );
}

export function getNetworkIdentity() {
	return apiFetch( { path: `${ NAMESPACE }/network-identity` } );
}

export function saveNetworkIdentity( data ) {
	return apiFetch( { path: `${ NAMESPACE }/network-identity`, method: 'POST', data } );
}

export function getCompleteness() {
	return apiFetch( { path: `${ NAMESPACE }/dashboard/completeness` } );
}
