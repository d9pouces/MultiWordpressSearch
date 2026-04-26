/* global jQuery, mwsAdmin */
( function ( $ ) {
	'use strict';

	var siteList = $( '#mws-sites-list' );

	/**
	 * Returns the current number of site rows.
	 *
	 * @return {number}
	 */
	function rowCount() {
		return siteList.find( '.mws-site-row' ).length;
	}

	/**
	 * Appends a new, empty site row to the list.
	 */
	function addSiteRow() {
		var index = rowCount();
		var optionName = 'mws_sites';

		var row = $(
			'<div class="mws-site-row">' +
				'<input type="url"' +
					' name="' + optionName + '[' + index + '][url]"' +
					' placeholder="' + $( '<div>' ).text( mwsAdmin.urlPlaceholder ).html() + '"' +
					' class="regular-text"' +
					' required />' +
				'<input type="text"' +
					' name="' + optionName + '[' + index + '][name]"' +
					' placeholder="' + $( '<div>' ).text( mwsAdmin.namePlaceholder ).html() + '"' +
					' class="regular-text" />' +
				'<button type="button" class="button mws-remove-site">' +
					$( '<div>' ).text( mwsAdmin.removeSiteLabel ).html() +
				'</button>' +
			'</div>'
		);

		siteList.append( row );
	}

	/**
	 * Removes a site row and re-indexes remaining rows.
	 *
	 * @param {jQuery} $button The Remove button that was clicked.
	 */
	function removeSiteRow( $button ) {
		$button.closest( '.mws-site-row' ).remove();

		// Re-index so the submitted array has no gaps.
		siteList.find( '.mws-site-row' ).each( function ( i ) {
			$( this ).find( 'input[type="url"]' ).attr( 'name', 'mws_sites[' + i + '][url]' );
			$( this ).find( 'input[type="text"]' ).attr( 'name', 'mws_sites[' + i + '][name]' );
		} );
	}

	// Bind events.
	$( '#mws-add-site' ).on( 'click', addSiteRow );

	siteList.on( 'click', '.mws-remove-site', function () {
		removeSiteRow( $( this ) );
	} );

}( jQuery ) );
