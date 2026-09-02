<?php
/**
 * Cashback rewards: the offers a site runs, and what each member has earned.
 *
 * Two halves that meet in one place.
 *
 * The **offers** are one option (`roova_cashback_rewards`), edited under
 * WooCommerce → Settings → Cashback rewards. Each is a plain rule — a hotel (or
 * every hotel), a minimum number of nights, an amount, an expiry date, and how
 * many days after checkout the money clears. Nothing about them is guessed: a
 * site that has added no offers runs no cashback, and every member's balance is
 * zero.
 *
 * The **ledger** is one user meta key per member (`roova_cashback_ledger`), and
 * it is the source of truth for every figure this feature shows. A completed
 * stay is written into it once, with the amount and the clearing date **frozen
 * at that moment** — editing an offer afterwards, or deleting it outright,
 * never rewrites what a member was already told they had earned. That is the
 * whole reason the ledger exists rather than the balances being recomputed from
 * the rules on every page load.
 *
 * Three things follow from that shape and are worth keeping:
 *
 * - **Nothing here needs a cron.** An entry stores the date it clears, and
 *   whether it *has* cleared is read off the calendar at render time — the same
 *   principle as availability excluding expired holds in SQL, so correctness
 *   never waits on a scheduled task firing on time.
 * - **Earning is idempotent, keyed by the stay.** `roova_cashback_sync()` walks
 *   the member's completed stays and writes only the ones the ledger is
 *   missing, so it can run on every page load and still only ever pay out once.
 * - **An offer only applies to stays that check out after it was created.**
 *   Every rule carries a `created` date it is given automatically. Without it,
 *   adding an offer today would silently pay out for stays that finished last
 *   year, which is not what anybody means by adding an offer.
 *
 * Cashback here is **display only**: a balance is a number this theme keeps and
 * shows, exactly as RoovaVIP shows benefits. Nothing in the theme deducts it
 * from an order — the client honours it at the desk. `roova_cashback_record()`
 * is the door a site or plugin uses to write a redemption into the ledger once
 * it has.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * The option the offers are stored in.
 */
const ROOVA_CASHBACK_OPTION = 'roova_cashback_rewards';

/**
 * The user meta a member's ledger is stored in.
 */
const ROOVA_CASHBACK_META = 'roova_cashback_ledger';

/**
 * Days after checkout a reward clears, when an offer does not say.
 */
const ROOVA_CASHBACK_CLEAR_DAYS = 14;

/* -------------------------------------------------------------------------
 * The offers
 * ---------------------------------------------------------------------- */

/**
 * The icons an offer card can be given, for the settings screen's picker.
 *
 * Every slug is one the bundled library draws — see inc/icons.php.
 *
 * @return array slug => label.
 */
function roova_cashback_icons() {
	$slugs = array(
		'coins',
		'moon',
		'sun',
		'users',
		'repeat',
		'gift',
		'percent',
		'star',
		'crown',
		'heart',
		'tag',
		'calendar-check',
		'bed-double',
		'best-rate',
		'instant',
		'breakfast',
		'pin',
	);

	$icons   = roova_icon_library();
	$choices = array();

	foreach ( $slugs as $slug ) {
		if ( isset( $icons[ $slug ] ) ) {
			$choices[ $slug ] = $icons[ $slug ][0];
		}
	}

	/**
	 * Filter the icons offered for a cashback reward.
	 *
	 * @param array $choices slug => label.
	 */
	return apply_filters( 'roova_cashback_icons', $choices );
}

/**
 * Put a stored or posted offer list into shape.
 *
 * Runs on the way in from the settings screen and again on the way out of the
 * option, so a hand-edited row can never reach a template half-formed.
 *
 * Two fields are filled in rather than read: `id`, which the ledger points at,
 * and `created`, which decides how far back an offer reaches. Both are kept
 * when the posted row already carries them — the settings form posts them back
 * in hidden inputs — so saving the screen does not quietly re-date every offer
 * on it and pay the lot out all over again.
 *
 * @param mixed $raw Stored or posted value.
 * @return array[]
 */
function roova_cashback_sanitize_rewards( $raw ) {
	if ( ! is_array( $raw ) ) {
		return array();
	}

	$rewards = array();
	$seen    = array();

	foreach ( $raw as $reward ) {
		if ( ! is_array( $reward ) ) {
			continue;
		}

		$amount = isset( $reward['amount'] ) ? (float) $reward['amount'] : 0;

		// An offer worth nothing is not an offer; it is a row somebody meant to
		// delete, or the empty one the "Add reward" button just put on screen.
		if ( $amount <= 0 ) {
			continue;
		}

		$id = isset( $reward['id'] ) ? sanitize_key( $reward['id'] ) : '';
		if ( '' === $id || isset( $seen[ $id ] ) ) {
			$id = uniqid( 'cb', false );
		}
		$seen[ $id ] = true;

		$created = isset( $reward['created'] ) ? roova_sanitize_date( $reward['created'] ) : '';
		if ( '' === $created ) {
			$created = roova_today();
		}

		$icon = isset( $reward['icon'] ) ? sanitize_key( $reward['icon'] ) : '';

		$rewards[] = array(
			'id'         => $id,
			'created'    => $created,
			'title'      => isset( $reward['title'] ) ? sanitize_text_field( $reward['title'] ) : '',
			'detail'     => isset( $reward['detail'] ) ? sanitize_text_field( $reward['detail'] ) : '',
			'icon'       => $icon ? $icon : 'coins',
			'hotel'      => isset( $reward['hotel'] ) ? absint( $reward['hotel'] ) : 0,
			'nights'     => isset( $reward['nights'] ) ? max( 1, (int) $reward['nights'] ) : 1,
			'amount'     => round( $amount, 2 ),
			'expires'    => isset( $reward['expires'] ) ? roova_sanitize_date( $reward['expires'] ) : '',
			'clear_days' => isset( $reward['clear_days'] ) ? max( 0, (int) $reward['clear_days'] ) : ROOVA_CASHBACK_CLEAR_DAYS,
		);
	}

	/*
	 * Most valuable first. That is the order the offer grid reads best in, and
	 * it is what lets roova_cashback_best_reward() stop at its first match.
	 */
	usort(
		$rewards,
		static function ( $a, $b ) {
			if ( $a['amount'] === $b['amount'] ) {
				return $b['nights'] <=> $a['nights'];
			}

			return $b['amount'] <=> $a['amount'];
		}
	);

	return $rewards;
}

/**
 * Every offer the site has configured, most valuable first.
 *
 * Unlike RoovaVIP, there are no defaults. The handoff's four offers name a
 * specific hotel, and two of them describe rules this model cannot express —
 * shipping them would be inventing promises on the client's behalf. A fresh
 * install runs no offers and every balance reads zero, which is the truth.
 *
 * @return array[]
 */
function roova_cashback_rewards() {
	$stored = roova_cashback_sanitize_rewards( get_option( ROOVA_CASHBACK_OPTION, array() ) );

	/**
	 * Filter the cashback offers.
	 *
	 * @param array[] $rewards Each: id, created, title, detail, icon, hotel,
	 *                         nights, amount, expires, clear_days.
	 */
	return apply_filters( 'roova_cashback_rewards', $stored );
}

/**
 * The offers still running today — the ones the tab advertises.
 *
 * An offer with no expiry date runs until it is deleted.
 *
 * @return array[]
 */
function roova_cashback_active_rewards() {
	$today  = roova_today();
	$active = array();

	foreach ( roova_cashback_rewards() as $reward ) {
		if ( '' === $reward['expires'] || $reward['expires'] >= $today ) {
			$active[] = $reward;
		}
	}

	return $active;
}

/**
 * Is the cashback tab shown at all?
 *
 * True by default, and deliberately not tied to there being offers: a member
 * with cashback already earned must keep somewhere to read their balance after
 * the offer that paid it has ended.
 *
 * @return bool
 */
function roova_cashback_enabled() {
	/**
	 * Filter whether the cashback rewards tab is shown.
	 *
	 * @param bool $enabled Default true.
	 */
	return (bool) apply_filters( 'roova_cashback_enabled', true );
}

/**
 * The hotel an offer applies to, as a name.
 *
 * @param array $reward Offer.
 * @return string
 */
function roova_cashback_reward_hotel_name( $reward ) {
	if ( empty( $reward['hotel'] ) ) {
		return __( 'All Hotels', 'roova' );
	}

	$title = get_the_title( $reward['hotel'] );

	// A hotel deleted since the offer was written must not leave a blank card.
	return $title ? $title : __( 'All Hotels', 'roova' );
}

/**
 * The heading on an offer card.
 *
 * The client's own title when they have written one, and the hotel's name
 * otherwise — which is what the handoff's cards are headed with.
 *
 * @param array $reward Offer.
 * @return string
 */
function roova_cashback_reward_title( $reward ) {
	if ( '' !== trim( (string) $reward['title'] ) ) {
		return $reward['title'];
	}

	return roova_cashback_reward_hotel_name( $reward );
}

/**
 * The sentence under an offer's heading.
 *
 * The client's when they have written one, and otherwise built from the rule
 * itself — so a card is never blank, and never describes a rule other than the
 * one that is actually running.
 *
 * @param array $reward Offer.
 * @return string
 */
function roova_cashback_reward_detail( $reward ) {
	if ( '' !== trim( (string) $reward['detail'] ) ) {
		return $reward['detail'];
	}

	$amount = wp_strip_all_tags( roova_cashback_amount( $reward['amount'] ) );

	if ( empty( $reward['hotel'] ) ) {
		$line = sprintf(
			/* translators: 1: number of nights, 2: reward amount, e.g. "RM10.00" */
			_n(
				'Stay %1$s night or more at any Roova hotel and earn %2$s.',
				'Stay %1$s nights or more at any Roova hotel and earn %2$s.',
				$reward['nights'],
				'roova'
			),
			number_format_i18n( $reward['nights'] ),
			$amount
		);
	} else {
		$line = sprintf(
			/* translators: 1: number of nights, 2: hotel name, 3: reward amount */
			_n(
				'Stay %1$s night or more at %2$s and earn %3$s.',
				'Stay %1$s nights or more at %2$s and earn %3$s.',
				$reward['nights'],
				'roova'
			),
			number_format_i18n( $reward['nights'] ),
			roova_cashback_reward_hotel_name( $reward ),
			$amount
		);
	}

	if ( $reward['clear_days'] > 0 ) {
		$line .= ' ' . sprintf(
			/* translators: %s: number of days */
			_n(
				'Added to your balance %s day after checkout.',
				'Added to your balance %s days after checkout.',
				$reward['clear_days'],
				'roova'
			),
			number_format_i18n( $reward['clear_days'] )
		);
	}

	return $line;
}

/**
 * The validity line at the foot of an offer card.
 *
 * @param array $reward Offer.
 * @return string
 */
function roova_cashback_reward_validity( $reward ) {
	if ( '' === $reward['expires'] ) {
		return __( 'Always on', 'roova' );
	}

	return sprintf(
		/* translators: %s: expiry date, e.g. "Dec 31, 2026" */
		__( 'Until %s', 'roova' ),
		date_i18n( 'M j, Y', strtotime( $reward['expires'] . ' 00:00:00' ) )
	);
}

/* -------------------------------------------------------------------------
 * Matching a stay
 * ---------------------------------------------------------------------- */

/**
 * Does a completed stay qualify for an offer?
 *
 * Four tests, and the last two are the ones that keep this honest: a stay has
 * to have finished **while the offer was running**, which means on or after the
 * day it was added and on or before the day it expires.
 *
 * @param array $reward Offer.
 * @param array $stay   Stay row from roova_account_stays().
 * @return bool
 */
function roova_cashback_reward_matches( $reward, $stay ) {
	// "Duration" is a minimum: a longer, more valuable stay must never earn
	// less than a shorter one.
	if ( (int) $stay['nights'] < (int) $reward['nights'] ) {
		return false;
	}

	if ( ! empty( $reward['hotel'] ) && (int) $reward['hotel'] !== (int) $stay['hotel_id'] ) {
		return false;
	}

	$check_out = roova_sanitize_date( $stay['check_out'] );
	if ( ! $check_out ) {
		return false;
	}

	if ( $check_out < $reward['created'] ) {
		return false;
	}

	if ( '' !== $reward['expires'] && $check_out > $reward['expires'] ) {
		return false;
	}

	/**
	 * Filter whether a stay qualifies for an offer.
	 *
	 * @param bool  $matches Whether it qualifies.
	 * @param array $reward  Offer.
	 * @param array $stay    Stay row.
	 */
	return (bool) apply_filters( 'roova_cashback_reward_matches', true, $reward, $stay );
}

/**
 * The one offer a stay earns: the most valuable it qualifies for.
 *
 * Rewards do not stack. A stay that satisfies three rules pays out the biggest
 * of the three, once — `roova_cashback_rewards()` comes back sorted most
 * valuable first, so the first match is the answer.
 *
 * @param array $stay Stay row from roova_account_stays().
 * @return array|null
 */
function roova_cashback_best_reward( $stay ) {
	foreach ( roova_cashback_rewards() as $reward ) {
		if ( roova_cashback_reward_matches( $reward, $stay ) ) {
			return $reward;
		}
	}

	return null;
}

/* -------------------------------------------------------------------------
 * The ledger
 * ---------------------------------------------------------------------- */

/**
 * Put a stored ledger into shape.
 *
 * @param mixed $raw Stored value.
 * @return array[] Keyed by entry key.
 */
function roova_cashback_sanitize_ledger( $raw ) {
	if ( ! is_array( $raw ) ) {
		return array();
	}

	$ledger = array();

	foreach ( $raw as $key => $entry ) {
		if ( ! is_array( $entry ) ) {
			continue;
		}

		$key = sanitize_text_field( (string) $key );
		if ( '' === $key ) {
			continue;
		}

		$amount = isset( $entry['amount'] ) ? round( (float) $entry['amount'], 2 ) : 0;
		if ( $amount <= 0 ) {
			continue;
		}

		$earned = isset( $entry['earned'] ) ? roova_sanitize_date( $entry['earned'] ) : '';
		if ( ! $earned ) {
			$earned = roova_today();
		}

		$clears = isset( $entry['clears'] ) ? roova_sanitize_date( $entry['clears'] ) : '';

		$ledger[ $key ] = array(
			'key'      => $key,
			'type'     => ( isset( $entry['type'] ) && 'redeem' === $entry['type'] ) ? 'redeem' : 'earn',
			'reward'   => isset( $entry['reward'] ) ? sanitize_key( $entry['reward'] ) : '',
			'icon'     => isset( $entry['icon'] ) ? sanitize_key( $entry['icon'] ) : '',
			'label'    => isset( $entry['label'] ) ? sanitize_text_field( $entry['label'] ) : '',
			'hotel_id' => isset( $entry['hotel_id'] ) ? absint( $entry['hotel_id'] ) : 0,
			'order_id' => isset( $entry['order_id'] ) ? absint( $entry['order_id'] ) : 0,
			'amount'   => $amount,
			'earned'   => $earned,
			// A redemption is spent the moment it is made; only an earning waits.
			'clears'   => $clears ? $clears : $earned,
		);
	}

	return $ledger;
}

/**
 * A member's ledger, newest entry first.
 *
 * Reading it is what keeps it current: `roova_cashback_sync()` runs first, so a
 * stay that completed since the last visit is already in the list.
 *
 * @param int $user_id User ID, or 0 for the current user.
 * @return array[] Keyed by entry key.
 */
function roova_cashback_ledger( $user_id = 0 ) {
	$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
	if ( ! $user_id ) {
		return array();
	}

	roova_cashback_sync( $user_id );

	$ledger = roova_cashback_sanitize_ledger( get_user_meta( $user_id, ROOVA_CASHBACK_META, true ) );

	// Newest first — the order Activity reads in.
	uasort(
		$ledger,
		static function ( $a, $b ) {
			return strcmp( $b['earned'], $a['earned'] );
		}
	);

	return $ledger;
}

/**
 * Bring a member's ledger up to date with their completed stays.
 *
 * Idempotent and cheap to call: an entry already written is left exactly as it
 * was, amount and clearing date included, so an offer edited or deleted after
 * the fact never changes what somebody has already earned.
 *
 * The one thing it does take away is an earning whose stay stopped being a
 * completed stay — an order refunded or cancelled after checkout. A stay whose
 * *order* has been deleted outright is left alone: there is nothing left to
 * judge it by, and quietly clawing a balance back on that basis would be worse
 * than leaving it.
 *
 * @param int $user_id User ID.
 * @return void
 */
function roova_cashback_sync( $user_id ) {
	$user_id = absint( $user_id );
	if ( ! $user_id || ! function_exists( 'roova_account_stays' ) ) {
		return;
	}

	// Once per request per member; the stay list behind it is static anyway.
	static $done = array();
	if ( isset( $done[ $user_id ] ) ) {
		return;
	}
	$done[ $user_id ] = true;

	/**
	 * Filter whether completed stays are turned into cashback automatically.
	 *
	 * @param bool $sync    Default true.
	 * @param int  $user_id User ID.
	 */
	if ( ! apply_filters( 'roova_cashback_sync', true, $user_id ) ) {
		return;
	}

	$ledger  = roova_cashback_sanitize_ledger( get_user_meta( $user_id, ROOVA_CASHBACK_META, true ) );
	$changed = false;

	$known     = array();
	$completed = array();

	foreach ( roova_account_stays( $user_id ) as $stay ) {
		$known[ $stay['key'] ] = true;

		if ( 'completed' !== $stay['status'] ) {
			continue;
		}

		$completed[ $stay['key'] ] = true;

		if ( isset( $ledger[ $stay['key'] ] ) ) {
			continue;
		}

		$reward = roova_cashback_best_reward( $stay );
		if ( ! $reward ) {
			continue;
		}

		$ledger[ $stay['key'] ] = array(
			'key'      => $stay['key'],
			'type'     => 'earn',
			'reward'   => $reward['id'],
			'icon'     => 'plus-circle',
			'label'    => sprintf(
				/* translators: %s: hotel name */
				__( 'Stay at %s', 'roova' ),
				$stay['hotel']
			),
			'hotel_id' => (int) $stay['hotel_id'],
			'order_id' => (int) $stay['order_id'],
			'amount'   => (float) $reward['amount'],
			'earned'   => $stay['check_out'],
			'clears'   => roova_add_days( $stay['check_out'], (int) $reward['clear_days'] ),
		);

		$changed = true;

		/**
		 * Fires when a completed stay earns cashback.
		 *
		 * @param int   $user_id User ID.
		 * @param array $entry   The ledger entry just written.
		 * @param array $stay    The stay that earned it.
		 */
		do_action( 'roova_cashback_earned', $user_id, $ledger[ $stay['key'] ], $stay );
	}

	foreach ( $ledger as $key => $entry ) {
		if ( 'earn' !== $entry['type'] ) {
			continue;
		}

		// Still a completed stay, or a stay this site can no longer see at all.
		if ( isset( $completed[ $key ] ) || ! isset( $known[ $key ] ) ) {
			continue;
		}

		unset( $ledger[ $key ] );
		$changed = true;
	}

	if ( $changed ) {
		update_user_meta( $user_id, ROOVA_CASHBACK_META, $ledger );
	}
}

/**
 * Write an entry into a member's ledger by hand.
 *
 * The door a site or plugin uses to record a redemption once it has honoured
 * one — this theme never spends a balance itself. Pass a `type` of `redeem`
 * with a positive `amount`; the amount is what comes *off*.
 *
 * @param int   $user_id User ID.
 * @param array $entry   type, amount, label, and optionally icon, earned,
 *                       clears, hotel_id, order_id, key.
 * @return string The entry key, or '' when nothing was written.
 */
function roova_cashback_record( $user_id, $entry ) {
	$user_id = absint( $user_id );
	if ( ! $user_id || empty( $entry['amount'] ) ) {
		return '';
	}

	$type = ( isset( $entry['type'] ) && 'redeem' === $entry['type'] ) ? 'redeem' : 'earn';
	$key  = isset( $entry['key'] ) ? sanitize_text_field( $entry['key'] ) : $type . '-' . uniqid( '', false );

	$ledger = roova_cashback_sanitize_ledger( get_user_meta( $user_id, ROOVA_CASHBACK_META, true ) );

	// Never overwrite: the key is what makes a repeated call safe to make.
	if ( isset( $ledger[ $key ] ) ) {
		return $key;
	}

	$entry['key']  = $key;
	$entry['type'] = $type;

	if ( empty( $entry['icon'] ) ) {
		$entry['icon'] = 'redeem' === $type ? 'minus-circle' : 'gift';
	}

	$ledger[ $key ] = $entry;
	$ledger         = roova_cashback_sanitize_ledger( $ledger );

	if ( ! isset( $ledger[ $key ] ) ) {
		return '';
	}

	update_user_meta( $user_id, ROOVA_CASHBACK_META, $ledger );

	return $key;
}

/**
 * Has an entry cleared into the spendable balance yet?
 *
 * Read off the calendar rather than off a stored flag, so no scheduled task has
 * to fire on time for a balance to be right.
 *
 * @param array $entry Ledger entry.
 * @return bool
 */
function roova_cashback_entry_cleared( $entry ) {
	if ( 'earn' !== $entry['type'] ) {
		return true;
	}

	return $entry['clears'] <= roova_today();
}

/**
 * The word beside an entry's date in Activity.
 *
 * @param array $entry Ledger entry.
 * @return string
 */
function roova_cashback_entry_state( $entry ) {
	if ( 'redeem' === $entry['type'] ) {
		return __( 'Applied', 'roova' );
	}

	return roova_cashback_entry_cleared( $entry ) ? __( 'Cleared', 'roova' ) : __( 'Pending', 'roova' );
}

/* -------------------------------------------------------------------------
 * Balances
 * ---------------------------------------------------------------------- */

/**
 * The three figures on the balance cards.
 *
 * - **available** — cleared earnings, less anything already redeemed.
 * - **pending** — earnings whose clearing date has not arrived.
 * - **earned** — every earning ever, cleared or not.
 *
 * `clears` is the earliest date a pending amount lands, so the pending card can
 * say *when* rather than repeat the rule.
 *
 * @param int $user_id User ID, or 0 for the current user.
 * @return array available, pending, earned, stays, clears.
 */
function roova_cashback_balances( $user_id = 0 ) {
	$totals = array(
		'available' => 0.0,
		'pending'   => 0.0,
		'earned'    => 0.0,
		'stays'     => 0,
		'clears'    => '',
	);

	foreach ( roova_cashback_ledger( $user_id ) as $entry ) {
		if ( 'redeem' === $entry['type'] ) {
			$totals['available'] -= $entry['amount'];
			continue;
		}

		$totals['earned'] += $entry['amount'];
		$totals['stays']++;

		if ( roova_cashback_entry_cleared( $entry ) ) {
			$totals['available'] += $entry['amount'];
			continue;
		}

		$totals['pending'] += $entry['amount'];

		if ( '' === $totals['clears'] || $entry['clears'] < $totals['clears'] ) {
			$totals['clears'] = $entry['clears'];
		}
	}

	/*
	 * More redeemed than cleared is a bookkeeping error somewhere else, not a
	 * debt to show the member. Cast rather than lean on max(), which hands back
	 * the int when the two arguments are equal — every figure here is money.
	 */
	$totals['available'] = (float) max( 0, round( $totals['available'], 2 ) );
	$totals['pending']   = (float) round( $totals['pending'], 2 );
	$totals['earned']    = (float) round( $totals['earned'], 2 );

	return $totals;
}

/**
 * The spendable balance, for the account hero's stat.
 *
 * @param int $user_id User ID, or 0 for the current user.
 * @return float
 */
function roova_cashback_available( $user_id = 0 ) {
	$balances = roova_cashback_balances( $user_id );

	return (float) $balances['available'];
}

/**
 * A cashback figure as money, in the store's currency.
 *
 * `wc_price()` wraps its output in the markup WooCommerce's own styles expect,
 * which is a nest of spans — see CLAUDE.md on scoping label rules with `>`.
 *
 * @param float $amount Amount.
 * @return string
 */
function roova_cashback_amount( $amount ) {
	if ( function_exists( 'wc_price' ) ) {
		return wc_price( (float) $amount );
	}

	return esc_html( number_format_i18n( (float) $amount, 2 ) );
}

/* -------------------------------------------------------------------------
 * Activity
 * ---------------------------------------------------------------------- */

/**
 * Everything that has happened to a member's balance, newest first.
 *
 * One row per ledger entry, already shaped for the template: the icon, the
 * line, the date, the state and how the amount should read.
 *
 * @param int $user_id User ID, or 0 for the current user.
 * @return array[]
 */
function roova_cashback_activity( $user_id = 0 ) {
	$rows = array();

	foreach ( roova_cashback_ledger( $user_id ) as $entry ) {
		$redeem = ( 'redeem' === $entry['type'] );

		/*
		 * The hotel's current name wherever it still has one, so a property that
		 * has been renamed reads correctly; the label frozen at earn time is the
		 * fallback for a hotel that has since been deleted.
		 */
		$label = $entry['label'];
		if ( $entry['hotel_id'] && get_post_status( $entry['hotel_id'] ) ) {
			$label = sprintf(
				/* translators: %s: hotel name */
				__( 'Stay at %s', 'roova' ),
				get_the_title( $entry['hotel_id'] )
			);
		}

		if ( '' === trim( (string) $label ) ) {
			$label = $redeem ? __( 'Cashback redeemed', 'roova' ) : __( 'Cashback earned', 'roova' );
		}

		$cleared = roova_cashback_entry_cleared( $entry );

		$rows[] = array(
			'icon'   => $entry['icon'] ? $entry['icon'] : ( $redeem ? 'minus-circle' : 'plus-circle' ),
			'label'  => $label,
			'date'   => date_i18n( 'M j, Y', strtotime( $entry['earned'] . ' 00:00:00' ) ),
			'state'  => roova_cashback_entry_state( $entry ),
			'amount' => roova_cashback_amount( $entry['amount'] ),
			'sign'   => $redeem ? '−' : '+',
			/*
			 * Muted while it is still pending, ink once it has been spent, green
			 * when it is money the member actually has.
			 */
			'tone'   => $redeem ? 'spent' : ( $cleared ? 'cleared' : 'pending' ),
		);
	}

	return $rows;
}
