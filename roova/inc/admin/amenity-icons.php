<?php
/**
 * Term extras: an icon for every amenity, an image and colour for every destination,
 * and a category, picture, distance and Google Maps link for every landmark.
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
	$landmark    = roova_landmark_taxonomy();

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

	if ( taxonomy_exists( $landmark ) ) {
		add_action( $landmark . '_add_form_fields', 'roova_landmark_add_fields' );
		add_action( $landmark . '_edit_form_fields', 'roova_landmark_edit_fields', 10, 1 );
		add_action( 'created_' . $landmark, 'roova_save_landmark_fields' );
		add_action( 'edited_' . $landmark, 'roova_save_landmark_fields' );

		add_filter( 'manage_edit-' . $landmark . '_columns', 'roova_landmark_columns' );
		add_filter( 'manage_' . $landmark . '_custom_column', 'roova_landmark_column_content', 10, 3 );
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
		<input type="text" name="roova_color" id="roova_color" value="" placeholder="#0d3a52" />
		<p class="description"><?php esc_html_e( 'Used when no tile image is set.', 'roova' ); ?></p>
	</div>

	<div class="form-field">
		<label for="roova_lat"><?php esc_html_e( 'Latitude', 'roova' ); ?></label>
		<input type="text" name="roova_lat" id="roova_lat" value="" placeholder="3.150" />
	</div>

	<div class="form-field">
		<label for="roova_lng"><?php esc_html_e( 'Longitude', 'roova' ); ?></label>
		<input type="text" name="roova_lng" id="roova_lng" value="" placeholder="101.760" />
		<p class="description"><?php esc_html_e( 'Where this destination is pinned on the homepage map. Malaysian towns the theme already knows can be left blank.', 'roova' ); ?></p>
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
	$lat   = (string) get_term_meta( $term->term_id, 'roova_lat', true );
	$lng   = (string) get_term_meta( $term->term_id, 'roova_lng', true );

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
			<input type="text" name="roova_color" id="roova_color" value="<?php echo esc_attr( $color ); ?>" placeholder="#0d3a52" />
			<p class="description"><?php esc_html_e( 'Used when no tile image is set.', 'roova' ); ?></p>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="roova_lat"><?php esc_html_e( 'Latitude', 'roova' ); ?></label></th>
		<td>
			<input type="text" name="roova_lat" id="roova_lat" value="<?php echo esc_attr( $lat ); ?>" placeholder="3.150" />
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="roova_lng"><?php esc_html_e( 'Longitude', 'roova' ); ?></label></th>
		<td>
			<input type="text" name="roova_lng" id="roova_lng" value="<?php echo esc_attr( $lng ); ?>" placeholder="101.760" />
			<p class="description"><?php esc_html_e( 'Where this destination is pinned on the homepage map. Malaysian towns the theme already knows can be left blank.', 'roova' ); ?></p>
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

	foreach ( array( 'roova_lat', 'roova_lng' ) as $key ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			continue;
		}
		$value = trim( sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
		update_term_meta( $term_id, $key, is_numeric( $value ) ? $value : '' );
	}
}

/* -------------------------------------------------------------------------
 * Landmarks
 * ---------------------------------------------------------------------- */

/**
 * The distance pair: a number and the unit it is in.
 *
 * One function rather than two copies, so the "add landmark" form and the
 * "edit landmark" form cannot drift apart.
 *
 * @param string $distance Stored distance.
 * @param string $unit     Stored unit key.
 */
function roova_landmark_distance_inputs( $distance = '', $unit = '' ) {
	$units = roova_landmark_units();
	$unit  = isset( $units[ $unit ] ) ? $unit : key( $units );
	?>
	<span class="roova-distance-field">
		<input type="number"
			name="roova_distance"
			id="roova_distance"
			value="<?php echo esc_attr( $distance ); ?>"
			step="any"
			min="0"
			placeholder="20.6" />
		<select name="roova_distance_unit" id="roova_distance_unit" aria-label="<?php esc_attr_e( 'Distance unit', 'roova' ); ?>">
			<?php foreach ( $units as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $unit ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</span>
	<?php
}

/**
 * The category select: every term in the Landmark category attribute.
 *
 * A dropdown of real terms rather than a list typed into the theme, so a client
 * can add "Night market" themselves. With no categories yet it says where they
 * are added instead of printing a select with nothing in it.
 *
 * @param int $selected Chosen category term ID.
 */
function roova_landmark_category_select( $selected = 0 ) {
	$categories = roova_landmark_category_terms();

	if ( ! $categories ) {
		printf(
			'<span class="roova-panel-note">%s <a href="%s">%s</a></span>',
			esc_html__( 'No landmark categories yet.', 'roova' ),
			esc_url( admin_url( 'edit-tags.php?taxonomy=' . rawurlencode( roova_landmark_category_taxonomy() ) . '&post_type=product' ) ),
			esc_html__( 'Add some first.', 'roova' )
		);
		return;
	}
	?>
	<select name="roova_category" id="roova_category">
		<option value="0"><?php esc_html_e( '— None —', 'roova' ); ?></option>
		<?php foreach ( $categories as $category ) : ?>
			<option value="<?php echo esc_attr( $category->term_id ); ?>" <?php selected( (int) $category->term_id, (int) $selected ); ?>>
				<?php echo esc_html( $category->name ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<?php
}

/**
 * Fields on the "add landmark" form.
 */
function roova_landmark_add_fields() {
	wp_nonce_field( 'roova_term_fields', 'roova_term_nonce' );
	?>
	<div class="form-field">
		<label for="roova_category"><?php esc_html_e( 'Category', 'roova' ); ?></label>
		<?php roova_landmark_category_select(); ?>
		<p class="description"><?php esc_html_e( 'What kind of place this is — a cafe, a shopping mall, a restaurant. The list is yours to edit under Products → Attributes → Landmark category.', 'roova' ); ?></p>
	</div>

	<div class="form-field">
		<label for="roova_image_id"><?php esc_html_e( 'Title image', 'roova' ); ?></label>
		<?php roova_media_field( 'roova_image_id', 0 ); ?>
		<p class="description"><?php esc_html_e( 'A photo of the landmark itself.', 'roova' ); ?></p>
	</div>

	<div class="form-field">
		<label for="roova_distance"><?php esc_html_e( 'Distance', 'roova' ); ?></label>
		<?php roova_landmark_distance_inputs(); ?>
		<p class="description"><?php esc_html_e( 'How far this landmark is. Leave it empty if the distance is better left unsaid.', 'roova' ); ?></p>
	</div>

	<div class="form-field">
		<label for="roova_map_link"><?php esc_html_e( 'Location (Google Maps link)', 'roova' ); ?></label>
		<input type="url" name="roova_map_link" id="roova_map_link" value="" placeholder="https://maps.app.goo.gl/…" />
		<p class="description"><?php esc_html_e( 'Open the landmark in Google Maps, press Share and paste the link here. Anything that is not a Google Maps link is dropped, and the landmark is then searched for by name instead.', 'roova' ); ?></p>
	</div>
	<?php
}

/**
 * Fields on the "edit landmark" form.
 *
 * @param WP_Term $term Term.
 */
function roova_landmark_edit_fields( $term ) {
	$image    = (int) get_term_meta( $term->term_id, 'roova_image_id', true );
	$distance = (string) get_term_meta( $term->term_id, 'roova_distance', true );
	$unit     = (string) get_term_meta( $term->term_id, 'roova_distance_unit', true );
	$link     = (string) get_term_meta( $term->term_id, 'roova_map_link', true );
	$category = roova_landmark_category( $term );

	wp_nonce_field( 'roova_term_fields', 'roova_term_nonce' );
	?>
	<tr class="form-field">
		<th scope="row"><label for="roova_category"><?php esc_html_e( 'Category', 'roova' ); ?></label></th>
		<td>
			<?php roova_landmark_category_select( $category ? $category->term_id : 0 ); ?>
			<p class="description"><?php esc_html_e( 'What kind of place this is — a cafe, a shopping mall, a restaurant. The list is yours to edit under Products → Attributes → Landmark category.', 'roova' ); ?></p>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="roova_image_id"><?php esc_html_e( 'Title image', 'roova' ); ?></label></th>
		<td>
			<?php roova_media_field( 'roova_image_id', $image ); ?>
			<p class="description"><?php esc_html_e( 'A photo of the landmark itself.', 'roova' ); ?></p>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="roova_distance"><?php esc_html_e( 'Distance', 'roova' ); ?></label></th>
		<td>
			<?php roova_landmark_distance_inputs( $distance, $unit ); ?>
			<p class="description"><?php esc_html_e( 'How far this landmark is. Leave it empty if the distance is better left unsaid.', 'roova' ); ?></p>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="roova_map_link"><?php esc_html_e( 'Location (Google Maps link)', 'roova' ); ?></label></th>
		<td>
			<input type="url" name="roova_map_link" id="roova_map_link" value="<?php echo esc_attr( $link ); ?>" placeholder="https://maps.app.goo.gl/…" class="large-text" />
			<p class="description"><?php esc_html_e( 'Open the landmark in Google Maps, press Share and paste the link here. Anything that is not a Google Maps link is dropped, and the landmark is then searched for by name instead.', 'roova' ); ?></p>
		</td>
	</tr>
	<?php
}

/**
 * Save landmark term meta.
 *
 * @param int $term_id Term ID.
 */
function roova_save_landmark_fields( $term_id ) {
	if ( ! isset( $_POST['roova_term_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['roova_term_nonce'] ) ), 'roova_term_fields' ) ) {
		return;
	}

	/*
	 * The category is stored as the chosen term's ID, and only when that term
	 * really is a landmark category — a posted ID from anywhere else is
	 * cleared rather than trusted.
	 */
	if ( isset( $_POST['roova_category'] ) ) {
		$category = get_term( absint( $_POST['roova_category'] ), roova_landmark_category_taxonomy() );
		update_term_meta( $term_id, 'roova_category', ( $category instanceof WP_Term ) ? $category->term_id : 0 );
	}

	if ( isset( $_POST['roova_image_id'] ) ) {
		update_term_meta( $term_id, 'roova_image_id', absint( $_POST['roova_image_id'] ) );
	}

	if ( isset( $_POST['roova_distance'] ) ) {
		// Anything that is not a distance — a word, a negative number — is
		// cleared rather than stored, the way an unparseable field always is
		// here: nothing beats "-2 km" on a hotel page.
		$distance = trim( sanitize_text_field( wp_unslash( $_POST['roova_distance'] ) ) );
		$keep     = is_numeric( $distance ) && (float) $distance >= 0;
		update_term_meta( $term_id, 'roova_distance', $keep ? $distance : '' );
	}

	if ( isset( $_POST['roova_distance_unit'] ) ) {
		$unit  = sanitize_key( wp_unslash( $_POST['roova_distance_unit'] ) );
		$units = roova_landmark_units();
		update_term_meta( $term_id, 'roova_distance_unit', isset( $units[ $unit ] ) ? $unit : key( $units ) );
	}

	/*
	 * Kept only if it really is a Google Maps URL — the same gate the hotel's
	 * own pasted link goes through, because this one is printed wherever the
	 * landmark is.
	 */
	if ( isset( $_POST['roova_map_link'] ) ) {
		update_term_meta( $term_id, 'roova_map_link', roova_maps_link( wp_unslash( $_POST['roova_map_link'] ) ) );
	}
}

/**
 * Add image, category and distance columns to the landmark list table.
 *
 * @param array $columns Columns.
 * @return array
 */
function roova_landmark_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'cb' === $key ) {
			$new['roova_image'] = __( 'Image', 'roova' );
		}
		if ( 'name' === $key ) {
			$new['roova_category'] = __( 'Category', 'roova' );
			$new['roova_distance'] = __( 'Distance', 'roova' );
		}
	}
	return $new;
}

/**
 * Render the landmark columns.
 *
 * @param string $content Column content.
 * @param string $column  Column key.
 * @param int    $term_id Term ID.
 * @return string
 */
function roova_landmark_column_content( $content, $column, $term_id ) {
	if ( 'roova_image' === $column ) {
		$image = (int) get_term_meta( $term_id, 'roova_image_id', true );
		return $image ? wp_get_attachment_image( $image, array( 40, 40 ), false, array( 'style' => 'border-radius:4px;' ) ) : '';
	}

	if ( 'roova_category' === $column ) {
		$category = roova_landmark_category( $term_id );
		return $category ? esc_html( $category->name ) : '—';
	}

	if ( 'roova_distance' === $column ) {
		return esc_html( roova_landmark_distance_label(
			get_term_meta( $term_id, 'roova_distance', true ),
			get_term_meta( $term_id, 'roova_distance_unit', true )
		) );
	}

	return $content;
}

/* -------------------------------------------------------------------------
 * Shared
 * ---------------------------------------------------------------------- */

/**
 * A type-to-search multi-select of every term in an attribute taxonomy.
 *
 * Used on the product screens so amenities and facilities can be set where the
 * rest of the hotel is edited, instead of only in WooCommerce's Attributes tab.
 * The wc-enhanced-select class hands the field to WooCommerce's own select2, so
 * it looks and behaves like the rest of the product screen; without JavaScript
 * it degrades to a plain multiple select and still saves.
 *
 * @param string $taxonomy    Attribute taxonomy, e.g. pa_amenity.
 * @param int[]  $selected    Currently attached term IDs.
 * @param string $field_name  Name of the field, also used as its id.
 * @param string $label       Field caption.
 * @param string $placeholder Placeholder shown when nothing is chosen.
 */
function roova_attribute_picker( $taxonomy, $selected, $field_name, $label, $placeholder = '' ) {
	if ( ! taxonomy_exists( $taxonomy ) ) {
		echo '<p class="roova-panel-note">' . esc_html__( 'This attribute does not exist yet. Open Products → Attributes to create it.', 'roova' ) . '</p>';
		return;
	}

	$terms    = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
	$selected = array_map( 'absint', (array) $selected );

	if ( is_wp_error( $terms ) || ! $terms ) {
		printf(
			'<p class="roova-panel-note">%s <a href="%s">%s</a></p>',
			esc_html__( 'No terms yet.', 'roova' ),
			esc_url( admin_url( 'edit-tags.php?taxonomy=' . rawurlencode( $taxonomy ) . '&post_type=product' ) ),
			esc_html__( 'Add some first.', 'roova' )
		);
		return;
	}

	$placeholder = $placeholder ? $placeholder : __( 'Type to search…', 'roova' );
	?>
	<p class="form-field roova-term-field">
		<label for="<?php echo esc_attr( $field_name ); ?>"><?php echo esc_html( $label ); ?></label>

		<?php /* Posted first, so clearing every choice still submits the field. */ ?>
		<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>[]" value="" />

		<select id="<?php echo esc_attr( $field_name ); ?>"
			name="<?php echo esc_attr( $field_name ); ?>[]"
			class="wc-enhanced-select roova-term-select"
			multiple="multiple"
			style="width:70%;"
			data-placeholder="<?php echo esc_attr( $placeholder ); ?>">
			<?php foreach ( $terms as $term ) : ?>
				<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( in_array( (int) $term->term_id, $selected, true ) ); ?>>
					<?php echo esc_html( $term->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<?php
}

/**
 * The term IDs a checklist posted back.
 *
 * @param string $field_name Field name used by roova_attribute_checklist().
 * @return int[]
 */
function roova_posted_attribute_terms( $field_name ) {
	// WooCommerce verifies the product nonce before the save hooks run.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$raw = isset( $_POST[ $field_name ] ) ? wp_unslash( $_POST[ $field_name ] ) : array();

	return array_values( array_filter( array_map( 'absint', (array) $raw ) ) );
}

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
