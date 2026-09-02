<?php
/**
 * Bundled SVG icon set.
 *
 * Every icon is drawn on a 24x24 grid with a 2px stroke so amenities, room
 * features and UI chrome all share one visual language. Icons are inlined
 * (never loaded as files) so they inherit currentColor.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * The icon library: slug => array( label, inner SVG markup ).
 *
 * @return array
 */
function roova_icon_library() {
	static $icons = null;
	if ( null !== $icons ) {
		return $icons;
	}

	$icons = array(
		// Connectivity + services.
		'wifi'          => array( __( 'Wi-Fi', 'roova' ), '<path d="M5 12.5a11 11 0 0 1 14 0"/><path d="M8 15.5a7 7 0 0 1 8 0"/><path d="M12 18.5v.1"/>' ),
		'phone'         => array( __( 'Telephone', 'roova' ), '<path d="M6 3h3l2 5-2.5 1.5a12 12 0 0 0 6 6L16 13l5 2v3a2 2 0 0 1-2.2 2A17 17 0 0 1 4 5.2 2 2 0 0 1 6 3z"/>' ),
		'reception'     => array( __( 'Front desk [24-hour]', 'roova' ), '<path d="M3 20h18"/><path d="M5 20v-5a7 7 0 0 1 14 0v5"/><path d="M9 10V6a3 3 0 0 1 6 0v4"/>' ),
		'concierge'     => array( __( 'Concierge', 'roova' ), '<path d="M2 19h20"/><path d="M4 19a8 8 0 0 1 16 0"/><path d="M12 6V4"/><circle cx="12" cy="8" r="2"/>' ),
		'room-service'  => array( __( 'Room service', 'roova' ), '<path d="M3 18h18"/><path d="M5 18a7 7 0 0 1 14 0"/><path d="M12 8V6"/><path d="M9 21h6"/>' ),
		'housekeeping'  => array( __( 'Daily housekeeping', 'roova' ), '<path d="M8 3l3 8"/><path d="M6 11h10l2 10H4z"/><path d="M9 15v3M13 15v3"/>' ),
		'laundry'       => array( __( 'Laundry', 'roova' ), '<rect x="4" y="3" width="16" height="18" rx="2"/><circle cx="12" cy="14" r="4"/><path d="M8 6.5h.01M11 6.5h.01"/>' ),
		'luggage'       => array( __( 'Luggage storage', 'roova' ), '<rect x="5" y="7" width="14" height="13" rx="2"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/><path d="M10 11v5M14 11v5"/>' ),
		'business'      => array( __( 'Business center', 'roova' ), '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/><path d="M3 12h18"/>' ),
		'meeting'       => array( __( 'Meeting room', 'roova' ), '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>' ),
		'language'      => array( __( 'Languages spoken', 'roova' ), '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18a15 15 0 0 1 0-18z"/>' ),
		'currency'      => array( __( 'Currency exchange', 'roova' ), '<circle cx="12" cy="12" r="9"/><path d="M15 9H10.5a2 2 0 0 0 0 4h3a2 2 0 0 1 0 4H9"/><path d="M12 6v12"/>' ),
		'atm'           => array( __( 'ATM', 'roova' ), '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/>' ),

		// Building + access.
		'parking'       => array( __( 'Car park', 'roova' ), '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9.5 17V7h3.2a3 3 0 0 1 0 6H9.5"/>' ),
		'shuttle'       => array( __( 'Airport shuttle', 'roova' ), '<rect x="3" y="6" width="15" height="9" rx="2"/><path d="M18 9h2.5L22 12v3h-4"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="17" cy="17.5" r="1.5"/>' ),
		'ev-charging'   => array( __( 'EV charging', 'roova' ), '<rect x="4" y="4" width="10" height="16" rx="2"/><path d="M9 8l-2 4h4l-2 4"/><path d="M14 10h3a2 2 0 0 1 2 2v4a1.5 1.5 0 0 0 3 0V9"/>' ),
		'elevator'      => array( __( 'Elevator', 'roova' ), '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M12 3v18"/><path d="M8 10l-1.5 2h3zM16 14l1.5-2h-3z"/>' ),
		'accessible'    => array( __( 'Wheelchair accessible', 'roova' ), '<circle cx="12" cy="4.5" r="1.5"/><path d="M9 8h6"/><path d="M11 8v6h5l2 5"/><path d="M11 14a4.5 4.5 0 1 0 3.5 7"/>' ),
		'security'      => array( __( 'Security [24-hour]', 'roova' ), '<path d="M12 3l8 3v6c0 4.5-3.2 8-8 9-4.8-1-8-4.5-8-9V6z"/><path d="M9 12l2 2 4-4"/>' ),
		'smoking'       => array( __( 'Smoking area', 'roova' ), '<path d="M3 17h14v3H3z"/><path d="M19 17h2v3h-2z"/><path d="M16 14c2-1 2-3 0-4s-2-3 0-4"/>' ),
		'non-smoking'   => array( __( 'Non-smoking', 'roova' ), '<path d="M3 15h13v4H3z"/><path d="M4 4l16 16"/><path d="M18 15h3v4h-3z"/>' ),
		'pets'          => array( __( 'Pets allowed', 'roova' ), '<circle cx="7" cy="9" r="1.8"/><circle cx="12" cy="6.5" r="1.8"/><circle cx="17" cy="9" r="1.8"/><path d="M12 11c3 0 5 2.4 5 4.6 0 2-1.6 3.4-3.4 2.9-1-.3-2.2-.3-3.2 0C8.6 19 7 17.6 7 15.6 7 13.4 9 11 12 11z"/>' ),
		'family'        => array( __( 'Family friendly', 'roova' ), '<circle cx="8" cy="7" r="2.5"/><circle cx="17" cy="8" r="2"/><path d="M3 20v-2a5 5 0 0 1 10 0v2"/><path d="M14 20v-1.5a3.5 3.5 0 0 1 7 0V20"/>' ),
		'child'         => array( __( 'Children', 'roova' ), '<circle cx="12" cy="5" r="2.8"/><path d="M12 7.8v5.4"/><path d="M8.4 10.2h7.2"/><path d="M12 13.2l-2.6 5.9M12 13.2l2.6 5.9"/>' ),

		// Food + drink.
		'breakfast'     => array( __( 'Breakfast', 'roova' ), '<circle cx="12" cy="13" r="6"/><path d="M12 9.5a3.5 3.5 0 0 1 0 7"/><path d="M3 21h18"/>' ),
		'restaurant'    => array( __( 'Restaurant', 'roova' ), '<path d="M6 3v8a2 2 0 0 0 4 0V3"/><path d="M8 11v10"/><path d="M17 3c-1.5 1.5-2 3-2 5s.7 3 2 3v10"/>' ),
		'bar'           => array( __( 'Bar', 'roova' ), '<path d="M4 4h16l-8 8z"/><path d="M12 12v7"/><path d="M8 19h8"/>' ),
		'coffee'        => array( __( 'Coffee / tea maker', 'roova' ), '<path d="M4 8h13v6a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5z"/><path d="M17 10h2a2.5 2.5 0 0 1 0 5h-2"/><path d="M8 3v2M12 3v2"/>' ),
		'kettle'        => array( __( 'Kettle', 'roova' ), '<path d="M7 9h8a1 1 0 0 1 1 1v8a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2v-8a1 1 0 0 1 1-1z"/><path d="M7 11L3.5 8"/><path d="M16 12.5a2.75 2.75 0 0 1 0 4.5"/><path d="M10 9V7.5h3V9"/>' ),
		'minibar'       => array( __( 'Minibar', 'roova' ), '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M5 11h14"/><path d="M9 7v1M9 15v1"/>' ),
		'fridge'        => array( __( 'Refrigerator', 'roova' ), '<rect x="6" y="2" width="12" height="20" rx="2"/><path d="M6 9h12"/><path d="M10 5.5v2M10 12v2.5"/>' ),
		'kitchen'       => array( __( 'Kitchenette', 'roova' ), '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.8"/><circle cx="15.5" cy="9" r="1.8"/><path d="M3 14h18"/>' ),
		'water'         => array( __( 'Drinking water', 'roova' ), '<path d="M12 3s6 6.5 6 10.5A6 6 0 0 1 6 13.5C6 9.5 12 3 12 3z"/>' ),

		// Leisure.
		'pool'          => array( __( 'Swimming pool', 'roova' ), '<path d="M2 17c2 0 2 1.6 4 1.6S8 17 10 17s2 1.6 4 1.6S16 17 18 17s2 1.6 4 1.6"/><path d="M7 15V6a2.5 2.5 0 0 1 5 0"/><path d="M12 15V6a2.5 2.5 0 0 1 5 0"/><path d="M7 10h5"/>' ),
		'gym'           => array( __( 'Fitness center', 'roova' ), '<path d="M4 9v6M7 7v10M17 7v10M20 9v6"/><path d="M7 12h10"/>' ),
		'spa'           => array( __( 'Spa', 'roova' ), '<path d="M12 21c0-5 3-9 9-10-1 6-5 9-9 10z"/><path d="M12 21c0-5-3-9-9-10 1 6 5 9 9 10z"/><path d="M12 21c0-4 .8-7 2-9"/>' ),
		'beach'         => array( __( 'Beach nearby', 'roova' ), '<path d="M12 12L4 10a8 8 0 0 1 16 0z"/><path d="M12 12v9"/><path d="M2 20c2 0 2 1.4 4 1.4S10 20 12 20s2 1.4 4 1.4S20 20 22 20"/>' ),
		'garden'        => array( __( 'Garden', 'roova' ), '<path d="M12 21v-7"/><path d="M12 14c-4 0-6-2.5-6-6 4 0 6 2.5 6 6z"/><path d="M12 14c4 0 6-2.5 6-6-4 0-6 2.5-6 6z"/><path d="M4 21h16"/>' ),
		'terrace'       => array( __( 'Terrace', 'roova' ), '<path d="M3 10h18L12 3z"/><path d="M5 10v11M19 10v11"/><path d="M9 21v-5h6v5"/>' ),
		'bbq'           => array( __( 'BBQ facilities', 'roova' ), '<path d="M5 6h14l-2.5 8h-9z"/><path d="M9 14l-2 6M15 14l2 6"/><path d="M8 20h8"/>' ),
		'playground'    => array( __( 'Playground', 'roova' ), '<path d="M4 20V6l16 6v8"/><path d="M4 12l16 6"/><path d="M8 20v-4M16 20v-3"/>' ),

		// In-room.
		'ac'            => array( __( 'Air conditioning', 'roova' ), '<rect x="3" y="5" width="18" height="8" rx="2"/><path d="M6 17c1.5 0 1.5 2 3 2M12 17c1.5 0 1.5 2 3 2"/><path d="M6 9h12"/>' ),
		'heating'       => array( __( 'Heating', 'roova' ), '<path d="M8 3v10a4 4 0 1 0 4 0V3a2 2 0 0 0-4 0z"/><path d="M15 6h4M15 10h3"/>' ),
		'tv'            => array( __( 'Television', 'roova' ), '<rect x="3" y="5" width="18" height="12" rx="2"/><path d="M8 21h8M12 17v4"/>' ),
		'bathtub'       => array( __( 'Bathtub', 'roova' ), '<path d="M3 12h18v3a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4z"/><path d="M6 12V6a2 2 0 0 1 4 0"/><path d="M6 19v2M18 19v2"/>' ),
		'shower'        => array( __( 'Shower', 'roova' ), '<path d="M5 12h14"/><path d="M7 12V7a5 5 0 0 1 10 0v5"/><path d="M8 16v2M12 17v2M16 16v2"/>' ),
		'bathroom'      => array( __( 'Private bathroom', 'roova' ), '<rect x="8.5" y="2" width="7" height="5.5" rx="1"/><path d="M5 12h14"/><path d="M6.5 12v1.5A4 4 0 0 0 10.5 17.5h3a4 4 0 0 0 4-4V12"/><path d="M12 12V9.8"/><path d="M10.5 17.5V21h3v-3.5"/>' ),
		'toiletries'    => array( __( 'Free toiletries', 'roova' ), '<rect x="7" y="8" width="10" height="13" rx="2"/><path d="M10 8V5h4v3"/><path d="M10 13h4"/>' ),
		'hairdryer'     => array( __( 'Hairdryer', 'roova' ), '<path d="M4 8h10a4 4 0 0 1 0 8H4z"/><path d="M8 16l1 5"/><path d="M18 10h3M18 14h3"/>' ),
		'mirror'        => array( __( 'Mirror', 'roova' ), '<rect x="6" y="2" width="12" height="16" rx="6"/><path d="M12 18v3"/><path d="M9 21h6"/><path d="M10 6.5a3.5 3.5 0 0 0-1.5 2.8"/>' ),
		'slippers'      => array( __( 'Slippers', 'roova' ), '<rect x="3.2" y="4" width="6.4" height="16" rx="3.2"/><path d="M3.5 9.4c1.6 1.3 4.6 1.3 6.2 0"/><rect x="14.4" y="4" width="6.4" height="16" rx="3.2"/><path d="M14.7 9.4c1.6 1.3 4.6 1.3 6.2 0"/>' ),
		'towels'        => array( __( 'Fresh towels', 'roova' ), '<path d="M6 4h10a3 3 0 0 1 3 3v13H9a3 3 0 0 1-3-3z"/><path d="M6 4a3 3 0 0 0 0 6h3"/>' ),
		'safe'          => array( __( 'In-room safe', 'roova' ), '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="11" cy="12" r="3"/><path d="M18 9v6"/>' ),
		'desk'          => array( __( 'Work desk', 'roova' ), '<path d="M3 9h18"/><path d="M5 9v11M19 9v11"/><path d="M3 5h18v4H3z"/>' ),
		'wardrobe'      => array( __( 'Wardrobe', 'roova' ), '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M12 3v18"/><path d="M10 11v2M14 11v2"/>' ),
		'iron'          => array( __( 'Iron', 'roova' ), '<path d="M3 16h18v3H3z"/><path d="M5 16V9h8a6 6 0 0 1 6 6v1"/><path d="M8 9V6h5"/>' ),
		'balcony'       => array( __( 'Balcony', 'roova' ), '<path d="M4 12h16v9H4z"/><path d="M4 16h16"/><path d="M9 12v9M15 12v9"/><path d="M7 8a5 5 0 0 1 10 0"/>' ),
		'window'        => array( __( 'Window that opens', 'roova' ), '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M12 4v16M4 12h16"/>' ),
		'soundproof'    => array( __( 'Soundproof', 'roova' ), '<path d="M11 5L6 9H3v6h3l5 4z"/><path d="M15 9.5a4 4 0 0 1 0 5"/><path d="M18 7a8 8 0 0 1 0 10"/>' ),
		'bed'           => array( __( 'Bed', 'roova' ), '<path d="M3 18v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6"/><path d="M3 18v2M21 18v2"/><path d="M3 12V8a2 2 0 0 1 2-2h4v6"/>' ),
		'sofa'          => array( __( 'Seating area', 'roova' ), '<path d="M4 12V8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v4"/><path d="M2 12a2 2 0 0 1 4 0v3h12v-3a2 2 0 0 1 4 0v6H2z"/>' ),
		'city-view'     => array( __( 'City view', 'roova' ), '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M6 16l4-4 3 3 5-5"/><circle cx="8" cy="8.5" r="1.2"/>' ),
		'sea-view'      => array( __( 'Sea view', 'roova' ), '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.8"/><path d="M3 15c2 0 2 1.4 4 1.4S11 15 13 15s2 1.4 4 1.4 2-1.4 4-1.4"/>' ),
		'mountain-view' => array( __( 'Mountain view', 'roova' ), '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M4 17l5-6 3.5 4L15 12l5 5"/>' ),
		'keycard'       => array( __( 'Key card access', 'roova' ), '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="8" cy="12" r="2"/><path d="M13 10h6M13 14h4"/>' ),

		// UI chrome.
		'size'          => array( __( 'Room size', 'roova' ), '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 3v18"/>' ),
		'guests'        => array( __( 'Guests', 'roova' ), '<path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>' ),
		'calendar'      => array( __( 'Dates', 'roova' ), '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>' ),
		'pin'           => array( __( 'Location', 'roova' ), '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>' ),
		'search'        => array( __( 'Search', 'roova' ), '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>' ),
		'chevron-left'  => array( __( 'Previous', 'roova' ), '<path d="M15 18l-6-6 6-6"/>' ),
		'chevron-right' => array( __( 'Next', 'roova' ), '<path d="M9 18l6-6-6-6"/>' ),
		'chevron-down'  => array( __( 'Expand', 'roova' ), '<path d="M6 9l6 6 6-6"/>' ),
		'close'         => array( __( 'Close', 'roova' ), '<path d="M18 6L6 18M6 6l12 12"/>' ),
		'check'         => array( __( 'Included', 'roova' ), '<path d="M20 6L9 17l-5-5"/>' ),
		'info'          => array( __( 'Information', 'roova' ), '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8v.01"/>' ),
		'clock'         => array( __( 'Times', 'roova' ), '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>' ),
		'tag'           => array( __( 'Offer', 'roova' ), '<path d="M3 12V4h8l9 9-8 8z"/><circle cx="7.5" cy="7.5" r="1.5"/>' ),
		'arrow-right'   => array( __( 'Continue', 'roova' ), '<path d="M4 12h15"/><path d="M13 6l6 6-6 6"/>' ),
		'check-circle'  => array( __( 'Confirmed', 'roova' ), '<circle cx="12" cy="12" r="9"/><path d="M8.4 12.4l2.4 2.4 4.8-5.2"/>' ),

		// Account chrome.
		'user'          => array( __( 'Account', 'roova' ), '<circle cx="12" cy="8" r="4"/><path d="M4.5 20.5v-.9A5.6 5.6 0 0 1 10.1 14h3.8a5.6 5.6 0 0 1 5.6 5.6v.9"/>' ),
		'log-out'       => array( __( 'Sign out', 'roova' ), '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/>' ),
		'crown'         => array( __( 'Membership', 'roova' ), '<path d="M3 7l4.5 4L12 4l4.5 7L21 7l-1.8 10H4.8z"/><path d="M4.8 20h14.4"/>' ),
		'star'          => array( __( 'Rating', 'roova' ), '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>' ),
		'heart'         => array( __( 'Saved stay', 'roova' ), '<path d="M12 20.5S3.5 15 3.5 9.3A4.3 4.3 0 0 1 12 7a4.3 4.3 0 0 1 8.5 2.3c0 5.7-8.5 11.2-8.5 11.2z"/>' ),
		'bed-double'    => array( __( 'Bookings', 'roova' ), '<path d="M2 20v-8a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v8"/><path d="M4 10V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v4"/><path d="M12 4v6"/><path d="M2 17h20"/>' ),
		'shield-check'  => array( __( 'Verified', 'roova' ), '<path d="M12 3l8 3v6c0 4.5-3.2 8-8 9-4.8-1-8-4.5-8-9V6z"/><path d="M9 12l2 2 4-4"/>' ),
		'pen-line'      => array( __( 'Write a review', 'roova' ), '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>' ),
		'percent'       => array( __( 'Cashback', 'roova' ), '<path d="M19 5L5 19"/><circle cx="7.5" cy="7.5" r="2.5"/><circle cx="16.5" cy="16.5" r="2.5"/>' ),
		'headset'       => array( __( 'Priority support', 'roova' ), '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><path d="M4 13h2a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1z"/><path d="M20 13h-2a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1z"/><path d="M20 19v.5a2.5 2.5 0 0 1-2.5 2.5H14"/>' ),
		'calendar-check' => array( __( 'Flexible cancellation', 'roova' ), '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/><path d="M9 15l2 2 4-4"/>' ),
		'eye'           => array( __( 'Show password', 'roova' ), '<path d="M2 12s3.7-6.5 10-6.5S22 12 22 12s-3.7 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>' ),
		'eye-off'       => array( __( 'Hide password', 'roova' ), '<path d="M10.7 5.7A10.7 10.7 0 0 1 12 5.5c6.3 0 10 6.5 10 6.5a18.4 18.4 0 0 1-3.3 4"/><path d="M6.4 7.5A17.8 17.8 0 0 0 2 12s3.7 6.5 10 6.5a10.3 10.3 0 0 0 4.2-.9"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/><path d="M3 3l18 18"/>' ),

		// Cashback rewards.
		'coins'         => array( __( 'Cashback', 'roova' ), '<ellipse cx="9" cy="6.5" rx="6" ry="2.8"/><path d="M3 6.5v4c0 1.6 2.7 2.8 6 2.8s6-1.2 6-2.8v-4"/><path d="M3 10.5v4c0 1.6 2.7 2.8 6 2.8 .6 0 1.2 0 1.7-.1"/><path d="M15 12.4c3.2.2 6 1.4 6 2.9v3c0 1.6-2.7 2.8-6 2.8s-6-1.2-6-2.8v-3c0-1.6 2.7-2.9 6-2.9z"/>' ),
		'moon'          => array( __( 'Long stay', 'roova' ), '<path d="M20.5 14.6A8.5 8.5 0 0 1 9.4 3.5a8.5 8.5 0 1 0 11.1 11.1z"/>' ),
		'sun'           => array( __( 'Weekday stay', 'roova' ), '<circle cx="12" cy="12" r="4"/><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M19.1 4.9l-1.8 1.8M6.7 17.3l-1.8 1.8"/>' ),
		'users'         => array( __( 'Referral', 'roova' ), '<circle cx="9" cy="8" r="3.4"/><path d="M2.5 20.5v-.7A5.2 5.2 0 0 1 7.7 14.6h2.6a5.2 5.2 0 0 1 5.2 5.2v.7"/><path d="M16.5 5.2a3.4 3.4 0 0 1 0 6.4"/><path d="M18 14.8a5.2 5.2 0 0 1 3.5 4.9v.8"/>' ),
		'repeat'        => array( __( 'Repeat booking', 'roova' ), '<path d="M4 9V8a3 3 0 0 1 3-3h13"/><path d="M17 2l3 3-3 3"/><path d="M20 15v1a3 3 0 0 1-3 3H4"/><path d="M7 22l-3-3 3-3"/>' ),
		'gift'          => array( __( 'Bonus', 'roova' ), '<rect x="3" y="8.5" width="18" height="4" rx="1"/><path d="M4.5 12.5V20a1 1 0 0 0 1 1h13a1 1 0 0 0 1-1v-7.5"/><path d="M12 8.5V21"/><path d="M12 8.5H7.8a2.4 2.4 0 1 1 0-4.8C10.6 3.7 12 8.5 12 8.5z"/><path d="M12 8.5h4.2a2.4 2.4 0 1 0 0-4.8C13.4 3.7 12 8.5 12 8.5z"/>' ),
		'plus-circle'   => array( __( 'Earned', 'roova' ), '<circle cx="12" cy="12" r="9"/><path d="M12 8.2v7.6M8.2 12h7.6"/>' ),
		'minus-circle'  => array( __( 'Redeemed', 'roova' ), '<circle cx="12" cy="12" r="9"/><path d="M8.2 12h7.6"/>' ),

		// Checkout chrome.
		'lock'          => array( __( 'Secure', 'roova' ), '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><path d="M12 14.5v2.5"/>' ),
		'bank'          => array( __( 'Online banking', 'roova' ), '<path d="M3 10l9-6 9 6"/><path d="M5 10v8M10 10v8M14 10v8M19 10v8"/><path d="M3 21h18"/>' ),
		'credit-card'   => array( __( 'Card', 'roova' ), '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 9.5h20"/><path d="M6 14.5h3M12 14.5h6"/>' ),
		'wallet'        => array( __( 'E-wallet', 'roova' ), '<path d="M20 8V7a2 2 0 0 0-2-2H5.5A2.5 2.5 0 0 0 3 7.5v10A2.5 2.5 0 0 0 5.5 20H18a2 2 0 0 0 2-2v-1"/><path d="M15 12.5h6v-3h-6a1.5 1.5 0 0 0 0 3z"/>' ),
		'building'      => array( __( 'Pay at the hotel', 'roova' ), '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h.01M12 7h.01M16 7h.01M8 11h.01M12 11h.01M16 11h.01"/><path d="M10 21v-5h4v5"/>' ),

		// The homepage guarantees row.
		'best-rate'     => array( __( 'Best rate', 'roova' ), '<circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6"/><path d="M9.4 9.4v.01M14.6 14.6v.01"/>' ),
		'no-fees'       => array( __( 'No booking fees', 'roova' ), '<path d="M5 3v18l2-1.2 2 1.2 2-1.2 2 1.2 2-1.2 2 1.2V3l-2 1.2-2-1.2-2 1.2-2-1.2-2 1.2z"/><path d="M9 9h6M9 13h4"/>' ),
		'instant'       => array( __( 'Instant confirmation', 'roova' ), '<path d="M13 2L4 13h7l-1 9 9-11h-7l1-9z"/>' ),
		'support'       => array( __( 'Support', 'roova' ), '<path d="M3 12a9 9 0 0 1 18 0v5a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/><path d="M3 12v5a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3"/><path d="M21 17v1a4 4 0 0 1-4 4h-4"/>' ),
	);

	/**
	 * Filter the icon library so child themes can add their own icons.
	 *
	 * @param array $icons slug => array( label, inner SVG ).
	 */
	$icons = apply_filters( 'roova_icon_library', $icons );

	return $icons;
}

/**
 * Render an icon from the library.
 *
 * @param string $name  Icon slug.
 * @param int    $size  Pixel size.
 * @param string $class Extra CSS class.
 * @return string Safe SVG markup, or '' when the icon does not exist.
 */
function roova_icon( $name, $size = 16, $class = '' ) {
	$icons = roova_icon_library();
	$name  = sanitize_key( $name );
	if ( ! isset( $icons[ $name ] ) ) {
		return '';
	}

	return sprintf(
		'<svg class="roova-icon %1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
		esc_attr( $class ),
		absint( $size ),
		$icons[ $name ][1] // Static markup defined above, not user input.
	);
}

/**
 * Print an icon.
 *
 * @param string $name  Icon slug.
 * @param int    $size  Pixel size.
 * @param string $class Extra CSS class.
 */
function roova_the_icon( $name, $size = 16, $class = '' ) {
	echo roova_icon( $name, $size, $class ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from the static library above.
}

/**
 * The icon whose name matches a term exactly, or '' when nothing matches.
 *
 * Lets a term called "Mirror", "kettle" or "Smoking area" show the right icon
 * with no admin work. Matching is deliberately exact (case-insensitively, on
 * either the icon slug or its label) so nothing is guessed wrongly — anything
 * else keeps the neutral tick.
 *
 * @param string $name Term name.
 * @return string Icon slug, or ''.
 */
function roova_icon_slug_for_name( $name ) {
	$name = strtolower( trim( (string) $name ) );
	if ( '' === $name ) {
		return '';
	}

	foreach ( roova_icon_library() as $slug => $icon ) {
		if ( $name === $slug || $name === strtolower( $icon[0] ) ) {
			return $slug;
		}
	}

	return '';
}

/**
 * The icon chosen for an amenity term, with a sensible fallback.
 *
 * @param int|WP_Term $term Term or term ID.
 * @param int         $size Pixel size.
 * @return string
 */
function roova_amenity_icon( $term, $size = 16 ) {
	$term_id = is_object( $term ) ? (int) $term->term_id : (int) $term;

	$custom = get_term_meta( $term_id, 'roova_icon_image', true );
	if ( $custom ) {
		$url = wp_get_attachment_image_url( (int) $custom, 'thumbnail' );
		if ( $url ) {
			return sprintf(
				'<img class="roova-icon roova-icon--image" src="%s" alt="" width="%d" height="%d" />',
				esc_url( $url ),
				absint( $size ),
				absint( $size )
			);
		}
	}

	$slug = get_term_meta( $term_id, 'roova_icon', true );
	$svg  = $slug ? roova_icon( $slug, $size ) : '';

	// Nothing chosen: fall back to an icon of the same name, then to a tick.
	if ( ! $svg ) {
		$name = is_object( $term ) ? $term->name : '';

		if ( '' === $name && $term_id ) {
			$object = get_term( $term_id );
			$name   = ( $object && ! is_wp_error( $object ) ) ? $object->name : '';
		}

		$guess = roova_icon_slug_for_name( $name );
		$svg   = $guess ? roova_icon( $guess, $size ) : '';
	}

	return $svg ? $svg : roova_icon( 'check', $size );
}

/**
 * A star rating row.
 *
 * @param int $stars Number of filled stars (0-5).
 * @param int $size  Pixel size.
 * @return string
 */
function roova_stars( $stars, $size = 14 ) {
	$stars = max( 0, min( 5, (int) $stars ) );
	if ( ! $stars ) {
		return '';
	}

	$out = '<span class="roova-stars" role="img" aria-label="' . esc_attr( sprintf( /* translators: %d: star count */ _n( '%d star hotel', '%d star hotel', $stars, 'roova' ), $stars ) ) . '">';
	for ( $i = 0; $i < 5; $i++ ) {
		$out .= sprintf(
			'<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="%2$s" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 2l2.9 6.6 7.1.6-5.4 4.7 1.6 7-6.2-3.8L6 21l1.6-7L2.2 9.2l7.1-.6L12 2z"/></svg>',
			absint( $size ),
			$i < $stars ? 'currentColor' : 'none'
		);
	}
	$out .= '</span>';

	return $out;
}
