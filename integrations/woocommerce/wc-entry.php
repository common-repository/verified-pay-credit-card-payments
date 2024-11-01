<?php
function verifiedpay_woocommerce_load() {
	if (class_exists(/*'WooCommerce'*/'WC_Payment_Gateway') === false) {
		return; // WooCommerce not installed
	}
	
	require_once (VPAY__PLUGIN_DIR . 'integrations/woocommerce/WcGateway.php');
	require_once (VPAY__PLUGIN_DIR . 'integrations/woocommerce/WcCheckout.php');
	$checkout = new Vpay\VerifiedPay\WcCheckout();
	
	add_filter('woocommerce_payment_gateways', array('Vpay\VerifiedPay\WcGateway', 'addWoocommerceGateway'), 10, 1);
}

add_action( 'plugins_loaded', function () {
	verifiedpay_woocommerce_load();
}, 100 );

add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		$pluginMainFile = VPAY__PLUGIN_DIR . 'verified-pay-credit-card-payments.php';
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', $pluginMainFile, true );
	}
} );
?>