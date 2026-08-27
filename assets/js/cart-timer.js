/**
 * Cart hold countdown.
 *
 * Ticks locally for smoothness but re-syncs with the server every 30 seconds,
 * so a tab left open in the background — where browsers throttle timers —
 * can't drift into showing time the customer doesn't actually have.
 *
 * When it reaches zero the hold is gone: a blocking dialog says so and the
 * customer is sent back to the shop, matching how ticketing and drop sites
 * handle an expired reservation.
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
	var expired = false;
	var ticker = null;
	var syncer = null;

	function format( seconds ) {
		var mins = Math.floor( seconds / 60 );
		var secs = seconds % 60;

		return mins + ':' + ( secs < 10 ? '0' : '' ) + secs;
	}

	function paint() {
		if ( remaining === null ) {
			// Clear the text as well as the attribute: some themes give
			// .woocommerce-info a `display` rule that overrides `[hidden]`,
			// which would otherwise leave the last countdown value frozen
			// on screen after the hold has ended.
			container.textContent = '';
			container.hidden = true;
			return;
		}

		if ( remaining <= 0 ) {
			expire();
			return;
		}

		container.textContent = config.i18n.held.replace( '%s', format( remaining ) );
		container.hidden = false;
	}

	/**
	 * Send the customer back to the shop once their hold is gone. The query
	 * flag lets the landing request clear the released line from the mini-cart
	 * straight away, rather than only on the next cart view.
	 */
	function redirectToShop() {
		var url = config.shopUrl || '/';

		url += ( url.indexOf( '?' ) === -1 ? '?' : '&' ) + 'fssgw-expired=1';
		window.location.assign( url );
	}

	/**
	 * Only trust a #rrggbb string from the config; anything else falls back to
	 * the built-in default so a bad value can't break the inline styles.
	 */
	function hexColor( value, fallback ) {
		return /^#[0-9a-fA-F]{6}$/.test( value ) ? value : fallback;
	}

	/**
	 * A blocking "your reservation expired" dialog, built inline because the
	 * plugin ships no stylesheet. Colours and text size come from the plugin
	 * settings; text and destination are fixed. The button and an
	 * unattended-tab fallback both lead back to the shop.
	 */
	function showExpiredModal() {
		if ( document.getElementById( 'fssgw-expired-modal' ) ) {
			return;
		}

		var style = config.modal || {};
		var fontSize = parseInt( style.fontSize, 10 );

		if ( ! ( fontSize >= 8 && fontSize <= 48 ) ) {
			fontSize = 16;
		}

		var overlay = document.createElement( 'div' );

		overlay.id = 'fssgw-expired-modal';
		overlay.setAttribute( 'role', 'alertdialog' );
		overlay.setAttribute( 'aria-modal', 'true' );
		overlay.setAttribute( 'aria-labelledby', 'fssgw-expired-title' );
		overlay.style.cssText =
			'position:fixed;inset:0;z-index:999999;display:flex;align-items:center;' +
			'justify-content:center;padding:20px;background:rgba(0,0,0,0.6)';

		var card = document.createElement( 'div' );

		card.style.cssText =
			'box-sizing:border-box;max-width:400px;width:100%;border-radius:8px;' +
			'padding:28px 24px;text-align:center;font-family:inherit;' +
			'box-shadow:0 10px 40px rgba(0,0,0,0.25)';
		card.style.background = hexColor( style.background, '#ffffff' );
		card.style.color = hexColor( style.textColor, '#1e1e1e' );
		card.style.fontSize = fontSize + 'px';

		var title = document.createElement( 'h2' );

		title.id = 'fssgw-expired-title';
		title.textContent = config.i18n.expiredTitle;
		title.style.cssText = 'margin:0 0 10px;font-size:1.25em;line-height:1.3';

		var body = document.createElement( 'p' );

		body.textContent = config.i18n.expiredBody;
		body.style.cssText = 'margin:0 0 20px;font-size:1em;line-height:1.5';

		var button = document.createElement( 'button' );

		button.type = 'button';
		button.textContent = config.i18n.expiredCta;
		button.style.cssText =
			'cursor:pointer;border:0;border-radius:4px;padding:12px 20px;' +
			'font:inherit;font-size:1em';
		button.style.background = hexColor( style.buttonBg, '#720eec' );
		button.style.color = hexColor( style.buttonColor, '#ffffff' );
		button.addEventListener( 'click', redirectToShop );

		card.appendChild( title );
		card.appendChild( body );
		card.appendChild( button );
		overlay.appendChild( card );
		document.body.appendChild( overlay );

		button.focus();

		// An untouched tab still returns to the shop on its own.
		window.setTimeout( redirectToShop, 10000 );
	}

	/**
	 * Hold's up. Stop the clock, drop the inline countdown, show the dialog.
	 * Guarded so a late sync or a second tick can't run it twice.
	 */
	function expire() {
		if ( expired ) {
			return;
		}

		expired = true;
		remaining = null;

		if ( ticker ) {
			window.clearInterval( ticker );
		}

		if ( syncer ) {
			window.clearInterval( syncer );
		}

		container.textContent = '';
		container.hidden = true;

		showExpiredModal();
	}

	function tick() {
		if ( expired || remaining === null ) {
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
				if ( expired ) {
					return;
				}

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
	syncer = window.setInterval( sync, 30000 );
	ticker = window.setInterval( tick, 1000 );

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
