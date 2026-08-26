<?php
/**
 * Flash Sale Stock Guard for WooCommerce Shipping Method.
 *
 * @package FlashSaleStockGuardWooCommerce\Woo\Shipping
 */

declare(strict_types=1);

namespace FlashSaleStockGuardWooCommerce\Woo\Shipping;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Shipping_Method.
 *
 * A real, registerable WC_Shipping_Method — flat-rate by default. Replace
 * calculate_shipping() with your own rate logic (weight, distance, API call...).
 */
class Shipping_Method extends \WC_Shipping_Method {

	/**
	 * Constructor.
	 *
	 * @param int $instance_id Shipping zone instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'fssgw_shipping';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Flash Sale Stock Guard for WooCommerce Shipping', 'flash-sale-stock-guard-for-woocommerce' );
		$this->method_description = __( 'Custom shipping method scaffolded by Flash Sale Stock Guard for WooCommerce.', 'flash-sale-stock-guard-for-woocommerce' );
		$this->supports            = array( 'shipping-zones', 'instance-settings' );

		$this->init();
	}

	/**
	 * Initialize settings fields and load current values.
	 *
	 * @return void
	 */
	public function init() {
		$this->init_form_fields();
		$this->init_settings();

		$this->title   = $this->get_option( 'title' );
		$this->enabled = $this->get_option( 'enabled' );
		$this->cost    = $this->get_option( 'cost', '0' );

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Define per-zone-instance settings fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->instance_form_fields = array(
			'title' => array(
				'title'   => __( 'Method Title', 'flash-sale-stock-guard-for-woocommerce' ),
				'type'    => 'text',
				'default' => __( 'Flash Sale Stock Guard for WooCommerce Shipping', 'flash-sale-stock-guard-for-woocommerce' ),
			),
			'cost'  => array(
				'title'       => __( 'Cost', 'flash-sale-stock-guard-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Flat shipping cost.', 'flash-sale-stock-guard-for-woocommerce' ),
				'default'     => '0',
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Calculate the shipping cost for the current package.
	 *
	 * @param array $package Shipping package (unused in this flat-rate stub).
	 * @return void
	 */
	public function calculate_shipping( $package = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- required by WC_Shipping_Method signature.
		$this->add_rate(
			array(
				'id'    => $this->get_rate_id(),
				'label' => $this->title,
				'cost'  => $this->cost,
			)
		);
	}
}
