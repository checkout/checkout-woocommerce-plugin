/**
 * Orders list: click a Checkout.com payment ID to copy it to the clipboard.
 */
( function () {
	'use strict';

	var copiedLabel = ( window.ckoOrderColumns && window.ckoOrderColumns.copied ) || 'Copied!';

	document.addEventListener( 'click', function ( event ) {
		var target = event.target && event.target.closest ? event.target.closest( '[data-cko-payment-id]' ) : null;

		if ( ! target ) {
			return;
		}

		var paymentId = target.getAttribute( 'data-cko-payment-id' ) || '';

		if ( ! paymentId || ! navigator.clipboard || ! navigator.clipboard.writeText ) {
			return;
		}

		navigator.clipboard.writeText( paymentId ).then( function () {
			target.setAttribute( 'title', copiedLabel );
		} );
	} );
} )();
