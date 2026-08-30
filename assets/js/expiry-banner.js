/**
 * Dismiss handler for the "reservation expired" banner.
 *
 * The banner is a one-off notice on the shop-landing page the countdown
 * dialog redirects to. It removes itself on click and does not come back
 * for the rest of the page's life — no state to persist.
 */
( function () {
	'use strict';

	var banner = document.getElementById( 'fssgw-expiry-banner' );
	var close = document.getElementById( 'fssgw-expiry-banner-close' );

	if ( banner && close ) {
		close.addEventListener( 'click', function () {
			banner.parentNode.removeChild( banner );
		} );
	}
} )();
