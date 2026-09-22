/**
 * Hotel Details → Location: pick the address on a Google map.
 *
 * Searching a place, dragging the pin or clicking the map writes the Address,
 * Latitude and Longitude fields; zooming writes Map zoom. Everything stays
 * editable by hand afterwards — the fields are the stored values, this only
 * fills them in.
 *
 * Vanilla rather than jQuery, unlike the rest of the admin scripts: it talks to
 * the Google Maps API and a handful of inputs by id, and has no WordPress markup to
 * traverse.
 *
 * Geocoding, not the Places widget, does the searching: it needs no second API
 * beyond the Geocoding API, and none of the Places autocomplete widgets that
 * Google has been deprecating. Type-ahead suggestions are attached *as well*
 * when the key has Places enabled and the library still offers the classic
 * widget — a bonus, never the mechanism.
 */
( function () {
	'use strict';

	var data = window.roovaAdminMap || {};
	var picker = document.querySelector( '[data-roova-map-picker]' );

	if ( ! picker || ! data.key ) {
		return;
	}

	var canvas = picker.querySelector( '[data-roova-map-canvas]' );
	var search = picker.querySelector( '[data-roova-map-search]' );
	var go = picker.querySelector( '[data-roova-map-go]' );
	var status = picker.querySelector( '[data-roova-map-status]' );

	var address = document.getElementById( '_roova_address' );
	var latField = document.getElementById( '_roova_lat' );
	var lngField = document.getElementById( '_roova_lng' );
	var zoomField = document.getElementById( '_roova_map_zoom' );
	var placeField = document.getElementById( '_roova_map_place_id' );

	var i18n = data.i18n || {};
	var map;
	var marker;
	var geocoder;

	function say( message, isError ) {
		if ( ! status ) {
			return;
		}
		status.textContent = message || '';
		status.classList.toggle( 'is-error', !! isError );
	}

	function number( field ) {
		var value = parseFloat( field && field.value );
		return isNaN( value ) ? null : value;
	}

	/**
	 * The point the map opens on: whatever is saved, else the store's own
	 * country as a starting view rather than the middle of the ocean.
	 */
	function startPoint() {
		var lat = number( latField );
		var lng = number( lngField );

		if ( null !== lat && null !== lng ) {
			return { position: { lat: lat, lng: lng }, pinned: true };
		}

		return {
			position: { lat: parseFloat( data.defaultLat ), lng: parseFloat( data.defaultLng ) },
			pinned: false
		};
	}

	function writeCoords( position ) {
		if ( latField ) {
			latField.value = position.lat().toFixed( 6 );
		}
		if ( lngField ) {
			lngField.value = position.lng().toFixed( 6 );
		}
	}

	function writeAddress( text ) {
		if ( address && text ) {
			address.value = text;
		}
	}

	/**
	 * Google's own ID for the place under the pin. It is what makes the map on
	 * the hotel page open the hotel's listing instead of dropping a pin on its
	 * coordinates, so it is cleared whenever the pin lands somewhere Google
	 * cannot name — a stale ID would point at the previous hotel.
	 *
	 * @param {string} id Place ID, or empty.
	 */
	function writePlaceId( id ) {
		if ( placeField ) {
			placeField.value = id || '';
		}
	}

	function writeZoom() {
		if ( zoomField && map ) {
			zoomField.value = Math.max( 1, Math.min( 20, map.getZoom() ) );
		}
	}

	function placePin( position, zoom ) {
		marker.setPosition( position );
		marker.setVisible( true );
		map.setCenter( position );

		if ( zoom ) {
			map.setZoom( zoom );
		}

		writeCoords( position );
	}

	/**
	 * Turn a dropped pin back into an address. A point in the sea has no
	 * address, and that is not an error worth shouting about — the coordinates
	 * are still written, so the pin is where the admin put it.
	 */
	function reverseGeocode( position ) {
		geocoder.geocode( { location: position }, function ( results, state ) {
			if ( 'OK' === state && results && results[ 0 ] ) {
				writeAddress( results[ 0 ].formatted_address );
				writePlaceId( results[ 0 ].place_id );
				say( i18n.updated || '' );
			} else {
				writePlaceId( '' );
				say( i18n.noAddress || '', false );
			}
		} );
	}

	function runSearch() {
		var query = search ? search.value.trim() : '';

		if ( ! query ) {
			return;
		}

		// The map never loaded — a wrong key, a blocked request. Say so rather
		// than throwing where nobody sees it.
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

			var place = results[ 0 ];

			placePin( place.geometry.location, 16 );
			writeAddress( place.formatted_address );
			writePlaceId( place.place_id );
			writeZoom();
			say( i18n.updated || '' );
		} );
	}

	/**
	 * Type-ahead, only if this key has Places enabled and the library still
	 * carries the classic widget. Picking a suggestion is the same as pressing
	 * Search, so nothing depends on it being there.
	 */
	function attachSuggestions() {
		if ( ! search || ! window.google.maps.places || ! window.google.maps.places.Autocomplete ) {
			return;
		}

		try {
			var auto = new window.google.maps.places.Autocomplete( search, {
				fields: [ 'formatted_address', 'geometry', 'name', 'place_id' ]
			} );

			auto.addListener( 'place_changed', function () {
				var place = auto.getPlace();

				if ( ! place || ! place.geometry || ! place.geometry.location ) {
					runSearch();
					return;
				}

				placePin( place.geometry.location, 16 );
				writeAddress( place.formatted_address || place.name );
				writePlaceId( place.place_id );
				writeZoom();
				say( i18n.updated || '' );
			} );
		} catch ( e ) {
			// No suggestions, and the Search button is unaffected.
		}
	}

	window.roovaAdminMapInit = function () {
		var start = startPoint();
		var zoom = parseInt( zoomField && zoomField.value, 10 );

		map = new window.google.maps.Map( canvas, {
			center: start.position,
			zoom: start.pinned ? ( zoom > 0 ? zoom : 15 ) : 5,
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
			writeCoords( event.latLng );
			reverseGeocode( event.latLng );
		} );

		map.addListener( 'click', function ( event ) {
			placePin( event.latLng );
			reverseGeocode( event.latLng );
		} );

		map.addListener( 'zoom_changed', writeZoom );

		attachSuggestions();

		/*
		 * The panel is hidden until the Hotel Details tab is opened, and a map
		 * built in a hidden element lays itself out at zero size. Redraw it the
		 * first time the tab is shown.
		 */
		var settle = function () {
			if ( canvas.offsetParent ) {
				window.google.maps.event.trigger( map, 'resize' );
				map.setCenter( marker.getPosition() );
			}
		};

		document.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '.roova_hotel_options, .product_data_tabs' ) ) {
				window.setTimeout( settle, 50 );
			}
		} );
	};

	if ( go ) {
		go.addEventListener( 'click', runSearch );
	}

	if ( search ) {
		// Enter searches rather than saving the product half-filled-in.
		search.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key ) {
				event.preventDefault();
				runSearch();
			}
		} );
	}

	var script = document.createElement( 'script' );
	script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent( data.key ) +
		'&libraries=places&callback=roovaAdminMapInit';
	script.async = true;
	script.onerror = function () {
		say( i18n.mapFailed || '', true );
	};
	document.head.appendChild( script );
}() );
