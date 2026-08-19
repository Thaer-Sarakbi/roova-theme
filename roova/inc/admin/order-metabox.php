<?php
/**
 * Booking summary on the order edit screen.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the metabox on both HPOS and legacy order screens.
 */
function roova_add_order_metabox() {
	$hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
		&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

	$screen = $hpos ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';

	add_meta_box(
		'roova_order_bookings',
		__( 'Bookings', 'roova' ),
		'roova_render_order_metabox',
		$screen,
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'roova_add_order_metabox' );

/**
 * Render the metabox.
 *
 * @param WP_Post|WC_Order $post_or_order Post or order.
 */
function roova_render_order_metabox( $post_or_order ) {
	$order = ( $post_or_order instanceof WC_Order ) ? $post_or_order : wc_get_order( $post_or_order->ID );
	if ( ! $order ) {
		return;
	}

	$rows = Roova_Holds::get_by_order( $order->get_id() );

	if ( ! $rows ) {
		echo '<p>' . esc_html__( 'No room bookings on this order.', 'roova' ) . '</p>';
		return;
	}
	?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Hotel', 'roova' ); ?></th>
				<th><?php esc_html_e( 'Room', 'roova' ); ?></th>
				<th><?php esc_html_e( 'Stay', 'roova' ); ?></th>
				<th><?php esc_html_e( 'Rooms', 'roova' ); ?></th>
				<th><?php esc_html_e( 'Guests', 'roova' ); ?></th>
				<th><?php esc_html_e( 'Status', 'roova' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<td>
						<?php if ( $row['hotel_id'] ) : ?>
							<a href="<?php echo esc_url( get_edit_post_link( $row['hotel_id'] ) ); ?>"><?php echo esc_html( get_the_title( $row['hotel_id'] ) ); ?></a>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( get_the_title( $row['room_id'] ) ); ?></td>
					<td>
						<?php
						printf(
							/* translators: 1: check-in date, 2: check-out date, 3: nights */
							esc_html__( '%1$s → %2$s (%3$s)', 'roova' ),
							esc_html( roova_format_date( $row['check_in'] ) ),
							esc_html( roova_format_date( $row['check_out'] ) ),
							esc_html(
								sprintf(
									/* translators: %d: nights */
									_n( '%d night', '%d nights', roova_nights( $row['check_in'], $row['check_out'] ), 'roova' ),
									roova_nights( $row['check_in'], $row['check_out'] )
								)
							)
						);
						?>
					</td>
					<td><?php echo esc_html( $row['units'] ); ?></td>
					<td><?php echo esc_html( Roova_Cart::guests_label( $row['adults'], $row['children'] ) ); ?></td>
					<td>
						<span class="roova-status roova-status--<?php echo esc_attr( $row['status'] ); ?>">
							<?php echo esc_html( roova_booking_status_label( $row['status'] ) ); ?>
						</span>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p class="description">
		<?php esc_html_e( 'Booking status follows the order: paid orders confirm the stay, cancelled or refunded orders release the dates.', 'roova' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=roova-bookings' ) ); ?>"><?php esc_html_e( 'Open the bookings screen', 'roova' ); ?></a>
	</p>
	<?php
}
