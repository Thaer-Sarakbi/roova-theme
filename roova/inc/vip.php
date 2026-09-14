<?php
/**
 * RoovaVIP: the tiers a member climbs, and the benefits each one carries.
 *
 * Tiers are earned by **completed bookings only** — no spend thresholds, no
 * expiry dates. That is the handoff's rule and it is also the only one this
 * theme can honour without inventing a figure: a completed stay is a fact the
 * bookings table already knows.
 *
 * A tier carries two kinds of benefit. The written ones are promises the hotel
 * keeps at the desk — late checkout, breakfast — which is why each is text the
 * client writes rather than logic the theme runs. Two are the exception, and
 * they are the only things here that touch money: **free nights** and a
 * **checkout discount**, applied as negative cart fees and each shown by name
 * in the order summary — the free-night line says how many nights were given.
 * Free nights ship at zero for every tier, and so does the discount for every
 * tier but Bronze: 2% is what signing up earns, and the checkout tells a guest
 * without an account exactly what that would take off their stay.
 *
 * Tiers and benefits live in one option so they can be added and deleted from
 * WooCommerce → Settings → RoovaVIP (see inc/admin/vip-settings.php).
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * The option the tier list is stored in.
 */
const ROOVA_VIP_OPTION = 'roova_vip_tiers';

/**
 * The user meta a hand-picked tier is stored in.
 */
const ROOVA_VIP_USER_META = 'roova_vip_tier';

/**
 * The tiers a site starts with.
 *
 * The five tiers and their thresholds are the handoff's. Only Gold ships with
 * benefits, because Gold is the only tier the handoff writes them for — the
 * rest would be figures nobody has agreed to yet, and a tier with no benefits
 * simply leaves that section off the page rather than printing an empty grid.
 *
 * **Bronze ships at 2% and every other tier at zero**, Gold included. Bronze
 * is the tier signing up puts a member on, and its 2% is the client's own
 * figure — the one the checkout quotes to a guest without an account (see
 * roova_vip_signup_total()). The higher tiers' percentages are money out of the
 * client's till that nobody has agreed to yet, so they start at nothing.
 *
 * @return array[] Each: name, min, discount, benefits[] (icon, title, note).
 */
function roova_vip_default_tiers() {
	return array(
		array(
			'name'        => __( 'Bronze', 'roova' ),
			'min'         => 0,
			'discount'    => 2,
			'free_nights' => 0,
			'benefits'    => array(),
		),
		array(
			'name'        => __( 'VIP Silver', 'roova' ),
			'min'         => 2,
			'discount'    => 0,
			'free_nights' => 0,
			'benefits'    => array(),
		),
		array(
			'name'        => __( 'VIP Gold', 'roova' ),
			'min'         => 5,
			'discount'    => 0,
			'free_nights' => 0,
			'benefits'    => array(
				array(
					'icon'  => 'percent',
					'title' => __( '6% cashback on every stay', 'roova' ),
					'note'  => __( 'Credited 14 days after checkout.', 'roova' ),
				),
				array(
					'icon'  => 'clock',
					'title' => __( 'Late checkout until 2pm', 'roova' ),
					'note'  => __( 'Subject to availability, requested automatically.', 'roova' ),
				),
				array(
					'icon'  => 'coffee',
					'title' => __( 'Complimentary breakfast', 'roova' ),
					'note'  => __( 'For two guests at participating hotels.', 'roova' ),
				),
				array(
					'icon'  => 'headset',
					'title' => __( 'Priority support line', 'roova' ),
					'note'  => __( 'Average answer time under 40 seconds.', 'roova' ),
				),
				array(
					'icon'  => 'calendar-check',
					'title' => __( 'Flexible cancellation', 'roova' ),
					'note'  => __( 'Free until 24 hours before check-in.', 'roova' ),
				),
			),
		),
		array(
			'name'        => __( 'VIP Platinum', 'roova' ),
			'min'         => 10,
			'discount'    => 0,
			'free_nights' => 0,
			'benefits'    => array(),
		),
		array(
			'name'        => __( 'VIP Diamond', 'roova' ),
			'min'         => 15,
			'discount'    => 0,
			'free_nights' => 0,
			'benefits'    => array(),
		),
	);
}

/**
 * The icons a benefit row can be given, for the settings screen's picker.
 *
 * Every slug is one the bundled library draws — see inc/icons.php.
 *
 * @return array slug => label.
 */
function roova_vip_benefit_icons() {
	$slugs = array(
		'percent',
		'clock',
		'coffee',
		'headset',
		'calendar-check',
		'star',
		'crown',
		'heart',
		'shield-check',
		'check-circle',
		'bed-double',
		'pin',
		'tag',
		'best-rate',
		'no-fees',
		'instant',
		'breakfast',
		'pool',
		'spa',
		'gym',
		'parking',
		'shuttle',
		'wifi',
		'luggage',
		'concierge',
		'room-service',
	);

	$icons   = roova_icon_library();
	$choices = array();

	foreach ( $slugs as $slug ) {
		if ( isset( $icons[ $slug ] ) ) {
			$choices[ $slug ] = $icons[ $slug ][0];
		}
	}

	/**
	 * Filter the icons offered for a VIP benefit row.
	 *
	 * @param array $choices slug => label.
	 */
	return apply_filters( 'roova_vip_benefit_icons', $choices );
}

/**
 * A discount percentage, clamped to something that can be charged.
 *
 * Nothing below zero (a "discount" that adds money), nothing above 100 (a total
 * the store would owe the guest), and two decimal places, which is as fine as a
 * percentage of a room rate is ever worth stating.
 *
 * @param mixed $raw Posted or stored value.
 * @return float
 */
function roova_vip_sanitize_percent( $raw ) {
	$percent = is_numeric( $raw ) ? (float) $raw : 0.0;

	return round( min( 100.0, max( 0.0, $percent ) ), 2 );
}

/**
 * A number of free nights, clamped to something a stay could actually use.
 *
 * Whole nights only — half a night is not a thing a hotel gives away — and no
 * more than 365, which is already far past any real offer and stops a typo in
 * the settings screen becoming a free year.
 *
 * @param mixed $raw Posted or stored value.
 * @return int
 */
function roova_vip_sanitize_nights( $raw ) {
	$nights = is_numeric( $raw ) ? (int) $raw : 0;

	return min( 365, max( 0, $nights ) );
}

/**
 * Put a stored tier list into shape.
 *
 * Used on the way in from the settings screen and again on the way out of the
 * option, so a hand-edited row can never reach a template half-formed. Tiers
 * come back sorted by threshold, which is the order the rail draws them in.
 *
 * @param mixed $raw Stored or posted value.
 * @return array[]
 */
function roova_vip_sanitize_tiers( $raw ) {
	if ( ! is_array( $raw ) ) {
		return array();
	}

	$tiers = array();

	foreach ( $raw as $tier ) {
		if ( ! is_array( $tier ) ) {
			continue;
		}

		$name = isset( $tier['name'] ) ? sanitize_text_field( $tier['name'] ) : '';
		if ( '' === trim( $name ) ) {
			// A tier with no name has nothing to show on the rail.
			continue;
		}

		$benefits = array();
		if ( isset( $tier['benefits'] ) && is_array( $tier['benefits'] ) ) {
			foreach ( $tier['benefits'] as $benefit ) {
				if ( ! is_array( $benefit ) ) {
					continue;
				}

				$title = isset( $benefit['title'] ) ? sanitize_text_field( $benefit['title'] ) : '';
				if ( '' === trim( $title ) ) {
					continue;
				}

				$icon = isset( $benefit['icon'] ) ? sanitize_key( $benefit['icon'] ) : '';

				$benefits[] = array(
					'icon'  => $icon ? $icon : 'check-circle',
					'title' => $title,
					'note'  => isset( $benefit['note'] ) ? sanitize_text_field( $benefit['note'] ) : '',
				);
			}
		}

		$tiers[] = array(
			'name'        => $name,
			'min'         => isset( $tier['min'] ) ? max( 0, (int) $tier['min'] ) : 0,
			'discount'    => roova_vip_sanitize_percent( isset( $tier['discount'] ) ? $tier['discount'] : 0 ),
			'free_nights' => roova_vip_sanitize_nights( isset( $tier['free_nights'] ) ? $tier['free_nights'] : 0 ),
			'benefits'    => $benefits,
		);
	}

	usort(
		$tiers,
		static function ( $a, $b ) {
			return $a['min'] <=> $b['min'];
		}
	);

	return $tiers;
}

/**
 * Every tier, lowest threshold first.
 *
 * @return array[]
 */
function roova_vip_tiers() {
	$stored = get_option( ROOVA_VIP_OPTION, null );

	// null, not false: an admin who deletes every row means it, and should not
	// be handed the defaults back on the next page load.
	$tiers = ( null === $stored ) ? roova_vip_default_tiers() : roova_vip_sanitize_tiers( $stored );

	/**
	 * Filter the VIP tiers.
	 *
	 * @param array[] $tiers Each: name, min, benefits[].
	 */
	return apply_filters( 'roova_vip_tiers', $tiers );
}

/**
 * Is there a VIP programme to show at all?
 *
 * @return bool
 */
function roova_vip_enabled() {
	/**
	 * Filter whether the VIP tab is shown.
	 *
	 * @param bool $enabled True when at least one tier is configured.
	 */
	return (bool) apply_filters( 'roova_vip_enabled', (bool) roova_vip_tiers() );
}

/**
 * The requirement line under a tier's name on the rail.
 *
 * @param array $tier Tier.
 * @return string
 */
function roova_vip_requirement_label( $tier ) {
	$min = isset( $tier['min'] ) ? (int) $tier['min'] : 0;

	if ( $min < 1 ) {
		return __( 'Member', 'roova' );
	}

	return sprintf(
		/* translators: %s: number of completed bookings */
		esc_html( _n( '%s booking', '%s bookings', $min, 'roova' ) ),
		number_format_i18n( $min )
	);
}

/**
 * Which tier a booking count reaches.
 *
 * The highest tier whose threshold the count has met — the rail is filled up to
 * and including it.
 *
 * **The lowest tier is the floor.** Signing up is what earns it, so a member
 * with no completed stays is Bronze rather than nothing at all — and a site that
 * sets its first tier above zero bookings still has somewhere to put a new
 * member instead of showing them an empty card.
 *
 * @param int $count Completed bookings.
 * @return int Index into roova_vip_tiers(), or -1 when there are no tiers.
 */
function roova_vip_current_index( $count ) {
	$tiers = roova_vip_tiers();
	if ( ! $tiers ) {
		return -1;
	}

	$count = max( 0, (int) $count );
	$index = 0;

	foreach ( $tiers as $i => $tier ) {
		if ( $count >= (int) $tier['min'] ) {
			$index = $i;
		}
	}

	return $index;
}

/**
 * The tier a member is on.
 *
 * @param int $count Completed bookings.
 * @return array|null
 */
function roova_vip_tier_for_count( $count ) {
	$tiers = roova_vip_tiers();
	$index = roova_vip_current_index( $count );

	return isset( $tiers[ $index ] ) ? $tiers[ $index ] : null;
}

/**
 * The next tier up, and how many bookings are left to reach it.
 *
 * `$index` is where the member actually stands, which is not always what their
 * count says: an admin can pin them to a tier by hand. Pass it and the line
 * counts on from there; leave it out and it is read from the count.
 *
 * Returns null when there is nothing left to say — the top tier, or a member
 * pinned below a tier they have already earned, where "0 more bookings to
 * reach VIP Silver" would be nonsense.
 *
 * @param int      $count Completed bookings.
 * @param int|null $index Current tier index, or null to derive it.
 * @return array|null array( tier, remaining ), or null.
 */
function roova_vip_next_tier( $count, $index = null ) {
	$tiers = roova_vip_tiers();
	$count = max( 0, (int) $count );

	if ( null === $index ) {
		$index = roova_vip_current_index( $count );
	}

	$next = isset( $tiers[ $index + 1 ] ) ? $tiers[ $index + 1 ] : null;
	if ( ! $next ) {
		return null;
	}

	$remaining = (int) $next['min'] - $count;
	if ( $remaining < 1 ) {
		return null;
	}

	return array(
		'tier'      => $next,
		'remaining' => $remaining,
	);
}

/**
 * The threshold of the last tier — what the progress line counts towards.
 *
 * @return int
 */
function roova_vip_top_threshold() {
	$tiers = roova_vip_tiers();
	if ( ! $tiers ) {
		return 0;
	}

	$last = end( $tiers );

	return (int) $last['min'];
}

/**
 * The tier a member is on, by user.
 *
 * @param int $user_id User ID, or 0 for the current user.
 * @return array|null
 */
function roova_vip_tier_for_user( $user_id = 0 ) {
	$tiers = roova_vip_tiers();
	$index = roova_vip_index_for_user( $user_id );

	return isset( $tiers[ $index ] ) ? $tiers[ $index ] : null;
}

/**
 * The tier an admin has pinned this member to, if any.
 *
 * Stored as the tier's **name**, because that is the only thing about a tier
 * that is stable: positions shift the moment a tier is added or deleted. A name
 * that no longer matches any tier — the tier was renamed or removed — is
 * ignored and the member goes back to being counted, which is the safe way for
 * this to fail.
 *
 * @param int $user_id User ID, or 0 for the current user.
 * @return string Tier name, or '' for automatic.
 */
function roova_vip_override( $user_id = 0 ) {
	$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
	if ( ! $user_id ) {
		return '';
	}

	$stored = (string) get_user_meta( $user_id, ROOVA_VIP_USER_META, true );

	/**
	 * Filter the tier a member is pinned to.
	 *
	 * @param string $stored  Tier name, or '' for automatic.
	 * @param int    $user_id User ID.
	 */
	$stored = (string) apply_filters( 'roova_vip_tier_override', $stored, $user_id );

	if ( '' === $stored ) {
		return '';
	}

	foreach ( roova_vip_tiers() as $tier ) {
		if ( $tier['name'] === $stored ) {
			return $stored;
		}
	}

	return '';
}

/**
 * Where a member actually stands: their pinned tier, or their earned one.
 *
 * @param int $user_id User ID, or 0 for the current user.
 * @return int Index into roova_vip_tiers(), or -1 when there are no tiers.
 */
function roova_vip_index_for_user( $user_id = 0 ) {
	$override = roova_vip_override( $user_id );

	if ( '' !== $override ) {
		foreach ( roova_vip_tiers() as $i => $tier ) {
			if ( $tier['name'] === $override ) {
				return $i;
			}
		}
	}

	return roova_vip_current_index( roova_account_completed_count( $user_id ) );
}

/**
 * The tier name shown beside the crown in the account header.
 *
 * @param int $user_id User ID, or 0 for the current user.
 * @return string '' when there is no programme.
 */
function roova_vip_member_label( $user_id = 0 ) {
	$tier = roova_vip_tier_for_user( $user_id );
	if ( ! $tier ) {
		return '';
	}

	return sprintf(
		/* translators: %s: tier name, e.g. "VIP Gold" */
		__( '%s member', 'roova' ),
		$tier['name']
	);
}

/* -------------------------------------------------------------------------
 * The checkout discount
 *
 * The one benefit the theme honours itself. Everything else on a tier is a
 * promise the hotel keeps at the desk; this comes off the guest's total.
 * ---------------------------------------------------------------------- */

/**
 * The discount a tier carries, as a percentage.
 *
 * @param array|null $tier Tier.
 * @return float 0.0 when the tier has none.
 */
function roova_vip_tier_discount( $tier ) {
	if ( ! is_array( $tier ) || ! isset( $tier['discount'] ) ) {
		return 0.0;
	}

	return roova_vip_sanitize_percent( $tier['discount'] );
}

/**
 * Is the discount switched on at all?
 *
 * @return bool
 */
function roova_vip_discount_enabled() {
	/**
	 * Filter whether VIP tiers discount the checkout total.
	 *
	 * Switching this off leaves the tiers, the rail and the written benefits
	 * exactly as they were — only the money stops.
	 *
	 * @param bool $enabled True unless a site says otherwise.
	 */
	return (bool) apply_filters( 'roova_vip_discount_enabled', true );
}

/**
 * The discount this member gets, as a percentage.
 *
 * Read from the tier they actually stand on, which is their pinned tier when an
 * admin has set one — see roova_vip_index_for_user(). A signed-out visitor gets
 * nothing: there is no member for a tier to belong to.
 *
 * @param int $user_id User ID, or 0 for the current user.
 * @return float 0.0 when there is no discount to give.
 */
function roova_vip_discount_percent( $user_id = 0 ) {
	$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

	$percent = 0.0;

	if ( $user_id && roova_vip_enabled() && roova_vip_discount_enabled() ) {
		$percent = roova_vip_tier_discount( roova_vip_tier_for_user( $user_id ) );
	}

	/**
	 * Filter the discount percentage a member's stay is reduced by.
	 *
	 * @param float $percent Percentage, 0-100.
	 * @param int   $user_id User ID.
	 */
	return roova_vip_sanitize_percent( apply_filters( 'roova_vip_discount_percent', $percent, $user_id ) );
}

/**
 * A percentage written the way a guest reads it: "10", "7.5", never "10.00".
 *
 * @param float $percent Percentage.
 * @return string
 */
function roova_vip_format_percent( $percent ) {
	$percent = roova_vip_sanitize_percent( $percent );

	$decimals = 2;
	if ( $percent === round( $percent, 0 ) ) {
		$decimals = 0;
	} elseif ( $percent === round( $percent, 1 ) ) {
		$decimals = 1;
	}

	return number_format_i18n( $percent, $decimals );
}

/**
 * What a percentage takes off a given amount.
 *
 * Rounded to the store's own precision before it reaches a total, so the figure
 * in the summary is the figure that was subtracted — a discount that rounds
 * differently on its way to the total is a penny nobody can account for.
 *
 * @param float $base     Amount to discount.
 * @param float $percent  Percentage.
 * @param int   $decimals Price decimals.
 * @return float Never negative, never more than the base.
 */
function roova_vip_discount_amount( $base, $percent, $decimals = 2 ) {
	$base    = (float) $base;
	$percent = roova_vip_sanitize_percent( $percent );

	if ( $base <= 0 || $percent <= 0 ) {
		return 0.0;
	}

	return round( min( $base, $base * $percent / 100 ), max( 0, (int) $decimals ) );
}

/**
 * The name the discount goes by on the summary, the order and the emails.
 *
 * The percentage is in the label rather than beside it, because this line is
 * read in a dozen places the theme does not draw — WooCommerce's order screen,
 * the customer email, a refund — and the name is the only part that travels.
 *
 * @param string $tier_name Tier name.
 * @param float  $percent   Percentage.
 * @return string
 */
function roova_vip_discount_label( $tier_name, $percent ) {
	$label = sprintf(
		/* translators: 1: VIP tier name, e.g. "VIP Gold", 2: discount percentage, e.g. "10" */
		__( '%1$s discount (%2$s%%)', 'roova' ),
		$tier_name,
		roova_vip_format_percent( $percent )
	);

	/**
	 * Filter the VIP discount's label.
	 *
	 * @param string $label     The line's name.
	 * @param string $tier_name Tier name.
	 * @param float  $percent   Percentage.
	 */
	return (string) apply_filters( 'roova_vip_discount_label', $label, $tier_name, $percent );
}

/**
 * The free nights a tier carries.
 *
 * @param array|null $tier Tier.
 * @return int 0 when the tier has none.
 */
function roova_vip_tier_free_nights( $tier ) {
	if ( ! is_array( $tier ) || ! isset( $tier['free_nights'] ) ) {
		return 0;
	}

	return roova_vip_sanitize_nights( $tier['free_nights'] );
}

/**
 * The free nights this member gets.
 *
 * Same rules as roova_vip_discount_percent(): the tier they actually stand on,
 * pinned or earned, and nothing at all for a signed-out visitor.
 *
 * @param int $user_id User ID, or 0 for the current user.
 * @return int
 */
function roova_vip_free_nights( $user_id = 0 ) {
	$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

	$nights = 0;

	if ( $user_id && roova_vip_enabled() && roova_vip_discount_enabled() ) {
		$nights = roova_vip_tier_free_nights( roova_vip_tier_for_user( $user_id ) );
	}

	/**
	 * Filter how many nights of a member's stay are free.
	 *
	 * @param int $nights  Free nights.
	 * @param int $user_id User ID.
	 */
	return roova_vip_sanitize_nights( apply_filters( 'roova_vip_free_nights', $nights, $user_id ) );
}

/**
 * What a tier's free nights are worth against the stay in the cart.
 *
 * A free night is worth **one room for one night**, at the nightly rate the
 * guest was quoted — the same figure the summary prints as "RM210.00 × 3
 * nights". It is not multiplied by the number of rooms: "two nights free" is
 * two nights off the stay, not two off every room in it.
 *
 * Two clamps, and both matter:
 *
 * - **Never more nights than the stay has.** Three free nights against a
 *   two-night booking is two, or the credit would pay for nights nobody booked.
 * - **Never more than the rooms cost.** The count in the label is trimmed with
 *   the amount rather than after it, so a guest is never told two nights are
 *   free while only one came off.
 *
 * The cart holds a single booking (see *One booking per cart*), but a site that
 * has filtered that off can have several. The dearest room is the one credited,
 * because a free night the guest chooses would be that one.
 *
 * @param WC_Cart $cart     Cart being calculated.
 * @param int     $free     Free nights the tier gives.
 * @param float   $ceiling  Most the credit may come to.
 * @param int     $decimals Price decimals.
 * @return array nights (int), amount (float).
 */
function roova_vip_free_nights_credit( $cart, $free, $ceiling, $decimals = 2 ) {
	$none = array(
		'nights' => 0,
		'amount' => 0.0,
	);

	$free    = roova_vip_sanitize_nights( $free );
	$ceiling = (float) $ceiling;

	if ( $free < 1 || $ceiling <= 0 || ! $cart instanceof WC_Cart ) {
		return $none;
	}

	$rate   = 0.0;
	$nights = 0;

	foreach ( $cart->get_cart() as $item ) {
		if ( empty( $item['roova_booking']['room_id'] ) ) {
			continue;
		}

		$item_rate = roova_room_rate( (int) $item['roova_booking']['room_id'] );

		if ( $item_rate > $rate ) {
			$rate   = $item_rate;
			$nights = max( 1, (int) $item['roova_booking']['nights'] );
		}
	}

	if ( $rate <= 0 || $nights < 1 ) {
		return $none;
	}

	$free = min( $free, $nights, (int) floor( $ceiling / $rate ) );

	if ( $free < 1 ) {
		return $none;
	}

	return array(
		'nights' => $free,
		'amount' => round( min( $ceiling, $free * $rate ), max( 0, (int) $decimals ) ),
	);
}

/**
 * The name the free-night credit goes by, with the count in it.
 *
 * The count is the point of the line — a guest reading the summary should be
 * able to see how many nights they were given without doing the division.
 *
 * @param string $tier_name Tier name.
 * @param int    $nights    Free nights actually credited.
 * @return string
 */
function roova_vip_free_nights_label( $tier_name, $nights ) {
	$nights = roova_vip_sanitize_nights( $nights );

	$label = sprintf(
		/* translators: 1: VIP tier name, e.g. "VIP Gold", 2: number of free nights */
		_n( '%1$s: %2$s free night', '%1$s: %2$s free nights', $nights, 'roova' ),
		$tier_name,
		number_format_i18n( $nights )
	);

	/**
	 * Filter the free-night credit's label.
	 *
	 * @param string $label     The line's name.
	 * @param string $tier_name Tier name.
	 * @param int    $nights    Free nights credited.
	 */
	return (string) apply_filters( 'roova_vip_free_nights_label', $label, $tier_name, $nights );
}

/**
 * Take the member's tier off the cart: free nights first, then the percentage.
 *
 * **Negative fees, not coupons.** A coupon would need a code, would sit in the
 * coupon row with a "remove" link beside it, and would have to exist in the
 * database for every tier. A fee is worked out fresh on every recalculation, so
 * it follows the rooms, the quantity and any coupon already applied.
 *
 * They are taxable, in the rooms' own tax class, so the tax rows fall with them
 * — a stay discounted by 10% should be taxed on what is actually charged, not on
 * what it would have cost.
 *
 * **Both are measured against the same subtotal**, not stacked one on the other.
 * WooCommerce orders fee lines by amount, so which of the two prints first is not
 * ours to choose — and a line that only makes sense underneath the other one
 * would read as an arithmetic error half the time. Ten percent off is ten percent
 * of the rooms above it, whichever line it lands under.
 *
 * The two together are held to the price of the stay. The free nights are taken
 * at full value — that is the concrete promise, and it is already capped at the
 * length of the booking — and the percentage takes whatever is left, so an
 * extravagant tier ends at a total of zero rather than below one.
 *
 * @param WC_Cart $cart Cart being calculated.
 */
function roova_vip_apply_cart_discount( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}

	if ( ! $cart instanceof WC_Cart || ! $cart->get_cart() ) {
		return;
	}

	$percent = roova_vip_discount_percent();
	$free    = roova_vip_free_nights();

	if ( $percent <= 0 && $free < 1 ) {
		return;
	}

	$tier = roova_vip_tier_for_user();
	if ( ! $tier ) {
		return;
	}

	$taxable = wc_tax_enabled();

	foreach ( roova_vip_cart_reductions( $cart, $tier['name'], $percent, $free ) as $line ) {
		$cart->add_fee( $line['name'], -$line['amount'], $taxable, $line['tax_class'] );
	}
}
add_action( 'woocommerce_cart_calculate_fees', 'roova_vip_apply_cart_discount' );

/**
 * What a tier's free nights and percentage would take off a cart, without
 * taking it.
 *
 * The one place the arithmetic lives. roova_vip_apply_cart_discount() turns
 * these lines into fees for a member; roova_vip_signup_total() reads them to
 * tell a guest what signing up would save — so the figure the checkout quotes
 * is the figure the checkout would then charge.
 *
 * @param WC_Cart $cart      Cart.
 * @param string  $tier_name Tier name, for the labels.
 * @param float   $percent   Discount percentage.
 * @param int     $free      Free nights.
 * @return array[] Each: name, amount (positive), tax_class. Free nights first.
 */
function roova_vip_cart_reductions( $cart, $tier_name, $percent, $free ) {
	$lines = array();

	if ( ! $cart instanceof WC_Cart || ! $cart->get_cart() || ( $percent <= 0 && $free < 1 ) ) {
		return $lines;
	}

	/*
	 * The line totals, not the subtotal: `line_total` is what each room costs
	 * after any coupon, and it is settled before fees are calculated. Taking the
	 * percentage off the pre-coupon subtotal would let a coupon and a tier
	 * together discount more than the stay is worth.
	 */
	$base      = 0.0;
	$tax_class = '';

	foreach ( $cart->get_cart() as $item ) {
		$base += isset( $item['line_total'] ) ? (float) $item['line_total'] : 0.0;

		if ( '' === $tax_class && ! empty( $item['data'] ) && is_object( $item['data'] ) ) {
			$tax_class = (string) $item['data']->get_tax_class();
		}
	}

	if ( $base <= 0 ) {
		return $lines;
	}

	$decimals = wc_get_price_decimals();

	$credit = roova_vip_free_nights_credit( $cart, $free, $base, $decimals );

	if ( $credit['amount'] > 0 ) {
		$lines[] = array(
			'name'      => roova_vip_free_nights_label( $tier_name, $credit['nights'] ),
			'amount'    => $credit['amount'],
			'tax_class' => $tax_class,
		);
	}

	// Off the same subtotal the free nights came off — but never more than the
	// stay has left to give.
	$amount = min(
		roova_vip_discount_amount( $base, $percent, $decimals ),
		$base - $credit['amount']
	);

	if ( $amount > 0 ) {
		$lines[] = array(
			'name'      => roova_vip_discount_label( $tier_name, $percent ),
			'amount'    => $amount,
			'tax_class' => $tax_class,
		);
	}

	return $lines;
}

/**
 * The tier signing up puts a new member on.
 *
 * The one a count of no completed stays reaches — which is the lowest tier,
 * because that is the floor (see roova_vip_current_index()).
 *
 * @return array|null Null when there is no programme.
 */
function roova_vip_signup_tier() {
	return roova_vip_tier_for_count( 0 );
}

/**
 * What this cart would come to for a guest who signed up now.
 *
 * The entry tier's free nights and percentage, worked out by the same
 * roova_vip_cart_reductions() the member's own checkout uses, then taken off
 * the grand total **with the tax that falls with them**. WooCommerce splits a
 * negative fee's tax across the rooms in proportion to what they are taxed, so
 * the rooms' own effective rate — their tax over their total — is the rate the
 * saving comes off at. That is what makes a 2% discount take exactly 2% off a
 * taxed total rather than 2% of the subtotal.
 *
 * Null whenever signing up would change nothing: a signed-in member, no
 * programme, the discount switched off, or an entry tier that gives nothing.
 * The checkout only makes the claim when there is something to claim.
 *
 * @param WC_Cart|null $cart Cart, or null for the current one.
 * @return float|null
 */
function roova_vip_signup_total( $cart = null ) {
	if ( null === $cart && function_exists( 'WC' ) && WC()->cart ) {
		$cart = WC()->cart;
	}

	if ( get_current_user_id() || ! $cart instanceof WC_Cart ) {
		return null;
	}

	if ( ! roova_vip_enabled() || ! roova_vip_discount_enabled() ) {
		return null;
	}

	$tier = roova_vip_signup_tier();
	if ( ! $tier ) {
		return null;
	}

	$lines = roova_vip_cart_reductions(
		$cart,
		$tier['name'],
		roova_vip_tier_discount( $tier ),
		roova_vip_tier_free_nights( $tier )
	);

	if ( ! $lines ) {
		return null;
	}

	$decimals = wc_get_price_decimals();

	$items    = (float) $cart->get_cart_contents_total();
	$tax_rate = ( wc_tax_enabled() && $items > 0 ) ? (float) $cart->get_cart_contents_tax() / $items : 0.0;

	$saving = 0.0;
	foreach ( $lines as $line ) {
		$saving += $line['amount'] + round( $line['amount'] * $tax_rate, $decimals );
	}

	$total = (float) $cart->get_total( 'edit' );

	/**
	 * Filter the total a guest is told they would pay as a member.
	 *
	 * @param float   $signup Total with the entry tier's benefits taken off.
	 * @param WC_Cart $cart   Cart.
	 * @param array   $tier   The tier signing up would put them on.
	 */
	return (float) apply_filters( 'roova_vip_signup_total', max( 0.0, round( $total - $saving, $decimals ) ), $cart, $tier );
}

/**
 * A tier's benefits with its discount drawn as the first of them.
 *
 * Generated rather than typed, so the figure a member reads on the VIP tab
 * cannot drift from the one their checkout actually takes off.
 *
 * @param array|null $tier Tier.
 * @return array[] icon, title, note.
 */
function roova_vip_tier_benefits( $tier ) {
	$benefits = ( is_array( $tier ) && ! empty( $tier['benefits'] ) ) ? (array) $tier['benefits'] : array();
	$honoured = roova_vip_discount_enabled();

	$percent = $honoured ? roova_vip_tier_discount( $tier ) : 0.0;
	$free    = $honoured ? roova_vip_tier_free_nights( $tier ) : 0;

	if ( $percent > 0 ) {
		array_unshift(
			$benefits,
			array(
				'icon'  => 'percent',
				'title' => sprintf(
					/* translators: %s: discount percentage, e.g. "10" */
					__( '%s%% off every booking', 'roova' ),
					roova_vip_format_percent( $percent )
				),
				'note'  => __( 'Taken off your total automatically at checkout.', 'roova' ),
			)
		);
	}

	// Ahead of the percentage, because it is the larger promise and the one a
	// member is likelier to have joined for.
	if ( $free > 0 ) {
		array_unshift(
			$benefits,
			array(
				'icon'  => 'bed-double',
				'title' => sprintf(
					/* translators: %s: number of free nights */
					_n( '%s free night on every stay', '%s free nights on every stay', $free, 'roova' ),
					number_format_i18n( $free )
				),
				'note'  => __( 'Taken off your total at checkout, up to the length of the stay.', 'roova' ),
			)
		);
	}

	return $benefits;
}
