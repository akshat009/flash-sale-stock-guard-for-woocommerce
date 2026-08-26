<?php
/**
 * Custom order email template (plain text).
 *
 * Copied into the generated plugin at templates/emails/plain/fssgw-custom-email.php
 * and loaded by FlashSaleStockGuardWooCommerce\Woo\Emails\Custom_Email — customize freely.
 *
 * @package FlashSaleStockGuardWooCommerce\Woo\Emails
 *
 * @var WC_Order $order         Order object.
 * @var string   $email_heading Email heading.
 * @var bool     $sent_to_admin Whether this copy is for the store admin.
 * @var bool     $plain_text    Whether this is the plain-text version.
 * @var WC_Email $email         Email object.
 */

defined( 'ABSPATH' ) || exit;

echo esc_html( wp_strip_all_tags( $email_heading ) ) . "\n\n";

/* translators: %s: Customer first name. */
echo esc_html( sprintf( __( 'Hi %s,', 'flash-sale-stock-guard-for-woocommerce' ), $order->get_billing_first_name() ) ) . "\n\n";

esc_html_e( 'This is a custom notification email — replace this content with your own.', 'flash-sale-stock-guard-for-woocommerce' );

echo "\n\n" . str_repeat( '-', 40 ) . "\n\n";

do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
