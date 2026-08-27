const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Flash Sale Stock Guard for WooCommerce settings page', () => {
	test( 'loads for an administrator', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'admin.php?page=flash-sale-stock-guard-for-woocommerce' );
		await expect( page.locator( 'h1' ) ).toContainText( 'Flash Sale Stock Guard for WooCommerce' );
	} );
} );
