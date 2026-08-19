<?php
/**
 * Wrapper for WooCommerce pages: cart, checkout, account, shop and archives.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="roova-woocommerce">
	<?php woocommerce_content(); ?>
</div>

<?php
get_footer();
