<?php
/**
 * Guest information.
 *
 * WooCommerce calls this "billing"; a stay has no delivery address, so the
 * fields are cut down to a name, a phone number and an email in
 * roova_checkout_fields() and rendered here in the design's field shell.
 *
 * @package Roova
 * @see     https://woocommerce.com/document/template-structure/
 * @var WC_Checkout $checkout
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="roova-checkout__section woocommerce-billing-fields">
	<h2 class="roova-checkout__heading"><?php esc_html_e( 'Guest information', 'roova' ); ?></h2>

	<?php do_action( 'woocommerce_before_checkout_billing_form', $checkout ); ?>

	<div class="roova-fields woocommerce-billing-fields__field-wrapper">
		<?php
		foreach ( $checkout->get_checkout_fields( 'billing' ) as $roova_key => $roova_field ) {
			roova_checkout_field( $roova_key, $roova_field, $checkout->get_value( $roova_key ) );
		}
		?>
	</div>

	<p class="roova-checkout__hint">
		<?php esc_html_e( 'Your voucher and hotel contact details are sent here.', 'roova' ); ?>
	</p>

	<?php do_action( 'woocommerce_after_checkout_billing_form', $checkout ); ?>
</section>

<?php if ( ! is_user_logged_in() && $checkout->is_registration_enabled() ) : ?>
	<section class="roova-checkout__section woocommerce-account-fields">
		<?php if ( ! $checkout->is_registration_required() ) : ?>
			<p class="form-row roova-checkbox create-account">
				<label class="roova-checkbox__row" for="createaccount">
					<input class="roova-checkbox__box input-checkbox" id="createaccount" <?php checked( ( true === $checkout->get_value( 'createaccount' ) || ( true === apply_filters( 'woocommerce_create_account_default_checked', false ) ) ), true ); ?> type="checkbox" name="createaccount" value="1" />
					<span><?php esc_html_e( 'Create an account so you can manage this booking later', 'roova' ); ?></span>
				</label>
			</p>
		<?php endif; ?>

		<?php do_action( 'woocommerce_before_checkout_registration_form', $checkout ); ?>

		<?php if ( $checkout->get_checkout_fields( 'account' ) ) : ?>
			<div class="create-account roova-fields">
				<?php
				foreach ( $checkout->get_checkout_fields( 'account' ) as $roova_key => $roova_field ) {
					roova_checkout_field( $roova_key, $roova_field, $checkout->get_value( $roova_key ) );
				}
				?>
			</div>
		<?php endif; ?>

		<?php do_action( 'woocommerce_after_checkout_registration_form', $checkout ); ?>
	</section>
<?php endif; ?>
