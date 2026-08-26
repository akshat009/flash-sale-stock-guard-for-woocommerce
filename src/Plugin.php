<?php
/**
 * Main Plugin Composition Root.
 *
 * @package FlashSaleStockGuardWooCommerce
 */

declare(strict_types=1);

namespace FlashSaleStockGuardWooCommerce;

use FlashSaleStockGuardWooCommerce\Core\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Composition root for Flash Sale Stock Guard for WooCommerce.
 *
 * Holds the application Container and the list of Service_Provider instances,
 * and knows how to run them. This is intentionally NOT a singleton: build one
 * with create() at runtime, or construct one directly with a fake container
 * and provider list in tests.
 */
final class Plugin {

	/**
	 * Application container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Registered Service_Provider instances (unfiltered, unconditioned).
	 *
	 * @var array<int, Contracts\Service_Provider>
	 */
	private array $providers;

	/**
	 * Constructor.
	 *
	 * @param Container $container Application container.
	 * @param array     $providers Service_Provider instances to run.
	 */
	public function __construct( Container $container, array $providers ) {
		$this->container = $container;
		$this->providers = $providers;
	}

	/**
	 * Build the real Plugin for this request: a fresh Container plus every
	 * selected module's provider.
	 *
	 * @return self
	 */
	public static function create(): self {
		$container = new Container();
		$providers = array();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$providers[] = new CLI\Commands();
		}

		$providers[] = new Admin\Settings_Registrar();

		$providers[] = new Rest\Rest_Controller();

		$providers[] = new Cron\Scheduler();

		$providers[] = new Database\Schema();

		$providers[] = new Woo\Providers\Gateway_Provider();

		$providers[] = new Woo\Providers\Shipping_Provider();

		$providers[] = new Woo\Providers\Email_Provider();

		$providers[] = new Woo\Providers\Product_Type_Provider();

		$providers[] = new Woo\Providers\Blocks_Provider();

		return new self( $container, $providers );
	}

	/**
	 * Get the application container.
	 *
	 * @return Container
	 */
	public function get_container(): Container {
		return $this->container;
	}

	/**
	 * Get the registered providers (unfiltered, unconditioned).
	 *
	 * @return array<int, Contracts\Service_Provider>
	 */
	public function get_providers(): array {
		return $this->providers;
	}

	/**
	 * Run only the register() pass on every active provider.
	 *
	 * Used by the activation/deactivation bridge in the main plugin file,
	 * which needs bindings available (e.g. so Activator can resolve a
	 * service from the container) without booting WordPress hooks that
	 * make no sense to fire during activation, and without running the
	 * 'fssgw_providers' filter (third-party filter callbacks aren't
	 * reliably available that early).
	 *
	 * @return void
	 */
	public function register_all(): void {
		foreach ( $this->active_providers( $this->providers ) as $provider ) {
			$provider->register( $this->container );
		}
	}

	/**
	 * Register and boot every active provider for a normal request.
	 *
	 * @return void
	 */
	public function boot(): void {
		/**
		 * Filter the providers to be registered and booted.
		 *
		 * @param array $providers Array of Service_Provider instances.
		 */
		$providers = apply_filters( 'fssgw_providers', $this->providers );

		foreach ( $this->active_providers( is_array( $providers ) ? $providers : $this->providers ) as $provider ) {
			$provider->register( $this->container );
			$provider->boot( $this->container );
		}
	}

	/**
	 * Filter a provider list down to the ones that should actually run:
	 * must implement Service_Provider, and if it also implements
	 * Conditional, is_needed() must return true.
	 *
	 * @param array $providers Candidate provider list (e.g. straight from
	 *                         the constructor, or from the 'fssgw_providers' filter).
	 * @return array<int, Contracts\Service_Provider>
	 */
	private function active_providers( array $providers ): array {
		$active = array();

		foreach ( $providers as $provider ) {
			if ( ! $provider instanceof Contracts\Service_Provider ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					_doing_it_wrong(
						__METHOD__,
						esc_html__( 'Every entry filtered into the providers list must implement Service_Provider.', 'flash-sale-stock-guard-for-woocommerce' ),
						'1.0.0'
					);
				}
				continue;
			}

			if ( $provider instanceof Contracts\Conditional && ! $provider->is_needed() ) {
				continue;
			}

			$active[] = $provider;
		}

		return $active;
	}
}
