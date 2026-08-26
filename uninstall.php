<?php
/**
 * Uninstall Handler.
 *
 * Runs when the plugin is deleted via the WordPress Admin dashboard. WordPress
 * invokes this file standalone — the main plugin file is never loaded — so it
 * needs its own autoloader rather than relying on plugin-main.php's constants.
 *
 * @package FlashSaleStockGuardWooCommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Autoload classes via PSR-4 with graceful fallback (same convention as plugin-main.php).
 */
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	spl_autoload_register(
		function ( $class_name ) {
			$prefix   = 'FlashSaleStockGuardWooCommerce\\';
			$base_dir = __DIR__ . '/src/';
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

/**
 * Perform uninstall cleanup tasks for the current site.
 *
 * @return void
 */
function fssgw_uninstall_cleanup(): void {
	( new \FlashSaleStockGuardWooCommerce\Core\Uninstaller() )->cleanup();
}

if ( is_multisite() ) {
	$fssgw_sites = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $fssgw_sites as $fssgw_site_id ) {
		switch_to_blog( $fssgw_site_id );
		fssgw_uninstall_cleanup();
		restore_current_blog();
	}
} else {
	fssgw_uninstall_cleanup();
}
