<?php
/**
 * The Bookings admin screen: every stay, plus a month-by-month availability grid.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add the menu entry under WooCommerce.
 */
function roova_bookings_menu() {
	add_submenu_page(
		'woocommerce',
		__( 'Bookings', 'roova' ),
		__( 'Bookings', 'roova' ),
		'manage_woocommerce',
		'roova-bookings',
		'roova_render_bookings_page'
	);
}
add_action( 'admin_menu', 'roova_bookings_menu', 20 );

/**
 * Handle status changes made from the bookings screen.
 */
function roova_handle_booking_actions() {
	if ( ! isset( $_GET['page'], $_GET['roova_action'], $_GET['booking'] ) || 'roova-bookings' !== $_GET['page'] ) {
		return;
	}

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	$booking_id = absint( $_GET['booking'] );
	$action     = sanitize_key( wp_unslash( $_GET['roova_action'] ) );

	check_admin_referer( 'roova_booking_' . $booking_id );

	if ( in_array( $action, array( 'confirm', 'cancel', 'delete' ), true ) ) {
		if ( 'delete' === $action ) {
			Roova_Holds::delete( $booking_id );
		} else {
			Roova_Holds::update( $booking_id, array(
				'status'     => 'confirm' === $action ? 'confirmed' : 'cancelled',
				'expires_at' => null,
			) );
		}
	}

	wp_safe_redirect( remove_query_arg( array( 'roova_action', 'booking', '_wpnonce' ) ) );
	exit;
}
add_action( 'admin_init', 'roova_handle_booking_actions' );

/**
 * Query bookings for the list table.
 *
 * @param array $args Filters.
 * @return array array( rows, total ).
 */
function roova_query_bookings( $args ) {
	global $wpdb;

	$args = wp_parse_args( $args, array(
		'hotel_id' => 0,
		'room_id'  => 0,
		'status'   => '',
		'from'     => '',
		'to'       => '',
		'search'   => '',
		'paged'    => 1,
		'per_page' => 30,
	) );

	$table  = Roova_Schema::table();
	$where  = array( '1=1' );
	$params = array();

	if ( $args['hotel_id'] ) {
		$where[]  = 'hotel_id = %d';
		$params[] = absint( $args['hotel_id'] );
	}
	if ( $args['room_id'] ) {
		$where[]  = 'room_id = %d';
		$params[] = absint( $args['room_id'] );
	}
	if ( $args['status'] ) {
		$where[]  = 'status = %s';
		$params[] = sanitize_key( $args['status'] );
	}
	if ( $args['from'] ) {
		$where[]  = 'check_out > %s';
		$params[] = $args['from'];
	}
	if ( $args['to'] ) {
		$where[]  = 'check_in < %s';
		$params[] = $args['to'];
	}
	if ( '' !== $args['search'] ) {
		if ( is_numeric( $args['search'] ) ) {
			// A number is an order reference; matching guest_name too would
			// sweep in every hold, which has no order at all.
			$where[]  = 'order_id = %d';
			$params[] = absint( $args['search'] );
		} else {
			$where[]  = 'guest_name LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}
	}

	$where_sql = implode( ' AND ', $where );
	$offset    = max( 0, ( (int) $args['paged'] - 1 ) * (int) $args['per_page'] );

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
	$total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql );

	$rows_sql   = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY check_in DESC, id DESC LIMIT %d OFFSET %d";
	$rows_params = array_merge( $params, array( (int) $args['per_page'], $offset ) );
	$rows       = $wpdb->get_results( $wpdb->prepare( $rows_sql, $rows_params ), ARRAY_A );
	// phpcs:enable

	return array(
		'rows'  => is_array( $rows ) ? $rows : array(),
		'total' => $total,
	);
}

/**
 * Human label for a booking status.
 *
 * @param string $status Status.
 * @return string
 */
function roova_booking_status_label( $status ) {
	$labels = array(
		'hold'      => __( 'In a cart', 'roova' ),
		'pending'   => __( 'Awaiting payment', 'roova' ),
		'confirmed' => __( 'Confirmed', 'roova' ),
		'cancelled' => __( 'Cancelled', 'roova' ),
	);
	return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
}

/**
 * Render the screen.
 */
function roova_render_bookings_page() {
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	?>
	<div class="wrap roova-bookings">
		<h1><?php esc_html_e( 'Bookings', 'roova' ); ?></h1>

		<nav class="nav-tab-wrapper">
			<a class="nav-tab <?php echo 'list' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=roova-bookings' ) ); ?>">
				<?php esc_html_e( 'All bookings', 'roova' ); ?>
			</a>
			<a class="nav-tab <?php echo 'availability' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=roova-bookings&tab=availability' ) ); ?>">
				<?php esc_html_e( 'Availability', 'roova' ); ?>
			</a>
		</nav>

		<?php
		if ( 'availability' === $tab ) {
			roova_render_availability_tab();
		} else {
			roova_render_bookings_list();
		}
		?>
	</div>
	<?php
}

/**
 * The list of bookings.
 */
function roova_render_bookings_list() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
	$filters = array(
		'hotel_id' => isset( $_GET['hotel_id'] ) ? absint( $_GET['hotel_id'] ) : 0,
		'status'   => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
		'from'     => isset( $_GET['from'] ) ? roova_sanitize_date( sanitize_text_field( wp_unslash( $_GET['from'] ) ) ) : '',
		'to'       => isset( $_GET['to'] ) ? roova_sanitize_date( sanitize_text_field( wp_unslash( $_GET['to'] ) ) ) : '',
		'search'   => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
		'paged'    => isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1,
	);
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$result   = roova_query_bookings( $filters );
	$per_page = 30;
	?>
	<form method="get" class="roova-bookings__filters">
		<input type="hidden" name="page" value="roova-bookings" />

		<select name="hotel_id">
			<option value="0"><?php esc_html_e( 'All hotels', 'roova' ); ?></option>
			<?php foreach ( roova_get_hotel_ids() as $hotel_id ) : ?>
				<option value="<?php echo esc_attr( $hotel_id ); ?>" <?php selected( $filters['hotel_id'], $hotel_id ); ?>>
					<?php echo esc_html( get_the_title( $hotel_id ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<select name="status">
			<option value=""><?php esc_html_e( 'Any status', 'roova' ); ?></option>
			<?php foreach ( array( 'hold', 'pending', 'confirmed', 'cancelled' ) as $status ) : ?>
				<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $filters['status'], $status ); ?>>
					<?php echo esc_html( roova_booking_status_label( $status ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<label>
			<?php esc_html_e( 'Staying between', 'roova' ); ?>
			<input type="date" name="from" value="<?php echo esc_attr( $filters['from'] ); ?>" />
			<input type="date" name="to" value="<?php echo esc_attr( $filters['to'] ); ?>" />
		</label>

		<input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Guest name or order number', 'roova' ); ?>" />

		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'roova' ); ?></button>
	</form>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Guest', 'roova' ); ?></th>
				<th><?php esc_html_e( 'Hotel / room', 'roova' ); ?></th>
				<th><?php esc_html_e( 'Check in', 'roova' ); ?></th>
				<th><?php esc_html_e( 'Check out', 'roova' ); ?></th>
				<th><?php esc_html_e( 'Rooms', 'roova' ); ?></th>
				<th><?php esc_html_e( 'Guests', 'roova' ); ?></th>
				<th><?php esc_html_e( 'Status', 'roova' ); ?></th>
				<th><?php esc_html_e( 'Order', 'roova' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'roova' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! $result['rows'] ) : ?>
				<tr><td colspan="9"><?php esc_html_e( 'No bookings yet.', 'roova' ); ?></td></tr>
			<?php endif; ?>

			<?php foreach ( $result['rows'] as $row ) : ?>
				<?php
				$order      = $row['order_id'] ? wc_get_order( $row['order_id'] ) : null;
				$base_url   = admin_url( 'admin.php?page=roova-bookings' );
				$confirm_url = wp_nonce_url( add_query_arg( array( 'roova_action' => 'confirm', 'booking' => $row['id'] ), $base_url ), 'roova_booking_' . $row['id'] );
				$cancel_url  = wp_nonce_url( add_query_arg( array( 'roova_action' => 'cancel', 'booking' => $row['id'] ), $base_url ), 'roova_booking_' . $row['id'] );
				?>
				<tr>
					<td>
						<?php echo esc_html( $row['guest_name'] ? $row['guest_name'] : __( '—', 'roova' ) ); ?>
					</td>
					<td>
						<?php
						if ( $row['hotel_id'] ) {
							echo '<strong>' . esc_html( get_the_title( $row['hotel_id'] ) ) . '</strong><br />';
						}
						echo esc_html( get_the_title( $row['room_id'] ) );
						?>
					</td>
					<td><?php echo esc_html( roova_format_date( $row['check_in'] ) ); ?></td>
					<td><?php echo esc_html( roova_format_date( $row['check_out'] ) ); ?></td>
					<td><?php echo esc_html( $row['units'] ); ?></td>
					<td><?php echo esc_html( Roova_Cart::guests_label( $row['adults'], $row['children'] ) ); ?></td>
					<td>
						<span class="roova-status roova-status--<?php echo esc_attr( $row['status'] ); ?>">
							<?php echo esc_html( roova_booking_status_label( $row['status'] ) ); ?>
						</span>
						<?php if ( 'hold' === $row['status'] && $row['expires_at'] ) : ?>
							<br /><small><?php echo esc_html( sprintf( /* translators: %s: time */ __( 'until %s', 'roova' ), mysql2date( 'H:i', $row['expires_at'] ) ) ); ?></small>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $order ) : ?>
							<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
					<td>
						<?php if ( 'confirmed' !== $row['status'] ) : ?>
							<a href="<?php echo esc_url( $confirm_url ); ?>"><?php esc_html_e( 'Confirm', 'roova' ); ?></a>
						<?php endif; ?>
						<?php if ( 'cancelled' !== $row['status'] ) : ?>
							| <a href="<?php echo esc_url( $cancel_url ); ?>"><?php esc_html_e( 'Cancel', 'roova' ); ?></a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php
	$pages = (int) ceil( $result['total'] / $per_page );
	if ( $pages > 1 ) :
		?>
		<div class="tablenav"><div class="tablenav-pages">
			<?php
			echo wp_kses_post( paginate_links( array(
				'base'      => add_query_arg( 'paged', '%#%' ),
				'format'    => '',
				'current'   => $filters['paged'],
				'total'     => $pages,
				'prev_text' => __( '«', 'roova' ),
				'next_text' => __( '»', 'roova' ),
			) ) );
			?>
		</div></div>
	<?php endif; ?>
	<?php
}

/**
 * A month grid showing units left per room per night.
 */
function roova_render_availability_tab() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
	$hotel_id = isset( $_GET['hotel_id'] ) ? absint( $_GET['hotel_id'] ) : 0;
	$month    = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : gmdate( 'Y-m' );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$hotels = roova_get_hotel_ids();
	if ( ! $hotel_id && $hotels ) {
		$hotel_id = $hotels[0];
	}

	if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
		$month = gmdate( 'Y-m' );
	}

	$first      = $month . '-01';
	$days       = (int) gmdate( 't', strtotime( $first ) );
	$last       = roova_add_days( $first, $days );
	$prev_month = gmdate( 'Y-m', strtotime( $first . ' -1 month' ) );
	$next_month = gmdate( 'Y-m', strtotime( $first . ' +1 month' ) );
	?>
	<form method="get" class="roova-bookings__filters">
		<input type="hidden" name="page" value="roova-bookings" />
		<input type="hidden" name="tab" value="availability" />

		<select name="hotel_id">
			<?php foreach ( $hotels as $id ) : ?>
				<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $hotel_id, $id ); ?>>
					<?php echo esc_html( get_the_title( $id ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<input type="month" name="month" value="<?php echo esc_attr( $month ); ?>" />
		<button type="submit" class="button"><?php esc_html_e( 'Show', 'roova' ); ?></button>

		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=roova-bookings&tab=availability&hotel_id=' . $hotel_id . '&month=' . $prev_month ) ); ?>">&laquo;</a>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=roova-bookings&tab=availability&hotel_id=' . $hotel_id . '&month=' . $next_month ) ); ?>">&raquo;</a>
	</form>

	<?php if ( ! $hotel_id ) : ?>
		<p><?php esc_html_e( 'Create a hotel first.', 'roova' ); ?></p>
		<?php
		return;
	endif;

	$room_ids = roova_get_hotel_room_ids( $hotel_id );
	if ( ! $room_ids ) :
		?>
		<p><?php esc_html_e( 'This hotel has no rooms yet.', 'roova' ); ?></p>
		<?php
		return;
	endif;
	?>

	<div class="roova-availability-scroll">
		<table class="wp-list-table widefat roova-availability">
			<thead>
				<tr>
					<th class="roova-availability__room"><?php esc_html_e( 'Room', 'roova' ); ?></th>
					<?php for ( $day = 1; $day <= $days; $day++ ) : ?>
						<th><?php echo esc_html( $day ); ?></th>
					<?php endfor; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $room_ids as $room_id ) : ?>
					<?php
					$details = roova_get_room_details( $room_id );
					$rows    = Roova_Availability::get_overlapping_rows( $room_id, $first, $last );

					$per_night = array();
					foreach ( $rows as $row ) {
						foreach ( roova_night_list( $row['check_in'], $row['check_out'] ) as $night ) {
							if ( ! isset( $per_night[ $night ] ) ) {
								$per_night[ $night ] = 0;
							}
							$per_night[ $night ] += max( 1, (int) $row['units'] );
						}
					}
					?>
					<tr>
						<td class="roova-availability__room">
							<strong><?php echo esc_html( get_the_title( $room_id ) ); ?></strong><br />
							<small>
								<?php
								printf(
									/* translators: %d: units */
									esc_html( _n( '%d unit', '%d units', $details['units'], 'roova' ) ),
									(int) $details['units']
								);
								?>
							</small>
						</td>
						<?php
						for ( $day = 1; $day <= $days; $day++ ) :
							$date  = sprintf( '%s-%02d', $month, $day );
							$used  = isset( $per_night[ $date ] ) ? (int) $per_night[ $date ] : 0;
							$left  = max( 0, (int) $details['units'] - $used );
							$class = 0 === $left ? 'is-full' : ( $left <= 2 ? 'is-low' : 'is-open' );
							?>
							<td class="roova-availability__cell <?php echo esc_attr( $class ); ?>" title="<?php echo esc_attr( $date ); ?>">
								<?php echo esc_html( $left ); ?>
							</td>
						<?php endfor; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<p class="description">
		<?php esc_html_e( 'Each cell shows how many rooms of that type are still free that night.', 'roova' ); ?>
	</p>
	<?php
}
