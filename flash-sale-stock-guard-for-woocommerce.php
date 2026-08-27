<?php
/**
 * Plugin Name:       Flash Sale Stock Guard for WooCommerce
 * Plugin URI:        https://github.com/akshat009/flash-sale-stock-guard-for-woocommerce
 * Description:       Stop overselling during flash sales and limited drops. Locks stock at add-to-cart on the products you choose, with an optional countdown.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Akshat Saxena
 * Author URI:        https://github.com/akshat009
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flash-sale-stock-guard-for-woocommerce
 * Domain Path:       /languages
 * Requires Plugins: woocommerce
 *
 * @package FlashSaleStockGuardWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FSSGW_VERSION', '1.0.0' );
define( 'FSSGW_FILE', __FILE__ );
define( 'FSSGW_PATH', plugin_dir_path( __FILE__ ) );
define( 'FSSGW_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoload classes via PSR-4 with graceful fallback.
 */
if ( file_exists( FSSGW_PATH . 'vendor/autoload.php' ) ) {
	require_once FSSGW_PATH . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		function ( $class_name ) {
			$prefix   = 'FlashSaleStockGuardWooCommerce\\';
			$base_dir = FSSGW_PATH . 'src/';
			$len      = strlen( $prefix );

			if ( 0 !== strncmp( $prefix, $class_name, $len ) ) {
				return;
			}

			$relative_class = substr( $class_name, $len );
			$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	);
}

register_activation_hook(
	__FILE__,
	static function () {
		$plugin = \FlashSaleStockGuardWooCommerce\Plugin::create();
		$plugin->register_all();
		( new \FlashSaleStockGuardWooCommerce\Core\Activator() )->activate( $plugin->get_container() );
	}
);
register_deactivation_hook(
	__FILE__,
	static function () {
		$plugin = \FlashSaleStockGuardWooCommerce\Plugin::create();
		$plugin->register_all();
		( new \FlashSaleStockGuardWooCommerce\Core\Deactivator() )->deactivate( $plugin->get_container() );
	}
);
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', FSSGW_FILE, true );
		}
	}
);


/**
 * Bootstrap the plugin orchestrator.
 *
 * @return void
 */
function fssgw_boot() {
	load_plugin_textdomain( 'flash-sale-stock-guard-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	\FlashSaleStockGuardWooCommerce\Plugin::create()->boot();
}

add_action( 'plugins_loaded', 'fssgw_boot' );
