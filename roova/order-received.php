<?php
/**
 * Order received — the booking confirmed, in its own document.
 *
 * Its own <html> rather than header.php + footer.php, and no longer inside
 * checkout.php either: the design's whole header here is the wordmark and a way
 * into My bookings, and a guest who has just paid should not be looking at a
 * banner above a checkout they have finished with.
 *
 * Reached through `roova_order_received_template()`. Every figure on the page
 * is read off the order at render time — see inc/received.php.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

$roova_order = roova_received_order();

if ( ! $roova_order ) {
	wp_safe_redirect( home_url( '/' ) );
	exit;
}

$roova_lines    = roova_order_lines( $roova_order );
$roova_primary  = roova_order_primary_line( $roova_order );
$roova_facts    = roova_received_facts( $roova_order );
$roova_actions  = roova_received_actions( $roova_order );
$roova_steps    = roova_received_next_steps( $roova_order );
$roova_rows     = roova_order_customer_rows( $roova_order );
$roova_hotel    = roova_received_hotel_rows( $roova_primary ? $roova_primary['hotel_id'] : 0 );
$roova_notes    = trim( (string) $roova_order->get_customer_note() );
$roova_method   = $roova_order->get_payment_method_title();
$roova_mine     = is_user_logged_in() && (int) $roova_order->get_user_id() === get_current_user_id();

/*
 * Three states, three honest headings. A failed payment must not be dressed as
 * a confirmation, and neither must an order still waiting for the money — its
 * rooms are held, not booked, until the payment clears.
 */
if ( $roova_order->has_status( 'failed' ) ) {
	$roova_tone  = 'failed';
	$roova_icon  = 'info';
	$roova_title = __( 'Payment did not go through', 'roova' );
	$roova_sub   = __( 'Your rooms are still being held. Try paying again, or use another method.', 'roova' );
} elseif ( $roova_order->needs_payment() ) {
	$roova_tone  = 'pending';
	$roova_icon  = 'clock';
	$roova_title = __( 'Booking received', 'roova' );
	$roova_sub   = sprintf(
		/* translators: %s: order number, e.g. #1155 */
		__( 'Order %s — your rooms are held while we wait for the payment to clear.', 'roova' ),
		$roova_order->get_order_number()
	);
} else {
	$roova_tone  = 'ok';
	$roova_icon  = 'check-circle';
	$roova_title = apply_filters( 'woocommerce_thankyou_order_received_text', __( 'Booking confirmed', 'roova' ), $roova_order );
	$roova_email = $roova_order->get_billing_email();
	$roova_sub   = $roova_email
		? sprintf(
			/* translators: 1: order number, 2: email address */
			__( 'Order %1$s — your voucher and the hotel\'s details are on their way to %2$s.', 'roova' ),
			$roova_order->get_order_number(),
			$roova_email
		)
		: sprintf(
			/* translators: %s: order number */
			__( 'Order %s — your voucher is below.', 'roova' ),
			$roova_order->get_order_number()
		);
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>

<body <?php body_class( 'roova-page-white roova-received-page' ); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#roova-content"><?php esc_html_e( 'Skip to content', 'roova' ); ?></a>

<header class="roova-rec__bar">
	<a class="roova-rec__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
		<?php echo roova_wordmark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
	</a>

	<?php if ( $roova_mine ) : ?>
		<a class="roova-rec__nav" href="<?php echo esc_url( roova_account_tab_url( 'bookings' ) ); ?>">
			<?php roova_the_icon( 'user', 15 ); ?>
			<?php esc_html_e( 'My bookings', 'roova' ); ?>
		</a>
	<?php else : ?>
		<a class="roova-rec__nav" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php roova_the_icon( 'search', 15 ); ?>
			<?php esc_html_e( 'Find another room', 'roova' ); ?>
		</a>
	<?php endif; ?>
</header>

<main id="roova-content" class="roova-rec">
	<?php
	if ( function_exists( 'wc_print_notices' ) ) {
		wc_print_notices();
	}
	?>

	<div class="roova-rec__hero roova-rec__hero--<?php echo esc_attr( $roova_tone ); ?>">
		<span class="roova-rec__seal" aria-hidden="true">
			<?php roova_the_icon( $roova_icon, 22 ); ?>
		</span>

		<div class="roova-rec__hero-body">
			<h1 class="roova-rec__title"><?php echo esc_html( $roova_title ); ?></h1>
			<p class="roova-rec__sub"><?php echo esc_html( $roova_sub ); ?></p>

			<?php if ( $roova_actions ) : ?>
				<div class="roova-rec__actions">
					<?php foreach ( $roova_actions as $roova_action ) : ?>
						<?php if ( 'print' === $roova_action['action'] ) : ?>
							<button
								class="roova-rec__btn roova-rec__btn--<?php echo esc_attr( $roova_action['style'] ); ?>"
								type="button"
								data-roova-print
							>
								<?php roova_the_icon( $roova_action['icon'], 16 ); ?>
								<?php echo esc_html( $roova_action['label'] ); ?>
							</button>
						<?php else : ?>
							<a
								class="roova-rec__btn roova-rec__btn--<?php echo esc_attr( $roova_action['style'] ); ?>"
								href="<?php echo esc_url( $roova_action['url'] ); ?>"
							>
								<?php echo esc_html( $roova_action['label'] ); ?>
								<?php roova_the_icon( $roova_action['icon'], 15 ); ?>
							</a>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $roova_facts ) : ?>
		<ul class="roova-rec__facts">
			<?php foreach ( $roova_facts as $roova_fact ) : ?>
				<li class="roova-rec__fact<?php echo $roova_fact['tone'] ? ' roova-rec__fact--' . esc_attr( $roova_fact['tone'] ) : ''; ?>">
					<span class="roova-rec__fact-label"><?php echo esc_html( $roova_fact['label'] ); ?></span>
					<strong class="roova-rec__fact-value"><?php echo wp_kses_post( $roova_fact['value'] ); ?></strong>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<div class="roova-rec__grid">
		<div class="roova-rec__main">

			<?php if ( $roova_lines ) : ?>
				<h2 class="roova-rec__heading"><?php esc_html_e( 'Your stay', 'roova' ); ?></h2>

				<div class="roova-rec__card">
					<?php foreach ( $roova_lines as $roova_line ) : ?>
						<?php $roova_line_facts = roova_order_stay_facts( $roova_line ); ?>
						<div class="roova-rec__line">
							<?php if ( $roova_line['image_id'] ) : ?>
								<div class="roova-rec__thumb">
									<?php
									echo get_the_post_thumbnail(
										$roova_line['image_id'],
										'roova-room-thumb',
										array(
											'loading' => 'lazy',
											'alt'     => esc_attr( $roova_line['hotel'] ? $roova_line['hotel'] : $roova_line['name'] ),
										)
									);
									?>
								</div>
							<?php endif; ?>

							<div class="roova-rec__line-body">
								<div class="roova-rec__line-top">
									<div>
										<h3 class="roova-rec__room"><?php echo esc_html( $roova_line['name'] ); ?></h3>

										<p class="roova-rec__room-meta">
											<?php
											$roova_meta = array_filter(
												array(
													$roova_line['hotel'],
													sprintf(
														/* translators: %s: number of rooms booked */
														__( 'Qty %s', 'roova' ),
														number_format_i18n( $roova_line['units'] )
													),
												)
											);
											echo esc_html( implode( ' · ', $roova_meta ) );
											?>
										</p>
									</div>

									<p class="roova-rec__line-total"><?php echo wp_kses_post( $roova_line['total_html'] ); ?></p>
								</div>

								<?php if ( $roova_line_facts ) : ?>
									<div class="roova-rec__stay-facts">
										<?php foreach ( $roova_line_facts as $roova_stay_fact ) : ?>
											<div class="roova-rec__stay-fact">
												<?php roova_the_icon( $roova_stay_fact['icon'], 15 ); ?>
												<div>
													<span class="roova-rec__stay-label"><?php echo esc_html( $roova_stay_fact['label'] ); ?></span>
													<span class="roova-rec__stay-value"><?php echo esc_html( $roova_stay_fact['value'] ); ?></span>
												</div>
											</div>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>

					<div class="roova-rec__totals">
						<?php foreach ( roova_order_total_rows( $roova_order ) as $roova_row ) : ?>
							<div class="roova-rec__row <?php echo esc_attr( $roova_row['class'] ); ?>">
								<span><?php echo esc_html( $roova_row['label'] ); ?></span>
								<span><?php echo wp_kses_post( $roova_row['value'] ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>

					<div class="roova-rec__total">
						<span class="roova-rec__total-label"><?php echo esc_html( roova_order_total_label( $roova_order ) ); ?></span>
						<span class="roova-rec__total-value"><?php echo wp_kses_post( $roova_order->get_formatted_order_total() ); ?></span>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $roova_steps ) : ?>
				<h2 class="roova-rec__heading"><?php esc_html_e( 'What happens next', 'roova' ); ?></h2>

				<ol class="roova-rec__steps">
					<?php foreach ( $roova_steps as $roova_i => $roova_step ) : ?>
						<li class="roova-rec__step">
							<span class="roova-rec__step-num" aria-hidden="true"><?php echo esc_html( number_format_i18n( $roova_i + 1 ) ); ?></span>

							<div class="roova-rec__step-body">
								<p class="roova-rec__step-title">
									<?php roova_the_icon( $roova_step['icon'], 15 ); ?>
									<span><?php echo esc_html( $roova_step['title'] ); ?></span>
								</p>
								<p class="roova-rec__step-note"><?php echo esc_html( $roova_step['note'] ); ?></p>
							</div>
						</li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>

			<?php
			/*
			 * The gateway's own word, last in the column. Bank transfer prints
			 * the account to pay into here, and an order that cannot be used
			 * until that has been read is not something to bury in a sidebar.
			 */
			ob_start();
			do_action( 'woocommerce_thankyou_' . $roova_order->get_payment_method(), $roova_order->get_id() );
			do_action( 'woocommerce_thankyou', $roova_order->get_id() );
			$roova_gateway = trim( (string) ob_get_clean() );
			?>
			<?php if ( '' !== $roova_gateway ) : ?>
				<div class="roova-rec__gateway">
					<?php echo $roova_gateway; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gateway output, already escaped by the gateway that printed it. ?>
				</div>
			<?php endif; ?>
		</div>

		<aside class="roova-rec__side">
			<?php if ( $roova_rows ) : ?>
				<section class="roova-rec__panel">
					<div class="roova-rec__panel-head">
						<h2 class="roova-rec__panel-title"><?php esc_html_e( 'Customer details', 'roova' ); ?></h2>

						<?php if ( $roova_mine ) : ?>
							<a class="roova-rec__panel-link" href="<?php echo esc_url( roova_account_tab_url( 'profile' ) ); ?>">
								<?php esc_html_e( 'Edit', 'roova' ); ?>
							</a>
						<?php endif; ?>
					</div>

					<div class="roova-rec__details">
						<?php foreach ( $roova_rows as $roova_detail ) : ?>
							<div class="roova-rec__detail">
								<p class="roova-rec__detail-label">
									<?php roova_the_icon( $roova_detail['icon'], 15 ); ?>
									<?php echo esc_html( $roova_detail['label'] ); ?>
								</p>
								<p class="roova-rec__detail-value<?php echo $roova_detail['wrap'] ? ' roova-rec__detail-value--wrap' : ''; ?>">
									<?php echo esc_html( $roova_detail['value'] ); ?>
								</p>
							</div>
						<?php endforeach; ?>
					</div>

					<?php if ( $roova_order->get_billing_phone() ) : ?>
						<p class="roova-rec__panel-note">
							<?php roova_the_icon( 'info', 14 ); ?>
							<span><?php esc_html_e( 'The hotel may ring this number to arrange your arrival — keep it reachable.', 'roova' ); ?></span>
						</p>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<?php if ( $roova_method || ! $roova_order->needs_payment() ) : ?>
				<section class="roova-rec__panel">
					<h2 class="roova-rec__panel-title"><?php esc_html_e( 'Payment', 'roova' ); ?></h2>

					<?php if ( $roova_method ) : ?>
						<div class="roova-rec__pay">
							<?php roova_the_icon( roova_payment_icon( $roova_order->get_payment_method() ), 17 ); ?>
							<div>
								<p class="roova-rec__pay-name"><?php echo esc_html( $roova_method ); ?></p>

								<?php $roova_note = roova_order_payment_note( $roova_order ); ?>
								<?php if ( $roova_note ) : ?>
									<p class="roova-rec__pay-note"><?php echo esc_html( $roova_note ); ?></p>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>

					<?php $roova_state = roova_order_payment_state( $roova_order ); ?>
					<p class="roova-rec__pay-state <?php echo esc_attr( $roova_state['class'] ); ?>">
						<?php roova_the_icon( $roova_state['icon'], 15 ); ?>
						<span><?php echo esc_html( $roova_state['label'] ); ?></span>
					</p>
				</section>
			<?php endif; ?>

			<?php if ( $roova_hotel ) : ?>
				<section class="roova-rec__panel roova-rec__panel--gold">
					<h2 class="roova-rec__panel-title">
						<?php
						echo esc_html(
							$roova_primary && $roova_primary['hotel']
								? $roova_primary['hotel']
								: __( 'Hotel contact', 'roova' )
						);
						?>
					</h2>

					<div class="roova-rec__contact">
						<?php foreach ( $roova_hotel as $roova_row ) : ?>
							<div class="roova-rec__contact-row">
								<?php roova_the_icon( $roova_row['icon'], 15 ); ?>

								<?php if ( $roova_row['url'] ) : ?>
									<a
										class="roova-rec__contact-value<?php echo $roova_row['wrap'] ? ' roova-rec__contact-value--wrap' : ''; ?>"
										href="<?php echo esc_url( $roova_row['url'] ); ?>"
										<?php echo 0 === strpos( $roova_row['url'], 'tel:' ) ? '' : 'target="_blank" rel="noopener noreferrer"'; ?>
									>
										<?php echo esc_html( $roova_row['value'] ); ?>
									</a>
								<?php else : ?>
									<span class="roova-rec__contact-value"><?php echo esc_html( $roova_row['value'] ); ?></span>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>

					<?php if ( $roova_primary && $roova_primary['hotel_url'] ) : ?>
						<a class="roova-rec__panel-cta" href="<?php echo esc_url( $roova_primary['hotel_url'] ); ?>">
							<?php esc_html_e( 'View the hotel', 'roova' ); ?>
							<?php roova_the_icon( 'arrow-right', 14 ); ?>
						</a>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<?php if ( $roova_notes ) : ?>
				<section class="roova-rec__panel roova-rec__panel--quiet">
					<h2 class="roova-rec__panel-title"><?php esc_html_e( 'Order notes', 'roova' ); ?></h2>
					<p class="roova-rec__notes"><?php echo esc_html( $roova_notes ); ?></p>
				</section>
			<?php endif; ?>
		</aside>
	</div>
</main>

<footer class="roova-rec__foot">
	<p>
		<?php
		printf(
			/* translators: 1: year, 2: site name */
			esc_html__( '© %1$s %2$s', 'roova' ),
			esc_html( gmdate( 'Y' ) ),
			esc_html( get_bloginfo( 'name' ) )
		);
		?>
	</p>

	<?php
	if ( has_nav_menu( 'footer' ) ) {
		wp_nav_menu(
			array(
				'theme_location' => 'footer',
				'container'      => false,
				'menu_class'     => 'roova-rec__foot-menu',
				'depth'          => 1,
				'fallback_cb'    => false,
			)
		);
	}
	?>
</footer>

<?php wp_footer(); ?>
</body>
</html>
