<?php
/**
 * REST endpoint powering the cart countdown.
 *
 * @package FlashSaleStockGuardWooCommerce\Rest
 */

declare(strict_types=1);

namespace FlashSaleStockGuardWooCommerce\Rest;

use FlashSaleStockGuardWooCommerce\Contracts\Service_Provider;
use FlashSaleStockGuardWooCommerce\Core\Container;
use FlashSaleStockGuardWooCommerce\Database\Hold_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Rest_Controller.
 *
 * Read-only. Returns the seconds remaining on the current cart's earliest
 * hold, so the frontend timer stays honest across tabs and page reloads
 * instead of counting down from a value baked into the HTML.
 */
class Rest_Controller implements Service_Provider {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	public const NAMESPACE_V1 = 'fssgw/v1';

	/**
	 * Route path.
	 *
	 * @var string
	 */
	public const ROUTE = '/hold-status';

	/**
	 * Hold data access.
	 *
	 * @var Hold_Repository
	 */
	private Hold_Repository $repository;

	/**
	 * Constructor.
	 *
	 * @param Hold_Repository|null $repository Injected for testing.
	 */
	public function __construct( ?Hold_Repository $repository = null ) {
		$this->repository = $repository ?? new Hold_Repository();
	}

	/**
	 * No container bindings needed.
	 *
	 * @param Container $container Application container.
	 * @return void
	 */
	public function register( Container $container ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	}

	/**
	 * Register REST routes.
	 *
	 * @param Container $container Application container.
	 * @return void
	 */
	public function boot( Container $container ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the status route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE_V1,
			self::ROUTE,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Seconds remaining on the earliest-expiring active hold.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_status(): \WP_REST_Response {
		if ( ! function_exists( 'WC' ) ) {
			return new \WP_REST_Response( array( 'active' => false ), 200 );
		}

		// WooCommerce doesn't boot the session for arbitrary REST routes. Only
		// the session is needed here — deliberately not wc_load_cart(), which
		// also constructs WC()->cart. That cart loads its contents on the
		// `wp_loaded` hook, already past by the time a REST route runs, so it
		// stays empty — and WooCommerce then "cleans up" what looks like an
		// abandoned cart by deleting the visitor's real session and cart
		// cookies out from under them.
		if ( ! WC()->session ) {
			if ( ! class_exists( 'WC_Session_Handler' ) ) {
				return new \WP_REST_Response( array( 'active' => false ), 200 );
			}

			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own filter, not one of ours.
			$session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
			WC()->session  = new $session_class();
			WC()->session->init();
		}

		$seconds = $this->repository->get_seconds_remaining(
			(string) WC()->session->get_customer_id()
		);

		if ( null === $seconds ) {
			return new \WP_REST_Response( array( 'active' => false ), 200 );
		}

		return new \WP_REST_Response(
			array(
				'active'  => true,
				'seconds' => $seconds,
			),
			200
		);
	}
}
