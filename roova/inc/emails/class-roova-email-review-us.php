<?php
/**
 * The "Review us" email: sent to a guest once their stay is over, with a link
 * straight to the review box on the hotel's page.
 *
 * A WooCommerce email rather than a bare wp_mail(), so the client switches it
 * on and off, rewrites the subject, heading and closing text, and picks the
 * delay under WooCommerce → Settings → Emails, and it is sent in the store's
 * own email template like every other message a guest gets. What decides *who*
 * gets it is in inc/review-us.php; this class only builds and sends one.
 *
 * Loaded from roova_review_us_email_class() on `woocommerce_email_classes`,
 * the one moment WC_Email is guaranteed to exist.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Roova_Email_Review_Us' ) || ! class_exists( 'WC_Email' ) ) {
	return;
}

/**
 * Review us.
 */
class Roova_Email_Review_Us extends WC_Email {

	/**
	 * Hotel product ID the email is about.
	 *
	 * @var int
	 */
	public $hotel_id = 0;

	/**
	 * Check-in date of the stay, Y-m-d.
	 *
	 * @var string
	 */
	public $check_in = '';

	/**
	 * Check-out date of the stay, Y-m-d.
	 *
	 * @var string
	 */
	public $check_out = '';

	/**
	 * Set it up.
	 */
	public function __construct() {
		$this->id             = 'roova_review_us';
		$this->customer_email = true;
		$this->title          = __( 'Review us', 'roova' );
		$this->description    = __( 'Sent to a guest after their check-out date, asking them to review the hotel they stayed at. The button opens the hotel page at its review form. Only guests with an account who have not yet reviewed that hotel receive it, and each booking is asked once.', 'roova' );

		/*
		 * Not WooCommerce's own customer-review-request.php: WooCommerce 10.8+
		 * ships a template by that name, and a theme file with the same name
		 * would silently replace it.
		 */
		$this->template_html  = 'emails/roova-review-us.php';
		$this->template_plain = 'emails/plain/roova-review-us.php';
		$this->template_base  = ROOVA_DIR . 'woocommerce/';

		$this->placeholders = array(
			'{hotel_name}'   => '',
			'{order_number}' => '',
			'{order_date}'   => '',
		);

		parent::__construct();
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'How was your stay at {hotel_name}?', 'roova' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Tell us about your stay', 'roova' );
	}

	/**
	 * Default closing text.
	 *
	 * @return string
	 */
	public function get_default_additional_content() {
		return __( 'Thank you for booking with us. We hope to welcome you back soon.', 'roova' );
	}

	/**
	 * How many days after check-out the email goes out.
	 *
	 * @return int 1–60.
	 */
	public function get_delay_days() {
		$days = (int) $this->get_option( 'delay_days', 1 );

		return max( 1, min( 60, $days ) );
	}

	/**
	 * The settings on WooCommerce → Settings → Emails → Review us: WooCommerce's
	 * own, with the delay added after the switch.
	 */
	public function init_form_fields() {
		parent::init_form_fields();

		$fields = array();
		foreach ( $this->form_fields as $key => $field ) {
			$fields[ $key ] = $field;

			if ( 'enabled' === $key ) {
				$fields['delay_days'] = array(
					'title'             => __( 'Days after check-out', 'roova' ),
					'type'              => 'number',
					'description'       => __( 'How many days after the guest checks out the email is sent (1–60). It goes out in the morning, site time.', 'roova' ),
					'default'           => '1',
					'desc_tip'          => true,
					'custom_attributes' => array(
						'min'  => '1',
						'max'  => '60',
						'step' => '1',
					),
				);
			}
		}

		$fields['additional_content']['description'] = __( 'Text shown under the button.', 'roova' ) . ' ' . $fields['additional_content']['description'];

		$this->form_fields = $fields;
	}

	/**
	 * Send the email for one stay.
	 *
	 * @param int    $order_id  Order ID.
	 * @param int    $hotel_id  Hotel product ID.
	 * @param string $check_in  Check-in date, Y-m-d.
	 * @param string $check_out Check-out date, Y-m-d.
	 * @return bool Whether it was sent.
	 */
	public function trigger( $order_id, $hotel_id = 0, $check_in = '', $check_out = '' ) {
		$this->setup_locale();

		$order = $order_id ? wc_get_order( $order_id ) : false;
		$sent  = false;

		$this->object    = $order instanceof WC_Order ? $order : false;
		$this->hotel_id  = absint( $hotel_id );
		$this->check_in  = (string) $check_in;
		$this->check_out = (string) $check_out;
		$this->recipient = $this->object ? $this->object->get_billing_email() : '';

		if ( $this->object ) {
			$created = $this->object->get_date_created();

			$this->placeholders['{hotel_name}']   = $this->hotel_id ? get_the_title( $this->hotel_id ) : '';
			$this->placeholders['{order_number}'] = $this->object->get_order_number();
			$this->placeholders['{order_date}']   = $created ? wc_format_datetime( $created ) : '';
		}

		if ( $this->object && $this->hotel_id && $this->is_enabled() && $this->get_recipient() ) {
			$sent = (bool) $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();

		return $sent;
	}

	/**
	 * What the templates are handed.
	 *
	 * @param bool $plain_text Plain text version.
	 * @return array
	 */
	protected function template_args( $plain_text ) {
		$hotel_name = $this->hotel_id ? get_the_title( $this->hotel_id ) : '';

		return array(
			'order'              => $this->object,
			'email_heading'      => $this->get_heading(),
			'additional_content' => $this->get_additional_content(),
			'hotel_name'         => $hotel_name ? $hotel_name : __( 'your hotel', 'roova' ),
			'review_url'         => $this->hotel_id && function_exists( 'roova_review_us_url' ) ? roova_review_us_url( $this->hotel_id ) : home_url( '/' ),
			'check_in'           => $this->check_in,
			'check_out'          => $this->check_out,
			'sent_to_admin'      => false,
			'plain_text'         => $plain_text,
			'email'              => $this,
		);
	}

	/**
	 * HTML body.
	 *
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html( $this->template_html, $this->template_args( false ), '', $this->template_base );
	}

	/**
	 * Plain text body.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, $this->template_args( true ), '', $this->template_base );
	}
}
