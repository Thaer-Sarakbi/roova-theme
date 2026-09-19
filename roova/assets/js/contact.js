/**
 * Contact us.
 *
 * One job: "Copy address" puts the office address on the clipboard.
 *
 * Vanilla, like theme.js, account.js and order.js. Everything else on the page
 * is a link — the number dials, the map opens, the socials open — so the only
 * thing a blocked script costs is this button, and the button is rendered
 * `hidden` and unhidden here rather than the other way round: copying cannot be
 * done without a script, and a visitor who has none should be shown no button
 * at all instead of a dead one.
 */
( function () {
	'use strict';

	var buttons = document.querySelectorAll( '[data-roova-copy]' );

	if ( ! buttons.length ) {
		return;
	}

	/**
	 * Put text on the clipboard the old way, through a selected textarea.
	 *
	 * @param {string} text Text to copy.
	 * @return {Promise} Resolves when the text has been copied.
	 */
	function copyBySelection( text ) {
		return new Promise( function ( resolve, reject ) {
			var field = document.createElement( 'textarea' );
			var ok = false;

			field.value = text;
			field.setAttribute( 'readonly', 'readonly' );
			field.style.position = 'fixed';
			field.style.top = '-1000px';
			field.style.opacity = '0';

			document.body.appendChild( field );
			field.select();
			field.setSelectionRange( 0, text.length );

			try {
				ok = document.execCommand( 'copy' );
			} catch ( error ) {
				ok = false;
			}

			document.body.removeChild( field );

			if ( ok ) {
				resolve();
			} else {
				reject();
			}
		} );
	}

	/**
	 * Put text on the clipboard.
	 *
	 * navigator.clipboard is absent on a site served over plain HTTP, and
	 * *rejects* where it exists but the browser refuses it — an unfocused
	 * document, a permissions policy, a headless run. Both cases fall through
	 * to the selection route, so the button works in either, and the rejection
	 * has to be caught rather than merely the absence: writeText refusing was
	 * leaving the button doing nothing at all.
	 *
	 * @param {string} text Text to copy.
	 * @return {Promise} Resolves when the text has been copied.
	 */
	function copy( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( text ).catch( function () {
				return copyBySelection( text );
			} );
		}

		return copyBySelection( text );
	}

	Array.prototype.forEach.call( buttons, function ( button ) {
		var label = button.querySelector( '[data-roova-copy-label]' );
		var idle = label ? label.textContent : '';
		var done = button.getAttribute( 'data-roova-copy-done' ) || idle;
		var timer = null;

		button.hidden = false;

		button.addEventListener( 'click', function () {
			copy( button.getAttribute( 'data-roova-copy' ) ).then( function () {
				button.classList.add( 'is-copied' );

				if ( label ) {
					label.textContent = done;
				}

				window.clearTimeout( timer );
				timer = window.setTimeout( function () {
					button.classList.remove( 'is-copied' );
					if ( label ) {
						label.textContent = idle;
					}
				}, 2200 );
			} ).catch( function () {
				/*
				 * Nothing to say: the address is on the page above this button,
				 * so a visitor whose browser refused the clipboard can still
				 * read it and select it by hand.
				 */
			} );
		} );
	} );
} )();
