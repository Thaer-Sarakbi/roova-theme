/**
 * Term screens: the media picker and the amenity icon picker.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		// Media picker.
		$( document ).on( 'click', '[data-roova-media-select]', function ( event ) {
			event.preventDefault();

			var wrapper = $( this ).closest( '[data-roova-media]' );
			var frame = window.wp.media( {
				title: wrapper.find( '[data-roova-media-select]' ).text(),
				multiple: false,
				library: { type: 'image' }
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				var url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;

				wrapper.find( '[data-roova-media-input]' ).val( attachment.id );
				wrapper.find( '[data-roova-media-preview]' ).html( $( '<img />' ).attr( 'src', url ) );
			} );

			frame.open();
		} );

		$( document ).on( 'click', '[data-roova-media-clear]', function ( event ) {
			event.preventDefault();
			var wrapper = $( this ).closest( '[data-roova-media]' );
			wrapper.find( '[data-roova-media-input]' ).val( '' );
			wrapper.find( '[data-roova-media-preview]' ).empty();
		} );

		// Icon picker selection state.
		$( document ).on( 'change', '.roova-icon-picker input[type="radio"]', function () {
			var picker = $( this ).closest( '.roova-icon-picker' );
			picker.find( '.roova-icon-option' ).removeClass( 'is-selected' );
			$( this ).closest( '.roova-icon-option' ).addClass( 'is-selected' );
		} );

		// After adding a term over AJAX the form resets; clear our fields too.
		$( document ).on( 'ajaxComplete', function ( event, xhr, settings ) {
			if ( settings && settings.data && settings.data.indexOf( 'action=add-tag' ) !== -1 ) {
				$( '[data-roova-media-preview]' ).empty();
				$( '[data-roova-media-input]' ).val( '' );
			}
		} );
	} );
}( window.jQuery ) );
