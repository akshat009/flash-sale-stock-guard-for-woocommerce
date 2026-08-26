<?php
/**
 * PHPUnit Bootstrap file.
 *
 * @package FlashSaleStockGuardWooCommerce\Tests
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Define WordPress constants for unit testing if not defined.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}
if ( ! defined( 'FSSGW_VERSION' ) ) {
	define( 'FSSGW_VERSION', '1.0.0' );
}
if ( ! defined( 'FSSGW_FILE' ) ) {
	define( 'FSSGW_FILE', dirname( __DIR__ ) . '/flash-sale-stock-guard-for-woocommerce.php' );
}
