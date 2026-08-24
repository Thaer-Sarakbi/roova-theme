/**
 * Roova front-end behaviour: search card, calendar, scroll reveal, the coverage
 * map, galleries and the room modal.
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

	/* ------------------------------------------------------------- reveal */

	/*
	 * Sections fade and rise as they come into view. Anchor jumps and scroll
	 * restoration can land past an element without the observer ever firing,
	 * so anything already above the fold counts as revealed, a scroll/resize
	 * sweep catches the rest, and a timeout is the last line of defence: a
	 * section stuck at opacity 0 is worse than one that never animated.
	 */
	( function initReveal() {
		var targets = qsa( '[data-roova-reveal]' );
		if ( ! targets.length ) {
			return;
		}

		var reduced = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

		function reveal( element ) {
			if ( element.dataset.roovaRevealDone ) {
				return;
			}
			element.dataset.roovaRevealDone = '1';

			if ( element.hasAttribute( 'data-roova-stagger' ) ) {
				Array.prototype.forEach.call( element.children, function ( child, index ) {
					child.style.transition =
						'opacity .7s cubic-bezier(.22,.8,.28,1) ' + ( index * 90 ) + 'ms, ' +
						'transform .7s cubic-bezier(.22,.8,.28,1) ' + ( index * 90 ) + 'ms';
				} );
			}

			element.classList.add( 'is-revealed' );
		}

		if ( reduced || ! window.IntersectionObserver ) {
			targets.forEach( reveal );
			return;
		}

		var observer = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				// Already scrolled past counts as visible.
				if ( ! entry.isIntersecting && entry.boundingClientRect.bottom > 0 ) {
					return;
				}
				reveal( entry.target );
				observer.unobserve( entry.target );
			} );
		}, { rootMargin: '0px 0px -12% 0px', threshold: 0.08 } );

		targets.forEach( function ( element ) {
			observer.observe( element );
		} );

		function sweep() {
			targets.forEach( function ( element ) {
				if ( element.dataset.roovaRevealDone ) {
					return;
				}
				var box = element.getBoundingClientRect();
				if ( box.bottom < 0 || box.top < window.innerHeight * 0.92 ) {
					reveal( element );
					observer.unobserve( element );
				}
			} );
		}

		sweep();
		window.addEventListener( 'scroll', sweep, { passive: true } );
		window.addEventListener( 'resize', sweep );
		window.setTimeout( function () {
			targets.forEach( reveal );
		}, 3000 );
	}() );

	/* -------------------------------------------------------- coverage map */

	/*
	 * Real Natural Earth geometry, drawn with d3-geo. The town list beside it
	 * is server-rendered, so if d3 or the atlas never arrives the section is
	 * still a usable list of links — only the drawing is missing.
	 */
	( function initAtlas() {
		var root = qs( '[data-roova-atlas]' );
		if ( ! root || ! window.d3 || ! window.topojson || ! data.atlasUrl ) {
			return;
		}

		var canvas = qs( '[data-roova-atlas-canvas]', root );
		var views = data.atlasViews || {};
		var places = [];

		try {
			places = JSON.parse( root.getAttribute( 'data-places' ) || '[]' );
		} catch ( error ) {
			return;
		}

		if ( ! canvas || ! places.length || ! views.country ) {
			return;
		}

		var reduced = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		var rows = qsa( '[data-roova-atlas-place]', root );
		var viewButtons = qsa( '[data-roova-atlas-view]', root );
		var viewsBar = qs( '[data-roova-atlas-views]', root );

		var svg = window.d3.select( canvas ).append( 'svg' );
		var land = svg.append( 'g' );
		var pinLayer = svg.append( 'g' );
		var projection = window.d3.geoMercator();
		var path = window.d3.geoPath( projection );
		var current = 'country';
		var width = 0;
		var height = 0;

		function measure() {
			var box = canvas.getBoundingClientRect();
			width = box.width;
			height = box.height;
			svg.attr( 'viewBox', [ 0, 0, width, height ] );
		}

		function highlight( name ) {
			pinLayer.selectAll( '.roova-pin' )
				.classed( 'is-active', function ( place ) {
					return place.name === name;
				} )
				.select( 'circle.roova-pin__dot' )
				.attr( 'r', function ( place ) {
					return place.name === name ? 8 : 5;
				} );

			rows.forEach( function ( row ) {
				row.classList.toggle( 'is-active', row.dataset.roovaAtlasPlace === name );
			} );
		}

		function render( instant ) {
			var box = views[ current ] || views.country;
			if ( ! width || ! height ) {
				return;
			}

			projection.fitExtent(
				[ [ 46, 46 ], [ Math.max( 47, width - 46 ), Math.max( 47, height - 46 ) ] ],
				{ type: 'MultiPoint', coordinates: box }
			);

			function place( pin ) {
				var point = projection( [ pin.lon, pin.lat ] );
				return 'translate(' + point[ 0 ] + ',' + point[ 1 ] + ')';
			}

			if ( instant || reduced ) {
				land.selectAll( 'path' ).attr( 'd', path );
				pinLayer.selectAll( '.roova-pin' ).attr( 'transform', place );
			} else {
				land.selectAll( 'path' ).transition().duration( 850 )
					.ease( window.d3.easeCubicInOut ).attr( 'd', path );
				pinLayer.selectAll( '.roova-pin' ).transition().duration( 850 )
					.ease( window.d3.easeCubicInOut ).attr( 'transform', place );
			}

			// Town names would collide at country zoom.
			pinLayer.selectAll( '.roova-pin__label' )
				.style( 'display', 'country' === current ? 'none' : null );
		}

		function setView( name ) {
			if ( ! views[ name ] ) {
				return;
			}
			current = name;
			viewButtons.forEach( function ( button ) {
				button.classList.toggle( 'is-active', button.dataset.roovaAtlasView === name );
			} );
			render( false );
		}

		viewButtons.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				setView( button.dataset.roovaAtlasView );
			} );
		} );

		rows.forEach( function ( row ) {
			row.addEventListener( 'mouseenter', function () {
				highlight( row.dataset.roovaAtlasPlace );
			} );
			row.addEventListener( 'mouseleave', function () {
				highlight( null );
			} );
			row.addEventListener( 'focus', function () {
				highlight( row.dataset.roovaAtlasPlace );
			} );
			row.addEventListener( 'blur', function () {
				highlight( null );
			} );
		} );

		window.d3.json( data.atlasUrl ).then( function ( topo ) {
			var countries = window.topojson.feature( topo, topo.objects.countries ).features;
			var home = data.atlasHome || 'Malaysia';

			measure();

			land.selectAll( 'path.roova-atlas__other' )
				.data( countries.filter( function ( feature ) {
					return feature.properties.name !== home;
				} ) )
				.join( 'path' )
				.attr( 'class', 'roova-atlas__other' );

			land.selectAll( 'path.roova-atlas__home' )
				.data( countries.filter( function ( feature ) {
					return feature.properties.name === home;
				} ) )
				.join( 'path' )
				.attr( 'class', 'roova-atlas__home' );

			var pins = pinLayer.selectAll( 'a.roova-pin' ).data( places ).join( 'a' )
				.attr( 'class', 'roova-pin' )
				.attr( 'href', function ( pin ) {
					return pin.url;
				} )
				.on( 'mouseenter', function ( event, pin ) {
					highlight( pin.name );
				} )
				.on( 'mouseleave', function () {
					highlight( null );
				} );

			pins.append( 'circle' ).attr( 'class', 'roova-pin__halo' ).attr( 'r', 6 );
			pins.append( 'circle' ).attr( 'class', 'roova-pin__dot' ).attr( 'r', 5 );
			pins.append( 'title' ).text( function ( pin ) {
				return pin.name;
			} );
			pins.append( 'text' )
				.attr( 'class', 'roova-pin__label' )
				.attr( 'dy', -11 )
				.attr( 'text-anchor', 'middle' )
				.text( function ( pin ) {
					return pin.name;
				} );

			if ( viewsBar && views.region ) {
				viewsBar.hidden = false;
			}

			render( true );

			var resizing = null;
			window.addEventListener( 'resize', function () {
				window.clearTimeout( resizing );
				resizing = window.setTimeout( function () {
					measure();
					render( true );
				}, 150 );
			} );
		} ).catch( function () {} );
	}() );

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
