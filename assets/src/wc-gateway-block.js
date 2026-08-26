/**
 * WooCommerce Blocks (block-based checkout) frontend registration for the
 * Flash Sale Stock Guard for WooCommerce gateway. Uses window.wc.* / window.wp.* globals instead of
 * ES imports so no extra build tooling is required beyond @wordpress/scripts —
 * the matching script dependency handles are declared in
 * src/Woo/Gateways/Blocks_Payment_Method_Type.php.
 */
( function () {
	const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
	const { getSetting } = window.wc.wcSettings;
	const { createElement } = window.wp.element;
	const { decodeEntities } = window.wp.htmlEntities;
	const { __ } = window.wp.i18n;

	const settings = getSetting( 'fssgw_gateway_data', {} );
	const label = decodeEntities( settings.title || '' ) || __( 'Flash Sale Stock Guard for WooCommerce', 'flash-sale-stock-guard-for-woocommerce' );

	const Content = () =>
		createElement( 'div', null, decodeEntities( settings.description || '' ) );

	registerPaymentMethod( {
		name: 'fssgw_gateway',
		label,
		content: createElement( Content ),
		edit: createElement( Content ),
		canMakePayment: () => true,
		ariaLabel: label,
		supports: {
			features: settings.supports || [ 'products' ],
		},
	} );
} )();
