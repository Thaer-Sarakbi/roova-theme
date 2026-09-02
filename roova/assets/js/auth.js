/**
 * Sign in and sign up.
 *
 * Vanilla, like theme.js — nothing on these pages is WooCommerce's, so there
 * are no jQuery events to listen for.
 *
 * Everything here is an enhancement: the forms post to themselves and
 * inc/auth.php checks every rule again, so a page with the script blocked still
 * creates accounts and still signs people in. The rules below are deliberately
 * the same ones, word for word, so a guest never reads one message in the
 * browser and a different one after the round trip.
 *
 * Errors stay hidden until the first submit, then update on every keystroke —
 * telling someone their email is invalid while they are still typing it is
 * noise.
 */
( function () {
	'use strict';

	var data = window.roovaAuth || {};
	var i18n = data.i18n || {};

	function qsa( selector, scope ) {
		return Array.prototype.slice.call( ( scope || document ).querySelectorAll( selector ) );
	}

	function text( key, fallback ) {
		return i18n[ key ] || fallback;
	}

	/* ------------------------------------------------------------- Rules */

	var EMAIL = /^[^@\s]+@[^@\s.]+\.[^@\s]+$/;
	var PHONE = /^[+\d][\d\s-]{7,}$/;

	/**
	 * The message for a field, or '' when it is fine.
	 *
	 * @param {HTMLInputElement} input The field.
	 * @param {HTMLFormElement}  form  Its form.
	 * @return {string} Error message.
	 */
	function validate( input, form ) {
		var rule = input.getAttribute( 'data-roova-rule' );
		var value = input.type === 'checkbox' ? input.checked : input.value.trim();

		switch ( rule ) {
			case 'name':
				return value.length >= 2 ? '' : nameMessage( input );

			case 'email':
				return EMAIL.test( value ) ? '' : text( 'email', 'Enter a valid email address.' );

			case 'phone':
				return PHONE.test( value ) ? '' : text( 'phone', 'Enter a valid phone number.' );

			case 'password':
				return strongEnough( input.value ) ? '' : text( 'password', 'Use at least 8 characters, with a letter and a number.' );

			case 'required-password':
				return input.value ? '' : text( 'passwordEmpty', 'Enter your password.' );

			case 'confirm-password':
				return input.value && input.value === valueOf( form, 'roova_password' )
					? ''
					: text( 'passwordConfirm', 'Passwords do not match.' );

			case 'terms':
				return value ? '' : text( 'terms', 'Please accept the terms to create your account.' );
		}

		return '';
	}

	function nameMessage( input ) {
		return input.name === 'roova_last_name'
			? text( 'lastName', 'Enter your last name.' )
			: text( 'firstName', 'Enter your first name.' );
	}

	function strongEnough( password ) {
		return password.length >= 8 && /[A-Za-z]/.test( password ) && /\d/.test( password );
	}

	function valueOf( form, name ) {
		var field = form.elements[ name ];
		return field ? field.value : '';
	}

	/* ----------------------------------------------------------- Errors */

	function rowOf( input ) {
		return input.closest( '[data-roova-field]' );
	}

	/**
	 * Show or clear one field's message.
	 *
	 * @param {HTMLInputElement} input   The field.
	 * @param {string}           message '' to clear it.
	 */
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

	/* --------------------------------------------------- Password extras */

	/**
	 * The shared show/hide button: one flag, both password fields.
	 *
	 * @param {HTMLFormElement} form The form.
	 */
	function wireEyes( form ) {
		qsa( '[data-roova-eye]', form ).forEach( function ( button ) {
			var group = button.getAttribute( 'data-roova-eye' );
			var inputs = qsa( '[data-roova-password="' + group + '"]', form );

			button.addEventListener( 'click', function ( event ) {
				// The button sits inside the label, which would refocus the input.
				event.preventDefault();

				var show = button.getAttribute( 'aria-pressed' ) !== 'true';

				inputs.forEach( function ( input ) {
					input.type = show ? 'text' : 'password';
				} );

				button.setAttribute( 'aria-pressed', show ? 'true' : 'false' );
				button.classList.toggle( 'is-on', show );
				button.setAttribute(
					'aria-label',
					show ? text( 'hidePassword', 'Hide password' ) : text( 'showPassword', 'Show password' )
				);
			} );
		} );
	}

	/**
	 * How many of the four things this password does.
	 *
	 * Length, mixed case, a digit, and either a symbol or real length. Clamped
	 * to 1 while there is anything at all typed, so the meter never reads empty
	 * over a password someone is part-way through.
	 *
	 * @param {string} password The password.
	 * @return {number} 0-4.
	 */
	function strengthOf( password ) {
		if ( ! password ) {
			return 0;
		}

		var level = 0;
		if ( password.length >= 8 ) {
			level++;
		}
		if ( /[a-z]/.test( password ) && /[A-Z]/.test( password ) ) {
			level++;
		}
		if ( /\d/.test( password ) ) {
			level++;
		}
		if ( /[^A-Za-z0-9]/.test( password ) || password.length >= 14 ) {
			level++;
		}

		return Math.max( 1, level );
	}

	function strengthLabel( level ) {
		if ( level >= 4 ) {
			return text( 'strong', 'Strong' );
		}
		if ( level === 3 ) {
			return text( 'good', 'Good' );
		}
		if ( level === 2 ) {
			return text( 'fair', 'Fair' );
		}
		if ( level === 1 ) {
			return text( 'weak', 'Weak' );
		}

		return text( 'strength', 'Password strength' );
	}

	/**
	 * Keep the meter and the confirm ticks in step with what is typed.
	 *
	 * @param {HTMLFormElement} form The form.
	 */
	function refreshHints( form ) {
		var meter = form.querySelector( '[data-roova-strength]' );
		if ( meter ) {
			var level = strengthOf( valueOf( form, 'roova_password' ) );
			meter.setAttribute( 'data-level', String( level ) );

			var label = meter.querySelector( '[data-roova-strength-label]' );
			if ( label ) {
				label.textContent = strengthLabel( level );
			}
		}

		qsa( '[data-roova-match]', form ).forEach( function ( tick ) {
			var input = tick.closest( '[data-roova-field]' ).querySelector( '.roova-field__input' );

			// The only confirm field left is the password one, and a password has
			// to match exactly — case and all.
			var matched = !! input && !! input.value && input.value === valueOf( form, 'roova_password' );

			tick.classList.toggle( 'is-match', matched );
		} );
	}

	/* ------------------------------------------------------------- Wiring */

	qsa( '[data-roova-auth-form]' ).forEach( function ( form ) {
		var fields = qsa( '[data-roova-rule]', form );
		var submitted = false;

		wireEyes( form );
		refreshHints( form );

		function check( input ) {
			if ( ! submitted ) {
				return true;
			}

			var message = validate( input, form );
			showError( input, message );

			return ! message;
		}

		fields.forEach( function ( input ) {
			var event = input.type === 'checkbox' ? 'change' : 'input';

			input.addEventListener( event, function () {
				refreshHints( form );
				check( input );

				/*
				 * A confirm field is only wrong relative to another one, so
				 * editing the first has to re-check the second.
				 */
				fields.forEach( function ( other ) {
					var rule = other.getAttribute( 'data-roova-rule' );
					if ( other !== input && rule && rule.indexOf( 'confirm' ) === 0 ) {
						check( other );
					}
				} );
			} );

			input.addEventListener( 'blur', function () {
				check( input );
			} );
		} );

		form.addEventListener( 'submit', function ( event ) {
			submitted = true;

			var firstBad = null;

			fields.forEach( function ( input ) {
				var message = validate( input, form );
				showError( input, message );

				if ( message && ! firstBad ) {
					firstBad = input;
				}
			} );

			if ( firstBad ) {
				event.preventDefault();
				firstBad.focus();
				return;
			}

			// Nothing to guard against a double post once it is on its way.
			var button = form.querySelector( '.roova-auth__submit' );
			if ( button ) {
				button.disabled = true;
			}
		} );
	} );
} )();
