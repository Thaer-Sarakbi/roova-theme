/**
 * WooCommerce → Settings → Cashback rewards: adding and removing reward rows.
 *
 * jQuery, like the theme's other admin scripts — WordPress admin convention.
 *
 * The same two traps admin-vip.js documents apply here, for the same reasons:
 *
 * - **New rows are numbered from a counter, not from how many rows there are.**
 *   Delete the second of three rewards and the next "Add reward" would
 *   otherwise reuse an index still on the page, and one row would silently
 *   overwrite the other when PHP read the posted array.
 * - **The Save button is enabled by hand.** WooCommerce's settings script binds
 *   its "something changed" handler directly to the inputs present at page
 *   load, so a field added afterwards never reaches it and the button would
 *   stay greyed out with unsaved rewards on screen.
 */
( function ( $ ) {
	'use strict';

	var strings = window.roovaCashback || {};
	var uid = 1000;

	function nextIndex() {
		uid += 1;
		return uid;
	}

	function markChanged() {
		$( '.woocommerce-save-button' ).removeAttr( 'disabled' );
	}

	function fill( selector, replacements ) {
		var template = document.querySelector( selector );
		if ( ! template ) {
			return '';
		}

		var html = template.innerHTML;

		Object.keys( replacements ).forEach( function ( key ) {
			html = html.split( key ).join( replacements[ key ] );
		} );

		return html;
	}

	$( document ).on( 'click', '[data-roova-cashback-add]', function ( event ) {
		event.preventDefault();

		var html = fill( '[data-roova-cashback-template]', { __REWARD__: nextIndex() } );
		if ( ! html ) {
			return;
		}

		var $list = $( '[data-roova-cashback-list]' );
		$list.append( html );

		// The empty-state line belongs to a screen with no rewards on it.
		$( '[data-roova-cashback-empty]' ).remove();

		// The amount is the field that decides whether a row survives the save,
		// so that is where the cursor belongs.
		$list.children().last().find( 'input[type="number"]' ).eq( 1 ).trigger( 'focus' );

		markChanged();
	} );

	$( document ).on( 'click', '[data-roova-cashback-remove]', function ( event ) {
		event.preventDefault();

		if ( strings.confirmRemove && ! window.confirm( strings.confirmRemove ) ) {
			return;
		}

		$( this ).closest( '[data-roova-cashback-reward]' ).remove();
		markChanged();
	} );
}( window.jQuery ) );
