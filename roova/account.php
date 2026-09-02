<?php
/**
 * My account — the dashboard view.
 *
 * Its own document rather than header.php + footer.php, for the reason checkout
 * and the two auth pages are: the design's whole header is the wordmark, the
 * member's tier and a way out, and nothing on the page should lead anywhere
 * except further into the account.
 *
 * Only the dashboard comes here. Every WooCommerce endpoint underneath My
 * account — view-order, edit-address, payment-methods, lost-password,
 * customer-logout — is left to WooCommerce and the normal site header. See
 * roova_account_template().
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

$roova_user = wp_get_current_user();
$roova_tab  = roova_account_current_tab();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>

<body <?php body_class( 'roova-page-white roova-account-page' ); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#roova-content"><?php esc_html_e( 'Skip to content', 'roova' ); ?></a>

<?php roova_account_topbar( $roova_user ); ?>

<main id="roova-content" class="roova-account">
	<?php
	if ( function_exists( 'wc_print_notices' ) ) {
		wc_print_notices();
	}
	?>

	<?php roova_account_hero( $roova_user ); ?>

	<?php roova_account_tab_strip( $roova_tab ); ?>

	<?php roova_account_panels( $roova_user, $roova_tab ); ?>
</main>

<?php wp_footer(); ?>
</body>
</html>
