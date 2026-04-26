/* global jQuery, mwsData */
( function ( $ ) {
	'use strict';

	var $form       = $( '#mws-search-form' );
	var $input      = $( '#mws-search-input' );
	var $results    = $( '#mws-live-results' );
	var searchTimer = null;
	var activeXhr   = null;

	// -----------------------------------------------------------------------
	// Live (as-you-type) search – shows a dropdown while typing.
	// -----------------------------------------------------------------------

	/**
	 * Renders a list of result items inside the live-results panel.
	 *
	 * @param {Array} items  Array of result objects from the server.
	 */
	function renderItems( items ) {
		$results.empty();

		if ( ! items || items.length === 0 ) {
			$results
				.append( $( '<p class="mws-live-results--empty">' ).text( mwsData.noResults ) )
				.removeAttr( 'hidden' );
			return;
		}

		var list = $( '<ul class="mws-results-list"></ul>' );

		$.each( items, function ( _i, item ) {
			var li = $( '<li class="mws-result-item"></li>' );
			var a  = $( '<a>' ).attr( 'href', item.url ).text( item.title );

			li.append( a );

			if ( item.excerpt ) {
				li.append( $( '<p class="mws-result-item__excerpt">' ).text( item.excerpt ) );
			}

			if ( item.site_name ) {
				li.append(
					$( '<p class="mws-result-item__meta">' ).text( item.site_name )
				);
			}

			list.append( li );
		} );

		$results.append( list ).removeAttr( 'hidden' );
	}

	/**
	 * Performs an AJAX search request.
	 *
	 * @param {string} query The search query string.
	 */
	function doSearch( query ) {
		if ( activeXhr ) {
			activeXhr.abort();
		}

		$results
			.empty()
			.append( $( '<p class="mws-live-results--loading">' ).text( mwsData.loading ) )
			.removeAttr( 'hidden' );

		activeXhr = $.get(
			mwsData.ajaxUrl,
			{
				action : 'mws_search',
				nonce  : mwsData.nonce,
				query  : query,
			},
			function ( response ) {
				if ( response && response.success ) {
					renderItems( response.data );
				} else {
					$results.attr( 'hidden', '' );
				}
			}
		).always( function () {
			activeXhr = null;
		} );
	}

	// Debounced input handler.
	$input.on( 'input', function () {
		var query = $.trim( $( this ).val() );

		clearTimeout( searchTimer );
		$results.attr( 'hidden', '' );

		if ( query.length < 2 ) {
			return;
		}

		searchTimer = setTimeout( function () {
			doSearch( query );
		}, 350 );
	} );

	// -----------------------------------------------------------------------
	// Close the live-results panel when the user clicks outside the form.
	// -----------------------------------------------------------------------
	$( document ).on( 'click', function ( e ) {
		if ( ! $form.is( e.target ) && $form.has( e.target ).length === 0 ) {
			$results.attr( 'hidden', '' );
		}
	} );

	// -----------------------------------------------------------------------
	// Keyboard navigation inside the live-results dropdown.
	// -----------------------------------------------------------------------
	$input.on( 'keydown', function ( e ) {
		if ( $results.attr( 'hidden' ) !== undefined ) {
			return;
		}

		var $items = $results.find( '.mws-result-item a' );
		var $active = $items.filter( ':focus' );

		if ( e.key === 'ArrowDown' ) {
			e.preventDefault();
			if ( $active.length === 0 ) {
				$items.first().trigger( 'focus' );
			} else {
				$active.closest( '.mws-result-item' ).next().find( 'a' ).trigger( 'focus' );
			}
		} else if ( e.key === 'Escape' ) {
			$results.attr( 'hidden', '' );
			$input.trigger( 'focus' );
		}
	} );

	$results.on( 'keydown', '.mws-result-item a', function ( e ) {
		var $active = $( this );

		if ( e.key === 'ArrowDown' ) {
			e.preventDefault();
			$active.closest( '.mws-result-item' ).next().find( 'a' ).trigger( 'focus' );
		} else if ( e.key === 'ArrowUp' ) {
			e.preventDefault();
			var $prev = $active.closest( '.mws-result-item' ).prev().find( 'a' );
			if ( $prev.length ) {
				$prev.trigger( 'focus' );
			} else {
				$input.trigger( 'focus' );
			}
		} else if ( e.key === 'Escape' ) {
			$results.attr( 'hidden', '' );
			$input.trigger( 'focus' );
		}
	} );

}( jQuery ) );
