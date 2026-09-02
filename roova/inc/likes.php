<?php
/**
 * Saved stays: the heart on every hotel, and the list behind the Likes tab.
 *
 * One user meta key holds the whole list — an array of hotel product IDs,
 * newest first — because that is exactly how the tab reads it back, and a
 * member's saved stays are never queried across users.
 *
 * The heart itself is deliberately **not** rendered inside the card's link:
 * a browser drops interactive markup nested in an `<a>` the same way it drops a
 * nested `<form>`, so the button is a sibling of the media anchor and is
 * positioned over it. See roova_hotel_card().
 *
 * Signed-out visitors get a link to the sign-in page rather than a dead button,
 * so the heart works with the script blocked and never silently loses a save.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * The user meta key the saved list lives in.
 */
const ROOVA_LIKES_META = 'roova_liked_hotels';

/**
 * A member's saved hotels, newest first.
 *
 * Anything that has since been unpublished or deleted is dropped on read, so a
 * stale ID never renders as a blank card.
 *
 * @param int $user_id User ID, or 0 for the current user.
 * @return int[] Hotel product IDs.
 */
function roova_get_likes( $user_id = 0 ) {
	$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
	if ( ! $user_id ) {
		return array();
	}

	$stored = get_user_meta( $user_id, ROOVA_LIKES_META, true );
	if ( ! is_array( $stored ) ) {
		return array();
	}

	$likes = array();
	foreach ( $stored as $hotel_id ) {
		$hotel_id = absint( $hotel_id );
		if ( $hotel_id && 'publish' === get_post_status( $hotel_id ) ) {
			$likes[] = $hotel_id;
		}
	}

	return array_values( array_unique( $likes ) );
}

/**
 * How many stays a member has saved.
 *
 * @param int $user_id User ID, or 0 for the current user.
 * @return int
 */
function roova_likes_count( $user_id = 0 ) {
	return count( roova_get_likes( $user_id ) );
}

/**
 * Has this member saved this hotel?
 *
 * @param int $hotel_id Hotel product ID.
 * @param int $user_id  User ID, or 0 for the current user.
 * @return bool
 */
function roova_is_liked( $hotel_id, $user_id = 0 ) {
	return in_array( absint( $hotel_id ), roova_get_likes( $user_id ), true );
}

/**
 * Save or unsave a hotel.
 *
 * @param int $hotel_id Hotel product ID.
 * @param int $user_id  User ID, or 0 for the current user.
 * @return bool The new state: true saved, false removed.
 */
function roova_toggle_like( $hotel_id, $user_id = 0 ) {
	$user_id  = $user_id ? absint( $user_id ) : get_current_user_id();
	$hotel_id = absint( $hotel_id );

	if ( ! $user_id || ! $hotel_id ) {
		return false;
	}

	$likes = roova_get_likes( $user_id );
	$index = array_search( $hotel_id, $likes, true );

	if ( false !== $index ) {
		unset( $likes[ $index ] );
		$liked = false;
	} else {
		// Newest first, which is the order the tab shows them in.
		array_unshift( $likes, $hotel_id );
		$liked = true;
	}

	update_user_meta( $user_id, ROOVA_LIKES_META, array_values( $likes ) );

	/**
	 * Fires after a member saves or unsaves a hotel.
	 *
	 * @param int  $hotel_id Hotel product ID.
	 * @param int  $user_id  User ID.
	 * @param bool $liked    The new state.
	 */
	do_action( 'roova_like_toggled', $hotel_id, $user_id, $liked );

	return $liked;
}

/**
 * Is this product something a guest can save?
 *
 * Rooms are not: a room is booked in the context of its hotel, and the hotel is
 * what the saved list links back to.
 *
 * @param int $hotel_id Product ID.
 * @return bool
 */
function roova_is_likeable( $hotel_id ) {
	return roova_is_hotel( $hotel_id );
}

/**
 * The URL the heart sends a signed-out visitor to.
 *
 * @return string
 */
function roova_like_signin_url() {
	$current = home_url( add_query_arg( array() ) );

	return roova_signin_url( $current );
}

/**
 * The heart button on a hotel card or a hotel page.
 *
 * A member gets a button the script toggles; a visitor gets a link into the
 * sign-in page and comes back to where they were.
 *
 * @param int   $hotel_id Hotel product ID.
 * @param array $args     class => extra CSS class, size => icon pixels.
 */
function roova_like_button( $hotel_id, $args = array() ) {
	$hotel_id = absint( $hotel_id );

	if ( ! $hotel_id || ! roova_is_likeable( $hotel_id ) ) {
		return;
	}

	/**
	 * Filter whether the saved-stays heart is rendered at all.
	 *
	 * @param bool $show     Default true.
	 * @param int  $hotel_id Hotel product ID.
	 */
	if ( ! apply_filters( 'roova_show_like_button', true, $hotel_id ) ) {
		return;
	}

	$args = wp_parse_args( $args, array(
		'class' => '',
		'size'  => 16,
	) );

	$name = get_the_title( $hotel_id );

	if ( ! is_user_logged_in() ) {
		?>
		<a
			class="roova-like <?php echo esc_attr( $args['class'] ); ?>"
			href="<?php echo esc_url( roova_like_signin_url() ); ?>"
			aria-label="<?php echo esc_attr( sprintf( /* translators: %s: hotel name */ __( 'Sign in to save %s', 'roova' ), $name ) ); ?>"
		>
			<?php roova_the_icon( 'heart', (int) $args['size'] ); ?>
		</a>
		<?php
		return;
	}

	$liked = roova_is_liked( $hotel_id );
	?>
	<button
		class="roova-like <?php echo esc_attr( $args['class'] ); ?><?php echo $liked ? ' is-liked' : ''; ?>"
		type="button"
		data-roova-like="<?php echo esc_attr( $hotel_id ); ?>"
		aria-pressed="<?php echo $liked ? 'true' : 'false'; ?>"
		aria-label="<?php echo esc_attr( $liked
			? sprintf( /* translators: %s: hotel name */ __( 'Remove %s from your saved stays', 'roova' ), $name )
			: sprintf( /* translators: %s: hotel name */ __( 'Save %s to your stays', 'roova' ), $name )
		); ?>"
	>
		<?php roova_the_icon( 'heart', (int) $args['size'] ); ?>
	</button>
	<?php
}

/**
 * Toggle a save from the front end.
 *
 * Nothing here trusts a user ID from the request: the save belongs to whoever
 * is signed in, and a signed-out request is answered with the sign-in URL so
 * the script can send them there rather than failing silently.
 */
function roova_ajax_toggle_like() {
	roova_check_ajax_nonce();

	if ( ! is_user_logged_in() ) {
		wp_send_json_error(
			array(
				'message'   => __( 'Sign in to save this stay.', 'roova' ),
				'signinUrl' => roova_signin_url(),
			),
			401
		);
	}

	$hotel_id = isset( $_POST['hotel_id'] ) ? absint( $_POST['hotel_id'] ) : 0;

	if ( ! $hotel_id || ! roova_is_likeable( $hotel_id ) ) {
		wp_send_json_error( array( 'message' => __( 'That stay could not be saved.', 'roova' ) ), 404 );
	}

	$liked = roova_toggle_like( $hotel_id );

	wp_send_json_success(
		array(
			'hotelId' => $hotel_id,
			'liked'   => $liked,
			'count'   => roova_likes_count(),
			'label'   => $liked
				/* translators: %s: hotel name */
				? sprintf( __( 'Remove %s from your saved stays', 'roova' ), get_the_title( $hotel_id ) )
				/* translators: %s: hotel name */
				: sprintf( __( 'Save %s to your stays', 'roova' ), get_the_title( $hotel_id ) ),
		)
	);
}
add_action( 'wp_ajax_roova_toggle_like', 'roova_ajax_toggle_like' );
add_action( 'wp_ajax_nopriv_roova_toggle_like', 'roova_ajax_toggle_like' );
