<?php
/**
 * The account page's markup: the shell, the tab strip and the six panels.
 *
 * Every panel is rendered here and shown one at a time by
 * `assets/js/account.js`. They are all in the document rather than fetched on
 * demand, so a member with the script blocked still reaches every tab through
 * `?tab=` — and switching tabs costs nothing.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Shell
 * ---------------------------------------------------------------------- */

/**
 * The bar across the top: wordmark, tier, and the way out.
 *
 * @param WP_User $user The member.
 */
function roova_account_topbar( $user ) {
	$tier_label = roova_vip_member_label( $user->ID );
	?>
	<header class="roova-account__bar">
		<a class="roova-account__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php echo roova_wordmark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
		</a>

		<div class="roova-account__bar-right">
			<?php if ( $tier_label ) : ?>
				<p class="roova-account__tier">
					<?php roova_the_icon( 'crown', 15 ); ?>
					<?php echo esc_html( $tier_label ); ?>
				</p>
			<?php endif; ?>

			<a class="roova-account__signout" href="<?php echo esc_url( roova_account_logout_url() ); ?>">
				<?php roova_the_icon( 'log-out', 15 ); ?>
				<?php esc_html_e( 'Sign out', 'roova' ); ?>
			</a>
		</div>
	</header>
	<?php
}

/**
 * The account hero: who this is, what they have to spend, and how many stays
 * they have booked.
 *
 * @param WP_User $user The member.
 */
function roova_account_hero( $user ) {
	$name  = roova_account_full_name( $user );
	$stays = roova_account_stay_count( $user->ID );
	?>
	<div class="roova-account__hero">
		<div class="roova-account__identity">
			<?php roova_account_avatar( $user ); ?>

			<div class="roova-account__identity-text">
				<p class="roova-account__eyebrow">
					<span class="roova-account__rule" aria-hidden="true"></span>
					<?php esc_html_e( 'My account', 'roova' ); ?>
				</p>

				<h1 class="roova-account__name">
					<?php echo esc_html( $name ? $name : __( 'Your account', 'roova' ) ); ?>
				</h1>

				<p class="roova-account__since"><?php echo esc_html( roova_account_since_line( $user ) ); ?></p>
			</div>
		</div>

		<div class="roova-account__stats">
			<?php if ( roova_cashback_enabled() ) : ?>
				<?php /* To the left of "Stays booked", as the handoff has it. */ ?>
				<div class="roova-account__stat">
					<span class="roova-account__stat-figure roova-account__stat-figure--money">
						<?php echo wp_kses_post( roova_cashback_amount( roova_cashback_available( $user->ID ) ) ); ?>
					</span>
					<span class="roova-account__stat-label"><?php esc_html_e( 'Cashback balance', 'roova' ); ?></span>
				</div>
			<?php endif; ?>

			<div class="roova-account__stat">
				<span class="roova-account__stat-figure"><?php echo esc_html( number_format_i18n( $stays ) ); ?></span>
				<span class="roova-account__stat-label"><?php esc_html_e( 'Stays booked', 'roova' ); ?></span>
			</div>
		</div>
	</div>
	<?php
}

/**
 * The tab strip.
 *
 * Each tab is a link to its own `?tab=` URL and a button at the same time: the
 * script intercepts the click and swaps panels, and without it the link still
 * loads the page on that tab.
 *
 * @param string $current Active tab key.
 */
function roova_account_tab_strip( $current ) {
	?>
	<nav class="roova-account__tabs" aria-label="<?php esc_attr_e( 'Account sections', 'roova' ); ?>">
		<?php foreach ( roova_account_tabs() as $key => $tab ) : ?>
			<?php $active = ( $key === $current ); ?>
			<a
				class="roova-account__tab<?php echo $active ? ' is-active' : ''; ?>"
				href="<?php echo esc_url( roova_account_tab_url( $key ) ); ?>"
				data-roova-tab="<?php echo esc_attr( $key ); ?>"
				aria-current="<?php echo $active ? 'page' : 'false'; ?>"
			>
				<?php roova_the_icon( $tab['icon'], 16 ); ?>
				<span><?php echo esc_html( $tab['label'] ); ?></span>

				<?php if ( null !== $tab['count'] ) : ?>
					<span class="roova-account__pill" data-roova-tab-count="<?php echo esc_attr( $key ); ?>">
						<?php echo esc_html( number_format_i18n( (int) $tab['count'] ) ); ?>
					</span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</nav>
	<?php
}

/**
 * Every panel, with the current one showing.
 *
 * @param WP_User $user    The member.
 * @param string  $current Active tab key.
 */
function roova_account_panels( $user, $current ) {
	foreach ( array_keys( roova_account_tabs() ) as $key ) {
		$callback = 'roova_account_panel_' . $key;

		if ( ! function_exists( $callback ) ) {
			continue;
		}
		?>
		<section
			class="roova-account__panel"
			id="roova-panel-<?php echo esc_attr( $key ); ?>"
			data-roova-panel="<?php echo esc_attr( $key ); ?>"
			<?php echo $key === $current ? '' : 'hidden'; ?>
		>
			<?php call_user_func( $callback, $user ); ?>
		</section>
		<?php
	}
}

/**
 * A section heading and the line under it.
 *
 * @param string $title Heading.
 * @param string $sub   Sub-line.
 */
function roova_account_heading( $title, $sub = '' ) {
	?>
	<div class="roova-account__head">
		<h2 class="roova-account__h2"><?php echo esc_html( $title ); ?></h2>
		<?php if ( $sub ) : ?>
			<p class="roova-account__sub"><?php echo esc_html( $sub ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * The panel shown when a list has nothing in it yet.
 *
 * @param string $message Line of copy.
 * @param string $cta     Button label, or '' for no button.
 * @param string $url     Where the button goes.
 */
function roova_account_empty( $message, $cta = '', $url = '' ) {
	?>
	<div class="roova-account__empty">
		<p><?php echo esc_html( $message ); ?></p>
		<?php if ( $cta && $url ) : ?>
			<a class="roova-account__btn" href="<?php echo esc_url( $url ); ?>">
				<span><?php echo esc_html( $cta ); ?></span>
				<?php roova_the_icon( 'arrow-right', 15 ); ?>
			</a>
		<?php endif; ?>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * 1 — Profile
 * ---------------------------------------------------------------------- */

/**
 * Personal details, the password panel and the save row.
 *
 * The fields are `roova_auth_field()`'s — the same shell the sign-in and
 * sign-up pages are built from, down to the borderless input inside its label.
 *
 * @param WP_User $user The member.
 */
function roova_account_panel_profile( $user ) {
	$saved     = roova_account_just_saved( 'profile' );
	$panel     = '1' === roova_auth_value( 'password_panel' );
	$age       = roova_account_password_age( $user );
	$phone     = get_user_meta( $user->ID, 'billing_phone', true );
	$verified  = roova_account_email_verified( $user );
	?>
	<div class="roova-account__column">
		<?php roova_account_heading(
			__( 'Personal details', 'roova' ),
			__( 'These details appear on your booking vouchers.', 'roova' )
		); ?>

		<?php roova_auth_form_error(); ?>

		<form class="roova-account__form" method="post" action="<?php echo esc_url( roova_account_tab_url( 'profile' ) ); ?>" novalidate data-roova-profile-form>
			<?php wp_nonce_field( 'roova_account_profile', 'roova_profile_nonce' ); ?>
			<input type="hidden" name="roova_account_action" value="profile" />

			<div class="roova-account__fields">
				<div class="roova-account__row">
					<?php
					roova_auth_field( array(
						'name'         => 'first_name',
						'label'        => __( 'First name', 'roova' ),
						'required'     => false,
						'autocomplete' => 'given-name',
						'rule'         => 'first-name',
						'value'        => roova_auth_value( 'first_name', $user->first_name ),
					) );

					roova_auth_field( array(
						'name'         => 'last_name',
						'label'        => __( 'Last name', 'roova' ),
						'required'     => false,
						'autocomplete' => 'family-name',
						'rule'         => 'last-name',
						'value'        => roova_auth_value( 'last_name', $user->last_name ),
					) );
					?>
				</div>

				<?php
				roova_auth_field( array(
					'name'         => 'phone',
					'label'        => __( 'Phone number', 'roova' ),
					'type'         => 'tel',
					'required'     => false,
					'autocomplete' => 'tel',
					'rule'         => 'phone-optional',
					'value'        => roova_auth_value( 'phone', (string) $phone ),
				) );
				?>

				<div class="roova-account__readonly">
					<span class="roova-account__readonly-main">
						<span class="roova-field__caption"><?php esc_html_e( 'Email address', 'roova' ); ?></span>
						<span class="roova-account__readonly-value"><?php echo esc_html( $user->user_email ); ?></span>
					</span>

					<?php if ( $verified ) : ?>
						<span class="roova-account__verified">
							<?php roova_the_icon( 'shield-check', 14 ); ?>
							<?php esc_html_e( 'Verified', 'roova' ); ?>
						</span>
					<?php endif; ?>
				</div>

				<p class="roova-account__note">
					<?php
					$support_url = roova_option( 'support_url', '' );
					if ( $support_url ) {
						printf(
							/* translators: %s: link to support, wrapped in an anchor */
							esc_html__( 'Email is your sign-in ID — %s to change it.', 'roova' ),
							'<a href="' . esc_url( $support_url ) . '">' . esc_html__( 'contact support', 'roova' ) . '</a>'
						);
					} else {
						esc_html_e( 'Email is your sign-in ID — contact support to change it.', 'roova' );
					}
					?>
				</p>
			</div>

			<div class="roova-account__password">
				<div class="roova-account__password-head">
					<div>
						<h2 class="roova-account__h2"><?php esc_html_e( 'Password', 'roova' ); ?></h2>
						<?php if ( $age ) : ?>
							<p class="roova-account__sub"><?php echo esc_html( $age ); ?></p>
						<?php endif; ?>
					</div>

					<button
						class="roova-account__ghost"
						type="button"
						data-roova-password-toggle
						aria-expanded="<?php echo $panel ? 'true' : 'false'; ?>"
						aria-controls="roova-password-panel"
						data-open-label="<?php esc_attr_e( 'Change password', 'roova' ); ?>"
						data-close-label="<?php esc_attr_e( 'Cancel', 'roova' ); ?>"
					>
						<?php echo esc_html( $panel ? __( 'Cancel', 'roova' ) : __( 'Change password', 'roova' ) ); ?>
					</button>
				</div>

				<div class="roova-account__password-fields" id="roova-password-panel" data-roova-password-panel <?php echo $panel ? '' : 'hidden'; ?>>
					<?php
					roova_auth_field( array(
						'name'         => 'current_password',
						'label'        => __( 'Current password', 'roova' ),
						'type'         => 'password',
						'required'     => false,
						'autocomplete' => 'current-password',
						'toggle'       => true,
						'group'        => 'current',
					) );
					?>

					<div class="roova-account__row">
						<?php
						roova_auth_field( array(
							'name'         => 'new_password',
							'label'        => __( 'New password', 'roova' ),
							'type'         => 'password',
							'placeholder'  => __( 'At least 8 characters', 'roova' ),
							'required'     => false,
							'autocomplete' => 'new-password',
							'toggle'       => true,
							'group'        => 'new',
						) );

						roova_auth_field( array(
							'name'         => 'confirm_password',
							'label'        => __( 'Confirm new password', 'roova' ),
							'type'         => 'password',
							'required'     => false,
							'autocomplete' => 'new-password',
							'match'        => true,
							'group'        => 'new',
						) );
						?>
					</div>
				</div>
			</div>

			<div class="roova-account__save">
				<button class="roova-account__btn" type="submit" data-roova-save>
					<span data-roova-save-label><?php esc_html_e( 'Save changes', 'roova' ); ?></span>
					<?php roova_the_icon( 'arrow-right', 15, 'roova-account__btn-arrow' ); ?>
					<?php roova_the_icon( 'check', 15, 'roova-account__btn-check' ); ?>
				</button>

				<p class="roova-account__saved" data-roova-saved <?php echo $saved ? '' : 'hidden'; ?>>
					<?php roova_the_icon( 'check-circle', 15 ); ?>
					<?php esc_html_e( 'Changes saved', 'roova' ); ?>
				</p>
			</div>
		</form>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * 2 — Bookings
 * ---------------------------------------------------------------------- */

/**
 * The chips a stay can wear, and the wording on each.
 *
 * @return array status => array( label, class ).
 */
function roova_account_stay_chips() {
	return array(
		'upcoming'  => __( 'Upcoming', 'roova' ),
		'completed' => __( 'Completed', 'roova' ),
		'cancelled' => __( 'Cancelled', 'roova' ),
		'payment'   => __( 'Payment due', 'roova' ),
	);
}

/**
 * The button on the right of a booking card.
 *
 * Every card but an unpaid one opens the order page, which is where the stay is
 * written out in full and where the follow-on actions live — book it again,
 * print the voucher, write the review. A card is the summary; that page is the
 * detail, and sending "Book again" straight to the hotel used to be the one
 * route that skipped past it.
 *
 * An order still waiting for payment is the exception: nothing on this page
 * matters more than paying for it, so the button says so and goes there.
 *
 * @param array $stay Stay row.
 * @return array label, url.
 */
function roova_account_stay_action( $stay ) {
	if ( 'payment' === $stay['status'] ) {
		return array(
			'label' => __( 'Pay now', 'roova' ),
			'url'   => $stay['order']->get_checkout_payment_url(),
		);
	}

	if ( 'cancelled' === $stay['status'] ) {
		return array(
			'label' => __( 'See details', 'roova' ),
			'url'   => $stay['view_url'],
		);
	}

	return array(
		'label' => __( 'View voucher', 'roova' ),
		'url'   => $stay['view_url'],
	);
}

/**
 * Upcoming and past stays.
 *
 * @param WP_User $user The member.
 */
function roova_account_panel_bookings( $user ) {
	$stays = roova_account_stays( $user->ID );
	$chips = roova_account_stay_chips();

	roova_account_heading(
		__( 'Your bookings', 'roova' ),
		__( 'Upcoming and past stays, newest first.', 'roova' )
	);

	if ( ! $stays ) {
		roova_account_empty(
			__( 'No stays yet. When you book a room it will appear here, with your voucher.', 'roova' ),
			__( 'Find a room', 'roova' ),
			roova_search_url()
		);
		return;
	}
	?>
	<div class="roova-account__list">
		<?php foreach ( $stays as $stay ) : ?>
			<?php
			$action = roova_account_stay_action( $stay );
			$image  = $stay['hotel_id'] && has_post_thumbnail( $stay['hotel_id'] ) ? $stay['hotel_id'] : $stay['room_id'];
			$meta   = array_filter( array(
				$stay['room'],
				roova_account_date_range( $stay['check_in'], $stay['check_out'] ),
				roova_account_guest_label( $stay ),
			) );
			?>
			<article class="roova-booking roova-booking--<?php echo esc_attr( $stay['status'] ); ?>">
				<div class="roova-booking__thumb">
					<?php
					if ( $image && has_post_thumbnail( $image ) ) {
						echo get_the_post_thumbnail( $image, 'roova-room-thumb', array(
							'loading' => 'lazy',
							'alt'     => esc_attr( $stay['hotel'] ),
						) );
					}
					?>
				</div>

				<div class="roova-booking__body">
					<p class="roova-booking__top">
						<span class="roova-booking__chip"><?php echo esc_html( $chips[ $stay['status'] ] ); ?></span>
						<span class="roova-booking__ref"><?php echo esc_html( $stay['ref'] ); ?></span>
					</p>

					<h3 class="roova-booking__hotel">
						<?php if ( $stay['hotel_url'] ) : ?>
							<a href="<?php echo esc_url( $stay['hotel_url'] ); ?>"><?php echo esc_html( $stay['hotel'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $stay['hotel'] ); ?>
						<?php endif; ?>
					</h3>

					<p class="roova-booking__meta"><?php echo esc_html( implode( ' · ', $meta ) ); ?></p>
				</div>

				<div class="roova-booking__side">
					<p class="roova-booking__total"><?php echo wp_kses_post( $stay['total_html'] ); ?></p>

					<a class="roova-account__ghost" href="<?php echo esc_url( $action['url'] ); ?>">
						<?php echo esc_html( $action['label'] ); ?>
					</a>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * 3 — Reviews
 * ---------------------------------------------------------------------- */

/**
 * Reviews written, and stays still waiting for one.
 *
 * @param WP_User $user The member.
 */
function roova_account_panel_reviews( $user ) {
	$reviews = roova_user_reviews( $user->ID );
	$pending = roova_reviewable_stays( $user->ID );
	$open    = absint( roova_auth_value( 'review_hotel' ) );
	$saved   = roova_account_just_saved( 'review' );

	$published = 0;
	foreach ( $reviews as $review ) {
		if ( $review['approved'] ) {
			$published++;
		}
	}

	$sub = sprintf(
		/* translators: 1: number of published reviews, 2: number of stays waiting to be rated */
		_n( '%1$s published · %2$s stay waiting for your rating.', '%1$s published · %2$s stays waiting for your rating.', count( $pending ), 'roova' ),
		number_format_i18n( $published ),
		number_format_i18n( count( $pending ) )
	);

	roova_account_heading( __( 'Your reviews', 'roova' ), $sub );

	if ( $saved ) {
		?>
		<p class="roova-account__flash">
			<?php roova_the_icon( 'check-circle', 15 ); ?>
			<?php esc_html_e( 'Thank you — your review has been sent.', 'roova' ); ?>
		</p>
		<?php
	}

	roova_auth_form_error();

	foreach ( $pending as $stay ) {
		roova_account_review_prompt( $stay, $open === $stay['hotel_id'] );
	}

	if ( ! $reviews ) {
		if ( ! $pending ) {
			roova_account_empty( __( 'Once you have completed a stay you can rate it here.', 'roova' ) );
		}
		return;
	}
	?>
	<div class="roova-account__list">
		<?php foreach ( $reviews as $review ) : ?>
			<article class="roova-review<?php echo $review['approved'] ? '' : ' is-pending'; ?>">
				<div class="roova-review__head">
					<h3 class="roova-review__hotel">
						<a href="<?php echo esc_url( $review['url'] ); ?>"><?php echo esc_html( $review['hotel'] ); ?></a>
					</h3>

					<div class="roova-review__rating">
						<?php echo roova_review_stars( $review['rating'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
						<span class="roova-review__date">
							<?php echo esc_html( date_i18n( 'M Y', strtotime( $review['date'] ) ) ); ?>
						</span>
					</div>
				</div>

				<p class="roova-review__body"><?php echo esc_html( $review['body'] ); ?></p>

				<?php if ( $review['subscores'] ) : ?>
					<div class="roova-review__scores">
						<?php foreach ( $review['subscores'] as $label => $value ) : ?>
							<span>
								<?php echo esc_html( $label ); ?>
								<strong><?php echo esc_html( number_format_i18n( $value, 1 ) ); ?></strong>
							</span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( ! $review['approved'] ) : ?>
					<p class="roova-review__pending">
						<?php roova_the_icon( 'clock', 14 ); ?>
						<?php esc_html_e( 'Waiting to be published.', 'roova' ); ?>
					</p>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * The gold callout asking a member to rate a stay, and the form behind it.
 *
 * @param array $stay Stay row.
 * @param bool  $open Whether the form starts open (it had an error).
 */
function roova_account_review_prompt( $stay, $open = false ) {
	$id = 'roova-review-' . $stay['hotel_id'];
	?>
	<div class="roova-account__prompt">
		<div class="roova-account__prompt-row">
			<div class="roova-account__prompt-text">
				<?php roova_the_icon( 'pen-line', 18 ); ?>
				<span>
					<strong>
						<?php
						printf(
							/* translators: %s: hotel name */
							esc_html__( 'Rate your stay at %s', 'roova' ),
							esc_html( $stay['hotel'] )
						);
						?>
					</strong>
					<span class="roova-account__prompt-sub">
						<?php
						/*
						 * "Aug 21, 2026" rather than roova_format_date()'s
						 * "Aug 21 / 2026" — that slash reads as a date range in
						 * the search bar, and as a typo inside a sentence.
						 */
						printf(
							/* translators: %s: checkout date */
							esc_html__( 'Checked out %s · takes about a minute', 'roova' ),
							esc_html( date_i18n( 'M j, Y', strtotime( $stay['check_out'] . ' 00:00:00' ) ) )
						);
						?>
					</span>
				</span>
			</div>

			<button
				class="roova-account__btn roova-account__btn--sm"
				type="button"
				data-roova-review-toggle="<?php echo esc_attr( $id ); ?>"
				aria-expanded="<?php echo $open ? 'true' : 'false'; ?>"
				aria-controls="<?php echo esc_attr( $id ); ?>"
			>
				<?php esc_html_e( 'Write review', 'roova' ); ?>
			</button>
		</div>

		<form class="roova-account__review-form" id="<?php echo esc_attr( $id ); ?>" method="post" action="<?php echo esc_url( roova_account_tab_url( 'reviews' ) ); ?>" novalidate data-roova-review-form <?php echo $open ? '' : 'hidden'; ?>>
			<?php wp_nonce_field( 'roova_account_review', 'roova_review_nonce' ); ?>
			<input type="hidden" name="roova_account_action" value="review" />
			<input type="hidden" name="roova_hotel_id" value="<?php echo esc_attr( $stay['hotel_id'] ); ?>" />

			<fieldset class="roova-rate">
				<legend class="roova-field__caption"><?php esc_html_e( 'Your rating', 'roova' ); ?></legend>

				<div class="roova-rate__stars" data-roova-rate>
					<?php for ( $star = 1; $star <= 5; $star++ ) : ?>
						<label class="roova-rate__star">
							<input
								type="radio"
								name="rating"
								value="<?php echo esc_attr( $star ); ?>"
								<?php checked( (int) roova_auth_value( 'review_rating' ), $star ); ?>
							/>
							<span aria-hidden="true">★</span>
							<span class="screen-reader-text">
								<?php
								printf(
									/* translators: %s: number of stars */
									esc_html( _n( '%s star', '%s stars', $star, 'roova' ) ),
									esc_html( number_format_i18n( $star ) )
								);
								?>
							</span>
						</label>
					<?php endfor; ?>
				</div>

				<?php $error = roova_auth_error( 'review_rating' ); ?>
				<p class="roova-field__error" data-roova-error <?php echo $error ? '' : 'hidden'; ?>>
					<?php echo esc_html( $error ); ?>
				</p>
			</fieldset>

			<div class="roova-account__row roova-account__row--three">
				<?php foreach ( roova_review_subscores() as $key => $label ) : ?>
					<label class="roova-field roova-field--select">
						<span class="roova-field__caption"><?php echo esc_html( $label ); ?></span>
						<select class="roova-field__input" name="roova_subscore[<?php echo esc_attr( $key ); ?>]">
							<option value="0"><?php esc_html_e( 'Not rated', 'roova' ); ?></option>
							<?php for ( $score = 5; $score >= 1; $score-- ) : ?>
								<option value="<?php echo esc_attr( $score ); ?>"><?php echo esc_html( number_format_i18n( $score ) ); ?></option>
							<?php endfor; ?>
						</select>
					</label>
				<?php endforeach; ?>
			</div>

			<label class="roova-field roova-field--area">
				<span class="roova-field__caption"><?php esc_html_e( 'Your review', 'roova' ); ?></span>
				<textarea
					class="roova-field__input"
					name="roova_review_body"
					rows="5"
					placeholder="<?php esc_attr_e( 'What would you tell a friend about this stay?', 'roova' ); ?>"
					data-roova-rule="review-body"
				><?php echo esc_textarea( roova_auth_value( 'review_body' ) ); ?></textarea>
			</label>

			<?php $body_error = roova_auth_error( 'review_body' ); ?>
			<p class="roova-field__error" data-roova-error <?php echo $body_error ? '' : 'hidden'; ?>>
				<?php echo esc_html( $body_error ); ?>
			</p>

			<div class="roova-account__save">
				<button class="roova-account__btn" type="submit">
					<span><?php esc_html_e( 'Publish review', 'roova' ); ?></span>
					<?php roova_the_icon( 'arrow-right', 15 ); ?>
				</button>
			</div>
		</form>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * 4 — Likes
 * ---------------------------------------------------------------------- */

/**
 * The saved stays grid.
 *
 * @param WP_User $user The member.
 */
function roova_account_panel_likes( $user ) {
	$likes = roova_get_likes( $user->ID );

	roova_account_heading(
		__( 'Saved stays', 'roova' ),
		__( 'Tap the heart to remove a stay from your list.', 'roova' )
	);

	if ( ! $likes ) {
		roova_account_empty(
			__( 'Nothing saved yet. Tap the heart on any hotel and it will be waiting here.', 'roova' ),
			__( 'Browse hotels', 'roova' ),
			roova_search_url()
		);
		return;
	}
	?>
	<div class="roova-account__grid" data-roova-likes>

		<?php foreach ( $likes as $hotel_id ) : ?>
			<?php
			$product = wc_get_product( $hotel_id );
			if ( ! $product ) {
				continue;
			}

			$place  = roova_hotel_location_label( $hotel_id );
			$rating = roova_hotel_rating( $hotel_id );
			$rate   = method_exists( $product, 'get_lowest_room_rate' ) ? $product->get_lowest_room_rate() : $product->get_price();
			$url    = roova_criteria_url( get_permalink( $hotel_id ) );
			?>
			<article class="roova-like-card" data-roova-like-card="<?php echo esc_attr( $hotel_id ); ?>">
				<div class="roova-like-card__media">
					<a href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( get_the_title( $hotel_id ) ); ?>">
						<?php
						if ( has_post_thumbnail( $hotel_id ) ) {
							echo get_the_post_thumbnail( $hotel_id, 'roova-hotel-card', array(
								'loading' => 'lazy',
								'alt'     => esc_attr( get_the_title( $hotel_id ) ),
							) );
						}
						?>
					</a>

					<?php roova_like_button( $hotel_id, array( 'class' => 'roova-like--card' ) ); ?>
				</div>

				<div class="roova-like-card__body">
					<h3 class="roova-like-card__name">
						<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( get_the_title( $hotel_id ) ); ?></a>
					</h3>

					<?php if ( $place ) : ?>
						<p class="roova-like-card__place"><?php echo esc_html( $place ); ?></p>
					<?php endif; ?>

					<div class="roova-like-card__foot">
						<?php if ( '' !== $rate && $rate > 0 ) : ?>
							<p class="roova-like-card__price">
								<?php echo wp_kses_post( wc_price( $rate ) ); ?>
								<span><?php esc_html_e( '/ night', 'roova' ); ?></span>
							</p>
						<?php else : ?>
							<p class="roova-like-card__price"><span><?php esc_html_e( 'Check availability', 'roova' ); ?></span></p>
						<?php endif; ?>

						<?php if ( $rating > 0 ) : ?>
							<p class="roova-like-card__rating">★ <?php echo esc_html( number_format_i18n( $rating, 1 ) ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			</article>
		<?php endforeach; ?>
	</div>

	<?php
	/*
	 * Unhiding the last heart empties the grid without a page load, so the empty
	 * state is already in the document waiting for the script to reveal it.
	 */
	?>
	<div data-roova-likes-empty hidden>
		<?php
		roova_account_empty(
			__( 'Nothing saved yet. Tap the heart on any hotel and it will be waiting here.', 'roova' ),
			__( 'Browse hotels', 'roova' ),
			roova_search_url()
		);
		?>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * 5 — VIP
 * ---------------------------------------------------------------------- */

/**
 * The membership card, the tier rail and the benefits of the current tier.
 *
 * @param WP_User $user The member.
 */
function roova_account_panel_vip( $user ) {
	$tiers = roova_vip_tiers();
	if ( ! $tiers ) {
		return;
	}

	$count   = roova_account_completed_count( $user->ID );
	// Where the member stands, which is their earned tier unless an admin has
	// pinned them to one — see roova_vip_index_for_user().
	$index   = roova_vip_index_for_user( $user->ID );
	$tier    = isset( $tiers[ $index ] ) ? $tiers[ $index ] : null;
	$next    = roova_vip_next_tier( $count, $index );
	$top     = roova_vip_top_threshold();
	$first   = $user->first_name ? $user->first_name : $user->display_name;
	?>
	<div class="roova-vip">
		<div class="roova-vip__card">
			<div class="roova-vip__greeting">
				<span class="roova-vip__initial" aria-hidden="true"><?php echo esc_html( roova_account_initial( $user ) ); ?></span>

				<div>
					<p class="roova-vip__hi">
						<?php
						printf(
							/* translators: %s: member's first name */
							esc_html__( 'Hi %s', 'roova' ),
							esc_html( $first )
						);
						?>
					</p>

					<?php if ( $tier ) : ?>
						<p class="roova-vip__badge">
							<span class="roova-vip__badge-left">
								<?php roova_the_icon( 'star', 12 ); ?>
								<?php esc_html_e( 'VIP', 'roova' ); ?>
							</span>
							<span class="roova-vip__badge-right"><?php echo esc_html( $tier['name'] ); ?></span>
						</p>
					<?php endif; ?>
				</div>
			</div>

			<div class="roova-vip__status">
				<p class="roova-vip__status-label"><?php esc_html_e( 'Your status', 'roova' ); ?></p>
				<p class="roova-vip__status-name">
					<?php
					if ( $tier ) {
						printf(
							/* translators: %s: tier name, e.g. "VIP Gold" */
							esc_html__( 'Roova %s', 'roova' ),
							esc_html( $tier['name'] )
						);
					} else {
						esc_html_e( 'Not a member yet', 'roova' );
					}
					?>
				</p>
			</div>

			<div class="roova-vip__progress">
				<p><?php esc_html_e( 'Progress to RoovaVIP status', 'roova' ); ?></p>
				<p class="roova-vip__progress-count">
					<?php
					printf(
						/* translators: 1: completed bookings, 2: bookings needed for the top tier */
						esc_html__( '%1$s/%2$s bookings completed', 'roova' ),
						esc_html( number_format_i18n( $count ) ),
						esc_html( number_format_i18n( $top ) )
					);
					?>
				</p>
			</div>

			<div class="roova-vip__rail-wrap">
			<ol class="roova-vip__rail" style="--roova-rail-steps:<?php echo absint( count( $tiers ) ); ?>">
				<?php foreach ( $tiers as $i => $rail_tier ) : ?>
					<?php
					$reached = ( $i <= $index );
					$classes = array( 'roova-vip__node' );
					if ( $reached ) {
						$classes[] = 'is-reached';
					}
					if ( $i === $index ) {
						$classes[] = 'is-current';
					}
					?>
					<li class="roova-vip__step">
						<span class="roova-vip__track" aria-hidden="true">
							<i class="roova-vip__line<?php echo $i <= $index ? ' is-filled' : ''; ?><?php echo 0 === $i ? ' is-hidden' : ''; ?>"></i>
							<span class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
								<?php roova_the_icon( 'star', 14 ); ?>
							</span>
							<i class="roova-vip__line<?php echo $i < $index ? ' is-filled' : ''; ?><?php echo ( count( $tiers ) - 1 ) === $i ? ' is-hidden' : ''; ?>"></i>
						</span>

						<span class="roova-vip__step-name<?php echo $reached ? ' is-reached' : ''; ?>">
							<?php echo esc_html( $rail_tier['name'] ); ?>
						</span>
						<span class="roova-vip__step-req<?php echo $i === $index ? ' is-current' : ''; ?>">
							<?php echo esc_html( roova_vip_requirement_label( $rail_tier ) ); ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ol>
			</div>

			<?php if ( $next ) : ?>
				<p class="roova-vip__next">
					<?php
					printf(
						/* translators: 1: number of bookings still needed, 2: the next tier's name */
						esc_html( _n( '%1$s more completed booking to reach %2$s.', '%1$s more completed bookings to reach %2$s.', $next['remaining'], 'roova' ) ),
						esc_html( number_format_i18n( $next['remaining'] ) ),
						esc_html( $next['tier']['name'] )
					);
					?>
				</p>
			<?php endif; ?>
		</div>

		<?php if ( $tier && ! empty( $tier['benefits'] ) ) : ?>
			<?php roova_account_heading(
				sprintf(
					/* translators: %s: tier name */
					__( 'Your %s benefits', 'roova' ),
					$tier['name']
				),
				__( 'Applied automatically at checkout.', 'roova' )
			); ?>

			<div class="roova-vip__benefits">
				<?php foreach ( $tier['benefits'] as $benefit ) : ?>
					<div class="roova-vip__benefit">
						<?php roova_the_icon( $benefit['icon'], 17 ); ?>
						<span>
							<strong><?php echo esc_html( $benefit['title'] ); ?></strong>
							<?php if ( $benefit['note'] ) : ?>
								<span><?php echo esc_html( $benefit['note'] ); ?></span>
							<?php endif; ?>
						</span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * 6 — Cashback rewards
 * ---------------------------------------------------------------------- */

/**
 * The balances, the offers running now, and everything already earned.
 *
 * Every figure is read from the member's own ledger (see inc/cashback.php) —
 * nothing on this panel is a placeholder, and a member who has earned nothing
 * reads three zeroes rather than the handoff's sample amounts.
 *
 * @param WP_User $user The member.
 */
function roova_account_panel_cashback( $user ) {
	$balances = roova_cashback_balances( $user->ID );
	$rewards  = roova_cashback_active_rewards();
	$activity = roova_cashback_activity( $user->ID );
	?>
	<div class="roova-cashback">
		<div class="roova-cashback__balances">
			<div class="roova-cashback__balance roova-cashback__balance--available">
				<span class="roova-cashback__balance-label"><?php esc_html_e( 'Available', 'roova' ); ?></span>
				<span class="roova-cashback__balance-figure">
					<?php echo wp_kses_post( roova_cashback_amount( $balances['available'] ) ); ?>
				</span>

				<?php if ( $balances['available'] > 0 ) : ?>
					<?php /* A link, not a button: this theme shows the balance, it never spends it. */ ?>
					<a class="roova-cashback__cta" href="<?php echo esc_url( roova_search_url() ); ?>">
						<span><?php esc_html_e( 'Redeem on next stay', 'roova' ); ?></span>
						<?php roova_the_icon( 'arrow-right', 15 ); ?>
					</a>
				<?php else : ?>
					<p class="roova-cashback__balance-note">
						<?php esc_html_e( 'Cashback lands here once a qualifying stay has cleared.', 'roova' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<div class="roova-cashback__balance">
				<span class="roova-cashback__balance-label"><?php esc_html_e( 'Pending', 'roova' ); ?></span>
				<span class="roova-cashback__balance-figure">
					<?php echo wp_kses_post( roova_cashback_amount( $balances['pending'] ) ); ?>
				</span>

				<p class="roova-cashback__pending">
					<?php if ( $balances['pending'] > 0 ) : ?>
						<span class="roova-cashback__dot" aria-hidden="true"></span>
					<?php endif; ?>

					<span>
						<?php
						if ( $balances['clears'] ) {
							printf(
								/* translators: %s: the date the next pending amount clears */
								esc_html__( 'Clears on %s', 'roova' ),
								esc_html( date_i18n( 'M j, Y', strtotime( $balances['clears'] . ' 00:00:00' ) ) )
							);
						} else {
							esc_html_e( 'Cashback clears a set number of days after checkout.', 'roova' );
						}
						?>
					</span>
				</p>
			</div>

			<div class="roova-cashback__balance">
				<span class="roova-cashback__balance-label"><?php esc_html_e( 'Earned all time', 'roova' ); ?></span>
				<span class="roova-cashback__balance-figure">
					<?php echo wp_kses_post( roova_cashback_amount( $balances['earned'] ) ); ?>
				</span>

				<p class="roova-cashback__balance-note">
					<?php
					if ( $balances['stays'] > 0 ) {
						printf(
							/* translators: %s: number of stays that have earned cashback */
							esc_html( _n( 'Across %s stay.', 'Across %s stays.', $balances['stays'], 'roova' ) ),
							esc_html( number_format_i18n( $balances['stays'] ) )
						);
					} else {
						esc_html_e( 'Nothing earned yet.', 'roova' );
					}
					?>
				</p>
			</div>
		</div>

		<?php roova_account_heading(
			__( 'Our rewards', 'roova' ),
			__( 'Active offers — cashback is added automatically once the stay completes.', 'roova' )
		); ?>

		<?php if ( $rewards ) : ?>
			<div class="roova-cashback__offers">
				<?php foreach ( $rewards as $reward ) : ?>
					<div class="roova-cashback__offer">
						<span class="roova-cashback__offer-icon" aria-hidden="true">
							<?php roova_the_icon( $reward['icon'], 18 ); ?>
						</span>

						<div class="roova-cashback__offer-body">
							<div class="roova-cashback__offer-head">
								<h3 class="roova-cashback__offer-title">
									<?php echo esc_html( roova_cashback_reward_title( $reward ) ); ?>
								</h3>
								<span class="roova-cashback__offer-reward">
									+<?php echo wp_kses_post( roova_cashback_amount( $reward['amount'] ) ); ?>
								</span>
							</div>

							<p class="roova-cashback__offer-detail">
								<?php echo esc_html( roova_cashback_reward_detail( $reward ) ); ?>
							</p>

							<p class="roova-cashback__offer-validity">
								<?php roova_the_icon( 'calendar-check', 13 ); ?>
								<?php /* Wrapped, and the icon is flex:none — a bare text node beside an SVG breaks the date mid-phrase. */ ?>
								<span><?php echo esc_html( roova_cashback_reward_validity( $reward ) ); ?></span>
							</p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<?php roova_account_empty(
				__( 'No offers are running just now. Cashback rewards appear here as soon as we add them.', 'roova' ),
				__( 'Find a room', 'roova' ),
				roova_search_url()
			); ?>
		<?php endif; ?>

		<?php roova_account_heading( __( 'Activity', 'roova' ) ); ?>

		<?php if ( $activity ) : ?>
			<div class="roova-cashback__activity">
				<?php foreach ( $activity as $row ) : ?>
					<div class="roova-cashback__row">
						<?php roova_the_icon( $row['icon'], 17 ); ?>

						<div class="roova-cashback__row-body">
							<p class="roova-cashback__row-label"><?php echo esc_html( $row['label'] ); ?></p>
							<p class="roova-cashback__row-meta">
								<?php
								printf(
									/* translators: 1: date, 2: state, e.g. "Cleared" */
									esc_html__( '%1$s · %2$s', 'roova' ),
									esc_html( $row['date'] ),
									esc_html( $row['state'] )
								);
								?>
							</p>
						</div>

						<span class="roova-cashback__row-amount is-<?php echo esc_attr( $row['tone'] ); ?>">
							<?php echo esc_html( $row['sign'] ); ?><?php echo wp_kses_post( $row['amount'] ); ?>
						</span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<?php roova_account_empty(
				__( 'Nothing here yet. Cashback shows up once a qualifying stay is complete.', 'roova' )
			); ?>
		<?php endif; ?>
	</div>
	<?php
}
