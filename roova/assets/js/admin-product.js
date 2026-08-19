/**
 * Product editor: show the right panels for the Hotel and Room product types.
 *
 * WooCommerce only knows how to toggle its own product types, so the theme
 * takes care of its two.
 */
( function ( $ ) {
	'use strict';

	var TYPES = [ 'hotel', 'room' ];

	function currentType() {
		return $( 'select#product-type' ).val();
	}

	function refresh() {
		var type = currentType();

		// Rooms are priced per night, so they need the General (pricing) panel.
		$( '.pricing' ).addClass( 'show_if_room' );

		TYPES.forEach( function ( name ) {
			var show = $( '.show_if_' + name );
			var hide = $( '.hide_if_' + name );

			if ( type === name ) {
				show.show();
				hide.hide();
			} else {
				show.hide();
			}
		} );

		if ( TYPES.indexOf( type ) !== -1 ) {
			// Neither type ships or holds classic stock.
			$( '.inventory_tab, .shipping_tab' ).hide();
			$( '#linked_product_data .options_group' ).show();
		}

		if ( 'hotel' === type ) {
			$( '.pricing' ).hide();
		}
	}

	$( function () {
		refresh();

		$( document.body ).on( 'woocommerce-product-type-change', function () {
			window.setTimeout( refresh, 0 );
		} );

		$( 'select#product-type' ).on( 'change', function () {
			window.setTimeout( refresh, 0 );
		} );
	} );
}( window.jQuery ) );
