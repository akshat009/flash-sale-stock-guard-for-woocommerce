<?php
/**
 * Activatable contract interface.
 *
 * @package FlashSaleStockGuardWooCommerce\Contracts
 */

declare(strict_types=1);

namespace FlashSaleStockGuardWooCommerce\Contracts;

use FlashSaleStockGuardWooCommerce\Core\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface Activatable
 *
 * Contract for the class run on plugin activation.
 */
interface Activatable {

	/**
	 * Run activation tasks.
	 *
	 * @param Container $container Application container.
	 * @return void
	 */
	public function activate( Container $container ): void;
}
