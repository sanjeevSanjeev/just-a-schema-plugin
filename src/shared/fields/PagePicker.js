import { useEffect, useRef, useState } from '@wordpress/element';
import { ComboboxControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { searchPosts, getPostsByIds } from '../api';

export const HOMEPAGE_VALUE = '{{homepage_id}}';

function dedupe( list ) {
	const seen = new Set();
	return list.filter( ( option ) => {
		if ( seen.has( option.value ) ) {
			return false;
		}
		seen.add( option.value );
		return true;
	} );
}

function homepageOption() {
	return { value: HOMEPAGE_VALUE, label: __( 'Your homepage', 'schema-plugin' ) };
}

/**
 * Plain-language "choose a page" picker for the post_id match type
 * (CLAUDE.md Section 3.4) -- a non-technical user searches for a page by
 * title instead of typing a raw numeric ID or the {{homepage_id}} token
 * from memory. "Your homepage" is always offered as a one-click option.
 */
export default function PagePicker( { value, onChange } ) {
	const [ options, setOptions ] = useState( [ homepageOption() ] );
	const debounceRef = useRef();

	// Resolve an already-saved numeric post ID to its title on mount.
	useEffect( () => {
		if ( value && value !== HOMEPAGE_VALUE && /^\d+$/.test( value ) ) {
			getPostsByIds( [ value ] ).then( ( posts ) => {
				if ( posts[ 0 ] ) {
					setOptions( ( prev ) =>
						dedupe( [
							{
								value: String( posts[ 0 ].id ),
								label: sprintf( '%1$s (%2$s)', posts[ 0 ].title, posts[ 0 ].type ),
							},
							...prev,
						] )
					);
				}
			} );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const handleFilter = ( search ) => {
		clearTimeout( debounceRef.current );
		if ( ! search ) {
			setOptions( ( prev ) => dedupe( [ homepageOption(), ...prev ] ) );
			return;
		}
		debounceRef.current = setTimeout( () => {
			searchPosts( search ).then( ( posts ) => {
				setOptions(
					dedupe( [
						homepageOption(),
						...posts.map( ( post ) => ( {
							value: String( post.id ),
							label: sprintf( '%1$s (%2$s)', post.title, post.type ),
						} ) ),
					] )
				);
			} );
		}, 300 );
	};

	return (
		<ComboboxControl
			label={ __( 'Choose a page', 'schema-plugin' ) }
			hideLabelFromVision
			value={ value || '' }
			onChange={ ( newValue ) => onChange( newValue || '' ) }
			options={ options }
			onFilterValueChange={ handleFilter }
		/>
	);
}
