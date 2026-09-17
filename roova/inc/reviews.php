<?php
/**
 * Guest reviews of a hotel.
 *
 * A review is a **WooCommerce product review** — a comment on the hotel product
 * with a `rating` meta — and nothing here reimplements that. WooCommerce
 * already recounts `_wc_average_rating` when a review is approved, moderation
 * settings already apply, and the client already has a Comments screen to
 * moderate from. What the theme adds is the three sub-scores the design asks
 * for (Cleanliness · Location · Service) and the rule about who may write one:
 *
 *   **only a guest who has actually completed a stay at that hotel**, and only
 *   once per hotel.
 *
 * The rating field is deliberately named `rating`, not `roova_rating`: that is
 * the key WooCommerce's own `preprocess_comment` check and its rating-meta
 * handler read, and renaming it would make WooCommerce refuse the comment.
 *
 * The hotel page's score comes from these reviews as soon as there is one; the
 * numbers typed into the Hotel Details tab are the fallback for a hotel nobody
 * has reviewed yet, not a second source of truth.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * The three sub-scores a review carries, as comment meta key => label.
 *
 * @return array
 */
function roova_review_subscores() {
	/**
	 * Filter the sub-scores collected with a review.
	 *
	 * @param array $scores meta key => label.
	 */
	return apply_filters(
		'roova_review_subscores',
		array(
			'roova_score_cleanliness' => __( 'Cleanliness', 'roova' ),
			'roova_score_location'    => __( 'Location', 'roova' ),
			'roova_score_service'     => __( 'Service', 'roova' ),
		)
	);
}

/**
 * Can a review be written for this hotel?
 *
 * Follows WooCommerce's own Reviews setting and the product's discussion
 * setting — a client who switches reviews off means it, and the theme should
 * not post comments behind their back.
 *
 * @param int $hotel_id Hotel product ID.
 * @return bool
 */
function roova_reviews_open( $hotel_id ) {
	$open = function_exists( 'wc_reviews_enabled' ) ? wc_reviews_enabled() : true;

	if ( $open && ! comments_open( absint( $hotel_id ) ) ) {
		$open = false;
	}

	/**
	 * Filter whether a hotel accepts reviews.
	 *
	 * @param bool $open     Whether reviews are open.
	 * @param int  $hotel_id Hotel product ID.
	 */
	return (bool) apply_filters( 'roova_reviews_open', $open, absint( $hotel_id ) );
}

/* -------------------------------------------------------------------------
 * Reading
 * ---------------------------------------------------------------------- */

/**
 * One review, flattened into what the templates need.
 *
 * @param WP_Comment $comment Comment.
 * @return array
 */
function roova_review_data( $comment ) {
	$hotel_id  = (int) $comment->comment_post_ID;
	$subscores = array();

	foreach ( roova_review_subscores() as $key => $label ) {
		$value = get_comment_meta( $comment->comment_ID, $key, true );
		if ( '' !== $value && null !== $value ) {
			$subscores[ $label ] = (float) $value;
		}
	}

	return array(
		'id'        => (int) $comment->comment_ID,
		'hotel_id'  => $hotel_id,
		'hotel'     => get_the_title( $hotel_id ),
		'url'       => get_permalink( $hotel_id ),
		'rating'    => (int) get_comment_meta( $comment->comment_ID, 'rating', true ),
		'body'      => $comment->comment_content,
		'date'      => $comment->comment_date,
		'approved'  => '1' === (string) $comment->comment_approved,
		'subscores' => $subscores,
	);
}

/**
 * Every review a member has written, newest first.
 *
 * Unapproved reviews are included — they are the member's own words and hiding
 * them would read as a review that never saved. The card says it is waiting.
 *
 * @param int $user_id User ID, or 0 for the current user.
 * @return array[]
 */
function roova_user_reviews( $user_id = 0 ) {
	$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
	if ( ! $user_id ) {
		return array();
	}

	static $cache = array();
	if ( isset( $cache[ $user_id ] ) ) {
		return $cache[ $user_id ];
	}

	$comments = get_comments(
		array(
			'user_id'    => $user_id,
			'post_type'  => 'product',
			'status'     => 'all',
			'orderby'    => 'comment_date_gmt',
			'order'      => 'DESC',
			'no_found_rows' => true,
		)
	);

	$reviews = array();
	foreach ( $comments as $comment ) {
		// Spam and trash are not "waiting" — they are gone.
		if ( in_array( (string) $comment->comment_approved, array( 'spam', 'trash' ), true ) ) {
			continue;
		}

		if ( ! roova_is_hotel( $comment->comment_post_ID ) ) {
			// Only hotels are reviewed here; a review on anything else is a stray.
			continue;
		}

		$reviews[] = roova_review_data( $comment );
	}

	$cache[ $user_id ] = $reviews;

	return $reviews;
}

/**
 * Has this member already reviewed this hotel?
 *
 * @param int $hotel_id Hotel product ID.
 * @param int $user_id  User ID, or 0 for the current user.
 * @return bool
 */
function roova_user_has_reviewed( $hotel_id, $user_id = 0 ) {
	$hotel_id = absint( $hotel_id );

	foreach ( roova_user_reviews( $user_id ) as $review ) {
		if ( $review['hotel_id'] === $hotel_id ) {
			return true;
		}
	}

	return false;
}

/**
 * Stays this member could still review: completed, and not yet written up.
 *
 * One entry per hotel — a guest who has stayed at the same hotel three times is
 * asked once, and their review covers the hotel rather than a single night.
 *
 * @param int $user_id User ID, or 0 for the current user.
 * @return array[] Stay rows from roova_account_stays().
 */
function roova_reviewable_stays( $user_id = 0 ) {
	$seen = array();
	$open = array();

	foreach ( roova_account_stays( $user_id ) as $stay ) {
		if ( 'completed' !== $stay['status'] || ! $stay['hotel_id'] ) {
			continue;
		}

		if ( isset( $seen[ $stay['hotel_id'] ] ) ) {
			continue;
		}
		$seen[ $stay['hotel_id'] ] = true;

		if ( roova_user_has_reviewed( $stay['hotel_id'], $user_id ) ) {
			continue;
		}

		if ( ! roova_reviews_open( $stay['hotel_id'] ) ) {
			continue;
		}

		$open[] = $stay;
	}

	return $open;
}

/**
 * May this member review this hotel right now?
 *
 * @param int $hotel_id Hotel product ID.
 * @param int $user_id  User ID, or 0 for the current user.
 * @return bool
 */
function roova_can_review( $hotel_id, $user_id = 0 ) {
	$hotel_id = absint( $hotel_id );

	foreach ( roova_reviewable_stays( $user_id ) as $stay ) {
		if ( $stay['hotel_id'] === $hotel_id ) {
			return true;
		}
	}

	return false;
}

/* -------------------------------------------------------------------------
 * Aggregation
 * ---------------------------------------------------------------------- */

/**
 * A hotel's score, review count and sub-score averages.
 *
 * The count is WooCommerce's own — it maintains that when a review is approved
 * — and everything else is averaged straight out of comment meta: each
 * criterion for the breakdown, and the mean of the reviews' own overalls for
 * the score. That last one is deliberately *not* WooCommerce's average, which
 * aggregates whole stars: a single 5/4/5 review is a 5 to WooCommerce and a 4.7
 * on its own card, and one page should not quote two different scores. It stays
 * the fallback for reviews carrying no sub-scores at all.
 *
 * `count` is 0 for a hotel nobody has reviewed, which is the caller's cue to
 * fall back to the numbers typed on the Hotel Details tab.
 *
 * @param int $hotel_id Hotel product ID.
 * @return array count, score, subscores (label => float).
 */
function roova_hotel_review_summary( $hotel_id ) {
	global $wpdb;

	$hotel_id = absint( $hotel_id );
	static $cache = array();

	if ( isset( $cache[ $hotel_id ] ) ) {
		return $cache[ $hotel_id ];
	}

	$summary = array(
		'count'     => 0,
		'score'     => 0.0,
		'subscores' => array(),
	);

	$product = function_exists( 'wc_get_product' ) ? wc_get_product( $hotel_id ) : null;
	if ( ! $product ) {
		$cache[ $hotel_id ] = $summary;
		return $summary;
	}

	$summary['count'] = (int) $product->get_review_count();
	$summary['score'] = round( (float) $product->get_average_rating(), 1 );

	if ( $summary['count'] > 0 ) {
		$labels       = roova_review_subscores();
		$keys         = array_keys( $labels );
		$placeholders = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );

		/*
		 * One row per sub-score rather than one per criterion, because two
		 * different averages are wanted from it: the per-criterion figures the
		 * breakdown prints, and the mean of each review's own overall — which is
		 * the score itself. Averaging every sub-score in one go would give a
		 * third number again the moment one review is missing a criterion.
		 */
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT cm.comment_id AS review_id, cm.meta_key AS score_key, cm.meta_value AS score_value
				FROM {$wpdb->commentmeta} cm
				INNER JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id
				WHERE c.comment_post_ID = %d
					AND c.comment_approved = '1'
					AND cm.meta_key IN ( {$placeholders} )",
				array_merge( array( $hotel_id ), $keys )
			),
			ARRAY_A
		);
		// phpcs:enable

		$by_key    = array();
		$by_review = array();

		foreach ( (array) $rows as $row ) {
			$key = isset( $row['score_key'] ) ? $row['score_key'] : '';
			if ( ! isset( $labels[ $key ] ) ) {
				continue;
			}

			$value = (float) $row['score_value'];

			$by_key[ $key ][]                        = $value;
			$by_review[ (int) $row['review_id'] ][] = $value;
		}

		foreach ( $labels as $key => $label ) {
			if ( ! empty( $by_key[ $key ] ) ) {
				$summary['subscores'][ $label ] = round( array_sum( $by_key[ $key ] ) / count( $by_key[ $key ] ), 1 );
			}
		}

		/*
		 * The score the whole site quotes is the average of the overalls the
		 * review cards print — (cleanliness + location + service) / 3, meaned
		 * across the reviews. WooCommerce's own average, set above, stays the
		 * fallback: it is all there is for a review written before the
		 * sub-scores existed, and it is what the star ratings aggregate.
		 */
		if ( $by_review ) {
			$overalls = array();

			foreach ( $by_review as $values ) {
				$overalls[] = array_sum( $values ) / count( $values );
			}

			$summary['score'] = round( array_sum( $overalls ) / count( $overalls ), 1 );
		}
	}

	$cache[ $hotel_id ] = $summary;

	return $summary;
}

/**
 * The rating shown on a hotel card, out of 5.
 *
 * Real reviews first; the Hotel Details score is the stand-in until there are
 * any. A score typed on a ten-point scale is halved so the star beside it never
 * promises "★ 8.9".
 *
 * @param int $hotel_id Hotel product ID.
 * @return float 0.0 when there is nothing to show.
 */
function roova_hotel_rating( $hotel_id ) {
	$summary = roova_hotel_review_summary( $hotel_id );

	if ( $summary['count'] > 0 && $summary['score'] > 0 ) {
		return $summary['score'];
	}

	$details = roova_get_hotel_details( $hotel_id );
	$score   = (float) $details['score'];

	if ( $score <= 0 ) {
		return 0.0;
	}

	return $score > 5 ? round( $score / 2, 1 ) : $score;
}

/* -------------------------------------------------------------------------
 * Writing
 * ---------------------------------------------------------------------- */

/**
 * Store a review written from the account page.
 *
 * The comment itself goes in through `wp_new_comment()` so WordPress applies
 * its own moderation, flood and duplicate rules — the theme decides only *who*
 * may write, never whether the comment is publishable.
 *
 * @param array $args hotel_id, rating, body, subscores (key => int).
 * @return int|WP_Error Comment ID, or an error to show on the form.
 */
function roova_submit_review( $args ) {
	$args = wp_parse_args( $args, array(
		'hotel_id'  => 0,
		'rating'    => 0,
		'body'      => '',
		'subscores' => array(),
	) );

	$hotel_id = absint( $args['hotel_id'] );
	$user     = wp_get_current_user();

	if ( ! $user || ! $user->ID ) {
		return new WP_Error( 'roova_review_auth', __( 'Sign in to write a review.', 'roova' ) );
	}

	if ( ! roova_can_review( $hotel_id, $user->ID ) ) {
		return new WP_Error(
			'roova_review_denied',
			__( 'Reviews are for stays you have completed, and one per hotel.', 'roova' )
		);
	}

	$rating = (int) $args['rating'];
	if ( $rating < 1 || $rating > 5 ) {
		return new WP_Error( 'rating', __( 'Choose a rating from 1 to 5 stars.', 'roova' ) );
	}

	$body = trim( (string) $args['body'] );
	if ( mb_strlen( $body ) < 10 ) {
		return new WP_Error( 'body', __( 'Tell other guests a little more — at least a sentence.', 'roova' ) );
	}

	$comment_id = wp_new_comment(
		array(
			'comment_post_ID'      => $hotel_id,
			'comment_content'      => $body,
			'comment_type'         => 'review',
			'comment_parent'       => 0,
			'user_id'              => $user->ID,
			'comment_author'       => $user->display_name,
			'comment_author_email' => $user->user_email,
			'comment_author_url'   => '',
		),
		true
	);

	if ( is_wp_error( $comment_id ) ) {
		return new WP_Error( 'roova_review_failed', wp_strip_all_tags( $comment_id->get_error_message() ) );
	}

	if ( ! $comment_id ) {
		return new WP_Error( 'roova_review_failed', __( 'That review could not be saved. Please try again.', 'roova' ) );
	}

	/*
	 * WooCommerce writes the rating meta itself when the form posts a `rating`
	 * field, but the account form is the theme's own — so write it here and let
	 * the update be a no-op when WooCommerce got there first.
	 */
	update_comment_meta( $comment_id, 'rating', $rating );

	/*
	 * Verified, and truthfully so: roova_can_review() has already established
	 * that this member completed a stay at this hotel. WooCommerce's own check
	 * looks for a purchase of *this* product, which never happens — a guest buys
	 * a room, and the review is on its hotel.
	 */
	update_comment_meta( $comment_id, 'verified', 1 );

	foreach ( roova_review_subscores() as $key => $label ) {
		$value = isset( $args['subscores'][ $key ] ) ? (int) $args['subscores'][ $key ] : 0;
		if ( $value >= 1 && $value <= 5 ) {
			update_comment_meta( $comment_id, $key, $value );
		}
	}

	/*
	 * WooCommerce recounts a product's score the moment a comment is inserted
	 * — which is above, before any of this meta exists, so the recount reads a
	 * review with no rating and settles on zero. Ask for it again now that the
	 * rating is written, or a hotel's score box and its card star disappear the
	 * moment its first review is published.
	 */
	if ( class_exists( 'WC_Comments' ) ) {
		WC_Comments::clear_transients( $hotel_id );
	}

	/**
	 * Fires after a member reviews a hotel from their account.
	 *
	 * @param int $comment_id Comment ID.
	 * @param int $hotel_id   Hotel product ID.
	 * @param int $user_id    User ID.
	 */
	do_action( 'roova_review_submitted', $comment_id, $hotel_id, $user->ID );

	return (int) $comment_id;
}

/* -------------------------------------------------------------------------
 * Markup
 * ---------------------------------------------------------------------- */

/**
 * The five-glyph star row on a review card.
 *
 * Glyphs rather than SVG: the design sets them on a 2px letter-spaced line, and
 * a half-filled row has to align with the date beside it on the baseline.
 *
 * @param float $rating Rating out of 5.
 * @return string
 */
function roova_review_stars( $rating ) {
	$filled = max( 0, min( 5, (int) round( (float) $rating ) ) );

	return sprintf(
		'<span class="roova-review__stars" role="img" aria-label="%s"><span class="roova-review__stars-on">%s</span><span class="roova-review__stars-off">%s</span></span>',
		esc_attr(
			sprintf(
				/* translators: %s: rating out of five */
				__( '%s out of 5', 'roova' ),
				number_format_i18n( (float) $rating, 1 )
			)
		),
		esc_html( str_repeat( '★', $filled ) ),
		esc_html( str_repeat( '★', 5 - $filled ) )
	);
}

/* -------------------------------------------------------------------------
 * The hotel page's reviews section
 *
 * Everything below draws — and accepts — reviews on the hotel page itself. The
 * account page keeps its own copy of the form, because a member who never
 * reopens a hotel page can still rate a stay from there, and both post through
 * roova_submit_review(), so the rule about who may write lives in one place.
 * ---------------------------------------------------------------------- */

/**
 * A review's overall score: the average of the sub-scores it carries.
 *
 * The design states it plainly — overall is (cleanliness + location + service)
 * / 3 — so it is derived here rather than read back from the `rating` meta.
 * That meta is the whole-star version WooCommerce aggregates the hotel's score
 * from; this is the number a guest reads on the card. A review written before
 * the sub-scores existed has none, and falls back to its star rating.
 *
 * @param array $review Row from roova_review_data().
 * @return float Out of 5, to one decimal.
 */
function roova_review_overall( $review ) {
	$scores = isset( $review['subscores'] ) ? array_filter( (array) $review['subscores'] ) : array();

	if ( $scores ) {
		return round( array_sum( $scores ) / count( $scores ), 1 );
	}

	return (float) ( isset( $review['rating'] ) ? $review['rating'] : 0 );
}

/**
 * Approved reviews of a hotel, sorted and cut to a page.
 *
 * Sorting happens in PHP rather than SQL on purpose: the list is ordered by the
 * same overall figure the cards print, and that figure is an average of comment
 * meta which no single `orderby` reproduces. The cost is that only the most
 * recent `roova_hotel_reviews_max` reviews are considered — 300 by default,
 * filterable, and well past what any of these properties carries.
 *
 * @param int   $hotel_id Hotel product ID.
 * @param array $args     sort (recent|high|low), number.
 * @return array total, shown, items.
 */
function roova_hotel_reviews( $hotel_id, $args = array() ) {
	$hotel_id = absint( $hotel_id );
	$args     = wp_parse_args(
		$args,
		array(
			'sort'   => 'recent',
			'number' => 5,
		)
	);

	static $cache = array();

	if ( ! isset( $cache[ $hotel_id ] ) ) {
		/**
		 * Filter how many of a hotel's reviews are read for the list.
		 *
		 * @param int $max      Maximum reviews fetched.
		 * @param int $hotel_id Hotel product ID.
		 */
		$max = (int) apply_filters( 'roova_hotel_reviews_max', 300, $hotel_id );

		$comments = get_comments(
			array(
				'post_id'       => $hotel_id,
				'status'        => 'approve',
				'parent'        => 0,
				'orderby'       => 'comment_date_gmt',
				'order'         => 'DESC',
				'number'        => max( 1, $max ),
				'no_found_rows' => true,
			)
		);

		$rows = array();

		foreach ( $comments as $comment ) {
			$row = roova_review_data( $comment );

			$row['overall']  = roova_review_overall( $row );
			$row['author']   = $comment->comment_author;
			$row['verified'] = (bool) get_comment_meta( $comment->comment_ID, 'verified', true );

			$rows[] = $row;
		}

		$cache[ $hotel_id ] = $rows;
	}

	$rows = $cache[ $hotel_id ];

	if ( 'high' === $args['sort'] || 'low' === $args['sort'] ) {
		$direction = 'high' === $args['sort'] ? -1 : 1;

		usort(
			$rows,
			static function ( $a, $b ) use ( $direction ) {
				if ( $a['overall'] === $b['overall'] ) {
					// Same score: the newer review wins, so the order is stable
					// rather than however the query happened to land.
					return strcmp( $b['date'], $a['date'] );
				}

				return ( $a['overall'] < $b['overall'] ? -1 : 1 ) * $direction;
			}
		);
	}

	$number = max( 1, (int) $args['number'] );

	return array(
		'total' => count( $rows ),
		'shown' => min( $number, count( $rows ) ),
		'items' => array_slice( $rows, 0, $number ),
	);
}

/**
 * The visitor's own review of this hotel, when it is still awaiting moderation.
 *
 * WordPress holds a comment from an author who has never had one approved —
 * `comment_previously_approved`, on by default — so a guest's *first* review
 * anywhere on the site is invisible the moment it is written. Without this the
 * hotel page answers a submission with "you have already reviewed this hotel"
 * and nothing else, which reads as a review that failed to save. The Reviews
 * tab in My account has always shown the author their own pending review; this
 * is the same promise kept on the page they wrote it from.
 *
 * It is deliberately kept out of the list, the count and the averages: it is
 * not public yet, and nobody else can see it.
 *
 * @param int $hotel_id Hotel product ID.
 * @param int $user_id  User ID, or 0 for the current user.
 * @return array|null Review row, or null when there is nothing waiting.
 */
function roova_hotel_pending_review( $hotel_id, $user_id = 0 ) {
	$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
	$hotel_id = absint( $hotel_id );

	if ( ! $user_id || ! $hotel_id ) {
		return null;
	}

	foreach ( roova_user_reviews( $user_id ) as $review ) {
		if ( $review['hotel_id'] !== $hotel_id || $review['approved'] ) {
			continue;
		}

		$comment = get_comment( $review['id'] );

		$review['overall']  = roova_review_overall( $review );
		$review['author']   = $comment ? $comment->comment_author : '';
		$review['verified'] = (bool) get_comment_meta( $review['id'], 'verified', true );

		return $review;
	}

	return null;
}

/**
 * Why the visitor may — or may not — review this hotel.
 *
 * roova_can_review() answers yes or no; the form needs the reason, because
 * "sign in" and "reviews are for stays you have completed" are different
 * sentences and a guest deserves the right one.
 *
 * @param int $hotel_id Hotel product ID.
 * @return string ok | closed | signed-out | no-stay | reviewed.
 */
function roova_hotel_review_gate( $hotel_id ) {
	$hotel_id = absint( $hotel_id );

	if ( ! roova_reviews_open( $hotel_id ) ) {
		return 'closed';
	}

	if ( ! is_user_logged_in() ) {
		return 'signed-out';
	}

	if ( roova_user_has_reviewed( $hotel_id ) ) {
		return 'reviewed';
	}

	/*
	 * The rule the client asked for: the box opens for a completed stay and
	 * nothing else. An upcoming booking, one still waiting for payment and a
	 * cancelled one all read as "no stay yet" — see roova_account_stay_status().
	 */
	foreach ( roova_account_stays() as $stay ) {
		if ( (int) $stay['hotel_id'] === $hotel_id && 'completed' === $stay['status'] ) {
			return 'ok';
		}
	}

	return 'no-stay';
}

/* -------------------------------------------------------------------------
 * Posting from the hotel page
 * ---------------------------------------------------------------------- */

/**
 * Handle a review posted from a hotel page.
 *
 * Runs before the template, like every other form in the theme, and ends a
 * success in a redirect: a reload must never repost a review.
 */
function roova_hotel_review_handle() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the action only selects this handler; the nonce is checked below.
	$action = isset( $_POST['roova_hotel_review_action'] ) ? sanitize_key( wp_unslash( $_POST['roova_hotel_review_action'] ) ) : '';

	if ( 'review' !== $action ) {
		return;
	}

	if ( ! isset( $_POST['roova_hotel_review_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['roova_hotel_review_nonce'] ) ), 'roova_hotel_review' ) ) {
		roova_auth_add_error( 'form', __( 'That form had expired. Please try again.', 'roova' ) );
		return;
	}

	$hotel_id = isset( $_POST['roova_hotel_id'] ) ? absint( $_POST['roova_hotel_id'] ) : 0;

	if ( ! roova_is_hotel( $hotel_id ) ) {
		roova_auth_add_error( 'form', __( 'That hotel could not be found.', 'roova' ) );
		return;
	}

	$body = isset( $_POST['roova_review_body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['roova_review_body'] ) ) : '';

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value is cast to an int below.
	$posted = isset( $_POST['roova_subscore'] ) && is_array( $_POST['roova_subscore'] ) ? wp_unslash( $_POST['roova_subscore'] ) : array();

	$subscores = array();
	$missing   = false;

	foreach ( roova_review_subscores() as $key => $label ) {
		$value = isset( $posted[ $key ] ) ? absint( $posted[ $key ] ) : 0;

		if ( $value < 1 || $value > 5 ) {
			$missing = true;
			$value   = 0;
		}

		$subscores[ $key ] = $value;
		roova_auth_set_value( 'review_score_' . $key, (string) $value );
	}

	roova_auth_set_value( 'review_body', $body );

	if ( $missing ) {
		roova_auth_add_error( 'review_scores', __( 'Rate all three — cleanliness, location and service.', 'roova' ) );
		return;
	}

	/*
	 * The overall score is the average of the three, and the star rating stored
	 * with the comment is that average rounded: WooCommerce aggregates whole
	 * stars, and the hotel score it maintains has to mean the same thing as the
	 * figure the cards print.
	 */
	$rating = (int) round( array_sum( $subscores ) / count( $subscores ) );

	$result = roova_submit_review(
		array(
			'hotel_id'  => $hotel_id,
			'rating'    => $rating,
			'body'      => $body,
			'subscores' => $subscores,
		)
	);

	if ( is_wp_error( $result ) ) {
		$field = 'body' === $result->get_error_code() ? 'review_body' : 'form';

		roova_auth_add_error( $field, $result->get_error_message() );
		return;
	}

	wp_safe_redirect( add_query_arg( 'roova_reviewed', '1', get_permalink( $hotel_id ) ) . '#reviews' );
	exit;
}
add_action( 'template_redirect', 'roova_hotel_review_handle', 5 );

/* -------------------------------------------------------------------------
 * Markup — the hotel page section
 * ---------------------------------------------------------------------- */

/**
 * The one-line hint under each sub-score's name on the form.
 *
 * Keyed by meta key so a site that filters roova_review_subscores() into a
 * fourth criterion simply gets no hint for it rather than a mismatched one.
 *
 * @return array
 */
function roova_review_subscore_hints() {
	/**
	 * Filter the hints shown beside each sub-score.
	 *
	 * @param array $hints meta key => hint.
	 */
	return apply_filters(
		'roova_review_subscore_hints',
		array(
			'roova_score_cleanliness' => array(
				'icon' => 'housekeeping',
				'hint' => __( 'Room, bathroom, linens', 'roova' ),
			),
			'roova_score_location'    => array(
				'icon' => 'pin',
				'hint' => __( "Getting around, what's nearby", 'roova' ),
			),
			'roova_score_service'     => array(
				'icon' => 'concierge',
				'hint' => __( 'Staff, check-in, responsiveness', 'roova' ),
			),
		)
	);
}

/**
 * Which order the list is in. A display-only GET parameter.
 *
 * @return string recent | high | low.
 */
function roova_hotel_reviews_sort() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sorts a public list; it changes nothing.
	$sort = isset( $_GET['roova_review_sort'] ) ? sanitize_key( wp_unslash( $_GET['roova_review_sort'] ) ) : 'recent';

	return in_array( $sort, array( 'recent', 'high', 'low' ), true ) ? $sort : 'recent';
}

/**
 * How many reviews the list is showing.
 *
 * "Show more" is a link carrying the next number rather than a script, so the
 * list keeps working with JavaScript blocked — and a guest can link someone
 * straight to a longer page of it.
 *
 * @return int
 */
function roova_hotel_reviews_shown() {
	$step = (int) apply_filters( 'roova_hotel_reviews_step', 5 );
	$step = max( 1, $step );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- pages a public list; it changes nothing.
	$shown = isset( $_GET['roova_reviews_shown'] ) ? absint( wp_unslash( $_GET['roova_reviews_shown'] ) ) : $step;

	// Bounded at both ends: 0 would show an empty list, and an invented number
	// in the address bar should not turn the page into a full table scan.
	return max( $step, min( $shown, 200 ) );
}

/**
 * The URL of this section with different list settings on it.
 *
 * @param int   $hotel_id Hotel product ID.
 * @param array $args     Query arguments to set.
 * @return string
 */
function roova_hotel_reviews_url( $hotel_id, $args ) {
	return add_query_arg( $args, get_permalink( absint( $hotel_id ) ) ) . '#reviews';
}

/**
 * The guest reviews section on a hotel page: the form, then the reviews.
 *
 * Draws nothing at all for a hotel with no reviews that this visitor cannot
 * review either — an empty "no reviews yet" panel on every page of a new site
 * is noise, not information.
 *
 * @param int $hotel_id Hotel product ID.
 */
function roova_hotel_reviews_section( $hotel_id ) {
	$hotel_id = absint( $hotel_id );
	$gate     = roova_hotel_review_gate( $hotel_id );
	$sort     = roova_hotel_reviews_sort();
	$shown    = roova_hotel_reviews_shown();

	$list = roova_hotel_reviews(
		$hotel_id,
		array(
			'sort'   => $sort,
			'number' => $shown,
		)
	);

	$pending = roova_hotel_pending_review( $hotel_id );

	/*
	 * A review still in moderation is reason enough to draw the section: its
	 * author has to be able to see that it exists, even on a hotel with nothing
	 * else on it yet.
	 */
	if ( ! $list['total'] && ! $pending && 'ok' !== $gate ) {
		return;
	}

	$summary = roova_hotel_review_summary( $hotel_id );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a display flag on the GET after a redirect.
	$just_saved = isset( $_GET['roova_reviewed'] );
	?>
	<section class="roova-card roova-greviews" id="reviews">
		<div class="roova-greviews__head">
			<div class="roova-greviews__heading">
				<h2 class="roova-card__title"><?php esc_html_e( 'Guest reviews', 'roova' ); ?></h2>
				<p class="roova-greviews__sub">
					<?php if ( $list['total'] ) : ?>
						<?php
						printf(
							/* translators: %s: number of reviews */
							esc_html( _n( '%s review from a verified stay', '%s reviews from verified stays', $list['total'], 'roova' ) ),
							esc_html( number_format_i18n( $list['total'] ) )
						);
						?>
					<?php elseif ( $pending ) : ?>
						<?php esc_html_e( 'Nothing published yet — yours is the first.', 'roova' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'No reviews yet — yours would be the first.', 'roova' ); ?>
					<?php endif; ?>
				</p>
			</div>

			<?php if ( $list['total'] && $summary['score'] > 0 ) : ?>
				<div class="roova-greviews__summary">
					<p class="roova-greviews__avg">
						<span><?php echo esc_html( number_format_i18n( $summary['score'], 1 ) ); ?></span>
						<small><?php esc_html_e( '/ 5 overall', 'roova' ); ?></small>
					</p>

					<?php if ( $summary['subscores'] ) : ?>
						<dl class="roova-greviews__breakdown">
							<?php foreach ( $summary['subscores'] as $label => $value ) : ?>
								<div>
									<dt><?php echo esc_html( $label ); ?></dt>
									<dd><?php echo esc_html( number_format_i18n( $value, 1 ) ); ?></dd>
								</div>
							<?php endforeach; ?>
						</dl>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $just_saved ) : ?>
			<p class="roova-greviews__flash">
				<?php roova_the_icon( 'check-circle', 16 ); ?>
				<?php esc_html_e( 'Thank you — your review has been sent, and appears once it is approved.', 'roova' ); ?>
			</p>
		<?php endif; ?>

		<?php if ( ! $pending || 'reviewed' !== $gate ) : ?>
			<?php roova_hotel_review_form( $hotel_id, $gate ); ?>
		<?php endif; ?>

		<?php if ( $pending ) : ?>
			<div class="roova-greviews__mine">
				<h3 class="roova-greviews__listing"><?php esc_html_e( 'Your review', 'roova' ); ?></h3>
				<?php roova_hotel_review_card( $pending, true ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $list['total'] ) : ?>
			<div class="roova-greviews__bar">
				<h3 class="roova-greviews__listing"><?php esc_html_e( 'What travellers say', 'roova' ); ?></h3>

				<?php if ( $list['total'] > 1 ) : ?>
					<div class="roova-greviews__sort">
						<span><?php esc_html_e( 'Sort', 'roova' ); ?></span>
						<?php
						$sorts = array(
							'recent' => __( 'Most recent', 'roova' ),
							'high'   => __( 'Highest rated', 'roova' ),
							'low'    => __( 'Lowest rated', 'roova' ),
						);

						foreach ( $sorts as $key => $label ) :
							$url = roova_hotel_reviews_url(
								$hotel_id,
								array(
									'roova_review_sort'   => $key,
									'roova_reviews_shown' => $shown,
								)
							);
							?>
							<a
								class="roova-greviews__sort-link<?php echo $key === $sort ? ' is-active' : ''; ?>"
								href="<?php echo esc_url( $url ); ?>"
								<?php echo $key === $sort ? 'aria-current="true"' : ''; ?>
							>
								<?php echo esc_html( $label ); ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="roova-greviews__list">
				<?php foreach ( $list['items'] as $review ) : ?>
					<?php roova_hotel_review_card( $review ); ?>
				<?php endforeach; ?>
			</div>

			<?php if ( $list['total'] > $list['shown'] ) : ?>
				<?php
				$step = max( 1, (int) apply_filters( 'roova_hotel_reviews_step', 5 ) );
				$more = roova_hotel_reviews_url(
					$hotel_id,
					array(
						'roova_review_sort'   => $sort,
						'roova_reviews_shown' => $shown + $step,
					)
				);
				?>
				<a class="roova-btn roova-btn--ghost roova-greviews__more" href="<?php echo esc_url( $more ); ?>">
					<?php esc_html_e( 'Show more reviews', 'roova' ); ?>
					<?php roova_the_icon( 'chevron-down', 15 ); ?>
				</a>
			<?php endif; ?>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * One review on the hotel page.
 *
 * @param array $review  Row from roova_hotel_reviews().
 * @param bool  $pending Whether this is the author's own, still in moderation.
 */
function roova_hotel_review_card( $review, $pending = false ) {
	$name    = trim( (string) $review['author'] );
	$name    = '' !== $name ? $name : __( 'Guest', 'roova' );
	$initial = mb_strtoupper( mb_substr( $name, 0, 1 ) );
	?>
	<article class="roova-greview<?php echo $pending ? ' roova-greview--pending' : ''; ?>">
		<div class="roova-greview__head">
			<div class="roova-greview__who">
				<span class="roova-greview__avatar" aria-hidden="true"><?php echo esc_html( $initial ); ?></span>
				<div>
					<p class="roova-greview__name"><?php echo esc_html( $name ); ?></p>
					<p class="roova-greview__meta">
						<span><?php echo esc_html( date_i18n( 'M Y', strtotime( $review['date'] ) ) ); ?></span>
						<?php if ( $review['verified'] ) : ?>
							<span class="roova-greview__verified">
								<?php roova_the_icon( 'shield-check', 13 ); ?>
								<?php esc_html_e( 'Verified stay', 'roova' ); ?>
							</span>
						<?php endif; ?>
					</p>
				</div>
			</div>

			<div class="roova-greview__score">
				<?php echo roova_review_stars( $review['overall'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
				<span class="roova-greview__overall"><?php echo esc_html( number_format_i18n( $review['overall'], 1 ) ); ?></span>
			</div>
		</div>

		<?php if ( $review['subscores'] ) : ?>
			<div class="roova-greview__scores">
				<?php foreach ( $review['subscores'] as $label => $value ) : ?>
					<span class="roova-greview__pill">
						<?php echo esc_html( $label ); ?>
						<strong><?php echo esc_html( number_format_i18n( $value, 1 ) ); ?></strong>
					</span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<p class="roova-greview__body"><?php echo esc_html( $review['body'] ); ?></p>

		<?php if ( $pending ) : ?>
			<p class="roova-greview__pending">
				<?php roova_the_icon( 'clock', 14 ); ?>
				<?php esc_html_e( 'Waiting to be published. Only you can see this.', 'roova' ); ?>
			</p>
		<?php endif; ?>
	</article>
	<?php
}

/**
 * The write-a-review form, or the reason there isn't one.
 *
 * The box is open to a member who has *completed* a stay at this hotel and has
 * not already reviewed it. Everybody else gets a sentence saying so, which is
 * the difference between a form that refuses to submit and a page that never
 * asked in the first place.
 *
 * @param int    $hotel_id Hotel product ID.
 * @param string $gate     Result of roova_hotel_review_gate().
 */
function roova_hotel_review_form( $hotel_id, $gate = '' ) {
	$hotel_id = absint( $hotel_id );
	$gate     = $gate ? $gate : roova_hotel_review_gate( $hotel_id );

	if ( 'closed' === $gate ) {
		return;
	}

	if ( 'ok' !== $gate ) {
		$notes = array(
			'signed-out' => __( 'Reviews are written by guests who have stayed here. Sign in to see whether you can add yours.', 'roova' ),
			'no-stay'    => __( 'Reviews are written by guests who have completed a stay here. Yours will open once you check out.', 'roova' ),
			'reviewed'   => __( 'Thanks — you have already reviewed this hotel.', 'roova' ),
		);

		if ( empty( $notes[ $gate ] ) ) {
			return;
		}
		?>
		<p class="roova-greviews__note">
			<?php roova_the_icon( 'info', 15 ); ?>
			<span><?php echo esc_html( $notes[ $gate ] ); ?></span>

			<?php if ( 'signed-out' === $gate ) : ?>
				<a href="<?php echo esc_url( roova_signin_url( get_permalink( $hotel_id ) . '#reviews' ) ); ?>">
					<?php esc_html_e( 'Sign in', 'roova' ); ?>
				</a>
			<?php endif; ?>
		</p>
		<?php
		return;
	}

	$hints      = roova_review_subscore_hints();
	$form_error = roova_auth_error( 'form' );
	$score_err  = roova_auth_error( 'review_scores' );
	$body_err   = roova_auth_error( 'review_body' );
	?>
	<form class="roova-rform" method="post" action="<?php echo esc_url( get_permalink( $hotel_id ) ); ?>#reviews" novalidate>
		<?php wp_nonce_field( 'roova_hotel_review', 'roova_hotel_review_nonce' ); ?>
		<input type="hidden" name="roova_hotel_review_action" value="review" />
		<input type="hidden" name="roova_hotel_id" value="<?php echo esc_attr( $hotel_id ); ?>" />

		<div class="roova-rform__head">
			<h3 class="roova-rform__title"><?php esc_html_e( 'Write a review', 'roova' ); ?></h3>
			<p class="roova-rform__overall">
				<span data-roova-rform-overall>—</span>
				<small><?php esc_html_e( '/ 5 overall', 'roova' ); ?></small>
			</p>
		</div>

		<?php if ( $form_error ) : ?>
			<p class="roova-rform__error"><?php echo esc_html( $form_error ); ?></p>
		<?php endif; ?>

		<div class="roova-rform__rows" data-roova-rform-scores>
			<?php foreach ( roova_review_subscores() as $key => $label ) : ?>
				<?php
				$hint    = isset( $hints[ $key ]['hint'] ) ? $hints[ $key ]['hint'] : '';
				$icon    = isset( $hints[ $key ]['icon'] ) ? $hints[ $key ]['icon'] : 'star';
				$current = (int) roova_auth_value( 'review_score_' . $key );
				?>
				<fieldset class="roova-rform__row">
					<legend class="screen-reader-text"><?php echo esc_html( $label ); ?></legend>

					<div class="roova-rform__label">
						<?php roova_the_icon( $icon, 17 ); ?>
						<span>
							<strong><?php echo esc_html( $label ); ?></strong>
							<?php if ( $hint ) : ?>
								<small><?php echo esc_html( $hint ); ?></small>
							<?php endif; ?>
						</span>
					</div>

					<div class="roova-rate__stars">
						<?php for ( $star = 1; $star <= 5; $star++ ) : ?>
							<label class="roova-rate__star">
								<input
									type="radio"
									name="roova_subscore[<?php echo esc_attr( $key ); ?>]"
									value="<?php echo esc_attr( $star ); ?>"
									<?php checked( $current, $star ); ?>
								/>
								<span aria-hidden="true">★</span>
								<span class="screen-reader-text">
									<?php
									printf(
										/* translators: 1: criterion name, 2: number of stars */
										esc_html__( '%1$s: %2$s out of 5', 'roova' ),
										esc_html( $label ),
										esc_html( number_format_i18n( $star ) )
									);
									?>
								</span>
							</label>
						<?php endfor; ?>
					</div>
				</fieldset>
			<?php endforeach; ?>
		</div>

		<?php if ( $score_err ) : ?>
			<p class="roova-rform__error"><?php echo esc_html( $score_err ); ?></p>
		<?php endif; ?>

		<label class="roova-rform__field">
			<span class="roova-rform__caption"><?php esc_html_e( 'Your review', 'roova' ); ?></span>
			<textarea
				name="roova_review_body"
				rows="5"
				placeholder="<?php esc_attr_e( 'What stood out? Tell other travellers about the room, the staff and the neighbourhood…', 'roova' ); ?>"
			><?php echo esc_textarea( roova_auth_value( 'review_body' ) ); ?></textarea>
		</label>

		<?php if ( $body_err ) : ?>
			<p class="roova-rform__error"><?php echo esc_html( $body_err ); ?></p>
		<?php endif; ?>

		<div class="roova-rform__foot">
			<p class="roova-rform__legal">
				<?php esc_html_e( 'Published under your name and checked against your booking.', 'roova' ); ?>
			</p>
			<button class="roova-btn" type="submit">
				<span><?php esc_html_e( 'Submit review', 'roova' ); ?></span>
				<?php roova_the_icon( 'arrow-right', 15 ); ?>
			</button>
		</div>
	</form>
	<?php
}
