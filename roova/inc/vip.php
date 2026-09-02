<?php
/**
 * RoovaVIP: the tiers a member climbs, and the benefits each one carries.
 *
 * Tiers are earned by **completed bookings only** — no spend thresholds, no
 * expiry dates. That is the handoff's rule and it is also the only one this
 * theme can honour without inventing a figure: a completed stay is a fact the
 * bookings table already knows.
 *
 * Nothing here changes what a guest is charged. The tab shows the tier, the
 * progress rail and the benefit list; the benefits themselves are promises the
 * hotel keeps at the desk, which is why every one of them is text the client
 * writes rather than logic the theme runs.
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
 * @return array[] Each: name, min, benefits[] (icon, title, note).
 */
function roova_vip_default_tiers() {
	return array(
		array(
			'name'     => __( 'Bronze', 'roova' ),
			'min'      => 0,
			'benefits' => array(),
		),
		array(
			'name'     => __( 'VIP Silver', 'roova' ),
			'min'      => 2,
			'benefits' => array(),
		),
		array(
			'name'     => __( 'VIP Gold', 'roova' ),
			'min'      => 5,
			'benefits' => array(
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
			'name'     => __( 'VIP Platinum', 'roova' ),
			'min'      => 10,
			'benefits' => array(),
		),
		array(
			'name'     => __( 'VIP Diamond', 'roova' ),
			'min'      => 15,
			'benefits' => array(),
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
			'name'     => $name,
			'min'      => isset( $tier['min'] ) ? max( 0, (int) $tier['min'] ) : 0,
			'benefits' => $benefits,
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
