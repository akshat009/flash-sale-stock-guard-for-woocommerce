<?php
/**
 * WooCommerce Blocks (Cart & Checkout) content integration for Flash Sale Stock Guard for WooCommerce.
 *
 * Injects custom content into the Cart and Checkout blocks' order-summary
 * sidebar via the ExperimentalOrderMeta slot — a stable extension point
 * shared by both blocks. Registered against both
 * woocommerce_blocks_cart_block_registration and
 * woocommerce_blocks_checkout_block_registration in Woo_Hooks.
 *
 * @package FlashSaleStockGuardWooCommerce\Woo\Blocks
 */

declare(strict_types=1);

namespace FlashSaleStockGuardWooCommerce\Woo\Blocks;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Integration.
 */
class Integration implements IntegrationInterface {

	/**
	 * Unique integration name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'fssgw-blocks-integration';
	}

	/**
	 * Register the frontend/editor script.
	 *
	 * @return void
	 */
	public function initialize() {
		$asset_file = FSSGW_PATH . 'assets/build/blocks-integration.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: array(
				'dependencies' => array(),
				'version'      => FSSGW_VERSION,
			);

		// Uses window.wc.* / window.wp.* globals rather than ES imports (see
		// assets/src/blocks-integration.js), so declare dependencies by hand.
		$dependencies = array_unique(
			array_merge(
				$asset['dependencies'],
				array( 'wc-blocks-checkout', 'wc-settings', 'wp-plugins', 'wp-element', 'wp-i18n' )
			)
		);

		wp_register_script(
			$this->get_name(),
			FSSGW_URL . 'assets/build/blocks-integration.js',
			$dependencies,
			$asset['version'],
			true
		);
	}

	/**
	 * Script handles required on the frontend (Cart/Checkout page).
	 *
	 * @return array
	 */
	public function get_script_handles() {
		return array( $this->get_name() );
	}

	/**
	 * Script handles required in the block editor.
	 *
	 * @return array
	 */
	public function get_editor_script_handles() {
		return array( $this->get_name() );
	}

	/**
	 * Data passed from PHP into the script (window.wc.wcSettings.getSetting()).
	 *
	 * @return array
	 */
	public function get_script_data() {
		return array(
			'message' => __( 'Custom content injected by Flash Sale Stock Guard for WooCommerce — replace this with your own.', 'flash-sale-stock-guard-for-woocommerce' ),
		);
	}
}
