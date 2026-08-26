<?php
/**
 * Flash Sale Stock Guard for WooCommerce Custom Order Email.
 *
 * @package FlashSaleStockGuardWooCommerce\Woo\Emails
 */

declare(strict_types=1);

namespace FlashSaleStockGuardWooCommerce\Woo\Emails;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Custom_Email.
 *
 * Fires on order completion by default — change the triggering hook and the
 * template content in templates/emails/ for your real notification.
 */
class Custom_Email extends \WC_Email {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'fssgw_custom_email';
		$this->title          = __( 'Flash Sale Stock Guard for WooCommerce Custom Email', 'flash-sale-stock-guard-for-woocommerce' );
		$this->description    = __( 'Sent when an order is marked complete. Change the trigger and content for your use case.', 'flash-sale-stock-guard-for-woocommerce' );
		$this->customer_email = true;
		$this->template_html  = 'emails/fssgw-custom-email.php';
		$this->template_plain = 'emails/plain/fssgw-custom-email.php';
		$this->template_base  = FSSGW_PATH . 'templates/';
		$this->placeholders   = array(
			'{order_number}' => '',
		);

		add_action( 'woocommerce_order_status_completed', array( $this, 'trigger' ) );

		parent::__construct();
	}

	/**
	 * Trigger the email for a given order.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function trigger( $order_id ) {
		if ( ! $order_id ) {
			return;
		}

		$this->object = wc_get_order( $order_id );

		if ( ! $this->object ) {
			return;
		}

		$this->placeholders['{order_number}'] = $this->object->get_order_number();
		$this->recipient                      = $this->object->get_billing_email();

		if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
			return;
		}

		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}

	/**
	 * Get the HTML content of the email.
	 *
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => false,
				'email'         => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Get the plain text content of the email.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => true,
				'email'         => $this,
			),
			'',
			$this->template_base
		);
	}
}
