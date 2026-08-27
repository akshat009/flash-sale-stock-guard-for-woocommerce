/**
 * Cart hold countdown.
 *
 * Ticks locally for smoothness but re-syncs with the server every 30 seconds,
 * so a tab left open in the background — where browsers throttle timers —
 * can't drift into showing time the customer doesn't actually have.
 */
( function () {
	'use strict';

	var config = window.fssgwCartTimer;

	if ( ! config || ! config.endpoint ) {
		return;
	}

	/**
	 * Find the timer container, creating it if the page didn't render one.
	 *
	 * WooCommerce's block-based Cart and Checkout don't fire the legacy
	 * `woocommerce_before_cart` action, so the PHP-side container is absent
	 * there. Creating it here keeps one code path working on both classic and
	 * block carts, instead of maintaining a separate slot-fill integration.
	 */
	function resolveContainer() {
		var existing = document.querySelector( '[data-fssgw-timer]' );

		if ( existing ) {
			return existing;
		}

		var anchor = document.querySelector(
			'.wp-block-woocommerce-cart, .wp-block-woocommerce-checkout, .woocommerce-cart-form, .woocommerce-checkout, .woocommerce'
		);

		if ( ! anchor || ! anchor.parentNode ) {
			return null;
		}

		var created = document.createElement( 'div' );
		created.className = 'woocommerce-info fssgw-cart-timer';
		created.setAttribute( 'data-fssgw-timer', '' );
		created.hidden = true;

		anchor.parentNode.insertBefore( created, anchor );

		return created;
	}

	var container = resolveContainer();

	if ( ! container ) {
		return;
	}

	var remaining = null;
	var reloaded = false;

	function format( seconds ) {
		var mins = Math.floor( seconds / 60 );
		var secs = seconds % 60;

		return mins + ':' + ( secs < 10 ? '0' : '' ) + secs;
	}

	function paint() {
		if ( remaining === null ) {
			container.hidden = true;
			return;
		}

		if ( remaining <= 0 ) {
			container.textContent = config.i18n.expired;
			container.hidden = false;

			if ( ! reloaded ) {
				reloaded = true;
				// Reload once so cart totals and stock messages catch up.
				window.setTimeout( function () {
					window.location.reload();
				}, 1500 );
			}

			return;
		}

		container.textContent = config.i18n.held.replace( '%s', format( remaining ) );
		container.hidden = false;
	}

	function tick() {
		if ( remaining === null ) {
			return;
		}

		remaining = Math.max( 0, remaining - 1 );
		paint();
	}

	function sync() {
		fetch( config.endpoint, {
			credentials: 'same-origin'
		} )
			.then( function ( response ) {
				return response.ok ? response.json() : null;
			} )
			.then( function ( data ) {
				if ( ! data || ! data.active ) {
					remaining = null;
					paint();
					return;
				}

				remaining = data.seconds;
				paint();
			} )
			.catch( function () {
				// Network hiccup — keep counting locally, next sync corrects.
			} );
	}

	sync();
	window.setInterval( sync, 30000 );
	window.setInterval( tick, 1000 );

	// Classic cart updates over AJAX without reloading, so the 30-second poll
	// would leave a stale countdown on screen — including one still counting
	// down on a cart that has just been emptied.
	if ( window.jQuery ) {
		window.jQuery( document.body ).on(
			'updated_cart_totals updated_wc_div wc_fragments_refreshed removed_from_cart',
			sync
		);
	}

	// Block-based Cart and Checkout don't fire those jQuery events, but they
	// do go through the Store API — so intercepting fetch catches the same
	// moments. The short delay lets the server-side hooks finish first,
	// otherwise the sync reads the state we're trying to move away from.
	var originalFetch = window.fetch;

	if ( originalFetch ) {
		window.fetch = function () {
			var target = arguments[ 0 ];
			var href = typeof target === 'string' ? target : ( target && target.url ) || '';

			return originalFetch.apply( this, arguments ).then( function ( response ) {
				if ( href.indexOf( '/wc/store/' ) !== -1 ) {
					window.setTimeout( sync, 250 );
				}

				return response;
			} );
		};
	}
} )();
