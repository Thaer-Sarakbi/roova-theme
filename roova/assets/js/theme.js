/**
 * Roova front-end behaviour: search card, calendar, galleries and the room modal.
 *
 * Dates are handled as plain Y-m-d strings and only turned into Date objects at
 * local midnight, so a stay never shifts by a day across time zones.
 */
( function () {
	'use strict';

	var data = window.roovaData || {};
	var i18n = data.i18n || {};

	/* ------------------------------------------------------------- helpers */

	function qs( selector, scope ) {
		return ( scope || document ).querySelector( selector );
	}

	function qsa( selector, scope ) {
		return Array.prototype.slice.call( ( scope || document ).querySelectorAll( selector ) );
	}

	function toDate( ymd ) {
		var parts = String( ymd || '' ).split( '-' );
		if ( parts.length !== 3 ) {
			return null;
		}
		return new Date( parseInt( parts[ 0 ], 10 ), parseInt( parts[ 1 ], 10 ) - 1, parseInt( parts[ 2 ], 10 ) );
	}

	function toYmd( date ) {
		var month = String( date.getMonth() + 1 ).padStart( 2, '0' );
		var day = String( date.getDate() ).padStart( 2, '0' );
		return date.getFullYear() + '-' + month + '-' + day;
	}

	function addDays( ymd, days ) {
		var date = toDate( ymd );
		if ( ! date ) {
			return ymd;
		}
		date.setDate( date.getDate() + days );
		return toYmd( date );
	}

	function formatDate( ymd ) {
		var date = toDate( ymd );
		if ( ! date ) {
			return '';
		}
		var months = data.months && data.months.length === 12 ? data.months : null;
		var month = months ? months[ date.getMonth() ].substring( 0, 3 ) : ( date.getMonth() + 1 );
		return month + ' ' + date.getDate() + ' / ' + date.getFullYear();
	}

	function post( action, body ) {
		var payload = new FormData();
		payload.append( 'action', action );
		payload.append( 'nonce', data.nonce || '' );

		Object.keys( body || {} ).forEach( function ( key ) {
			payload.append( key, body[ key ] );
		} );

		return fetch( data.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: payload
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	/* -------------------------------------------------------------- panels */

	function closePanels( except ) {
		qsa( '.roova-panel' ).forEach( function ( panel ) {
			if ( panel !== except ) {
				panel.hidden = true;
			}
		} );
	}

	document.addEventListener( 'click', function ( event ) {
		if ( ! event.target.closest( '.roova-panel' ) && ! event.target.closest( '[data-roova-field]' ) ) {
			closePanels();
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key ) {
			closePanels();
			closeModal();
		}
	} );

	/* -------------------------------------------------------- search forms */

	function initSearchForm( form ) {
		var state = {
			checkIn: qs( '[data-roova-checkin]', form ).value,
			checkOut: qs( '[data-roova-checkout]', form ).value,
			rooms: parseInt( qs( '[data-roova-rooms]', form ).value, 10 ) || 1,
			adults: parseInt( qs( '[data-roova-adults]', form ).value, 10 ) || 2,
			children: parseInt( qs( '[data-roova-children]', form ).value, 10 ) || 0,
			calMonth: null,
			pickingEnd: false,
			blocked: []
		};

		var fields = {
			destination: qs( '[data-roova-field="destination"]', form ),
			dates: qs( '[data-roova-field="dates"]', form ),
			guests: qs( '[data-roova-field="guests"]', form )
		};

		/* --- open / close ------------------------------------------------ */
		Object.keys( fields ).forEach( function ( name ) {
			var field = fields[ name ];
			if ( ! field ) {
				return;
			}
			var panel = qs( '[data-roova-panel]', field );

			field.addEventListener( 'click', function ( event ) {
				if ( event.target.closest( '.roova-panel' ) ) {
					return;
				}
				var wasOpen = ! panel.hidden;
				closePanels();
				panel.hidden = wasOpen;

				if ( ! panel.hidden && 'destination' === name ) {
					loadSuggestions( '' );
					var filter = qs( '[data-roova-destination-filter]', panel );
					if ( filter ) {
						filter.focus();
					}
				}

				if ( ! panel.hidden && 'dates' === name ) {
					renderCalendar();
					loadBlockedDates();
				}
			} );
		} );

		/* --- destination suggestions ------------------------------------- */
		var destinationInput = qs( '[data-roova-destination-input]', form );
		var suggestionList = qs( '[data-roova-destination-list]', form );
		var suggestionFilter = qs( '[data-roova-destination-filter]', form );
		var suggestionTimer = null;

		function loadSuggestions( search ) {
			if ( ! suggestionList ) {
				return;
			}
			suggestionList.innerHTML = '<p class="roova-suggestion">' + ( i18n.loading || '' ) + '</p>';

			post( 'roova_suggestions', { search: search } ).then( function ( response ) {
				if ( ! response || ! response.success ) {
					suggestionList.innerHTML = '';
					return;
				}

				var items = response.data.items || [];
				if ( ! items.length ) {
					suggestionList.innerHTML = '<p class="roova-suggestion">' + ( i18n.noResults || '' ) + '</p>';
					return;
				}

				suggestionList.innerHTML = '';
				items.forEach( function ( item ) {
					var button = document.createElement( 'button' );
					button.type = 'button';
					button.className = 'roova-suggestion';
					button.innerHTML = '<span></span><small></small>';
					button.firstChild.textContent = item.label;
					button.lastChild.textContent = item.sub || '';

					button.addEventListener( 'click', function () {
						destinationInput.value = item.label;
						closePanels();
					} );

					suggestionList.appendChild( button );
				} );
			} ).catch( function () {
				suggestionList.innerHTML = '';
			} );
		}

		if ( suggestionFilter ) {
			suggestionFilter.addEventListener( 'input', function ( event ) {
				window.clearTimeout( suggestionTimer );
				var value = event.target.value;
				suggestionTimer = window.setTimeout( function () {
					loadSuggestions( value );
				}, 200 );
			} );
		}

		/* --- calendar ----------------------------------------------------- */
		var calendarPanel = qs( '[data-roova-calendar]', form );

		function loadBlockedDates() {
			if ( ! calendarPanel || ! calendarPanel.dataset.hotelId ) {
				return;
			}
			post( 'roova_unavailable_dates', {
				hotel_id: calendarPanel.dataset.hotelId,
				from: data.today,
				months: 4
			} ).then( function ( response ) {
				if ( response && response.success ) {
					state.blocked = response.data.dates || [];
					renderCalendar();
				}
			} ).catch( function () {} );
		}

		function renderCalendar() {
			if ( ! calendarPanel ) {
				return;
			}

			var start = toDate( state.checkIn ) || new Date();
			if ( ! state.calMonth ) {
				state.calMonth = new Date( start.getFullYear(), start.getMonth(), 1 );
			}

			var year = state.calMonth.getFullYear();
			var month = state.calMonth.getMonth();
			var monthName = data.months && data.months.length === 12 ? data.months[ month ] : month + 1;

			var head = document.createElement( 'div' );
			head.className = 'roova-cal__head';
			head.innerHTML =
				'<button type="button" data-roova-cal-prev aria-label="&laquo;">&#8249;</button>' +
				'<span class="roova-cal__title">' + monthName + ' ' + year + '</span>' +
				'<button type="button" data-roova-cal-next aria-label="&raquo;">&#8250;</button>';

			var grid = document.createElement( 'div' );
			grid.className = 'roova-cal__grid';

			var startOfWeek = parseInt( data.startOfWeek, 10 ) || 0;
			var dayNames = data.days && data.days.length === 7 ? data.days : [ 'S', 'M', 'T', 'W', 'T', 'F', 'S' ];

			for ( var d = 0; d < 7; d++ ) {
				var dow = document.createElement( 'div' );
				dow.className = 'roova-cal__dow';
				dow.textContent = dayNames[ ( startOfWeek + d ) % 7 ];
				grid.appendChild( dow );
			}

			var first = new Date( year, month, 1 );
			var offset = ( first.getDay() - startOfWeek + 7 ) % 7;
			for ( var o = 0; o < offset; o++ ) {
				grid.appendChild( document.createElement( 'div' ) );
			}

			var daysInMonth = new Date( year, month + 1, 0 ).getDate();
			for ( var day = 1; day <= daysInMonth; day++ ) {
				( function ( dayNumber ) {
					var ymd = toYmd( new Date( year, month, dayNumber ) );
					var button = document.createElement( 'button' );
					button.type = 'button';
					button.className = 'roova-cal__day';
					button.textContent = dayNumber;

					if ( ymd < data.today || state.blocked.indexOf( ymd ) !== -1 ) {
						button.disabled = true;
					}

					if ( ymd === state.checkIn ) {
						button.classList.add( 'is-start' );
					}
					if ( ymd === state.checkOut ) {
						button.classList.add( 'is-end' );
					}
					if ( ymd > state.checkIn && ymd < state.checkOut ) {
						button.classList.add( 'is-in-range' );
					}

					button.addEventListener( 'click', function ( event ) {
						event.stopPropagation();
						pickDate( ymd );
					} );

					grid.appendChild( button );
				}( day ) );
			}

			var footer = document.createElement( 'div' );
			footer.className = 'roova-cal__footer';
			footer.textContent = state.pickingEnd ? ( i18n.selectDates || '' ) : '';

			calendarPanel.innerHTML = '';
			calendarPanel.appendChild( head );
			calendarPanel.appendChild( grid );
			calendarPanel.appendChild( footer );

			qs( '[data-roova-cal-prev]', calendarPanel ).addEventListener( 'click', function ( event ) {
				event.stopPropagation();
				state.calMonth = new Date( year, month - 1, 1 );
				renderCalendar();
			} );

			qs( '[data-roova-cal-next]', calendarPanel ).addEventListener( 'click', function ( event ) {
				event.stopPropagation();
				state.calMonth = new Date( year, month + 1, 1 );
				renderCalendar();
			} );
		}

		function pickDate( ymd ) {
			if ( ! state.pickingEnd ) {
				state.checkIn = ymd;
				state.checkOut = addDays( ymd, 1 );
				state.pickingEnd = true;
			} else if ( ymd <= state.checkIn ) {
				state.checkIn = ymd;
				state.checkOut = addDays( ymd, 1 );
			} else {
				state.checkOut = ymd;
				state.pickingEnd = false;
				closePanels();
			}

			qs( '[data-roova-checkin]', form ).value = state.checkIn;
			qs( '[data-roova-checkout]', form ).value = state.checkOut;

			var label = qs( '[data-roova-dates-label]', form );
			if ( label ) {
				label.textContent = formatDate( state.checkIn ) + ' — ' + formatDate( state.checkOut );
			}

			renderCalendar();
		}

		/* --- guests ------------------------------------------------------- */
		function updateGuestsLabel() {
			var label = qs( '[data-roova-guests-label]', form );
			if ( ! label ) {
				return;
			}
			var guests = state.adults + state.children;
			label.textContent =
				state.rooms + ' ' + ( 1 === state.rooms ? i18n.room : i18n.rooms ) +
				' · ' + guests + ' ' + ( 1 === guests ? i18n.guest : i18n.guests );
		}

		var limits = {
			rooms: [ 1, 8 ],
			adults: [ 1, 16 ],
			children: [ 0, 12 ]
		};

		qsa( '[data-roova-step]', form ).forEach( function ( button ) {
			button.addEventListener( 'click', function ( event ) {
				event.stopPropagation();

				var key = button.dataset.roovaStep;
				var delta = parseInt( button.dataset.roovaDelta, 10 );
				var range = limits[ key ];

				state[ key ] = Math.min( range[ 1 ], Math.max( range[ 0 ], state[ key ] + delta ) );

				var count = qs( '[data-roova-count="' + key + '"]', form );
				if ( count ) {
					count.textContent = state[ key ];
				}

				var input = qs( '[data-roova-' + key + ']', form );
				if ( input ) {
					input.value = state[ key ];
				}

				updateGuestsLabel();
			} );
		} );
	}

	qsa( '[data-roova-search]' ).forEach( initSearchForm );

	/* ------------------------------------------------------------- slider */

	qsa( '[data-roova-slide]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var slider = qs( '[data-roova-slider]', button.closest( '.roova-section' ) );
			if ( ! slider ) {
				return;
			}
			var step = parseInt( button.dataset.roovaSlide, 10 ) * 302;
			slider.scrollBy( { left: step, behavior: 'smooth' } );
		} );
	} );

	/* ------------------------------------------------------------ gallery */

	( function initGallery() {
		var gallery = qs( '[data-roova-gallery]' );
		if ( ! gallery ) {
			return;
		}

		var slides = qsa( '[data-roova-gallery-slide]', gallery );
		var thumbs = qsa( '[data-roova-gallery-go]', gallery );
		var index = 0;

		function show( next ) {
			index = ( next + slides.length ) % slides.length;

			slides.forEach( function ( slide, i ) {
				slide.classList.toggle( 'is-active', i === index );
			} );

			thumbs.forEach( function ( thumb, i ) {
				var active = i === index;
				thumb.classList.toggle( 'is-active', active );
				thumb.setAttribute( 'aria-selected', active ? 'true' : 'false' );
			} );

			// Keep the current thumbnail in sight when stepping with the arrows.
			if ( thumbs[ index ] && thumbs[ index ].scrollIntoView ) {
				thumbs[ index ].scrollIntoView( { block: 'nearest', inline: 'nearest', behavior: 'smooth' } );
			}

			var counter = qs( '[data-roova-gallery-index]', gallery );
			if ( counter ) {
				counter.textContent = index + 1;
			}
		}

		qsa( '[data-roova-gallery-step]', gallery ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				show( index + parseInt( button.dataset.roovaGalleryStep, 10 ) );
			} );
		} );

		thumbs.forEach( function ( thumb ) {
			thumb.addEventListener( 'click', function () {
				show( parseInt( thumb.dataset.roovaGalleryGo, 10 ) );
			} );
		} );
	}() );

	/* -------------------------------------------------------------- clamp */

	qsa( '[data-roova-clamp-toggle]' ).forEach( function ( button ) {
		var target = qs( '[data-roova-clamp]', button.parentNode );
		if ( ! target ) {
			return;
		}

		button.addEventListener( 'click', function () {
			var open = target.classList.toggle( 'is-open' );
			button.textContent = open ? button.dataset.less : button.dataset.more;
		} );
	} );

	/* -------------------------------------------------------------- modal */

	var modal = qs( '[data-roova-modal]' );

	function closeModal() {
		if ( modal ) {
			modal.hidden = true;
		}
	}

	if ( modal ) {
		modal.addEventListener( 'click', function ( event ) {
			if ( event.target === modal || event.target.closest( '[data-roova-modal-close]' ) ) {
				closeModal();
			}
		} );
	}

	qsa( '[data-roova-room-details]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			if ( ! modal ) {
				return;
			}

			post( 'roova_room_details', { room_id: button.dataset.roovaRoomDetails } ).then( function ( response ) {
				if ( ! response || ! response.success ) {
					return;
				}

				var room = response.data;

				qs( '[data-roova-modal-title]', modal ).textContent = room.name;

				var meta = qs( '[data-roova-modal-meta]', modal );
				meta.innerHTML = '';
				[ room.size, room.beds, room.max_adults ].forEach( function ( value ) {
					if ( ! value ) {
						return;
					}
					var span = document.createElement( 'span' );
					span.textContent = value;
					meta.appendChild( span );
				} );

				qs( '[data-roova-modal-desc]', modal ).innerHTML = room.description || '';

				var gallery = qs( '[data-roova-modal-gallery]', modal );
				gallery.innerHTML = '';
				if ( room.images && room.images.length ) {
					// The photo is shown whole (object-fit: contain); the blurred
					// copy behind it fills whatever the shape leaves over.
					var blur = document.createElement( 'span' );
					blur.className = 'roova-gallery__blur';
					blur.setAttribute( 'aria-hidden', 'true' );
					gallery.appendChild( blur );

					var image = document.createElement( 'img' );
					gallery.appendChild( image );

					var position = 0;

					var showImage = function ( index ) {
						position = ( index + room.images.length ) % room.images.length;
						image.src = room.images[ position ].src;
						image.alt = room.images[ position ].alt || room.name;
						blur.style.backgroundImage = 'url("' + room.images[ position ].src + '")';
					};

					showImage( 0 );

					if ( room.images.length > 1 ) {
						[ -1, 1 ].forEach( function ( step ) {
							var arrow = document.createElement( 'button' );
							arrow.type = 'button';
							arrow.className = 'roova-gallery__arrow ' + ( step < 0 ? 'roova-gallery__arrow--prev' : 'roova-gallery__arrow--next' );
							arrow.textContent = step < 0 ? '‹' : '›';
							arrow.addEventListener( 'click', function () {
								showImage( position + step );
							} );
							gallery.appendChild( arrow );
						} );
					}
				}

				var amenities = qs( '[data-roova-modal-amenities]', modal );
				amenities.innerHTML = '';
				( room.amenities || [] ).forEach( function ( amenity ) {
					var span = document.createElement( 'span' );
					span.innerHTML = amenity.icon;
					var label = document.createElement( 'span' );
					label.textContent = amenity.label;
					span.appendChild( label );
					amenities.appendChild( span );
				} );

				modal.hidden = false;
			} ).catch( function () {} );
		} );
	} );

	/* ------------------------------------------------------------- navbar */

	qsa( '[data-roova-nav-toggle]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var inner = button.closest( '.roova-nav__inner' );
			var open = inner.classList.toggle( 'is-open' );
			button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );
	} );

	/* --------------------------------------------------------------- maps */

	( function initMap() {
		var canvas = qs( '[data-roova-map]' );
		if ( ! canvas || ! data.mapsKey ) {
			return;
		}

		window.roovaInitMap = function () {
			var position = {
				lat: parseFloat( canvas.dataset.lat ),
				lng: parseFloat( canvas.dataset.lng )
			};

			var map = new window.google.maps.Map( canvas, {
				center: position,
				zoom: parseInt( canvas.dataset.zoom, 10 ) || 15,
				mapTypeControl: false,
				streetViewControl: false,
				fullscreenControl: false
			} );

			new window.google.maps.Marker( {
				position: position,
				map: map,
				title: canvas.dataset.title || ''
			} );
		};

		var script = document.createElement( 'script' );
		script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent( data.mapsKey ) + '&callback=roovaInitMap';
		script.async = true;
		document.head.appendChild( script );
	}() );
}() );
