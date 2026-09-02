<?php
/**
 * The RoovaVIP status dropdown on a user's profile screen.
 *
 * Tiers are earned by completed bookings, and that is still what happens by
 * itself. This is the exception: a comped VIP, a hotel's own staff, a guest
 * whose stays predate the site. Picking a tier here **pins** the member to it —
 * it stops moving with their bookings until it is set back to Automatic.
 *
 * The choice is stored as the tier's **name**, not its position: positions shift
 * the moment a tier is added or deleted, and a pin that silently became a
 * different tier would be worse than one that quietly stopped applying. A name
 * that no longer matches a tier is ignored, and the member goes back to being
 * counted.
 *
 * Only someone who can edit users sees the field or can save it — `edit_user`
 * alone is true for a member editing their own profile, which would let a
 * customer promote themselves.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * May the current user set someone's tier by hand?
 *
 * @param int $user_id The user being edited.
 * @return bool
 */
function roova_vip_can_edit_status( $user_id ) {
	return current_user_can( 'edit_users' ) && current_user_can( 'edit_user', absint( $user_id ) );
}

/**
 * The dropdown, under its own heading on the profile screen.
 *
 * @param WP_User $user The user being edited.
 */
function roova_vip_user_field( $user ) {
	if ( ! roova_vip_enabled() || ! roova_vip_can_edit_status( $user->ID ) ) {
		return;
	}

	$tiers    = roova_vip_tiers();
	$override = roova_vip_override( $user->ID );
	$count    = roova_account_completed_count( $user->ID );
	$earned   = roova_vip_tier_for_count( $count );
	?>
	<h2><?php esc_html_e( 'RoovaVIP', 'roova' ); ?></h2>

	<table class="form-table" role="presentation">
		<tr>
			<th><label for="roova_vip_tier"><?php esc_html_e( 'Member status', 'roova' ); ?></label></th>
			<td>
				<?php wp_nonce_field( 'roova_vip_user_' . $user->ID, 'roova_vip_user_nonce' ); ?>

				<select name="roova_vip_tier" id="roova_vip_tier">
					<option value="">
						<?php
						printf(
							/* translators: %s: the tier the member's bookings currently earn */
							esc_html__( 'Automatic — %s', 'roova' ),
							esc_html( $earned ? $earned['name'] : __( 'no tier', 'roova' ) )
						);
						?>
					</option>

					<?php foreach ( $tiers as $tier ) : ?>
						<option value="<?php echo esc_attr( $tier['name'] ); ?>" <?php selected( $override, $tier['name'] ); ?>>
							<?php echo esc_html( $tier['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<p class="description">
					<?php
					printf(
						/* translators: %s: number of completed bookings */
						esc_html( _n(
							'%s completed booking on this account. Leave this on Automatic and the status follows the bookings; pick a tier to hold the member there until you set it back.',
							'%s completed bookings on this account. Leave this on Automatic and the status follows the bookings; pick a tier to hold the member there until you set it back.',
							$count,
							'roova'
						) ),
						esc_html( number_format_i18n( $count ) )
					);
					?>
				</p>

				<?php if ( '' !== $override ) : ?>
					<p class="description roova-vip-user__pinned">
						<?php
						printf(
							/* translators: %s: tier name */
							esc_html__( 'Currently pinned to %s by hand.', 'roova' ),
							esc_html( $override )
						);
						?>
					</p>
				<?php endif; ?>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'roova_vip_user_field' );
add_action( 'edit_user_profile', 'roova_vip_user_field' );

/**
 * Save the dropdown.
 *
 * An empty value clears the pin rather than storing one, so an account that has
 * never been touched carries no meta at all.
 *
 * @param int $user_id The user being saved.
 */
function roova_vip_save_user_field( $user_id ) {
	$user_id = absint( $user_id );

	if ( ! roova_vip_can_edit_status( $user_id ) ) {
		return;
	}

	if ( ! isset( $_POST['roova_vip_user_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['roova_vip_user_nonce'] ) ), 'roova_vip_user_' . $user_id ) ) {
		return;
	}

	$chosen = isset( $_POST['roova_vip_tier'] ) ? sanitize_text_field( wp_unslash( $_POST['roova_vip_tier'] ) ) : '';

	// Only a tier that exists: anything else would pin the member to nothing.
	$valid = '';
	foreach ( roova_vip_tiers() as $tier ) {
		if ( $tier['name'] === $chosen ) {
			$valid = $tier['name'];
			break;
		}
	}

	if ( '' === $valid ) {
		delete_user_meta( $user_id, ROOVA_VIP_USER_META );
	} else {
		update_user_meta( $user_id, ROOVA_VIP_USER_META, $valid );
	}

	/**
	 * Fires after a member's tier is set by hand.
	 *
	 * @param int    $user_id User ID.
	 * @param string $valid   Tier name, or '' for automatic.
	 */
	do_action( 'roova_vip_status_saved', $user_id, $valid );
}
add_action( 'personal_options_update', 'roova_vip_save_user_field' );
add_action( 'edit_user_profile_update', 'roova_vip_save_user_field' );

/**
 * A "VIP status" column on Users, so a pinned member is visible from the list.
 *
 * @param array $columns Columns.
 * @return array
 */
function roova_vip_user_column( $columns ) {
	if ( roova_vip_enabled() ) {
		$columns['roova_vip'] = __( 'VIP status', 'roova' );
	}

	return $columns;
}
add_filter( 'manage_users_columns', 'roova_vip_user_column' );

/**
 * Fill the column.
 *
 * @param string $output      Current output.
 * @param string $column_name Column key.
 * @param int    $user_id     User ID.
 * @return string
 */
function roova_vip_user_column_value( $output, $column_name, $user_id ) {
	if ( 'roova_vip' !== $column_name ) {
		return $output;
	}

	$tiers = roova_vip_tiers();
	$index = roova_vip_index_for_user( $user_id );
	$tier  = isset( $tiers[ $index ] ) ? $tiers[ $index ] : null;

	if ( ! $tier ) {
		return '—';
	}

	if ( '' !== roova_vip_override( $user_id ) ) {
		return sprintf(
			/* translators: %s: tier name */
			esc_html__( '%s (set by hand)', 'roova' ),
			esc_html( $tier['name'] )
		);
	}

	return esc_html( $tier['name'] );
}
add_filter( 'manage_users_custom_column', 'roova_vip_user_column_value', 10, 3 );
