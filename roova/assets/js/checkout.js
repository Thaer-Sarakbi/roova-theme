/**
 * Checkout behaviour.
 *
 * jQuery, unlike the rest of the theme's front end: everything here hangs off
 * WooCommerce's own checkout events (`checkout_place_order`, `updated_checkout`,
 * `payment_method_selected`), and those are jQuery custom events — they never
 * reach a native listener.
 *
 * What it does NOT do: submit the order. That is WooCommerce's job, through the
 * normal `form.checkout` post. This only refuses to let a submit start while the
 * guest's details are incomplete, so they see the problem next to the field
 * instead of as a notice at the top of the page.
 */
( function ( $ ) {
	'use strict';

	var strings = window.roovaCheckout || {};
	var i18n = strings.i18n || {};
	var attempted = false;

	/* ------------------------------------------------------------ Validation */

	var rules = {
		billing_full_name: {
			test: function ( value ) {
				return value.trim().length > 1;
			},
			message: i18n.name || 'Please enter the guest’s full name.'
		},
		billing_phone: {
			test: function ( value ) {
				return /^[+\d][\d\s-]{7,}$/.test( value.trim() );
			},
			message: i18n.phone || 'Enter a valid phone number.'
		},
		billing_email: {
			test: function ( value ) {
				return /^[^@\s]+@[^@\s.]+\.[^@\s]+$/.test( value.trim() );
			},
			message: i18n.email || 'Enter a valid email address.'
		}
	};

	/**
	 * Check one field and show or clear its message.
	 *
	 * @param {string} key Field key.
	 * @return {boolean} Whether the field is valid.
	 */
	function checkField( key ) {
		var $row = $( '[data-roova-field="' + key + '"]' );
		var $input = $( '#' + key );

		if ( ! $row.length || ! $input.length ) {
			return true; // The field is not on this checkout.
		}

		var valid = rules[ key ].test( String( $input.val() || '' ) );

		$row.toggleClass( 'roova-field--invalid', ! valid );
		$row.find( '[data-roova-error]' ).text( valid ? '' : rules[ key ].message );

		return valid;
	}

	/**
	 * The terms box, which lives in its own block.
	 *
	 * @return {boolean} Whether it is ticked.
	 */
	function checkTerms() {
		var $terms = $( '#terms' );
		var $block = $( '.roova-terms' );

		if ( ! $terms.length ) {
			return true;
		}

		var valid = $terms.is( ':checked' );

		$block.toggleClass( 'roova-terms--invalid', ! valid );
		$block.find( '[data-roova-error]' ).text( valid ? '' : ( i18n.terms || 'Please accept the booking terms to continue.' ) );

		return valid;
	}

	/**
	 * Validate everything, and scroll to the first thing that is wrong.
	 *
	 * @return {boolean} Whether the form may be submitted.
	 */
	function validate() {
		var ok = true;

		$.each( rules, function ( key ) {
			ok = checkField( key ) && ok;
		} );

		ok = checkTerms() && ok;

		if ( ! ok ) {
			var $first = $( '.roova-field--invalid, .roova-terms--invalid' ).first();
			if ( $first.length ) {
				$( 'html, body' ).animate( { scrollTop: $first.offset().top - 90 }, 320 );
				$first.find( 'input, textarea' ).first().trigger( 'focus' );
			}
		}

		return ok;
	}

	/* --------------------------------------------------------- Payment cards */

	/**
	 * Mirror the checked radio onto its card, so the whole card can show it.
	 */
	function markChosenPayment() {
		$( '.roova-pay__item' ).each( function () {
			var $item = $( this );
			$item.toggleClass( 'roova-pay__item--on', $item.find( 'input[name="payment_method"]' ).is( ':checked' ) );
		} );
	}

	/* ------------------------------------------------- Removing a room */

	/**
	 * Ask the server to change the cart, then let WooCommerce redraw the summary.
	 *
	 * @param {string}   action Ajax action.
	 * @param {string}   key    Cart item key.
	 * @param {Function} done   Called with the response data on success.
	 */
	function cartRequest( action, key, done ) {
		var $summary = $( '.roova-summary__card' );
		var cleared = false;

		// The summary is redrawn by WooCommerce's own refresh, so the veil lifts
		// on `updated_checkout` — with a timeout behind it, because a summary
		// left greyed out is worse than one that flickers.
		var clear = function () {
			if ( cleared ) {
				return;
			}
			cleared = true;
			$summary.css( 'opacity', '' );
		};

		$summary.css( 'opacity', .55 );
		$( document.body ).one( 'updated_checkout', clear );
		window.setTimeout( clear, 8000 );

		$.post( strings.ajaxUrl, {
			action: action,
			nonce: strings.nonce,
			cart_item_key: key
		} ).done( function ( res ) {
			if ( ! res || ! res.success ) {
				clear();
				showUndo( ( res && res.data && res.data.message ) || i18n.removeFailed, null );
				return;
			}

			done( res.data || {} );
		} ).fail( function () {
			clear();
			showUndo( i18n.removeFailed, null );
		} );
	}

	/**
	 * Put a message above the totals, with an Undo link when there is one.
	 *
	 * @param {string}      message What happened.
	 * @param {string|null} key     Cart item key to restore, or null for no link.
	 */
	function showUndo( message, key ) {
		var $undo = $( '[data-roova-undo]' );
		if ( ! $undo.length ) {
			return;
		}

		$undo.empty().append( $( '<span/>' ).text( message ) );

		if ( key ) {
			$undo.append( ' ' ).append(
				$( '<button/>', {
					type: 'button',
					'class': 'roova-summary__undo-btn',
					text: i18n.undo || 'Undo',
					'data-roova-restore': key
				} )
			);
		}

		$undo.prop( 'hidden', false );
	}

	/* ----------------------------------------------------------- Rate hold */

	/**
	 * Count the held rooms down to the minute their hold runs out.
	 */
	function startCountdown() {
		var $hold = $( '[data-roova-hold]' );
		if ( ! $hold.length ) {
			return;
		}

		var left = parseInt( $hold.attr( 'data-roova-hold' ), 10 ) || 0;
		var $time = $hold.find( '[data-roova-hold-time]' );

		var tick = window.setInterval( function () {
			left -= 1;

			if ( left <= 0 ) {
				window.clearInterval( tick );
				$hold.text( i18n.holdExpired || 'Your hold has run out — refresh to check the rooms are still free.' );
				return;
			}

			$time.text( Math.floor( left / 60 ) + ':' + ( '0' + ( left % 60 ) ).slice( -2 ) );
		}, 1000 );
	}

	/* -------------------------------------------------------------- Wiring */

	$( function () {
		var $form = $( 'form.checkout' );

		markChosenPayment();
		startCountdown();

		$( document.body ).on( 'change', 'input[name="payment_method"]', markChosenPayment );
		$( document.body ).on( 'updated_checkout', markChosenPayment );

		// Remove a room, and offer it back.
		$( document.body ).on( 'click', '[data-roova-remove]', function () {
			var key = $( this ).data( 'roova-remove' );

			cartRequest( 'roova_remove_cart_item', key, function ( data ) {
				if ( data.empty ) {
					// An empty checkout is nothing; WooCommerce sends it to the cart.
					window.location.reload();
					return;
				}

				showUndo(
					( i18n.removed || '%s removed.' ).replace( '%s', data.name || '' ).trim(),
					key
				);
				$( document.body ).trigger( 'update_checkout' );
			} );
		} );

		$( document.body ).on( 'click', '[data-roova-restore]', function () {
			var key = $( this ).data( 'roova-restore' );

			cartRequest( 'roova_restore_cart_item', key, function () {
				$( '[data-roova-undo]' ).prop( 'hidden', true ).empty();
				$( document.body ).trigger( 'update_checkout' );
			} );
		} );

		if ( ! $form.length ) {
			return;
		}

		// Refuse the submit while something is missing; WooCommerce does the rest.
		$form.on( 'checkout_place_order', function () {
			attempted = true;
			return validate();
		} );

		// Once they have tried, keep the messages honest as they type.
		$form.on( 'input change', '#billing_full_name, #billing_phone, #billing_email', function () {
			if ( attempted ) {
				checkField( this.id );
			}
		} );

		$( document.body ).on( 'change', '#terms', function () {
			if ( attempted ) {
				checkTerms();
			}
		} );
	} );
}( jQuery ) );
