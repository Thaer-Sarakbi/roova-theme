<?php
/**
 * One payment method, as a radio card.
 *
 * Title, description and any logo come from the gateway's own settings. The
 * description is the line the selected card expands — WooCommerce's checkout
 * script slides `div.payment_box.payment_method_{id}` open when its radio is
 * chosen, which is why those class names are kept exactly.
 *
 * @package Roova
 * @see     https://woocommerce.com/document/template-structure/
 * @var WC_Payment_Gateway $gateway
 */

defined( 'ABSPATH' ) || exit;

$roova_note   = roova_payment_note( $gateway );
$roova_badge  = roova_payment_badge( $gateway );
$roova_logo   = $gateway->get_icon();
$roova_detail = $gateway->has_fields() || $gateway->get_description();
?>
<li class="wc_payment_method payment_method_<?php echo esc_attr( $gateway->id ); ?> roova-pay__item <?php echo $gateway->chosen ? 'roova-pay__item--on' : ''; ?>">
	<input
		id="payment_method_<?php echo esc_attr( $gateway->id ); ?>"
		type="radio"
		class="input-radio roova-pay__radio"
		name="payment_method"
		value="<?php echo esc_attr( $gateway->id ); ?>"
		<?php checked( $gateway->chosen, true ); ?>
		<?php // Gateways that name their own button keep the total on it too. ?>
		data-order_button_text="<?php echo esc_attr( $gateway->order_button_text ? roova_place_order_label( $gateway->order_button_text ) : '' ); ?>"
	/>

	<label class="roova-pay__label" for="payment_method_<?php echo esc_attr( $gateway->id ); ?>">
		<span class="roova-pay__dot" aria-hidden="true"></span>

		<span class="roova-pay__icon" aria-hidden="true">
			<?php roova_the_icon( roova_payment_icon( $gateway ), 19 ); ?>
		</span>

		<span class="roova-pay__text">
			<span class="roova-pay__title"><?php echo wp_kses_post( $gateway->get_title() ); ?></span>
			<?php if ( $roova_note ) : ?>
				<span class="roova-pay__note"><?php echo esc_html( $roova_note ); ?></span>
			<?php endif; ?>
		</span>

		<?php if ( $roova_logo ) : ?>
			<span class="roova-pay__logo"><?php echo wp_kses_post( $roova_logo ); ?></span>
		<?php endif; ?>

		<?php if ( $roova_badge ) : ?>
			<span class="roova-pay__badge"><?php echo esc_html( $roova_badge ); ?></span>
		<?php endif; ?>
	</label>

	<?php if ( $roova_detail ) : ?>
		<div class="payment_box payment_method_<?php echo esc_attr( $gateway->id ); ?> roova-pay__box" <?php echo ! $gateway->chosen ? 'style="display:none;"' : ''; ?>>
			<?php $gateway->payment_fields(); ?>
		</div>
	<?php endif; ?>
</li>
