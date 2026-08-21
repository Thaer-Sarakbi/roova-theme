<?php
/**
 * Template tags — the markup the theme's pages are built from.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * The search card: destination, dates, guests, submit.
 *
 * @param array $args compact => bool, hotel_id => int.
 */
function roova_search_form( $args = array() ) {
	$args = wp_parse_args( $args, array(
		'compact'  => false,
		'hotel_id' => 0,
		'class'    => '',
	) );

	$criteria = roova_get_criteria();
	$guests   = (int) $criteria['adults'] + (int) $criteria['children'];

	/*
	 * Destination links carry the term slug, so show the term's real name in the
	 * box rather than "coastal-borneo". Either value searches the same.
	 */
	$destination_label = $criteria['destination'];
	if ( $destination_label && function_exists( 'roova_resolve_destination' ) ) {
		$resolved = roova_resolve_destination( $destination_label );
		if ( $resolved['term'] ) {
			$destination_label = $resolved['term']->name;
		}
	}

	/*
	 * On a hotel page the form is an "update my stay" control, so it reloads
	 * that hotel with the new dates rather than sending the guest to search.
	 */
	$action = ( $args['compact'] && $args['hotel_id'] )
		? get_permalink( $args['hotel_id'] )
		: roova_search_url();
	?>
	<form class="roova-search <?php echo $args['compact'] ? 'roova-search--compact' : ''; ?> <?php echo esc_attr( $args['class'] ); ?>"
		method="get"
		action="<?php echo esc_url( $action ); ?>"
		data-roova-search>

		<?php if ( $args['hotel_id'] && $action === roova_search_url() ) : ?>
			<input type="hidden" name="roova_hotel" value="<?php echo esc_attr( $args['hotel_id'] ); ?>" />
		<?php endif; ?>

		<div class="roova-search__field" data-roova-field="destination">
			<span class="roova-search__label"><?php esc_html_e( 'Destination or hotel name', 'roova' ); ?></span>
			<div class="roova-search__value">
				<?php roova_the_icon( 'pin', 16 ); ?>
				<input type="text"
					name="roova_dest"
					value="<?php echo esc_attr( $destination_label ); ?>"
					placeholder="<?php esc_attr_e( 'Where are you going?', 'roova' ); ?>"
					autocomplete="off"
					data-roova-destination-input />
			</div>
			<div class="roova-panel roova-panel--destination" data-roova-panel="destination" hidden>
				<input type="search" class="roova-panel__search" placeholder="<?php esc_attr_e( 'Search destinations or hotels', 'roova' ); ?>" data-roova-destination-filter />
				<div class="roova-panel__list" data-roova-destination-list></div>
			</div>
		</div>

		<span class="roova-search__divider" aria-hidden="true"></span>

		<div class="roova-search__field" data-roova-field="dates">
			<span class="roova-search__label"><?php esc_html_e( 'Check in — out', 'roova' ); ?></span>
			<div class="roova-search__value">
				<?php roova_the_icon( 'calendar', 16 ); ?>
				<span data-roova-dates-label>
					<?php
					printf(
						'%s — %s',
						esc_html( roova_format_date( $criteria['check_in'] ) ),
						esc_html( roova_format_date( $criteria['check_out'] ) )
					);
					?>
				</span>
			</div>
			<input type="hidden" name="checkin" value="<?php echo esc_attr( $criteria['check_in'] ); ?>" data-roova-checkin />
			<input type="hidden" name="checkout" value="<?php echo esc_attr( $criteria['check_out'] ); ?>" data-roova-checkout />
			<div class="roova-panel roova-panel--calendar" data-roova-panel="dates" data-roova-calendar
				<?php echo $args['hotel_id'] ? 'data-hotel-id="' . esc_attr( $args['hotel_id'] ) . '"' : ''; ?> hidden></div>
		</div>

		<span class="roova-search__divider" aria-hidden="true"></span>

		<div class="roova-search__field" data-roova-field="guests">
			<span class="roova-search__label"><?php esc_html_e( 'Rooms and guests', 'roova' ); ?></span>
			<div class="roova-search__value">
				<?php roova_the_icon( 'guests', 16 ); ?>
				<span data-roova-guests-label>
					<?php
					printf(
						/* translators: 1: rooms count phrase, 2: guests count phrase */
						esc_html__( '%1$s · %2$s', 'roova' ),
						esc_html( sprintf( /* translators: %d: rooms */ _n( '%d room', '%d rooms', $criteria['rooms'], 'roova' ), $criteria['rooms'] ) ),
						esc_html( sprintf( /* translators: %d: guests */ _n( '%d guest', '%d guests', $guests, 'roova' ), $guests ) )
					);
					?>
				</span>
			</div>
			<input type="hidden" name="rooms" value="<?php echo esc_attr( $criteria['rooms'] ); ?>" data-roova-rooms />
			<input type="hidden" name="adults" value="<?php echo esc_attr( $criteria['adults'] ); ?>" data-roova-adults />
			<input type="hidden" name="children" value="<?php echo esc_attr( $criteria['children'] ); ?>" data-roova-children />

			<div class="roova-panel roova-panel--guests" data-roova-panel="guests" hidden>
				<?php
				$rows = array(
					'rooms'    => __( 'Rooms', 'roova' ),
					'adults'   => __( 'Adults', 'roova' ),
					'children' => __( 'Children', 'roova' ),
				);
				foreach ( $rows as $key => $label ) :
					?>
					<div class="roova-stepper">
						<span class="roova-stepper__label"><?php echo esc_html( $label ); ?></span>
						<div class="roova-stepper__controls">
							<button type="button" class="roova-stepper__btn" data-roova-step="<?php echo esc_attr( $key ); ?>" data-roova-delta="-1" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: field name */ __( 'Decrease %s', 'roova' ), $label ) ); ?>">&minus;</button>
							<span class="roova-stepper__count" data-roova-count="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $criteria[ $key ] ); ?></span>
							<button type="button" class="roova-stepper__btn" data-roova-step="<?php echo esc_attr( $key ); ?>" data-roova-delta="1" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: field name */ __( 'Increase %s', 'roova' ), $label ) ); ?>">+</button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<button type="submit" class="roova-search__submit">
			<?php roova_the_icon( 'search', 16 ); ?>
			<span>
				<?php
				if ( $args['compact'] ) {
					esc_html_e( 'Update', 'roova' );
				} else {
					esc_html_e( 'Search', 'roova' );
				}
				?>
			</span>
		</button>
	</form>
	<?php
}

/**
 * A hotel card for sliders, grids and search results.
 *
 * @param int   $hotel_id Hotel product ID.
 * @param array $args     show_price => bool, availability => array|null.
 */
function roova_hotel_card( $hotel_id, $args = array() ) {
	$args = wp_parse_args( $args, array(
		'show_price'   => true,
		'availability' => null,
		'class'        => '',
	) );

	$product = wc_get_product( $hotel_id );
	if ( ! $product ) {
		return;
	}

	$details  = roova_get_hotel_details( $hotel_id );
	$location = roova_hotel_location_label( $hotel_id );
	$url      = roova_criteria_url( get_permalink( $hotel_id ) );
	$stars    = (int) $details['stars'];
	?>
	<article class="roova-hotel-card <?php echo esc_attr( $args['class'] ); ?>">
		<a class="roova-hotel-card__media" href="<?php echo esc_url( $url ); ?>">
			<?php
			if ( has_post_thumbnail( $hotel_id ) ) {
				echo get_the_post_thumbnail( $hotel_id, 'roova-hotel-card', array( 'loading' => 'lazy', 'alt' => esc_attr( get_the_title( $hotel_id ) ) ) );
			} else {
				echo '<span class="roova-hotel-card__placeholder" aria-hidden="true"></span>';
			}
			?>
			<?php if ( $location ) : ?>
				<span class="roova-hotel-card__loc"><?php roova_the_icon( 'pin', 13 ); ?><?php echo esc_html( $location ); ?></span>
			<?php endif; ?>
		</a>
		<div class="roova-hotel-card__body">
			<?php if ( $stars ) : ?>
				<div class="roova-hotel-card__stars"><?php echo roova_stars( $stars, 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<?php endif; ?>

			<h3 class="roova-hotel-card__name">
				<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( get_the_title( $hotel_id ) ); ?></a>
			</h3>

			<?php if ( $args['show_price'] ) : ?>
				<div class="roova-hotel-card__price">
					<?php
					if ( is_array( $args['availability'] ) ) {
						if ( $args['availability']['has_availability'] && null !== $args['availability']['rate'] ) {
							printf(
								'%s <strong>%s</strong> %s',
								esc_html__( 'From', 'roova' ),
								wp_kses_post( wc_price( $args['availability']['rate'] ) ),
								esc_html__( '/ night', 'roova' )
							);
						} else {
							echo '<span class="roova-unavailable">' . esc_html__( 'No rooms free for these dates', 'roova' ) . '</span>';
						}
					} else {
						echo wp_kses_post( $product->get_price_html() );
					}
					?>
				</div>
			<?php endif; ?>
		</div>
	</article>
	<?php
}

/**
 * Fallback card for non-hotel products in a loop.
 *
 * @param WC_Product $product Product.
 */
function roova_simple_product_card( $product ) {
	?>
	<article class="roova-hotel-card">
		<a class="roova-hotel-card__media" href="<?php echo esc_url( $product->get_permalink() ); ?>">
			<?php echo wp_kses_post( $product->get_image( 'roova-hotel-card' ) ); ?>
		</a>
		<div class="roova-hotel-card__body">
			<h3 class="roova-hotel-card__name">
				<a href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
			</h3>
			<div class="roova-hotel-card__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>
		</div>
	</article>
	<?php
}

/**
 * A destination tile.
 *
 * @param array  $destination Destination data from roova_get_destinations().
 * @param string $class       Extra class.
 */
function roova_destination_tile( $destination, $class = '' ) {
	$term  = $destination['term'];
	$image = $destination['image_id'] ? wp_get_attachment_image_url( $destination['image_id'], 'roova-hotel-card' ) : '';
	$color = $destination['color'] ? $destination['color'] : '#0c3b57';
	?>
	<a class="roova-destination <?php echo esc_attr( $class ); ?>"
		href="<?php echo esc_url( $destination['url'] ); ?>"
		style="<?php echo $image ? 'background-image:url(' . esc_url( $image ) . ');' : 'background:' . esc_attr( $color ) . ';'; ?>">
		<span class="roova-destination__content">
			<span class="roova-destination__name"><?php echo esc_html( $term->name ); ?></span>
			<span class="roova-destination__rule" aria-hidden="true"></span>
			<span class="roova-destination__count">
				<?php
				printf(
					/* translators: %d: number of hotels */
					esc_html( _n( '%d hotel', '%d hotels', $destination['count'], 'roova' ) ),
					(int) $destination['count']
				);
				?>
			</span>
		</span>
	</a>
	<?php
}

/**
 * A bookable room row on the hotel page.
 *
 * @param int   $room_id  Room product ID.
 * @param array $data     Room data from Roova_Availability::get_bookable_rooms().
 * @param array $criteria Search criteria.
 */
function roova_room_card( $room_id, $data, $criteria ) {
	$product = $data['product'];
	$details = $data['details'];
	$nights  = max( 1, (int) $data['nights'] );
	?>
	<div class="roova-room" id="room-<?php echo esc_attr( $room_id ); ?>">
		<div class="roova-room__media">
			<?php
			if ( $product->get_image_id() ) {
				echo wp_kses_post( $product->get_image( 'roova-room-thumb', array( 'loading' => 'lazy' ) ) );
			} else {
				echo '<span class="roova-room__placeholder" aria-hidden="true"></span>';
			}
			?>
		</div>

		<div class="roova-room__info">
			<h3 class="roova-room__name"><?php echo esc_html( $product->get_name() ); ?></h3>

			<div class="roova-room__meta">
				<?php if ( $details['size'] ) : ?>
					<span><?php roova_the_icon( 'size', 14 ); ?><?php echo esc_html( $details['size'] ); ?></span>
				<?php endif; ?>
				<?php if ( $details['beds'] ) : ?>
					<span><?php roova_the_icon( 'bed', 14 ); ?><?php echo esc_html( $details['beds'] ); ?></span>
				<?php endif; ?>
				<span>
					<?php roova_the_icon( 'guests', 14 ); ?>
					<?php
					printf(
						/* translators: %d: adults */
						esc_html( _n( 'Max %d adult', 'Max %d adults', $details['max_adults'], 'roova' ) ),
						(int) $details['max_adults']
					);
					?>
				</span>

				<?php if ( $details['max_children'] > 0 ) : ?>
					<span>
						<?php roova_the_icon( 'child', 14 ); ?>
						<?php
						/**
						 * Filter the age a guest stops counting as a child.
						 *
						 * @param int $age Maximum child age, in years.
						 */
						$roova_child_age = (int) apply_filters( 'roova_child_max_age', 10 );

						printf(
							/* translators: 1: number of children, 2: oldest age counted as a child */
							esc_html( _n( 'Max %1$d kid (up to %2$d years)', 'Max %1$d kids (up to %2$d years)', $details['max_children'], 'roova' ) ),
							(int) $details['max_children'],
							$roova_child_age
						);
						?>
					</span>
				<?php endif; ?>
			</div>

			<?php
			$amenities = roova_get_amenities( $room_id );
			if ( $amenities ) :
				?>
				<div class="roova-room__amenities">
					<?php foreach ( array_slice( $amenities, 0, 4 ) as $term ) : ?>
						<span>
							<?php echo roova_amenity_icon( $term, 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo esc_html( $term->name ); ?>
						</span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<button type="button" class="roova-link-btn" data-roova-room-details="<?php echo esc_attr( $room_id ); ?>">
				<?php esc_html_e( 'Room photos and details', 'roova' ); ?>
			</button>
		</div>

		<div class="roova-room__book">
			<div class="roova-room__price">
				<span><?php esc_html_e( 'from', 'roova' ); ?></span>
				<strong><?php echo wp_kses_post( wc_price( $data['rate'] ) ); ?></strong>
				<span><?php esc_html_e( '/ night', 'roova' ); ?></span>
			</div>

			<?php if ( $nights > 1 || $criteria['rooms'] > 1 ) : ?>
				<div class="roova-room__total">
					<?php
					printf(
						/* translators: 1: total price, 2: nights phrase, 3: rooms phrase */
						esc_html__( '%1$s total for %2$s, %3$s', 'roova' ),
						wp_kses_post( wc_price( $data['total'] ) ),
						esc_html( sprintf( /* translators: %d: nights */ _n( '%d night', '%d nights', $nights, 'roova' ), $nights ) ),
						esc_html( sprintf( /* translators: %d: rooms */ _n( '%d room', '%d rooms', $criteria['rooms'], 'roova' ), $criteria['rooms'] ) )
					);
					?>
				</div>
			<?php endif; ?>

			<?php if ( ! $data['fits'] ) : ?>
				<p class="roova-room__note">
					<?php
					printf(
						/* translators: 1: adults, 2: children */
						esc_html__( 'Takes up to %1$d adults and %2$d children per room.', 'roova' ),
						(int) $details['max_adults'],
						(int) $details['max_children']
					);
					?>
				</p>
			<?php elseif ( ! $data['bookable'] ) : ?>
				<p class="roova-room__note roova-unavailable">
					<?php if ( $data['in_cart'] > 0 ) : ?>
						<?php esc_html_e( 'Already in your booking', 'roova' ); ?>
					<?php elseif ( $nights < $details['min_nights'] ) : ?>
						<?php
						printf(
							/* translators: %d: minimum nights */
							esc_html( _n( 'Minimum stay of %d night.', 'Minimum stay of %d nights.', $details['min_nights'], 'roova' ) ),
							(int) $details['min_nights']
						);
						?>
					<?php else : ?>
						<?php esc_html_e( 'Sold out for these dates', 'roova' ); ?>
					<?php endif; ?>
				</p>
			<?php else : ?>
				<?php if ( $data['available'] <= 3 ) : ?>
					<p class="roova-room__scarcity">
						<?php
						printf(
							/* translators: %d: rooms left */
							esc_html( _n( 'Only %d left', 'Only %d left', $data['available'], 'roova' ) ),
							(int) $data['available']
						);
						?>
					</p>
				<?php endif; ?>

				<form class="roova-room__form" method="post" action="<?php echo esc_url( roova_criteria_url( get_permalink( roova_get_room_hotel_id( $room_id ) ) ) ); ?>">
					<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $room_id ); ?>" />
					<input type="hidden" name="quantity" value="<?php echo esc_attr( $criteria['rooms'] ); ?>" />
					<input type="hidden" name="roova_checkin" value="<?php echo esc_attr( $criteria['check_in'] ); ?>" />
					<input type="hidden" name="roova_checkout" value="<?php echo esc_attr( $criteria['check_out'] ); ?>" />
					<input type="hidden" name="roova_adults" value="<?php echo esc_attr( $criteria['adults'] ); ?>" />
					<input type="hidden" name="roova_children" value="<?php echo esc_attr( $criteria['children'] ); ?>" />
					<?php wp_nonce_field( 'roova_book_room', 'roova_book_nonce' ); ?>
					<button type="submit" class="roova-btn roova-btn--outline"><?php esc_html_e( 'Book now', 'roova' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * The facilities checklist.
 *
 * @param int $product_id Product ID.
 */
function roova_facilities_grid( $product_id ) {
	$facilities = roova_get_facilities( $product_id );
	if ( ! $facilities ) {
		return;
	}
	?>
	<div class="roova-facilities">
		<?php foreach ( $facilities as $term ) : ?>
			<span class="roova-facility">
				<?php roova_the_icon( 'check', 16 ); ?>
				<span><?php echo esc_html( $term->name ); ?></span>
			</span>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * The destinations a hotel sits in, each linking to that destination's hotels.
 *
 * Falls back to the plain street address when the hotel has no destination term.
 *
 * @param int $hotel_id Hotel product ID.
 */
function roova_hotel_destination_links( $hotel_id ) {
	$terms = roova_get_hotel_destinations( $hotel_id );

	if ( ! $terms ) {
		$details = roova_get_hotel_details( $hotel_id );
		if ( $details['address'] ) {
			echo '<span>' . esc_html( $details['address'] ) . '</span>';
		}
		return;
	}

	$links = array();
	foreach ( $terms as $term ) {
		$links[] = sprintf(
			'<a class="roova-dest-link" href="%1$s" title="%2$s">%3$s</a>',
			esc_url( roova_destination_url( $term ) ),
			/* translators: %s: destination name */
			esc_attr( sprintf( __( 'See every hotel in %s', 'roova' ), $term->name ) ),
			esc_html( $term->name )
		);
	}

	echo implode( '<span class="roova-sep" aria-hidden="true">·</span>', $links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each part escaped above.
}

/**
 * The amenities grid.
 *
 * @param int $product_id Product ID.
 */
function roova_amenities_grid( $product_id ) {
	$amenities = roova_get_amenities( $product_id );
	if ( ! $amenities ) {
		return;
	}
	?>
	<div class="roova-amenities">
		<?php foreach ( $amenities as $term ) : ?>
			<span class="roova-amenity">
				<?php echo roova_amenity_icon( $term, 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo esc_html( $term->name ); ?>
			</span>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * The two landmark columns.
 *
 * @param int $hotel_id Hotel product ID.
 */
function roova_landmarks( $hotel_id ) {
	$details  = roova_get_hotel_details( $hotel_id );
	$popular  = roova_parse_landmarks( $details['landmarks_popular'] );
	$nearby   = roova_parse_landmarks( $details['landmarks_nearby'] );

	if ( ! $popular && ! $nearby ) {
		return;
	}
	?>
	<div class="roova-landmarks">
		<?php
		$columns = array(
			__( 'Popular landmarks', 'roova' ) => $popular,
			__( 'Nearby landmarks', 'roova' )  => $nearby,
		);
		foreach ( $columns as $heading => $items ) :
			if ( ! $items ) {
				continue;
			}
			?>
			<div class="roova-landmarks__col">
				<h4><?php echo esc_html( $heading ); ?></h4>
				<?php foreach ( $items as $item ) : ?>
					<div class="roova-landmark">
						<?php roova_the_icon( 'pin', 14 ); ?>
						<div>
							<?php echo esc_html( $item['name'] ); ?>
							<?php if ( $item['distance'] ) : ?>
								<small><?php echo esc_html( $item['distance'] ); ?></small>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * The guest review score box.
 *
 * @param int $hotel_id Hotel product ID.
 */
function roova_review_box( $hotel_id ) {
	$details = roova_get_hotel_details( $hotel_id );
	$score   = (float) $details['score'];

	if ( $score <= 0 ) {
		return;
	}

	$bars = array(
		__( 'Cleanliness', 'roova' ) => (float) $details['score_cleanliness'],
		__( 'Location', 'roova' )    => (float) $details['score_location'],
		__( 'Service', 'roova' )     => (float) $details['score_service'],
	);
	?>
	<div class="roova-card roova-reviews">
		<div class="roova-reviews__head">
			<span class="roova-reviews__score"><?php echo esc_html( number_format_i18n( $score, 1 ) ); ?></span>
			<span class="roova-reviews__text">
				<?php if ( $details['score_label'] ) : ?>
					<strong><?php echo esc_html( $details['score_label'] ); ?></strong>
				<?php endif; ?>
				<?php if ( $details['review_count'] ) : ?>
					<span>
						<?php
						printf(
							/* translators: %s: number of reviews */
							esc_html( _n( '%s review', '%s reviews', (int) $details['review_count'], 'roova' ) ),
							esc_html( number_format_i18n( (int) $details['review_count'] ) )
						);
						?>
					</span>
				<?php endif; ?>
			</span>
		</div>

		<?php foreach ( $bars as $label => $value ) : ?>
			<?php if ( $value > 0 ) : ?>
				<div class="roova-reviews__row">
					<span><?php echo esc_html( $label ); ?></span>
					<span class="roova-reviews__bar"><i style="width:<?php echo esc_attr( min( 100, ( $value / 5 ) * 100 ) ); ?>%"></i></span>
					<span><?php echo esc_html( number_format_i18n( $value, 1 ) ); ?></span>
				</div>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * The hotel map, or a placeholder that links out to Google Maps.
 *
 * @param int $hotel_id Hotel product ID.
 */
function roova_hotel_map( $hotel_id ) {
	$details = roova_get_hotel_details( $hotel_id );
	$lat     = trim( (string) $details['lat'] );
	$lng     = trim( (string) $details['lng'] );
	$address = trim( (string) $details['address'] );
	$key     = roova_option( 'maps_api_key', '' );
	$title   = get_the_title( $hotel_id );

	if ( ! $lat && ! $lng && ! $address ) {
		return;
	}

	$query = ( $lat && $lng ) ? $lat . ',' . $lng : $address;
	$link  = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $query );
	?>
	<div class="roova-card roova-map">
		<span class="roova-eyebrow"><?php esc_html_e( 'Location', 'roova' ); ?></span>

		<?php if ( $address ) : ?>
			<p class="roova-map__address"><?php echo esc_html( $address ); ?></p>
		<?php endif; ?>

		<?php if ( $key && $lat && $lng ) : ?>
			<div class="roova-map__canvas"
				data-roova-map
				data-lat="<?php echo esc_attr( $lat ); ?>"
				data-lng="<?php echo esc_attr( $lng ); ?>"
				data-zoom="<?php echo esc_attr( (int) $details['map_zoom'] ); ?>"
				data-title="<?php echo esc_attr( $title ); ?>"></div>
		<?php else : ?>
			<a class="roova-map__placeholder" href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener noreferrer">
				<?php roova_the_icon( 'pin', 26 ); ?>
				<span><?php esc_html_e( 'Open in Google Maps', 'roova' ); ?></span>
			</a>
		<?php endif; ?>

		<a class="roova-map__link" href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Get directions', 'roova' ); ?>
		</a>
	</div>
	<?php
}

/**
 * The sticky booking box on the hotel page.
 *
 * @param int   $hotel_id Hotel product ID.
 * @param array $criteria Search criteria.
 * @param float $from     Lowest nightly rate, or null.
 */
function roova_booking_box( $hotel_id, $criteria, $from = null ) {
	?>
	<div class="roova-card roova-booking-box">
		<?php if ( null !== $from ) : ?>
			<div class="roova-booking-box__from">
				<span><?php esc_html_e( 'From', 'roova' ); ?></span>
				<strong><?php echo wp_kses_post( wc_price( $from ) ); ?></strong>
				<span><?php esc_html_e( '/ night', 'roova' ); ?></span>
			</div>
		<?php endif; ?>

		<?php roova_search_form( array( 'compact' => true, 'hotel_id' => $hotel_id ) ); ?>

		<?php if ( function_exists( 'wc_get_cart_url' ) && WC()->cart && ! WC()->cart->is_empty() ) : ?>
			<a class="roova-btn roova-btn--ghost" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
				<?php esc_html_e( 'View your booking', 'roova' ); ?>
			</a>
		<?php endif; ?>

		<?php
		$details = roova_get_hotel_details( $hotel_id );
		if ( $details['checkin_time'] || $details['checkout_time'] ) :
			?>
			<p class="roova-booking-box__times">
				<?php roova_the_icon( 'clock', 14 ); ?>
				<?php
				printf(
					/* translators: 1: check-in time, 2: check-out time */
					esc_html__( 'Check in from %1$s · check out by %2$s', 'roova' ),
					esc_html( $details['checkin_time'] ),
					esc_html( $details['checkout_time'] )
				);
				?>
			</p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * The room details modal shell. Contents are filled in over AJAX.
 */
function roova_room_modal() {
	?>
	<div class="roova-modal" data-roova-modal hidden>
		<div class="roova-modal__box" role="dialog" aria-modal="true" aria-labelledby="roova-modal-title">
			<button type="button" class="roova-modal__close" data-roova-modal-close aria-label="<?php esc_attr_e( 'Close', 'roova' ); ?>">
				<?php roova_the_icon( 'close', 16 ); ?>
			</button>
			<div class="roova-modal__gallery" data-roova-modal-gallery></div>
			<div class="roova-modal__body">
				<h3 id="roova-modal-title" data-roova-modal-title></h3>
				<div class="roova-modal__meta" data-roova-modal-meta></div>
				<div class="roova-modal__desc" data-roova-modal-desc></div>
				<div class="roova-modal__amenities" data-roova-modal-amenities></div>
			</div>
		</div>
	</div>
	<?php
}
