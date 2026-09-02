/**
 * WooCommerce → Settings → RoovaVIP: adding and removing tiers and benefits.
 *
 * jQuery, like the theme's other admin scripts — WordPress admin convention.
 *
 * Two things here are less obvious than they look:
 *
 * - **New rows are numbered from a counter, not from how many rows there are.**
 *   Delete the second of three tiers and the next "Add tier" would otherwise
 *   reuse an index that is still on the page, and one row would silently
 *   overwrite the other when PHP read the posted array.
 * - **The Save button is enabled by hand.** WooCommerce's own settings script
 *   binds its "something changed" handler directly to the inputs that exist at
 *   page load, so a field added afterwards never reaches it and the button
 *   would stay greyed out with unsaved rows on screen.
 */
( function ( $ ) {
	'use strict';

	var strings = window.roovaVip || {};
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

	$( document ).on( 'click', '[data-roova-vip-add-tier]', function ( event ) {
		event.preventDefault();

		var html = fill( '[data-roova-vip-tier-template]', { __TIER__: nextIndex() } );
		if ( ! html ) {
			return;
		}

		var $list = $( '[data-roova-vip-list]' );
		$list.append( html );
		$list.children().last().find( 'input[type="text"]' ).first().trigger( 'focus' );

		markChanged();
	} );

	$( document ).on( 'click', '[data-roova-vip-remove-tier]', function ( event ) {
		event.preventDefault();

		if ( strings.confirmTier && ! window.confirm( strings.confirmTier ) ) {
			return;
		}

		$( this ).closest( '[data-roova-vip-tier]' ).remove();
		markChanged();
	} );

	$( document ).on( 'click', '[data-roova-vip-add-benefit]', function ( event ) {
		event.preventDefault();

		var $tier = $( this ).closest( '[data-roova-vip-tier]' );

		var html = fill( '[data-roova-vip-benefit-template]', {
			__TIER__: $tier.data( 'roova-vip-tier' ),
			__BENEFIT__: nextIndex()
		} );

		if ( ! html ) {
			return;
		}

		var $benefits = $tier.find( '[data-roova-vip-benefits]' );
		$benefits.append( html );
		$benefits.children().last().find( 'input[type="text"]' ).first().trigger( 'focus' );

		markChanged();
	} );

	$( document ).on( 'click', '[data-roova-vip-remove-benefit]', function ( event ) {
		event.preventDefault();

		$( this ).closest( '[data-roova-vip-benefit]' ).remove();
		markChanged();
	} );
}( window.jQuery ) );
