<?php
/**
 * WooCommerce → Settings → Cashback rewards: the offers a site runs.
 *
 * A plain settings tab rather than a `WC_Settings_Page` subclass, the same
 * shape as RoovaVIP's and for the same reason — the screen is one repeatable
 * list of cards, which WooCommerce's field types cannot draw, so there is
 * nothing to inherit.
 *
 * Two fields on every row are hidden and posted straight back: `id`, which the
 * member ledgers point at, and `created`, the day the offer was added. Neither
 * is the client's business, and both have to survive a save — a re-dated offer
 * would reach back over stays it had already declined to pay for, and a
 * re-issued id would break the link from a ledger entry to the rule behind it.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * The settings tab's id.
 */
const ROOVA_CASHBACK_TAB = 'roova_cashback';

/**
 * Add the tab.
 *
 * @param array $tabs Existing tabs.
 * @return array
 */
function roova_cashback_settings_tab( $tabs ) {
	$tabs[ ROOVA_CASHBACK_TAB ] = __( 'Cashback rewards', 'roova' );

	return $tabs;
}
add_filter( 'woocommerce_settings_tabs_array', 'roova_cashback_settings_tab', 51 );

/**
 * Every hotel, for the reward's hotel picker.
 *
 * "All Hotels" is the first choice and the value is 0 — an offer that names no
 * hotel runs at every one of them, including hotels added after it was written.
 *
 * @return array id => name.
 */
function roova_cashback_hotel_choices() {
	$choices = array( 0 => __( 'All Hotels', 'roova' ) );

	foreach ( roova_get_hotel_ids() as $hotel_id ) {
		$title = get_the_title( $hotel_id );

		$choices[ $hotel_id ] = $title ? $title : sprintf(
			/* translators: %d: hotel product ID */
			__( 'Hotel #%d', 'roova' ),
			$hotel_id
		);
	}

	return $choices;
}

/**
 * Draw the tab.
 */
function roova_cashback_settings_render() {
	$rewards = roova_cashback_rewards();
	$icons   = roova_cashback_icons();
	$hotels  = roova_cashback_hotel_choices();

	$blank = array(
		'id'         => '',
		'created'    => '',
		'title'      => '',
		'detail'     => '',
		'icon'       => 'coins',
		'hotel'      => 0,
		'nights'     => 1,
		'amount'     => '',
		'expires'    => '',
		'clear_days' => ROOVA_CASHBACK_CLEAR_DAYS,
	);
	?>
	<div class="roova-cashback-settings">
		<h2><?php esc_html_e( 'Cashback rewards', 'roova' ); ?></h2>

		<p class="description">
			<?php esc_html_e( 'A reward pays out once a guest\'s stay is complete — they have checked out and the order is paid. Members see their balance and everything they have earned on the Cashback rewards tab of My account.', 'roova' ); ?>
		</p>

		<p class="description">
			<?php esc_html_e( 'The nights figure is a minimum: a reward set to 7 nights also pays out on a 9-night stay. A stay that qualifies for more than one reward earns the most valuable of them, once — rewards do not stack.', 'roova' ); ?>
		</p>

		<p class="description">
			<?php esc_html_e( 'A reward applies to stays that check out between the day you add it and the day it expires, so adding one never pays out for stays that finished before it existed. Editing or deleting a reward never changes cashback a member has already earned.', 'roova' ); ?>
		</p>

		<p class="description">
			<?php esc_html_e( 'Cashback is shown to the member; nothing here changes what they are charged at checkout.', 'roova' ); ?>
		</p>

		<?php if ( count( $hotels ) < 2 ) : ?>
			<p class="description roova-cashback-settings__note">
				<?php esc_html_e( 'There are no hotels published yet, so every reward here will apply to all hotels.', 'roova' ); ?>
			</p>
		<?php endif; ?>

		<div class="roova-cashback-settings__list" data-roova-cashback-list>
			<?php foreach ( $rewards as $index => $reward ) : ?>
				<?php roova_cashback_settings_reward( $index, $reward, $icons, $hotels ); ?>
			<?php endforeach; ?>
		</div>

		<?php if ( ! $rewards ) : ?>
			<p class="roova-cashback-settings__empty" data-roova-cashback-empty>
				<?php esc_html_e( 'No rewards yet. Add one and it starts applying to stays that check out from today.', 'roova' ); ?>
			</p>
		<?php endif; ?>

		<p>
			<button type="button" class="button button-secondary" data-roova-cashback-add>
				<?php esc_html_e( 'Add reward', 'roova' ); ?>
			</button>
		</p>

		<template data-roova-cashback-template>
			<?php roova_cashback_settings_reward( '__REWARD__', $blank, $icons, $hotels ); ?>
		</template>
	</div>
	<?php
}
add_action( 'woocommerce_settings_tabs_' . ROOVA_CASHBACK_TAB, 'roova_cashback_settings_render' );

/**
 * One reward's card.
 *
 * @param int|string $index   Row index, or the template placeholder.
 * @param array      $reward  Reward.
 * @param array      $icons   Icon choices.
 * @param array      $hotels  Hotel choices.
 */
function roova_cashback_settings_reward( $index, $reward, $icons, $hotels ) {
	$name     = 'roova_cashback[' . $index . ']';
	$field_id = 'roova-cashback-' . $index;
	?>
	<div class="roova-cashback-reward" data-roova-cashback-reward="<?php echo esc_attr( $index ); ?>">
		<input type="hidden" name="<?php echo esc_attr( $name . '[id]' ); ?>" value="<?php echo esc_attr( $reward['id'] ); ?>" />
		<input type="hidden" name="<?php echo esc_attr( $name . '[created]' ); ?>" value="<?php echo esc_attr( $reward['created'] ); ?>" />

		<div class="roova-cashback-reward__row">
			<label class="roova-cashback-reward__field roova-cashback-reward__field--wide">
				<span><?php esc_html_e( 'Hotel', 'roova' ); ?></span>
				<select name="<?php echo esc_attr( $name . '[hotel]' ); ?>" id="<?php echo esc_attr( $field_id . '-hotel' ); ?>">
					<?php foreach ( $hotels as $hotel_id => $hotel_name ) : ?>
						<option value="<?php echo esc_attr( $hotel_id ); ?>" <?php selected( (int) $reward['hotel'], (int) $hotel_id ); ?>>
							<?php echo esc_html( $hotel_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>

			<label class="roova-cashback-reward__field roova-cashback-reward__field--small">
				<span><?php esc_html_e( 'Duration (nights)', 'roova' ); ?></span>
				<input
					type="number"
					min="1"
					step="1"
					name="<?php echo esc_attr( $name . '[nights]' ); ?>"
					value="<?php echo esc_attr( (int) $reward['nights'] ); ?>"
				/>
			</label>

			<label class="roova-cashback-reward__field roova-cashback-reward__field--small">
				<span>
					<?php
					printf(
						/* translators: %s: the store's currency symbol, e.g. "RM" */
						esc_html__( 'Reward amount (%s)', 'roova' ),
						esc_html( roova_cashback_currency_symbol() )
					);
					?>
				</span>
				<input
					type="number"
					min="0"
					step="0.01"
					name="<?php echo esc_attr( $name . '[amount]' ); ?>"
					value="<?php echo esc_attr( $reward['amount'] ); ?>"
					placeholder="10.00"
				/>
			</label>

			<label class="roova-cashback-reward__field">
				<span><?php esc_html_e( 'Expiry date', 'roova' ); ?></span>
				<input
					type="date"
					name="<?php echo esc_attr( $name . '[expires]' ); ?>"
					value="<?php echo esc_attr( $reward['expires'] ); ?>"
				/>
			</label>

			<label class="roova-cashback-reward__field roova-cashback-reward__field--small">
				<span><?php esc_html_e( 'Clears after (days)', 'roova' ); ?></span>
				<input
					type="number"
					min="0"
					step="1"
					name="<?php echo esc_attr( $name . '[clear_days]' ); ?>"
					value="<?php echo esc_attr( (int) $reward['clear_days'] ); ?>"
				/>
			</label>

			<button type="button" class="button-link roova-cashback-reward__remove" data-roova-cashback-remove>
				<?php esc_html_e( 'Remove reward', 'roova' ); ?>
			</button>
		</div>

		<div class="roova-cashback-reward__row roova-cashback-reward__row--copy">
			<label class="roova-cashback-reward__field">
				<span><?php esc_html_e( 'Icon', 'roova' ); ?></span>
				<select name="<?php echo esc_attr( $name . '[icon]' ); ?>">
					<?php foreach ( $icons as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $reward['icon'], $slug ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>

			<label class="roova-cashback-reward__field roova-cashback-reward__field--grow">
				<span><?php esc_html_e( 'Card title (optional)', 'roova' ); ?></span>
				<input
					type="text"
					name="<?php echo esc_attr( $name . '[title]' ); ?>"
					value="<?php echo esc_attr( $reward['title'] ); ?>"
					placeholder="<?php esc_attr_e( 'Leave blank to use the hotel name', 'roova' ); ?>"
				/>
			</label>

			<label class="roova-cashback-reward__field roova-cashback-reward__field--grow">
				<span><?php esc_html_e( 'Card description (optional)', 'roova' ); ?></span>
				<input
					type="text"
					name="<?php echo esc_attr( $name . '[detail]' ); ?>"
					value="<?php echo esc_attr( $reward['detail'] ); ?>"
					placeholder="<?php esc_attr_e( 'Leave blank to describe the rule automatically', 'roova' ); ?>"
				/>
			</label>
		</div>

		<?php if ( $reward['created'] ) : ?>
			<p class="roova-cashback-reward__since">
				<?php
				printf(
					/* translators: %s: date the reward was added */
					esc_html__( 'Running since %s.', 'roova' ),
					esc_html( date_i18n( get_option( 'date_format' ), strtotime( $reward['created'] . ' 00:00:00' ) ) )
				);
				?>
			</p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * The store's currency symbol, for the amount field's label.
 *
 * @return string
 */
function roova_cashback_currency_symbol() {
	if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
		return html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );
	}

	return '';
}

/**
 * Save the tab.
 *
 * WooCommerce checks its own nonce before firing this, and the capability again
 * here — this option decides what every member is told they have earned.
 */
function roova_cashback_settings_save() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	check_admin_referer( 'woocommerce-settings' );

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- every leaf is sanitised in roova_cashback_sanitize_rewards().
	$posted = isset( $_POST['roova_cashback'] ) ? wp_unslash( $_POST['roova_cashback'] ) : array();

	update_option( ROOVA_CASHBACK_OPTION, roova_cashback_sanitize_rewards( $posted ) );
}
add_action( 'woocommerce_update_options_' . ROOVA_CASHBACK_TAB, 'roova_cashback_settings_save' );

/**
 * The screen's own script and styles.
 *
 * @param string $hook Current admin page.
 */
function roova_cashback_settings_assets( $hook ) {
	if ( 'woocommerce_page_wc-settings' !== $hook ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which settings tab is open, to decide whether to enqueue.
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

	if ( ROOVA_CASHBACK_TAB !== $tab ) {
		return;
	}

	wp_enqueue_style( 'roova-admin', ROOVA_URI . 'assets/css/admin.css', array(), ROOVA_VERSION );
	wp_enqueue_script( 'roova-admin-cashback', ROOVA_URI . 'assets/js/admin-cashback.js', array( 'jquery' ), ROOVA_VERSION, true );

	wp_localize_script(
		'roova-admin-cashback',
		'roovaCashback',
		array(
			'confirmRemove' => __( 'Remove this reward? Cashback members have already earned from it is kept.', 'roova' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'roova_cashback_settings_assets' );
