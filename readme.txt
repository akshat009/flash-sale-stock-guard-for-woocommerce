=== Flash Sale Stock Guard for WooCommerce ===
Contributors: developerakshat
Tags: woocommerce, flash sale, stock management, prevent overselling, countdown timer
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Prevent overselling in WooCommerce — hold stock on add-to-cart, flash sale or any day, with a live countdown for shoppers.

== Description ==

Overselling isn't just a flash-sale problem. Any product with more people watching it than units left — a restock, a handmade batch, a limited print run, a popular size running low — can sell the same last unit to two customers within seconds of each other. **Flash Sale Stock Guard for WooCommerce** closes that window for good: the instant a guarded product is added to cart, its stock is held against that shopper, so nobody else can check out with units that are already spoken for. If they don't complete checkout in time, the hold expires automatically and the stock goes back into circulation — no manual cleanup, no oversold orders to refund and apologize for.

It's named for the moment it matters most, but it runs every day, on every guarded product — quietly protecting stock whether or not a sale is even happening.

Stock holds like this are usually locked behind premium inventory or checkout add-ons. Here, that protection is built in and free from the moment you activate the plugin — no upsell, no "Pro" version required for concurrency-safe locking, per-variation targeting, order-status-aware release, or the developer filters below.

= How it works =

* **Hold on add-to-cart.** Adding a guarded item reserves that quantity immediately, before checkout even starts.
* **Race-condition safe.** When two shoppers fight over the last unit at the same instant, a database-level lock scoped to that exact product decides it — not a stock check that was already stale by the time it ran.
* **Automatic expiry.** Holds release themselves after a duration you set (1 minute to 24 hours) — long enough to check out, short enough that an abandoned cart doesn't sit on your inventory.
* **Silent re-validation before payment.** If a hold has lapsed by the time a shopper reaches checkout, the plugin quietly tries to re-secure it first rather than immediately showing a "this item is no longer available" error — a real hold-up, not a fussy one.
* **Order-aware.** A completed order converts the hold so it stops expiring; a cancelled, failed, or refunded order releases it back to stock.

= Choose what gets guarded =

* **Every stock-managed product** — a store-wide safety net.
* **Only products at or below a low-stock threshold you set** (the default) — guard scarce items automatically without a manual checklist before every drop.
* **Only products you mark individually** — a checkbox right on the product's Inventory tab, with its own checkbox per variation, so one variation of a size or color can be protected without guarding the whole product.
* Per-item checkboxes always guard that item, no matter what the store-wide mode says — a manual override on top of the automatic rule, not instead of it.

= Built for how customers actually shop =

* Works on both the classic shortcode-based Cart/Checkout **and** the block-based Cart/Checkout — no separate setup for either.
* An optional live countdown on the cart and checkout pages tells shoppers exactly how long their hold is good for, and refreshes automatically if it lapses — no guessing, no surprise "sold out" at the final step.
* Turn the countdown off entirely and the stock protection still runs invisibly in the background.

= For developers =

* `fssgw_hold_ttl` filter — vary hold duration per product or globally.
* `fssgw_is_product_guarded` filter — decide guarding programmatically, by category, campaign, or anything the settings screen doesn't cover.
* `fssgw_release_statuses` filter — control which order statuses release a converted hold.
* `fssgw_providers` filter — register additional service providers without touching plugin files.
* `fssgw_holds_expired` action — fires after each cron expiry sweep, for logging or metrics.
* WP-CLI: `wp fssgw status`.

See the plugin's [GitHub README](https://github.com/akshat009/flash-sale-stock-guard-for-woocommerce#filters--actions) for parameters and code examples for each hook.

== Installation ==

1. Make sure WooCommerce is installed and active — this plugin requires it.
2. Upload the plugin files to `/wp-content/plugins/flash-sale-stock-guard-for-woocommerce`, or install it through the WordPress plugins screen directly.
3. Activate the plugin through the 'Plugins' screen in WordPress.
4. Go to **WooCommerce → Stock Guard** to choose which products are guarded, set the hold duration, and turn the countdown on or off.

== Frequently Asked Questions ==

= How do I stop overselling on WooCommerce? =

Overselling happens because WooCommerce only checks stock at checkout — two shoppers can add the last unit to their carts at the same time, and both are allowed to try to pay. Turn on Flash Sale Stock Guard for a product (or let the low-stock default catch it automatically), and stock is held the moment it's added to cart instead — so only one of those two shoppers can ever complete the order.

= Is this only useful during flash sales? =

No — that's just the scenario overselling happens fastest in. The same hold-on-add-to-cart protection runs every day for any product you guard, sale or no sale: low-stock items, one-off restocks, made-to-order or limited-run products, anything where two people could otherwise grab the same last unit.

= Does this require WooCommerce? =

Yes. It has no purpose without it, and won't do anything if WooCommerce isn't active.

= What happens when a customer's hold expires? =

The held quantity is released back to available stock automatically. If they're still shopping, adding the item again (or revisiting checkout) tries to re-secure it — they only see an error if the stock has genuinely been taken by someone else in the meantime.

= Does this work with variable products? =

Yes — holds are tracked per variation, not just per product.

= Will this slow down my store? =

No. Stock checks use a lightweight database lock scoped to the specific product being held, so shoppers buying different items never wait on each other.

= Does the countdown work with the block-based Cart and Checkout? =

Yes, alongside the classic shortcode-based versions.

= Can I turn off the countdown but keep the stock protection? =

Yes — the countdown display and the hold logic are controlled by separate settings.

= Will uninstalling the plugin delete my data? =

Not by default. Your guard settings and per-product overrides stay in the database in case you reinstall. Data is only wiped if you tick "Delete data on uninstall" in the settings screen first.

== Screenshots ==

1. Settings screen under WooCommerce → Stock Guard.
2. The "Always guard this item" checkbox on a product's Inventory tab.
3. Live countdown shown on the cart page.
4. Live countdown shown on the checkout page.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release version.
