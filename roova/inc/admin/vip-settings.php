<?php
/**
 * WooCommerce → Settings → RoovaVIP: the tiers and what each one gets.
 *
 * A plain settings tab rather than a `WC_Settings_Page` subclass: the screen is
 * one repeatable list inside another, which WooCommerce's field types cannot
 * draw, so there is nothing to inherit and a class would only need its own
 * renderer anyway.
 *
 * Deleting every tier switches RoovaVIP off — the tab disappears from the
 * account page — and adding one brings it back. That is deliberate: a client
 * who does not run a loyalty programme should not have to look at one.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * The settings tab's id.
 */
const ROOVA_VIP_TAB = 'roova_vip';

/**
 * Add the tab.
 *
 * @param array $tabs Existing tabs.
 * @return array
 */
function roova_vip_settings_tab( $tabs ) {
	$tabs[ ROOVA_VIP_TAB ] = __( 'RoovaVIP', 'roova' );

	return $tabs;
}
add_filter( 'woocommerce_settings_tabs_array', 'roova_vip_settings_tab', 50 );

/**
 * Draw the tab.
 */
function roova_vip_settings_render() {
	$tiers = roova_vip_tiers();
	$icons = roova_vip_benefit_icons();
	?>
	<div class="roova-vip-settings">
		<h2><?php esc_html_e( 'RoovaVIP tiers', 'roova' ); ?></h2>

		<p class="description">
			<?php esc_html_e( 'Tiers are earned by completed bookings — a stay counts once the guest has checked out and the order is paid. Members see their tier, their progress and the benefits of the tier they are on, on the VIP tab of My account.', 'roova' ); ?>
		</p>

		<p class="description">
			<?php esc_html_e( 'Benefits are shown to the member; nothing here changes what they are charged. Delete every tier to switch RoovaVIP off.', 'roova' ); ?>
		</p>

		<div class="roova-vip-settings__list" data-roova-vip-list>
			<?php foreach ( $tiers as $index => $tier ) : ?>
				<?php roova_vip_settings_tier( $index, $tier, $icons ); ?>
			<?php endforeach; ?>
		</div>

		<p>
			<button type="button" class="button button-secondary" data-roova-vip-add-tier>
				<?php esc_html_e( 'Add tier', 'roova' ); ?>
			</button>
		</p>

		<template data-roova-vip-tier-template>
			<?php roova_vip_settings_tier( '__TIER__', array( 'name' => '', 'min' => 0, 'benefits' => array() ), $icons ); ?>
		</template>

		<template data-roova-vip-benefit-template>
			<?php roova_vip_settings_benefit( '__TIER__', '__BENEFIT__', array( 'icon' => '', 'title' => '', 'note' => '' ), $icons ); ?>
		</template>
	</div>
	<?php
}
add_action( 'woocommerce_settings_tabs_' . ROOVA_VIP_TAB, 'roova_vip_settings_render' );

/**
 * One tier's card.
 *
 * @param int|string $index Row index, or the template placeholder.
 * @param array      $tier  Tier.
 * @param array      $icons Icon choices.
 */
function roova_vip_settings_tier( $index, $tier, $icons ) {
	$name = 'roova_vip[' . $index . ']';
	?>
	<div class="roova-vip-tier" data-roova-vip-tier="<?php echo esc_attr( $index ); ?>">
		<div class="roova-vip-tier__head">
			<label class="roova-vip-tier__field">
				<span><?php esc_html_e( 'Tier name', 'roova' ); ?></span>
				<input
					type="text"
					name="<?php echo esc_attr( $name . '[name]' ); ?>"
					value="<?php echo esc_attr( $tier['name'] ); ?>"
					placeholder="<?php esc_attr_e( 'VIP Gold', 'roova' ); ?>"
					class="regular-text"
				/>
			</label>

			<label class="roova-vip-tier__field roova-vip-tier__field--small">
				<span><?php esc_html_e( 'Completed bookings', 'roova' ); ?></span>
				<input
					type="number"
					min="0"
					step="1"
					name="<?php echo esc_attr( $name . '[min]' ); ?>"
					value="<?php echo esc_attr( (int) $tier['min'] ); ?>"
				/>
			</label>

			<button type="button" class="button-link roova-vip-tier__remove" data-roova-vip-remove-tier>
				<?php esc_html_e( 'Remove tier', 'roova' ); ?>
			</button>
		</div>

		<div class="roova-vip-tier__benefits" data-roova-vip-benefits>
			<?php foreach ( (array) $tier['benefits'] as $benefit_index => $benefit ) : ?>
				<?php roova_vip_settings_benefit( $index, $benefit_index, $benefit, $icons ); ?>
			<?php endforeach; ?>
		</div>

		<p>
			<button type="button" class="button button-small" data-roova-vip-add-benefit>
				<?php esc_html_e( 'Add benefit', 'roova' ); ?>
			</button>
		</p>
	</div>
	<?php
}

/**
 * One benefit row.
 *
 * @param int|string $tier_index    Tier index, or the template placeholder.
 * @param int|string $benefit_index Benefit index, or the template placeholder.
 * @param array      $benefit       Benefit.
 * @param array      $icons         Icon choices.
 */
function roova_vip_settings_benefit( $tier_index, $benefit_index, $benefit, $icons ) {
	$name = 'roova_vip[' . $tier_index . '][benefits][' . $benefit_index . ']';
	?>
	<div class="roova-vip-benefit" data-roova-vip-benefit>
		<label>
			<span class="screen-reader-text"><?php esc_html_e( 'Icon', 'roova' ); ?></span>
			<select name="<?php echo esc_attr( $name . '[icon]' ); ?>">
				<?php foreach ( $icons as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $benefit['icon'], $slug ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</label>

		<label class="roova-vip-benefit__title">
			<span class="screen-reader-text"><?php esc_html_e( 'Benefit', 'roova' ); ?></span>
			<input
				type="text"
				name="<?php echo esc_attr( $name . '[title]' ); ?>"
				value="<?php echo esc_attr( $benefit['title'] ); ?>"
				placeholder="<?php esc_attr_e( 'Late checkout until 2pm', 'roova' ); ?>"
			/>
		</label>

		<label class="roova-vip-benefit__note">
			<span class="screen-reader-text"><?php esc_html_e( 'Note', 'roova' ); ?></span>
			<input
				type="text"
				name="<?php echo esc_attr( $name . '[note]' ); ?>"
				value="<?php echo esc_attr( $benefit['note'] ); ?>"
				placeholder="<?php esc_attr_e( 'Subject to availability.', 'roova' ); ?>"
			/>
		</label>

		<button type="button" class="button-link roova-vip-benefit__remove" data-roova-vip-remove-benefit aria-label="<?php esc_attr_e( 'Remove benefit', 'roova' ); ?>">
			&times;
		</button>
	</div>
	<?php
}

/**
 * Save the tab.
 *
 * WooCommerce checks its own nonce before firing this, and the capability again
 * here — this option decides what every member is told they are entitled to.
 */
function roova_vip_settings_save() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	check_admin_referer( 'woocommerce-settings' );

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- every leaf is sanitised in roova_vip_sanitize_tiers().
	$posted = isset( $_POST['roova_vip'] ) ? wp_unslash( $_POST['roova_vip'] ) : array();

	update_option( ROOVA_VIP_OPTION, roova_vip_sanitize_tiers( $posted ) );
}
add_action( 'woocommerce_update_options_' . ROOVA_VIP_TAB, 'roova_vip_settings_save' );

/**
 * The screen's own script and styles.
 *
 * @param string $hook Current admin page.
 */
function roova_vip_settings_assets( $hook ) {
	if ( 'woocommerce_page_wc-settings' !== $hook ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which settings tab is open, to decide whether to enqueue.
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

	if ( ROOVA_VIP_TAB !== $tab ) {
		return;
	}

	wp_enqueue_style( 'roova-admin', ROOVA_URI . 'assets/css/admin.css', array(), ROOVA_VERSION );
	wp_enqueue_script( 'roova-admin-vip', ROOVA_URI . 'assets/js/admin-vip.js', array( 'jquery' ), ROOVA_VERSION, true );

	wp_localize_script(
		'roova-admin-vip',
		'roovaVip',
		array(
			'confirmTier' => __( 'Remove this tier and its benefits?', 'roova' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'roova_vip_settings_assets' );
