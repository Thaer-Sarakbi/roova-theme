<?php
/**
 * Term extras: an icon for every amenity, an image and colour for every destination.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * Attach the term field hooks once the attribute taxonomies exist.
 */
function roova_register_term_fields() {
	$amenity     = roova_amenity_taxonomy();
	$destination = roova_destination_taxonomy();

	if ( taxonomy_exists( $amenity ) ) {
		add_action( $amenity . '_add_form_fields', 'roova_amenity_add_fields' );
		add_action( $amenity . '_edit_form_fields', 'roova_amenity_edit_fields', 10, 1 );
		add_action( 'created_' . $amenity, 'roova_save_amenity_fields' );
		add_action( 'edited_' . $amenity, 'roova_save_amenity_fields' );

		add_filter( 'manage_edit-' . $amenity . '_columns', 'roova_amenity_columns' );
		add_filter( 'manage_' . $amenity . '_custom_column', 'roova_amenity_column_content', 10, 3 );
	}

	if ( taxonomy_exists( $destination ) ) {
		add_action( $destination . '_add_form_fields', 'roova_destination_add_fields' );
		add_action( $destination . '_edit_form_fields', 'roova_destination_edit_fields', 10, 1 );
		add_action( 'created_' . $destination, 'roova_save_destination_fields' );
		add_action( 'edited_' . $destination, 'roova_save_destination_fields' );
	}
}
add_action( 'admin_init', 'roova_register_term_fields' );

/* -------------------------------------------------------------------------
 * Amenities
 * ---------------------------------------------------------------------- */

/**
 * The icon picker control.
 *
 * @param string $selected Selected icon slug.
 */
function roova_icon_picker( $selected = '' ) {
	?>
	<div class="roova-icon-picker">
		<label class="roova-icon-option <?php echo '' === $selected ? 'is-selected' : ''; ?>">
			<input type="radio" name="roova_icon" value="" <?php checked( '', $selected ); ?> />
			<span class="roova-icon-option__none"><?php esc_html_e( 'None', 'roova' ); ?></span>
		</label>

		<?php foreach ( roova_icon_library() as $slug => $icon ) : ?>
			<label class="roova-icon-option <?php echo $slug === $selected ? 'is-selected' : ''; ?>" title="<?php echo esc_attr( $icon[0] ); ?>">
				<input type="radio" name="roova_icon" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $slug, $selected ); ?> />
				<?php roova_the_icon( $slug, 20 ); ?>
				<span class="roova-icon-option__label"><?php echo esc_html( $icon[0] ); ?></span>
			</label>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * Fields on the "add amenity" form.
 */
function roova_amenity_add_fields() {
	wp_nonce_field( 'roova_term_fields', 'roova_term_nonce' );
	?>
	<div class="form-field">
		<label><?php esc_html_e( 'Icon', 'roova' ); ?></label>
		<?php roova_icon_picker( '' ); ?>
		<p class="description"><?php esc_html_e( 'Pick the icon shown next to this amenity on hotel and room pages.', 'roova' ); ?></p>
	</div>

	<div class="form-field">
		<label for="roova_icon_image"><?php esc_html_e( 'Custom icon image', 'roova' ); ?></label>
		<?php roova_media_field( 'roova_icon_image', 0 ); ?>
		<p class="description"><?php esc_html_e( 'Optional. Overrides the icon above with your own SVG or PNG.', 'roova' ); ?></p>
	</div>
	<?php
}

/**
 * Fields on the "edit amenity" form.
 *
 * @param WP_Term $term Term.
 */
function roova_amenity_edit_fields( $term ) {
	$icon  = get_term_meta( $term->term_id, 'roova_icon', true );
	$image = (int) get_term_meta( $term->term_id, 'roova_icon_image', true );

	wp_nonce_field( 'roova_term_fields', 'roova_term_nonce' );
	?>
	<tr class="form-field">
		<th scope="row"><label><?php esc_html_e( 'Icon', 'roova' ); ?></label></th>
		<td>
			<?php roova_icon_picker( $icon ); ?>
			<p class="description"><?php esc_html_e( 'Pick the icon shown next to this amenity on hotel and room pages.', 'roova' ); ?></p>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="roova_icon_image"><?php esc_html_e( 'Custom icon image', 'roova' ); ?></label></th>
		<td>
			<?php roova_media_field( 'roova_icon_image', $image ); ?>
			<p class="description"><?php esc_html_e( 'Optional. Overrides the icon above with your own SVG or PNG.', 'roova' ); ?></p>
		</td>
	</tr>
	<?php
}

/**
 * Save amenity term meta.
 *
 * @param int $term_id Term ID.
 */
function roova_save_amenity_fields( $term_id ) {
	if ( ! isset( $_POST['roova_term_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['roova_term_nonce'] ) ), 'roova_term_fields' ) ) {
		return;
	}

	if ( isset( $_POST['roova_icon'] ) ) {
		$icon  = sanitize_key( wp_unslash( $_POST['roova_icon'] ) );
		$icons = roova_icon_library();
		update_term_meta( $term_id, 'roova_icon', isset( $icons[ $icon ] ) ? $icon : '' );
	}

	if ( isset( $_POST['roova_icon_image'] ) ) {
		update_term_meta( $term_id, 'roova_icon_image', absint( $_POST['roova_icon_image'] ) );
	}
}

/**
 * Add an icon column to the amenity list table.
 *
 * @param array $columns Columns.
 * @return array
 */
function roova_amenity_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'cb' === $key ) {
			$new['roova_icon'] = __( 'Icon', 'roova' );
		}
	}
	return $new;
}

/**
 * Render the icon column.
 *
 * @param string $content Column content.
 * @param string $column  Column key.
 * @param int    $term_id Term ID.
 * @return string
 */
function roova_amenity_column_content( $content, $column, $term_id ) {
	if ( 'roova_icon' !== $column ) {
		return $content;
	}
	return roova_amenity_icon( $term_id, 18 );
}

/* -------------------------------------------------------------------------
 * Destinations
 * ---------------------------------------------------------------------- */

/**
 * Fields on the "add destination" form.
 */
function roova_destination_add_fields() {
	wp_nonce_field( 'roova_term_fields', 'roova_term_nonce' );
	?>
	<div class="form-field">
		<label for="roova_image_id"><?php esc_html_e( 'Tile image', 'roova' ); ?></label>
		<?php roova_media_field( 'roova_image_id', 0 ); ?>
		<p class="description"><?php esc_html_e( 'Used for this destination on the homepage grid.', 'roova' ); ?></p>
	</div>

	<div class="form-field">
		<label for="roova_color"><?php esc_html_e( 'Tile colour', 'roova' ); ?></label>
		<input type="text" name="roova_color" id="roova_color" value="" placeholder="#0c3b57" />
		<p class="description"><?php esc_html_e( 'Used when no tile image is set.', 'roova' ); ?></p>
	</div>
	<?php
}

/**
 * Fields on the "edit destination" form.
 *
 * @param WP_Term $term Term.
 */
function roova_destination_edit_fields( $term ) {
	$image = (int) get_term_meta( $term->term_id, 'roova_image_id', true );
	$color = (string) get_term_meta( $term->term_id, 'roova_color', true );

	wp_nonce_field( 'roova_term_fields', 'roova_term_nonce' );
	?>
	<tr class="form-field">
		<th scope="row"><label for="roova_image_id"><?php esc_html_e( 'Tile image', 'roova' ); ?></label></th>
		<td>
			<?php roova_media_field( 'roova_image_id', $image ); ?>
			<p class="description"><?php esc_html_e( 'Used for this destination on the homepage grid.', 'roova' ); ?></p>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="roova_color"><?php esc_html_e( 'Tile colour', 'roova' ); ?></label></th>
		<td>
			<input type="text" name="roova_color" id="roova_color" value="<?php echo esc_attr( $color ); ?>" placeholder="#0c3b57" />
			<p class="description"><?php esc_html_e( 'Used when no tile image is set.', 'roova' ); ?></p>
		</td>
	</tr>
	<?php
}

/**
 * Save destination term meta.
 *
 * @param int $term_id Term ID.
 */
function roova_save_destination_fields( $term_id ) {
	if ( ! isset( $_POST['roova_term_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['roova_term_nonce'] ) ), 'roova_term_fields' ) ) {
		return;
	}

	if ( isset( $_POST['roova_image_id'] ) ) {
		update_term_meta( $term_id, 'roova_image_id', absint( $_POST['roova_image_id'] ) );
	}

	if ( isset( $_POST['roova_color'] ) ) {
		$color = sanitize_hex_color( wp_unslash( $_POST['roova_color'] ) );
		update_term_meta( $term_id, 'roova_color', $color ? $color : '' );
	}
}

/* -------------------------------------------------------------------------
 * Shared
 * ---------------------------------------------------------------------- */

/**
 * A media picker field.
 *
 * @param string $name        Field name.
 * @param int    $attachment  Current attachment ID.
 */
function roova_media_field( $name, $attachment ) {
	$url = $attachment ? wp_get_attachment_image_url( $attachment, 'thumbnail' ) : '';
	?>
	<span class="roova-media-field" data-roova-media>
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $attachment ); ?>" data-roova-media-input />
		<span class="roova-media-field__preview" data-roova-media-preview>
			<?php if ( $url ) : ?>
				<img src="<?php echo esc_url( $url ); ?>" alt="" />
			<?php endif; ?>
		</span>
		<button type="button" class="button" data-roova-media-select><?php esc_html_e( 'Choose image', 'roova' ); ?></button>
		<button type="button" class="button-link" data-roova-media-clear><?php esc_html_e( 'Remove', 'roova' ); ?></button>
	</span>
	<?php
}
