<?php
/**
 * Order received.
 *
 * Where Place order lands. It wears the same header and banner as checkout —
 * see roova_checkout_template(), which routes this view through the theme's
 * own document too.
 *
 * @package Roova
 * @see     https://woocommerce.com/document/template-structure/
 * @var WC_Order $order
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="woocommerce-order roova-received">

	<?php if ( $order ) : ?>

		<?php if ( $order->has_status( 'failed' ) ) : ?>

			<div class="roova-received__panel roova-received__panel--failed">
				<h2 class="roova-received__title"><?php esc_html_e( 'Payment did not go through', 'roova' ); ?></h2>
				<p class="roova-received__body"><?php esc_html_e( 'Your rooms are still being held. Try paying again, or use another method.', 'roova' ); ?></p>

				<p class="roova-received__actions">
					<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="roova-btn"><?php esc_html_e( 'Try again', 'roova' ); ?></a>
					<?php if ( is_user_logged_in() ) : ?>
						<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="roova-btn roova-btn--ghost"><?php esc_html_e( 'My bookings', 'roova' ); ?></a>
					<?php endif; ?>
				</p>
			</div>

		<?php else : ?>

			<div class="roova-received__panel">
				<?php roova_the_icon( 'check-circle', 22, 'roova-received__icon' ); ?>

				<div>
					<h2 class="roova-received__title"><?php echo esc_html( apply_filters( 'woocommerce_thankyou_order_received_text', __( 'Booking confirmed', 'roova' ), $order ) ); ?></h2>
					<p class="roova-received__body">
						<?php
						printf(
							/* translators: 1: order number, 2: email address */
							esc_html__( 'Order %1$s — a confirmation has been sent to %2$s.', 'roova' ),
							esc_html( $order->get_order_number() ),
							esc_html( $order->get_billing_email() )
						);
						?>
					</p>
				</div>
			</div>

			<ul class="roova-received__facts">
				<li>
					<span><?php esc_html_e( 'Order number', 'roova' ); ?></span>
					<strong><?php echo esc_html( $order->get_order_number() ); ?></strong>
				</li>
				<li>
					<span><?php esc_html_e( 'Date', 'roova' ); ?></span>
					<strong><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></strong>
				</li>
				<li>
					<span><?php esc_html_e( 'Total', 'roova' ); ?></span>
					<strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong>
				</li>
				<?php if ( $order->get_payment_method_title() ) : ?>
					<li>
						<span><?php esc_html_e( 'Payment', 'roova' ); ?></span>
						<strong><?php echo wp_kses_post( $order->get_payment_method_title() ); ?></strong>
					</li>
				<?php endif; ?>
			</ul>

		<?php endif; ?>

		<?php do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() ); ?>
		<?php do_action( 'woocommerce_thankyou', $order->get_id() ); ?>

	<?php else : ?>

		<div class="roova-received__panel">
			<?php roova_the_icon( 'check-circle', 22, 'roova-received__icon' ); ?>
			<div>
				<h2 class="roova-received__title"><?php echo esc_html( apply_filters( 'woocommerce_thankyou_order_received_text', __( 'Booking confirmed', 'roova' ), null ) ); ?></h2>
			</div>
		</div>

	<?php endif; ?>

</div>
