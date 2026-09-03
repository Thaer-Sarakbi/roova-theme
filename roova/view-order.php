<?php
/**
 * View order — one booking, in its own document.
 *
 * Its own <html> rather than header.php + footer.php, for the reason checkout,
 * the auth pages and the account dashboard have theirs: the design's whole
 * header is the wordmark and a way back to the bookings list, and nothing on the
 * page should lead anywhere but further into this booking.
 *
 * Reached through roova_view_order_template(), which routes WooCommerce's
 * view-order endpoint here — the endpoint that "View voucher" on the Bookings
 * tab links at. Every other endpoint under My account is left to WooCommerce
 * inside the normal site header.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

$roova_order = roova_view_order();

if ( ! $roova_order ) {
	wp_safe_redirect( roova_account_url() );
	exit;
}

$roova_lines   = roova_order_lines( $roova_order );
$roova_status  = roova_order_status( $roova_order );
$roova_chips   = roova_account_stay_chips();
$roova_actions = roova_order_actions( $roova_order );
$roova_rows    = roova_order_customer_rows( $roova_order );
$roova_notes   = trim( (string) $roova_order->get_customer_note() );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>

<body <?php body_class( 'roova-page-white roova-order-page' ); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#roova-content"><?php esc_html_e( 'Skip to content', 'roova' ); ?></a>

<header class="roova-order__bar">
	<a class="roova-order__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
		<?php echo roova_wordmark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
	</a>

	<a class="roova-order__back" href="<?php echo esc_url( roova_account_tab_url( 'bookings' ) ); ?>">
		<?php roova_the_icon( 'chevron-left', 15 ); ?>
		<?php esc_html_e( 'Back to my bookings', 'roova' ); ?>
	</a>
</header>

<main id="roova-content" class="roova-order">
	<?php
	if ( function_exists( 'wc_print_notices' ) ) {
		wc_print_notices();
	}
	?>

	<div class="roova-order__head">
		<p class="roova-order__eyebrow">
			<span class="roova-order__rule" aria-hidden="true"></span>
			<?php
			printf(
				/* translators: %s: order number, e.g. #1150 */
				esc_html__( 'Order #%s', 'roova' ),
				esc_html( $roova_order->get_order_number() )
			);
			?>
		</p>

		<div class="roova-order__title-row">
			<div>
				<h1 class="roova-order__title"><?php esc_html_e( 'Order details', 'roova' ); ?></h1>

				<?php $roova_summary = roova_order_summary_line( $roova_order ); ?>
				<?php if ( $roova_summary ) : ?>
					<p class="roova-order__sub"><?php echo esc_html( $roova_summary ); ?></p>
				<?php endif; ?>
			</div>

			<p class="roova-order__status roova-order__status--<?php echo esc_attr( $roova_status ); ?>">
				<span class="roova-order__status-dot" aria-hidden="true"></span>
				<?php echo esc_html( $roova_chips[ $roova_status ] ); ?>
			</p>
		</div>
	</div>

	<div class="roova-order__grid">
		<div class="roova-order__main">
			<div class="roova-order__card">
				<?php foreach ( $roova_lines as $roova_line ) : ?>
					<?php $roova_facts = roova_order_stay_facts( $roova_line ); ?>
					<div class="roova-order__line">
						<div class="roova-order__thumb">
							<?php
							if ( $roova_line['image_id'] ) {
								echo get_the_post_thumbnail( $roova_line['image_id'], 'roova-room-thumb', array(
									'loading' => 'lazy',
									'alt'     => esc_attr( $roova_line['hotel'] ? $roova_line['hotel'] : $roova_line['name'] ),
								) );
							}
							?>
						</div>

						<div class="roova-order__line-body">
							<div class="roova-order__line-top">
								<div>
									<h2 class="roova-order__room"><?php echo esc_html( $roova_line['name'] ); ?></h2>

									<p class="roova-order__room-meta">
										<?php
										$roova_meta = array_filter( array(
											$roova_line['hotel'],
											sprintf(
												/* translators: %s: number of rooms booked */
												__( 'Qty %s', 'roova' ),
												number_format_i18n( $roova_line['units'] )
											),
										) );
										echo esc_html( implode( ' · ', $roova_meta ) );
										?>
									</p>
								</div>

								<p class="roova-order__line-total"><?php echo wp_kses_post( $roova_line['total_html'] ); ?></p>
							</div>

							<?php if ( $roova_facts ) : ?>
								<div class="roova-order__facts">
									<?php foreach ( $roova_facts as $roova_fact ) : ?>
										<div class="roova-order__fact">
											<?php roova_the_icon( $roova_fact['icon'], 15 ); ?>
											<div>
												<span class="roova-order__fact-label"><?php echo esc_html( $roova_fact['label'] ); ?></span>
												<span class="roova-order__fact-value"><?php echo esc_html( $roova_fact['value'] ); ?></span>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>

				<div class="roova-order__totals">
					<?php foreach ( roova_order_total_rows( $roova_order ) as $roova_row ) : ?>
						<div class="roova-order__row <?php echo esc_attr( $roova_row['class'] ); ?>">
							<span><?php echo esc_html( $roova_row['label'] ); ?></span>
							<span><?php echo wp_kses_post( $roova_row['value'] ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="roova-order__total">
					<span class="roova-order__total-label"><?php echo esc_html( roova_order_total_label( $roova_order ) ); ?></span>
					<span class="roova-order__total-value"><?php echo wp_kses_post( $roova_order->get_formatted_order_total() ); ?></span>
				</div>
			</div>

			<?php if ( $roova_actions ) : ?>
				<div class="roova-order__actions">
					<?php foreach ( $roova_actions as $roova_action ) : ?>
						<?php if ( 'print' === $roova_action['action'] ) : ?>
							<button
								class="roova-order__btn roova-order__btn--<?php echo esc_attr( $roova_action['style'] ); ?>"
								type="button"
								data-roova-print
							>
								<?php roova_the_icon( $roova_action['icon'], 16 ); ?>
								<?php echo esc_html( $roova_action['label'] ); ?>
							</button>
						<?php else : ?>
							<a
								class="roova-order__btn roova-order__btn--<?php echo esc_attr( $roova_action['style'] ); ?>"
								href="<?php echo esc_url( $roova_action['url'] ); ?>"
							>
								<?php echo esc_html( $roova_action['label'] ); ?>
								<?php roova_the_icon( $roova_action['icon'], 16 ); ?>
							</a>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<aside class="roova-order__side">
			<?php if ( $roova_rows ) : ?>
				<section class="roova-order__panel">
					<h2 class="roova-order__panel-title"><?php esc_html_e( 'Customer details', 'roova' ); ?></h2>

					<div class="roova-order__details">
						<?php foreach ( $roova_rows as $roova_detail ) : ?>
							<div class="roova-order__detail">
								<p class="roova-order__detail-label">
									<?php roova_the_icon( $roova_detail['icon'], 15 ); ?>
									<?php echo esc_html( $roova_detail['label'] ); ?>
								</p>
								<p class="roova-order__detail-value<?php echo $roova_detail['wrap'] ? ' roova-order__detail-value--wrap' : ''; ?>">
									<?php echo esc_html( $roova_detail['value'] ); ?>
								</p>
							</div>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php $roova_method = $roova_order->get_payment_method_title(); ?>
			<?php if ( $roova_method || ! $roova_order->needs_payment() ) : ?>
				<section class="roova-order__panel">
					<h2 class="roova-order__panel-title"><?php esc_html_e( 'Payment', 'roova' ); ?></h2>

					<?php if ( $roova_method ) : ?>
						<div class="roova-order__pay">
							<?php roova_the_icon( roova_payment_icon( $roova_order->get_payment_method() ), 17 ); ?>
							<div>
								<p class="roova-order__pay-name"><?php echo esc_html( $roova_method ); ?></p>

								<?php $roova_note = roova_order_payment_note( $roova_order ); ?>
								<?php if ( $roova_note ) : ?>
									<p class="roova-order__pay-note"><?php echo esc_html( $roova_note ); ?></p>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>

					<?php $roova_state = roova_order_payment_state( $roova_order ); ?>
					<p class="roova-order__pay-state <?php echo esc_attr( $roova_state['class'] ); ?>">
						<?php roova_the_icon( $roova_state['icon'], 15 ); ?>
						<span><?php echo esc_html( $roova_state['label'] ); ?></span>
					</p>
				</section>
			<?php endif; ?>

			<?php if ( $roova_notes ) : ?>
				<section class="roova-order__panel roova-order__panel--quiet">
					<h2 class="roova-order__panel-title"><?php esc_html_e( 'Order notes', 'roova' ); ?></h2>
					<p class="roova-order__notes"><?php echo esc_html( $roova_notes ); ?></p>
				</section>
			<?php endif; ?>
		</aside>
	</div>
</main>

<?php wp_footer(); ?>
</body>
</html>
