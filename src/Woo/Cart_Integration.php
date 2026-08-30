<?php
/**
 * Wires WooCommerce cart/checkout events to stock holds.
 *
 * @package FlashSaleStockGuardWooCommerce\Woo
 */

declare(strict_types=1);

namespace FlashSaleStockGuardWooCommerce\Woo;

use FlashSaleStockGuardWooCommerce\Contracts\Service_Provider;
use FlashSaleStockGuardWooCommerce\Core\Container;
use FlashSaleStockGuardWooCommerce\Database\Hold_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cart_Integration.
 *
 * Hold lifecycle:
 *  - add to cart / quantity change -> release old, hold new quantity.
 *    Release-then-hold keeps a single source of truth and handles
 *    WooCommerce merging quantities when the same product is added twice,
 *    instead of tracking per-cart-item deltas in three places.
 *  - remove / empty -> release.
 *  - order created -> convert (stops expiring, tied to the order).
 *  - order cancelled/failed/refunded -> release by order.
 *  - before payment -> re-validate, silently re-acquire where possible.
 */
class Cart_Integration implements Service_Provider {

	/**
	 * Hold data access.
	 *
	 * @var Hold_Repository|null
	 */
	private ?Hold_Repository $repository = null;
	/**
	 * Cart contents captured before WooCommerce empties them, so the
	 * emptied hook still knows what to release.
	 *
	 * @var array
	 */
	private array $emptying_contents = array();

	/**
	 * Guards against printing the shop-landing expiry banner more than once
	 * when several theme hooks fire on the same request.
	 *
	 * @var bool
	 */
	private bool $expiry_banner_done = false;


	/**
	 * Bind the repository so other providers resolve the same instance.
	 *
	 * @param Container $container Application container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton( Hold_Repository::class, static fn () => new Hold_Repository() );
	}

	/**
	 * Register WooCommerce hooks.
	 *
	 * @param Container $container Application container.
	 * @return void
	 */
	public function boot( Container $container ): void {
		$this->repository = $container->get( Hold_Repository::class );

		add_action( 'woocommerce_add_to_cart', array( $this, 'on_add_to_cart' ), 10, 6 );
		add_action( 'woocommerce_cart_item_removed', array( $this, 'on_cart_item_removed' ), 10, 2 );
		add_action( 'woocommerce_cart_item_restored', array( $this, 'on_cart_item_restored' ), 10, 2 );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'on_quantity_update' ), 10, 4 );
		add_action( 'woocommerce_before_cart_emptied', array( $this, 'on_before_cart_emptied' ) );
		add_action( 'woocommerce_cart_emptied', array( $this, 'on_cart_emptied' ) );
		add_action( 'woocommerce_check_cart_items', array( $this, 'on_check_cart_items' ) );
		add_action( 'template_redirect', array( $this, 'on_expired_landing' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_expiry_assets' ) );
		add_action( 'wp_body_open', array( $this, 'render_expiry_banner' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_order_processed' ), 10, 3 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_order_status_changed' ), 10, 4 );

		add_filter( 'woocommerce_product_get_stock_quantity', array( $this, 'filter_stock_quantity' ), 10, 2 );
		add_filter( 'woocommerce_variation_get_stock_quantity', array( $this, 'filter_stock_quantity' ), 10, 2 );
	}

	/**
	 * Hold stock when an item is added to the cart.
	 *
	 * @param string $cart_item_key  Cart item key.
	 * @param int    $product_id     Product ID.
	 * @param int    $quantity       Quantity just added.
	 * @param int    $variation_id   Variation ID, or 0.
	 * @param array  $variation      Variation attributes.
	 * @param array  $cart_item_data Extra cart item data.
	 * @return void
	 */
	public function on_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ): void {
		unset( $variation, $cart_item_data );

		$product_id   = (int) $product_id;
		$variation_id = (int) $variation_id;

		if ( ! $this->should_guard( $product_id, $variation_id ) || ! $this->has_cart() ) {
			return;
		}

		$cart_item = WC()->cart->get_cart_item( $cart_item_key );

		// Use the cart's merged quantity, not the amount just added — adding
		// the same product twice produces one line with a combined quantity.
		$total_quantity = $cart_item ? (int) $cart_item['quantity'] : (int) $quantity;

		$session_id = $this->get_session_id();
		$user_id    = get_current_user_id();

		$this->repository->release( $product_id, $variation_id, $session_id, $user_id );

		$result = $this->repository->reserve(
			$product_id,
			$variation_id,
			$total_quantity,
			$session_id,
			$user_id,
			$this->get_ttl_seconds( $product_id, $variation_id )
		);

		if ( is_wp_error( $result ) ) {
			WC()->cart->remove_cart_item( $cart_item_key );
			wc_add_notice( $result->get_error_message(), 'error' );
		}
	}

	/**
	 * Release when an item is removed.
	 *
	 * @param string   $cart_item_key Cart item key.
	 * @param \WC_Cart $cart          Cart instance.
	 * @return void
	 */
	public function on_cart_item_removed( $cart_item_key, $cart ): void {
		$cart_item = $cart->removed_cart_contents[ $cart_item_key ] ?? null;

		if ( ! $cart_item ) {
			return;
		}

		$product_id   = (int) $cart_item['product_id'];
		$variation_id = (int) ( $cart_item['variation_id'] ?? 0 );

		if ( ! $this->should_guard( $product_id, $variation_id ) ) {
			return;
		}

		$this->repository->release( $product_id, $variation_id, $this->get_session_id(), get_current_user_id() );
	}

	/**
	 * Re-hold when a removal is undone.
	 *
	 * @param string   $cart_item_key Cart item key.
	 * @param \WC_Cart $cart          Cart instance.
	 * @return void
	 */
	public function on_cart_item_restored( $cart_item_key, $cart ): void {
		$cart_item = $cart->cart_contents[ $cart_item_key ] ?? null;

		if ( ! $cart_item ) {
			return;
		}

		$product_id   = (int) $cart_item['product_id'];
		$variation_id = (int) ( $cart_item['variation_id'] ?? 0 );

		if ( ! $this->should_guard( $product_id, $variation_id ) ) {
			return;
		}

		$result = $this->repository->reserve(
			$product_id,
			$variation_id,
			(int) $cart_item['quantity'],
			$this->get_session_id(),
			get_current_user_id(),
			$this->get_ttl_seconds( $product_id, $variation_id )
		);

		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );
		}
	}

	/**
	 * Re-hold at the new quantity.
	 *
	 * @param string   $cart_item_key Cart item key.
	 * @param int      $quantity      New quantity.
	 * @param int      $old_quantity  Previous quantity.
	 * @param \WC_Cart $cart          Cart instance.
	 * @return void
	 */
	public function on_quantity_update( $cart_item_key, $quantity, $old_quantity, $cart ): void {
		$cart_item = $cart->get_cart_item( $cart_item_key );

		if ( ! $cart_item ) {
			return;
		}

		$product_id   = (int) $cart_item['product_id'];
		$variation_id = (int) ( $cart_item['variation_id'] ?? 0 );

		if ( ! $this->should_guard( $product_id, $variation_id ) ) {
			return;
		}

		$session_id = $this->get_session_id();
		$user_id    = get_current_user_id();
		$ttl        = $this->get_ttl_seconds( $product_id, $variation_id );

		$this->repository->release( $product_id, $variation_id, $session_id, $user_id );

		$result = $this->repository->reserve( $product_id, $variation_id, (int) $quantity, $session_id, $user_id, $ttl );

		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );

			// Not enough stock for the new quantity — revert the cart and
			// restore the hold at the old quantity so the two stay in sync.
			$cart->set_quantity( $cart_item_key, $old_quantity, false );
			$this->repository->reserve( $product_id, $variation_id, (int) $old_quantity, $session_id, $user_id, $ttl );
		}
	}

		/**
		 * Snapshot the cart before WooCommerce clears it.
		 *
		 * `woocommerce_cart_emptied` fires *after* the contents are gone, so
		 * reading the cart there returns nothing — the holds would be left
		 * active and the countdown would keep running on an empty cart.
		 *
		 * @return void
		 */
	public function on_before_cart_emptied(): void {
		$this->emptying_contents = $this->has_cart() ? WC()->cart->get_cart() : array();
	}

	/**
	 * Release everything the cart held when it was emptied.
	 *
	 * @return void
	 */
	public function on_cart_emptied(): void {
		$session_id = $this->get_session_id();
		$user_id    = get_current_user_id();

		foreach ( $this->emptying_contents as $cart_item ) {
			$product_id   = (int) ( $cart_item['product_id'] ?? 0 );
			$variation_id = (int) ( $cart_item['variation_id'] ?? 0 );

			if ( ! $product_id || ! $this->should_guard( $product_id, $variation_id ) ) {
				continue;
			}

			$this->repository->release( $product_id, $variation_id, $session_id, $user_id );
		}

		$this->emptying_contents = array();
	}

	/**
	 * Drop lapsed guarded lines whenever the customer is on or acting on their
	 * cart. `woocommerce_check_cart_items` fires as the cart and checkout pages
	 * render and again when an order is submitted (classic, AJAX and the Store
	 * API all trigger it).
	 *
	 * @return void
	 */
	public function on_check_cart_items(): void {
		$this->purge_lapsed_holds();
	}

	/**
	 * Same purge when the customer lands back on the shop after the countdown
	 * dialog sent them there (`?fssgw-expired=1`), so the mini-cart drops the
	 * released item right away instead of only on the next cart view. No
	 * per-line notice here — the dismissable banner (see render_expiry_banner)
	 * carries the message on that page instead.
	 *
	 * @return void
	 */
	public function on_expired_landing(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presence flag only; the purge is idempotent and acts solely on the caller's own cart against live hold state.
		if ( empty( $_GET['fssgw-expired'] ) ) {
			return;
		}

		$this->purge_lapsed_holds( false );
	}

	/**
	 * A dismissable "reservation expired" bar for the page the countdown dialog
	 * redirects to. WooCommerce's own notices can't be closed without reloading,
	 * and the shop page isn't somewhere a customer expects to reload — so this
	 * one has an explicit close button and doesn't come back.
	 *
	 * Hooked to `wp_body_open` (fired by every current theme, block or classic),
	 * so it sits as a full-width bar at the top of the page. The flag keeps it
	 * to one copy.
	 *
	 * @return void
	 */
	public function render_expiry_banner(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presence flag only, nothing is changed here.
		if ( $this->expiry_banner_done || empty( $_GET['fssgw-expired'] ) ) {
			return;
		}

		$this->expiry_banner_done = true;

		$message = __( 'Your reservation expired — the held items have been released.', 'flash-sale-stock-guard-for-woocommerce' );
		$dismiss = __( 'Dismiss', 'flash-sale-stock-guard-for-woocommerce' );

		// Styling and the dismiss handler live in assets/css|js/expiry-banner.*,
		// enqueued by enqueue_expiry_assets() on this same request.
		?>
		<div id="fssgw-expiry-banner" class="fssgw-expiry-banner" role="status">
			<?php echo esc_html( $message ); ?>
			<button type="button" id="fssgw-expiry-banner-close" class="fssgw-expiry-banner__close" aria-label="<?php echo esc_attr( $dismiss ); ?>">&times;</button>
		</div>
		<?php
	}

	/**
	 * Enqueue the expiry-banner stylesheet and dismiss script, but only on the
	 * shop-landing request the countdown dialog redirects to
	 * (`?fssgw-expired=1`) — the banner is rendered nowhere else.
	 *
	 * @return void
	 */
	public function enqueue_expiry_assets(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presence flag only, nothing is read from the value and nothing is changed.
		if ( empty( $_GET['fssgw-expired'] ) ) {
			return;
		}

		wp_enqueue_style(
			'fssgw-expiry-banner',
			FSSGW_URL . 'assets/css/expiry-banner.css',
			array(),
			FSSGW_VERSION
		);

		wp_enqueue_script(
			'fssgw-expiry-banner',
			FSSGW_URL . 'assets/js/expiry-banner.js',
			array(),
			FSSGW_VERSION,
			true
		);
	}

	/**
	 * Remove every guarded cart line that no longer has an active hold covering
	 * it, leaving a notice in its place.
	 *
	 * A hold that has run out means the customer's reserved window closed — the
	 * countdown told them so — so the item leaves the cart rather than silently
	 * reverting to unguarded stock they could still lose at payment. Only their
	 * own cart is touched, and only on a real request they made, so this never
	 * mutates a session out from under anyone. Re-acquiring the hold here
	 * instead was tried and dropped: it fires on plain page views, which kept
	 * abandoned carts held forever and restarted the countdown on every reload.
	 *
	 * @param bool $notify Whether to leave a WooCommerce notice per removed line.
	 *                     The shop-landing path passes false and shows its own
	 *                     dismissable banner instead.
	 * @return void
	 */
	private function purge_lapsed_holds( bool $notify = true ): void {
		if ( ! $this->has_cart() ) {
			return;
		}

		$session_id = $this->get_session_id();
		$user_id    = get_current_user_id();

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$product = $cart_item['data'];

			if ( ! $product || ! $product->managing_stock() ) {
				continue;
			}

			$product_id   = (int) $cart_item['product_id'];
			$variation_id = (int) ( $cart_item['variation_id'] ?? 0 );
			$quantity     = (int) $cart_item['quantity'];

			if ( ! $this->should_guard( $product_id, $variation_id ) ) {
				continue;
			}

			if ( $this->repository->has_active_hold( $product_id, $variation_id, $session_id, $user_id, $quantity ) ) {
				continue;
			}

			WC()->cart->remove_cart_item( $cart_item_key );

			if ( $notify ) {
				wc_add_notice(
					sprintf(
						/* translators: %s: product name. */
						__( 'Your hold on “%s” expired, so it was removed from your cart.', 'flash-sale-stock-guard-for-woocommerce' ),
						$product->get_name()
					),
					'notice'
				);
			}
		}
	}

	/**
	 * Convert to an order-backed hold once the order exists.
	 *
	 * @param int       $order_id    Order ID.
	 * @param array     $posted_data Posted checkout data.
	 * @param \WC_Order $order       Order object.
	 * @return void
	 */
	public function on_order_processed( $order_id, $posted_data, $order ): void {
		unset( $posted_data, $order );

		$this->repository->convert_to_order( $this->get_session_id(), get_current_user_id(), (int) $order_id );
	}

	/**
	 * Release when an order fails, is cancelled, or refunded — otherwise a
	 * converted hold, which no longer expires, would sit on stock forever.
	 *
	 * @param int       $order_id   Order ID.
	 * @param string    $old_status Previous status.
	 * @param string    $new_status New status.
	 * @param \WC_Order $order      Order object.
	 * @return void
	 */
	public function on_order_status_changed( $order_id, $old_status, $new_status, $order ): void {
		unset( $old_status, $order );

		/**
		 * Filter which order statuses release a converted hold.
		 *
		 * @param array $statuses Order status slugs.
		 */
		$release_statuses = (array) apply_filters( 'fssgw_release_statuses', array( 'cancelled', 'failed', 'refunded' ) );

		if ( in_array( $new_status, $release_statuses, true ) ) {
			$this->repository->release_by_order( (int) $order_id );
		}
	}

	/**
	 * Subtract held units from displayed stock.
	 *
	 * @param mixed              $value   Stock quantity, or null if unmanaged.
	 * @param \WC_Product|object $product Product object.
	 * @return mixed
	 */
	public function filter_stock_quantity( $value, $product ) {
		if ( null === $value || ! $product ) {
			return $value;
		}

		$is_variation = method_exists( $product, 'is_type' ) && $product->is_type( 'variation' );
		$product_id   = $is_variation ? (int) $product->get_parent_id() : (int) $product->get_id();
		$variation_id = $is_variation ? (int) $product->get_id() : 0;

		if ( ! $this->should_guard( $product_id, $variation_id ) ) {
			return $value;
		}

		return max( 0, (int) $value - $this->repository->get_held_quantity( $product_id, $variation_id ) );
	}

	/**
	 * Whether this request should act: plugin enabled, repository available,
	 * and the product opted in.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID, or 0.
	 * @return bool
	 */
	private function should_guard( int $product_id, int $variation_id ): bool {
		if ( ! $this->repository || ! get_option( 'fssgw_enabled', true ) ) {
			return false;
		}

		return Product_Setting::is_guarded( $product_id, $variation_id );
	}

	/**
	 * Whether a usable cart exists on this request.
	 *
	 * @return bool
	 */
	private function has_cart(): bool {
		return function_exists( 'WC' ) && WC()->cart;
	}

	/**
	 * Stable per-session identity, guest or logged in.
	 *
	 * @return string
	 */
	private function get_session_id(): string {
		if ( function_exists( 'WC' ) && WC()->session ) {
			return (string) WC()->session->get_customer_id();
		}

		return '';
	}

	/**
	 * Hold lifetime in seconds.
	 *
	 * A single global duration by design — store owners adjust it directly
	 * when a drop needs a shorter or longer window. The filter is the escape
	 * hatch for stores that want it varied programmatically.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID, or 0.
	 * @return int
	 */
	private function get_ttl_seconds( int $product_id, int $variation_id ): int {
		$ttl = (int) get_option( 'fssgw_hold_ttl', 15 * MINUTE_IN_SECONDS );

		/**
		 * Filter the hold lifetime, optionally per product.
		 *
		 * @param int $ttl          Seconds.
		 * @param int $product_id   Product ID.
		 * @param int $variation_id Variation ID, or 0.
		 */
		return (int) apply_filters( 'fssgw_hold_ttl', $ttl, $product_id, $variation_id );
	}
}
