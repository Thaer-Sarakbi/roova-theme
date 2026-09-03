/**
 * View order.
 *
 * One job: "Download voucher" prints the page. The voucher *is* the page — the
 * print rules at the foot of order.css strip the top bar and the buttons, so
 * what the printer (or "Save as PDF") gets is the booking on its own.
 *
 * Vanilla, like theme.js and account.js. Everything else on the page is a link
 * or a form and needs no script at all, so a blocked script costs nothing but
 * this button — which is why it is rendered as a <button> the browser simply
 * does nothing with, rather than a link that would go somewhere wrong.
 */
( function () {
	'use strict';

	var buttons = document.querySelectorAll( '[data-roova-print]' );

	if ( ! buttons.length || typeof window.print !== 'function' ) {
		return;
	}

	Array.prototype.forEach.call( buttons, function ( button ) {
		button.addEventListener( 'click', function () {
			window.print();
		} );
	} );
} )();
