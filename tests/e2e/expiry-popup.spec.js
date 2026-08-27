const path = require( 'path' );
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * Regenerates the WordPress.org screenshot of the "reservation expired" popup
 * (screenshot-5.png), matched to the house style of screenshots 1–4: a browser
 * chrome frame on a soft gradient, no theme header/footer/notices in shot,
 * retina scale.
 *
 * It drives the real plugin code — the cart page loads assets/js/cart-timer.js
 * as shipped and the script's own showExpiredModal() renders; only the
 * hold-status response is stubbed so the countdown trips immediately.
 *
 * Prerequisites on the target site (WP_BASE_URL):
 *  - WooCommerce active, with a Cart page and at least one in-stock product.
 *  - Stock Guard enabled with "Show countdown in cart" on (both defaults).
 *
 * Run just this file:
 *   WP_BASE_URL="http://your-site.local" npx playwright test expiry-popup
 */

const OUT = path.join( __dirname, '..', '..', '.wordpress-org', 'screenshot-5.png' );

const INNER_WIDTH = 1280;
const INNER_HEIGHT = 620;
const PAD = 72;
const BAR = 44;

const HIDE_CHROME = `
	header, footer, [role="banner"], [role="contentinfo"],
	.wp-block-template-part[data-type="header"],
	.wp-block-template-part[data-type="footer"],
	.wp-site-blocks > header, .wp-site-blocks > footer,
	#masthead, .site-header, .site-footer,
	.woocommerce-message, .woocommerce-info:not([data-fssgw-timer]),
	.wc-block-components-notice-banner, .woocommerce-notices-wrapper
	{ display: none !important; }
`;

test.describe( 'Expiry popup screenshot', () => {
	test.use( {
		viewport: { width: INNER_WIDTH, height: INNER_HEIGHT },
		deviceScaleFactor: 2,
	} );

	test( 'captures the popup in a browser frame', async ( { page, baseURL } ) => {
		// Put "Hat" (a WooCommerce sample product) in the cart so the backdrop
		// is a real cart. Fall back to any non-test product with a photo.
		try {
			const res = await page.request.get(
				`${ baseURL }/wp-json/wc/store/v1/products?per_page=100&stock_status=instock`
			);
			const products = ( await res.json() ) || [];
			const named = ( name ) =>
				products.find(
					( p ) => ( p.name || '' ).trim().toLowerCase() === name
				);
			const pick =
				named( 'hat' ) ||
				products.find(
					( p ) =>
						p.images &&
						p.images.length &&
						! /test/i.test( p.name || '' )
				) ||
				products[ 0 ];

			if ( pick ) {
				await page.goto( `/?add-to-cart=${ pick.id }` );
			}
		} catch ( e ) {
			// Nice-to-have only; the popup still renders over an empty cart.
		}

		// Report the current hold as already expired so the first sync trips
		// straight into showExpiredModal().
		await page.route( '**/fssgw/v1/hold-status*', ( route ) =>
			route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( { active: true, seconds: 0 } ),
			} )
		);

		await page.goto( '/cart/' );

		const modal = page.locator( '#fssgw-expired-modal' );

		await expect( modal ).toBeVisible( { timeout: 15000 } );
		await expect( modal.locator( '#fssgw-expired-title' ) ).toBeVisible();
		await expect( modal.locator( 'button' ) ).toBeVisible();

		// Wait for the Cart block to swap its loading skeleton for real rows,
		// so the dimmed backdrop reads as a cart and not grey bars.
		await page
			.waitForFunction(
				() => {
					const loading = document.querySelector(
						'.wp-block-woocommerce-cart.is-loading, .wc-block-components-skeleton'
					);
					const names = document.querySelectorAll(
						'.wc-block-components-product-name, .product-name'
					);

					return (
						! loading &&
						names.length > 0 &&
						Array.from( names ).every(
							( n ) => n.textContent.trim().length > 0
						)
					);
				},
				{ timeout: 20000 }
			)
			.catch( () => {} );
		await page
			.waitForFunction(
				() =>
					Array.from( document.images ).every(
						( img ) => img.complete && img.naturalWidth > 0
					),
				{ timeout: 10000 }
			)
			.catch( () => {} );

		await page.addStyleTag( { content: HIDE_CHROME } );
		await page.waitForTimeout( 1200 );

		const inner = ( await page.screenshot() ).toString( 'base64' );

		// Composite into the browser-chrome frame.
		await page.setViewportSize( {
			width: INNER_WIDTH + PAD * 2,
			height: INNER_HEIGHT + BAR + PAD * 2,
		} );

		await page.setContent( `<!doctype html><html><head><meta charset="utf-8"><style>
			*{box-sizing:border-box;margin:0;padding:0}
			body{background:linear-gradient(135deg,#eef1f9,#e6ecf8);padding:${ PAD }px;
				font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
			.window{width:${ INNER_WIDTH }px;border-radius:14px;overflow:hidden;background:#fff;
				box-shadow:0 40px 90px -20px rgba(30,40,80,.35),0 0 0 1px rgba(30,40,80,.06)}
			.bar{height:${ BAR }px;display:flex;align-items:center;gap:8px;padding:0 16px;
				background:#f6f6f7;border-bottom:1px solid #e7e7ea}
			.dot{width:12px;height:12px;border-radius:50%}
			.r{background:#ff5f57}.y{background:#febc2e}.g{background:#28c840}
			.url{flex:1;margin-left:12px;height:26px;border-radius:13px;background:#fff;
				border:1px solid #e3e3e6;display:flex;align-items:center;justify-content:center;
				gap:7px;font-size:13px;color:#555}
			.url svg{width:11px;height:11px}
			img{display:block;width:${ INNER_WIDTH }px}
		</style></head><body>
			<div class="window">
				<div class="bar">
					<span class="dot r"></span><span class="dot y"></span><span class="dot g"></span>
					<span class="url">
						<svg viewBox="0 0 24 24" fill="none" stroke="#8a8a8a" stroke-width="2">
							<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>
						</svg>
						my-plugin-check.local/cart/
					</span>
				</div>
				<img src="data:image/png;base64,${ inner }" alt="">
			</div>
		</body></html>` );

		await page.waitForFunction( () => {
			const img = document.querySelector( '.window img' );
			return img && img.complete && img.naturalWidth > 0;
		} );

		await page.screenshot( { path: OUT } );
	} );
} );
