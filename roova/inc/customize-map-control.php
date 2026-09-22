<?php
/**
 * The "Find your office" control: a Google map with a search box, in the
 * Customizer's Contact page section.
 *
 * It is the Hotel Details address picker, for the one address that is not a
 * hotel's. Searching a place or dragging the pin fills in the Address,
 * Latitude, Longitude and Map zoom controls beside it — this control's own
 * setting is the place ID, which is what makes the map open Google's listing
 * for the office rather than a pin on its coordinates.
 *
 * Loaded from roova_customize_register(), the one moment WP_Customize_Control
 * is guaranteed to exist.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Roova_Customize_Map_Control' ) || ! class_exists( 'WP_Customize_Control' ) ) {
	return;
}

/**
 * Search-a-place control.
 */
class Roova_Customize_Map_Control extends WP_Customize_Control {

	/**
	 * Control type.
	 *
	 * @var string
	 */
	public $type = 'roova_map';

	/**
	 * Render it.
	 *
	 * With no Google Maps key there is no map to draw, so the control says
	 * where the key goes instead of showing an empty grey box — the rule the
	 * Hotel Details picker follows.
	 */
	public function render_content() {
		$key = function_exists( 'roova_option' ) ? roova_option( 'maps_api_key', '' ) : '';
		?>
		<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>

		<?php if ( $this->description ) : ?>
			<span class="description customize-control-description"><?php echo esc_html( $this->description ); ?></span>
		<?php endif; ?>

		<?php if ( ! $key ) : ?>
			<p class="description roova-cmap__missing">
				<?php esc_html_e( 'Add a Google Maps API key under Google Maps, in this same panel, to search for your office instead of typing its address.', 'roova' ); ?>
			</p>
			<?php
			return;
		endif;
		?>

		<div class="roova-cmap" data-roova-cmap>
			<div class="roova-cmap__search">
				<input type="text"
					class="roova-cmap__input"
					placeholder="<?php esc_attr_e( 'Company name, street or landmark…', 'roova' ); ?>"
					autocomplete="off"
					data-roova-cmap-search />
				<button type="button" class="button" data-roova-cmap-go><?php esc_html_e( 'Search', 'roova' ); ?></button>
			</div>

			<div class="roova-cmap__canvas" data-roova-cmap-canvas></div>

			<p class="description roova-cmap__status" data-roova-cmap-status role="status"></p>

			<p class="description">
				<?php esc_html_e( 'Search for your office, then drag the pin to the door. The address, coordinates and zoom below follow it, and can still be edited by hand.', 'roova' ); ?>
			</p>

			<?php // The place ID this control stores: written by the script, never typed. ?>
			<input type="hidden" <?php $this->link(); ?> value="<?php echo esc_attr( $this->value() ); ?>" data-roova-cmap-place />
		</div>
		<?php
	}
}
