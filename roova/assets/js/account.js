/**
 * My account: tabs, the profile form, the password panel and the review box.
 *
 * Vanilla, like theme.js — nothing on this page is WooCommerce's, so there are
 * no jQuery events to listen for. Everything here only adds to a page that
 * already works: every tab is reachable through its own `?tab=` link, and every
 * rule this script checks is checked again in inc/account.php, word for word,
 * so a member never reads one message in the browser and a different one after
 * the round trip.
 *
 * The saved-stays heart is not here — it lives in theme.js, because the same
 * button appears on hotel cards all over the site. This file only listens for
 * the event it fires, so a stay unsaved from the Likes tab leaves the grid.
 */
( function () {
	'use strict';

	var data = window.roovaAccount || {};
	var i18n = data.i18n || {};

	function qs( selector, scope ) {
		return ( scope || document ).querySelector( selector );
	}

	function qsa( selector, scope ) {
		return Array.prototype.slice.call( ( scope || document ).querySelectorAll( selector ) );
	}

	function text( key, fallback ) {
		return i18n[ key ] || fallback;
	}

	/* --------------------------------------------------------------- Tabs */

	( function initTabs() {
		var tabs = qsa( '[data-roova-tab]' );
		var panels = qsa( '[data-roova-panel]' );

		if ( ! tabs.length || ! panels.length ) {
			return;
		}

		function show( key, push ) {
			tabs.forEach( function ( tab ) {
				var active = tab.getAttribute( 'data-roova-tab' ) === key;
				tab.classList.toggle( 'is-active', active );
				tab.setAttribute( 'aria-current', active ? 'page' : 'false' );
			} );

			panels.forEach( function ( panel ) {
				panel.hidden = panel.getAttribute( 'data-roova-panel' ) !== key;
			} );

			// Switching tabs clears the "saved" confirmation — it belongs to the
			// form that was just submitted, not to the page.
			clearSaved();

			if ( push && window.history && window.history.replaceState ) {
				var tab = tabs.filter( function ( item ) {
					return item.getAttribute( 'data-roova-tab' ) === key;
				} )[ 0 ];

				if ( tab ) {
					window.history.replaceState( {}, '', tab.href );
				}
			}
		}

		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function ( event ) {
				// A modified click is the visitor asking for a new tab or window.
				if ( event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0 ) {
					return;
				}

				event.preventDefault();
				show( tab.getAttribute( 'data-roova-tab' ), true );
			} );
		} );
	}() );

	function clearSaved() {
		var flag = qs( '[data-roova-saved]' );
		if ( flag ) {
			flag.hidden = true;
		}

		var button = qs( '[data-roova-save]' );
		if ( button ) {
			button.classList.remove( 'is-saved' );

			var label = qs( '[data-roova-save-label]', button );
			if ( label && button.dataset.saveLabel ) {
				label.textContent = button.dataset.saveLabel;
			}
		}
	}

	/* ------------------------------------------------------------- Errors */

	var PHONE = /^[+\d][\d\s-]{7,}$/;

	function rowOf( input ) {
		return input.closest( '[data-roova-field]' );
	}

	function showError( input, message ) {
		var row = rowOf( input );
		if ( ! row ) {
			return;
		}

		var slot = row.querySelector( '[data-roova-error]' );
		var box = row.querySelector( '.roova-field' );

		if ( slot ) {
			slot.textContent = message;
			slot.hidden = ! message;
		}

		if ( box ) {
			box.classList.toggle( 'is-invalid', !! message );
		}

		if ( message ) {
			input.setAttribute( 'aria-invalid', 'true' );
		} else {
			input.removeAttribute( 'aria-invalid' );
		}
	}

	function strongEnough( password ) {
		return password.length >= 8 && /[A-Za-z]/.test( password ) && /\d/.test( password );
	}

	function valueOf( form, name ) {
		var field = form.elements[ name ];
		return field ? field.value : '';
	}

	/* ------------------------------------------------- Password extras */

	/**
	 * The show/hide buttons beside the password fields.
	 *
	 * @param {HTMLElement} scope Where to look.
	 */
	function wireEyes( scope ) {
		qsa( '[data-roova-eye]', scope ).forEach( function ( button ) {
			var group = button.getAttribute( 'data-roova-eye' );
			var inputs = qsa( '[data-roova-password="' + group + '"]', scope );

			button.addEventListener( 'click', function () {
				var showing = button.classList.toggle( 'is-on' );

				inputs.forEach( function ( input ) {
					input.type = showing ? 'text' : 'password';
				} );

				button.setAttribute( 'aria-pressed', showing ? 'true' : 'false' );
				button.setAttribute(
					'aria-label',
					showing ? text( 'hidePassword', 'Hide password' ) : text( 'showPassword', 'Show password' )
				);
			} );
		} );
	}

	/**
	 * The tick beside "Confirm new password".
	 *
	 * @param {HTMLFormElement} form The profile form.
	 */
	function refreshMatch( form ) {
		qsa( '[data-roova-match]', form ).forEach( function ( tick ) {
			var row = tick.closest( '[data-roova-field]' );
			var input = row ? row.querySelector( '.roova-field__input' ) : null;
			var matched = !! input && !! input.value && input.value === valueOf( form, 'roova_new_password' );

			tick.classList.toggle( 'is-match', matched );
		} );
	}

	/* ------------------------------------------------------ Profile form */

	( function initProfile() {
		var form = qs( '[data-roova-profile-form]' );
		if ( ! form ) {
			return;
		}

		var button = qs( '[data-roova-save]', form );
		var label = button ? qs( '[data-roova-save-label]', button ) : null;

		if ( button && label ) {
			button.dataset.saveLabel = label.textContent;
		}

		// The page came back from a save: show the button in its saved state
		// until something on the form is touched again.
		var flag = qs( '[data-roova-saved]' );
		if ( flag && ! flag.hidden && button && label ) {
			button.classList.add( 'is-saved' );
			label.textContent = text( 'saved', 'Saved' );
		}

		wireEyes( form );

		var toggle = qs( '[data-roova-password-toggle]', form );
		var panel = qs( '[data-roova-password-panel]', form );

		if ( toggle && panel ) {
			toggle.addEventListener( 'click', function () {
				var open = panel.hidden;

				panel.hidden = ! open;
				toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
				toggle.textContent = open
					? toggle.getAttribute( 'data-close-label' )
					: toggle.getAttribute( 'data-open-label' );

				if ( open ) {
					var first = qs( '.roova-field__input', panel );
					if ( first ) {
						first.focus();
					}
				} else {
					// Closing the panel abandons the change, so nothing is left
					// half-typed to be submitted by accident.
					qsa( '.roova-field__input', panel ).forEach( function ( input ) {
						input.value = '';
						showError( input, '' );
					} );
					refreshMatch( form );
				}
			} );
		}

		/**
		 * The message for one field, or '' when it is fine.
		 *
		 * Mirrors roova_account_save_profile() exactly.
		 *
		 * @param {HTMLInputElement} input The field.
		 * @return {string} Error message.
		 */
		function validate( input ) {
			var value = input.value.trim();
			var name = input.name;

			if ( name === 'roova_first_name' ) {
				return value.length >= 2 ? '' : text( 'firstName', 'Enter your first name.' );
			}

			if ( name === 'roova_last_name' ) {
				return value.length >= 2 ? '' : text( 'lastName', 'Enter your last name.' );
			}

			// A phone number is optional, but a typed one has to be a number.
			if ( name === 'roova_phone' ) {
				if ( ! value ) {
					return '';
				}
				return PHONE.test( value ) ? '' : text( 'phone', 'Enter a valid phone number.' );
			}

			// The password fields are only checked once the panel is in use.
			if ( ! panel || panel.hidden || ! passwordTouched() ) {
				return '';
			}

			if ( name === 'roova_current_password' ) {
				return input.value ? '' : text( 'currentPassword', 'Enter your current password.' );
			}

			if ( name === 'roova_new_password' ) {
				return strongEnough( input.value )
					? ''
					: text( 'newPassword', 'New password needs 8+ characters with a letter and a number.' );
			}

			if ( name === 'roova_confirm_password' ) {
				return input.value && input.value === valueOf( form, 'roova_new_password' )
					? ''
					: text( 'confirmPassword', 'New passwords don\'t match.' );
			}

			return '';
		}

		function passwordTouched() {
			return [ 'roova_current_password', 'roova_new_password', 'roova_confirm_password' ].some( function ( name ) {
				return valueOf( form, name ) !== '';
			} );
		}

		var fields = qsa( '.roova-field__input', form ).filter( function ( input ) {
			return input.name && input.name.indexOf( 'roova_' ) === 0;
		} );

		var submitted = false;

		fields.forEach( function ( input ) {
			input.addEventListener( 'input', function () {
				clearSaved();
				refreshMatch( form );

				if ( submitted ) {
					fields.forEach( function ( other ) {
						showError( other, validate( other ) );
					} );
				} else if ( input.getAttribute( 'aria-invalid' ) ) {
					showError( input, validate( input ) );
				}
			} );

			input.addEventListener( 'blur', function () {
				if ( input.value.trim() ) {
					showError( input, validate( input ) );
				}
			} );
		} );

		form.addEventListener( 'submit', function ( event ) {
			submitted = true;

			var firstBad = null;

			fields.forEach( function ( input ) {
				var message = validate( input );
				showError( input, message );

				if ( message && ! firstBad ) {
					firstBad = input;
				}
			} );

			if ( firstBad ) {
				event.preventDefault();
				firstBad.focus();
			}
		} );
	}() );

	/* ------------------------------------------------------ Review forms */

	qsa( '[data-roova-review-toggle]' ).forEach( function ( button ) {
		var form = document.getElementById( button.getAttribute( 'data-roova-review-toggle' ) );
		if ( ! form ) {
			return;
		}

		button.addEventListener( 'click', function () {
			var open = form.hidden;

			form.hidden = ! open;
			button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );

			if ( open ) {
				var first = qs( 'input[name="rating"]', form );
				if ( first ) {
					first.focus();
				}
			}
		} );
	} );

	qsa( '[data-roova-review-form]' ).forEach( function ( form ) {
		var body = qs( '[data-roova-rule="review-body"]', form );

		form.addEventListener( 'submit', function ( event ) {
			var errors = qsa( '[data-roova-error]', form );
			var rating = form.querySelector( 'input[name="rating"]:checked' );
			var message = '';

			errors.forEach( function ( slot ) {
				slot.textContent = '';
				slot.hidden = true;
			} );

			if ( ! rating ) {
				message = text( 'rating', 'Choose a rating from 1 to 5 stars.' );

				if ( errors[ 0 ] ) {
					errors[ 0 ].textContent = message;
					errors[ 0 ].hidden = false;
				}
			}

			// Ten characters, the same floor inc/reviews.php holds the review to.
			if ( body && body.value.trim().length < 10 ) {
				var slot = errors[ errors.length - 1 ];
				if ( slot ) {
					slot.textContent = text( 'reviewBody', 'Tell other guests a little more — at least a sentence.' );
					slot.hidden = false;
				}
				message = message || 'body';
			}

			if ( message ) {
				event.preventDefault();
			}
		} );
	} );

	/* ------------------------------------------------------------- Likes */

	document.addEventListener( 'roova:like', function ( event ) {
		var detail = event.detail || {};
		var grid = qs( '[data-roova-likes]' );

		if ( ! grid || detail.liked ) {
			return;
		}

		var card = qs( '[data-roova-like-card="' + detail.hotelId + '"]', grid );
		if ( card ) {
			card.classList.add( 'is-leaving' );
			window.setTimeout( function () {
				card.remove();

				if ( ! grid.children.length ) {
					grid.hidden = true;

					var empty = qs( '[data-roova-likes-empty]' );
					if ( empty ) {
						empty.hidden = false;
					}
				}
			}, 250 );
		}

		var pill = qs( '[data-roova-tab-count="likes"]' );
		if ( pill && typeof detail.count === 'number' ) {
			pill.textContent = String( detail.count );
		}
	} );
}() );
