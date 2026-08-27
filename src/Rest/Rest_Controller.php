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

		$session_id = $this->resolve_session_id();

		if ( '' === $session_id ) {
			return new \WP_REST_Response( array( 'active' => false ), 200 );
		}

		$seconds = $this->repository->get_seconds_remaining( $session_id );

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

	/**
	 * The cart identity the hold rows are keyed on.
	 *
	 * The countdown request runs without a nonce (see Hold_Repository), so
	 * WordPress has already reset it to user 0 by the time this runs.
	 *
	 * For a logged-in customer the hold is stored under their numeric user ID.
	 * wp_validate_auth_cookie() re-reads and cryptographically checks the login
	 * cookie, so it can't be forged, and it hands back that ID even on this
	 * nominally unauthenticated request. Doing it this way keeps
	 * WC_Session_Handler out of the picture for them entirely: an
	 * unauthenticated init() treats the logged-in session cookie as a stale
	 * logged-out one, calls destroy_session() — which runs wc_empty_cart() —
	 * and returns a throwaway guest hash. That emptied the customer's cart on
	 * every 30-second poll and never matched a hold.
	 *
	 * A guest only ever has the WooCommerce session cookie, and its `t_` id
	 * passes that same validity check, so booting the handler is safe there. A
	 * non-guest id we couldn't authenticate above is a logged-in customer with
	 * an expired login cookie — bail rather than let init() destroy it.
	 *
	 * @return string Session id, or '' when it can't be resolved.
	 */
	private function resolve_session_id(): string {
		$user_id = wp_validate_auth_cookie( '', 'logged_in' );

		if ( $user_id ) {
			return (string) $user_id;
		}

		$cookie_name = 'wp_woocommerce_session_' . COOKIEHASH;

		if ( ! empty( $_COOKIE[ $cookie_name ] ) ) {
			$raw = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) );
			$id  = explode( '|', str_replace( '||', '|', $raw ) )[0];

			if ( '' !== $id && 0 !== strpos( $id, 't_' ) ) {
				return '';
			}
		}

		if ( ! WC()->session ) {
			if ( ! class_exists( 'WC_Session_Handler' ) ) {
				return '';
			}

			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own filter, not one of ours.
			$session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
			WC()->session  = new $session_class();
			WC()->session->init();
		}

		return (string) WC()->session->get_customer_id();
	}
}
