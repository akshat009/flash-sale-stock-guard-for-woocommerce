<?php
/**
 * Flash Sale Stock Guard for WooCommerce Payment Gateway (classic checkout).
 *
 * @package FlashSaleStockGuardWooCommerce\Woo\Gateways
 */

declare(strict_types=1);

namespace FlashSaleStockGuardWooCommerce\Woo\Gateways;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Gateway.
 *
 * A real, registerable WC_Payment_Gateway. process_payment() is a stub —
 * replace it with a call to your actual payment processor before going live.
 */
class Gateway extends \WC_Payment_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'fssgw_gateway';
		$this->icon               = '';
		$this->has_fields         = false;
		$this->method_title       = __( 'Flash Sale Stock Guard for WooCommerce', 'flash-sale-stock-guard-for-woocommerce' );
		$this->method_description = __( 'Custom payment gateway scaffolded by Flash Sale Stock Guard for WooCommerce.', 'flash-sale-stock-guard-for-woocommerce' );
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled      = $this->get_option( 'enabled' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Define settings fields shown on the classic (non-block) gateway settings screen.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enable/Disable', 'flash-sale-stock-guard-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this payment method', 'flash-sale-stock-guard-for-woocommerce' ),
				'default' => 'no',
			),
			'title'       => array(
				'title'       => __( 'Title', 'flash-sale-stock-guard-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Payment method title customers see at checkout.', 'flash-sale-stock-guard-for-woocommerce' ),
				'default'     => __( 'Flash Sale Stock Guard for WooCommerce', 'flash-sale-stock-guard-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'flash-sale-stock-guard-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description customers see at checkout.', 'flash-sale-stock-guard-for-woocommerce' ),
				'default'     => __( 'Pay securely.', 'flash-sale-stock-guard-for-woocommerce' ),
			),
		);
	}

	/**
	 * Process the payment for an order.
	 *
	 * TODO: integrate with your real payment processor — this stub marks the
	 * order paid immediately without charging anything.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return array( 'result' => 'failure' );
		}

		$order->payment_complete();
		$order->add_order_note( __( 'Paid via Flash Sale Stock Guard for WooCommerce (stub gateway — no real charge was made).', 'flash-sale-stock-guard-for-woocommerce' ) );

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}
}
