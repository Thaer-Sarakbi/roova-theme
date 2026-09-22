/**
 * The "Find your office" control in the Customizer's Contact page section.
 *
 * The Hotel Details address picker, wired to Customizer settings instead of
 * form fields: searching a place, dragging the pin or clicking the map sets
 * Address, Latitude and Longitude, zooming sets Map zoom, and the control's own
 * setting takes Google's place ID. Everything is set through wp.customize, so
 * the controls beside it update on screen, the preview refreshes, and nothing is
 * saved until the client presses Publish.
 *
 * Geocoding does the searching for the reason admin-map.js gives: no API beyond
 * Geocoding, and none of the Places widgets Google keeps deprecating.
 */
( function ( wp ) {
	'use strict';

	var data = window.roovaCustomizeMap || {};

	if ( ! wp || ! wp.customize || ! data.key ) {
		return;
	}

	var i18n = data.i18n || {};
	var map;
	var marker;
	var geocoder;
	var control;
	var status;

	function setting( id ) {
		return wp.customize( 'roova_' + id );
	}

	function write( id, value ) {
		var target = setting( id );
		if ( target ) {
			target.set( value );
		}
	}

	function say( message, isError ) {
		if ( ! status ) {
			return;
		}
		status.textContent = message || '';
		status.style.color = isError ? '#b32d2e' : '';
	}

	function writePosition( position ) {
		write( 'contact_lat', position.lat().toFixed( 6 ) );
		write( 'contact_lng', position.lng().toFixed( 6 ) );
	}

	function writeZoom() {
		if ( map ) {
			write( 'contact_map_zoom', Math.max( 1, Math.min( 21, map.getZoom() ) ) );
		}
	}

	/**
	 * Google hands back one line; the address setting is a textarea, one line
	 * per line of the address, so the commas become line breaks. The client can
	 * still rewrite it — this is a starting point, not a format.
	 *
	 * @param {string} text Formatted address.
	 */
	function writeAddress( text ) {
		if ( ! text ) {
			return;
		}

		write( 'contact_address', text.split( ',' ).map( function ( part ) {
			return part.trim();
		} ).filter( Boolean ).join( '\n' ) );
	}

	function writePlaceId( id ) {
		write( 'contact_place_id', id || '' );
	}

	function placePin( position, zoom ) {
		marker.setPosition( position );
		marker.setVisible( true );
		map.setCenter( position );

		if ( zoom ) {
			map.setZoom( zoom );
		}

		writePosition( position );
	}

	function reverseGeocode( position ) {
		geocoder.geocode( { location: position }, function ( results, state ) {
			if ( 'OK' === state && results && results[ 0 ] ) {
				writeAddress( results[ 0 ].formatted_address );
				writePlaceId( results[ 0 ].place_id );
				say( i18n.updated || '' );
			} else {
				writePlaceId( '' );
				say( i18n.noAddress || '' );
			}
		} );
	}

	function runSearch( input ) {
		var query = input.value.trim();

		if ( ! query ) {
			return;
		}

		if ( ! geocoder ) {
			say( i18n.mapFailed || '', true );
			return;
		}

		say( i18n.searching || '' );

		geocoder.geocode( { address: query }, function ( results, state ) {
			if ( 'OK' !== state || ! results || ! results[ 0 ] ) {
				say( i18n.notFound || '', true );
				return;
			}

			placePin( results[ 0 ].geometry.location, 16 );
			writeAddress( results[ 0 ].formatted_address );
			writePlaceId( results[ 0 ].place_id );
			writeZoom();
			say( i18n.updated || '' );
		} );
	}

	/**
	 * The point to open on: the saved pin, else the country the store sits in.
	 */
	function startPoint() {
		var lat = parseFloat( setting( 'contact_lat' ) ? setting( 'contact_lat' ).get() : '' );
		var lng = parseFloat( setting( 'contact_lng' ) ? setting( 'contact_lng' ).get() : '' );

		if ( ! isNaN( lat ) && ! isNaN( lng ) ) {
			return { position: { lat: lat, lng: lng }, pinned: true };
		}

		return {
			position: { lat: parseFloat( data.defaultLat ), lng: parseFloat( data.defaultLng ) },
			pinned: false
		};
	}

	window.roovaCustomizeMapInit = function () {
		var canvas = control.querySelector( '[data-roova-cmap-canvas]' );
		var input = control.querySelector( '[data-roova-cmap-search]' );
		var go = control.querySelector( '[data-roova-cmap-go]' );
		var start = startPoint();
		var zoom = parseInt( setting( 'contact_map_zoom' ) ? setting( 'contact_map_zoom' ).get() : 16, 10 );

		map = new window.google.maps.Map( canvas, {
			center: start.position,
			zoom: start.pinned ? ( zoom > 0 ? zoom : 16 ) : 5,
			mapTypeControl: false,
			streetViewControl: false,
			fullscreenControl: false
		} );

		marker = new window.google.maps.Marker( {
			map: map,
			position: start.position,
			draggable: true,
			visible: start.pinned
		} );

		geocoder = new window.google.maps.Geocoder();

		marker.addListener( 'dragend', function ( event ) {
			writePosition( event.latLng );
			reverseGeocode( event.latLng );
		} );

		map.addListener( 'click', function ( event ) {
			placePin( event.latLng );
			reverseGeocode( event.latLng );
		} );

		map.addListener( 'zoom_changed', writeZoom );

		go.addEventListener( 'click', function () {
			runSearch( input );
		} );

		input.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key ) {
				// The Customizer would otherwise take Enter as "save".
				event.preventDefault();
				runSearch( input );
			}
		} );

		/*
		 * A map built inside a collapsed section lays itself out at zero size,
		 * and the Contact page section is collapsed until the client opens it.
		 */
		var section = wp.customize.section( 'roova_contact' );

		if ( section ) {
			section.expanded.bind( function ( expanded ) {
				if ( expanded ) {
					window.setTimeout( function () {
						window.google.maps.event.trigger( map, 'resize' );
						map.setCenter( marker.getPosition() );
					}, 120 );
				}
			} );
		}
	};

	wp.customize.bind( 'ready', function () {
		control = document.querySelector( '[data-roova-cmap]' );

		if ( ! control ) {
			return;
		}

		status = control.querySelector( '[data-roova-cmap-status]' );

		var script = document.createElement( 'script' );
		script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent( data.key ) +
			'&callback=roovaCustomizeMapInit';
		script.async = true;
		script.onerror = function () {
			say( i18n.mapFailed || '', true );
		};
		document.head.appendChild( script );
	} );
}( window.wp ) );
