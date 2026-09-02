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
 * The headline score and count are WooCommerce's own — it maintains them when a
 * review is approved — and the sub-scores are averaged straight out of comment
 * meta. `count` is 0 for a hotel nobody has reviewed, which is the caller's cue
 * to fall back to the numbers typed on the Hotel Details tab.
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

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT cm.meta_key AS score_key, AVG( cm.meta_value + 0 ) AS score_value
				FROM {$wpdb->commentmeta} cm
				INNER JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id
				WHERE c.comment_post_ID = %d
					AND c.comment_approved = '1'
					AND cm.meta_key IN ( {$placeholders} )
				GROUP BY cm.meta_key",
				array_merge( array( $hotel_id ), $keys )
			),
			ARRAY_A
		);
		// phpcs:enable

		foreach ( (array) $rows as $row ) {
			$key = isset( $row['score_key'] ) ? $row['score_key'] : '';
			if ( isset( $labels[ $key ] ) ) {
				$summary['subscores'][ $labels[ $key ] ] = round( (float) $row['score_value'], 1 );
			}
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
