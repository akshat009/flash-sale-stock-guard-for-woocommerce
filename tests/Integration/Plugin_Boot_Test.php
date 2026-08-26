<?php
/**
 * Integration test: boots the plugin against a real WordPress test environment.
 *
 * @package FlashSaleStockGuardWooCommerce\Tests\Integration
 */

declare(strict_types=1);

namespace FlashSaleStockGuardWooCommerce\Tests\Integration;

use FlashSaleStockGuardWooCommerce\Plugin;
use FlashSaleStockGuardWooCommerce\Contracts\Service_Provider;
use FlashSaleStockGuardWooCommerce\Core\Container;
use FlashSaleStockGuardWooCommerce\Core\Activator;
use WP_UnitTestCase;

/**
 * Class Plugin_Boot_Test.
 *
 * Unlike tests/Unit/Example_Test.php (Brain Monkey — WordPress functions are
 * stubs), this runs against a real, if minimal, WordPress install:
 * register_post_type(), get_option(), and add_filter() below are the genuine
 * WordPress Core implementations, not test doubles. Use this file as the
 * starting point for tests that need real WordPress behavior (taxonomy
 * term relationships, real option persistence, real REST dispatch, etc.)
 * that Brain Monkey can't meaningfully fake.
 */
class Plugin_Boot_Test extends WP_UnitTestCase {

	/**
	 * A provider added via the 'fssgw_providers' filter should be
	 * booted exactly like one built into Plugin::create().
	 *
	 * @return void
	 */
	public function test_plugin_boots_providers_added_via_the_providers_filter(): void {
		$probe = new class() implements Service_Provider {
			/**
			 * Whether boot() ran.
			 *
			 * @var bool
			 */
			public bool $booted = false;

			/**
			 * No bindings needed.
			 *
			 * @param Container $container Application container.
			 * @return void
			 */
			public function register( Container $container ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
			}

			/**
			 * Record that boot() ran.
			 *
			 * @param Container $container Application container.
			 * @return void
			 */
			public function boot( Container $container ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
				$this->booted = true;
			}
		};

		add_filter(
			'fssgw_providers',
			function ( $providers ) use ( $probe ) {
				$providers[] = $probe;
				return $providers;
			}
		);

		Plugin::create()->boot();

		$this->assertTrue( $probe->booted );
	}

	/**
	 * Activation should persist the current version through a real get_option()/update_option() round trip.
	 *
	 * @return void
	 */
	public function test_activation_persists_the_version_option(): void {
		( new Activator() )->activate( new Container() );

		$this->assertSame( FSSGW_VERSION, get_option( 'fssgw_version' ) );
	}
}
