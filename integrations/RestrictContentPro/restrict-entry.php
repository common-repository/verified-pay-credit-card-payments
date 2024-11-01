<?php
namespace Vpay\VerifiedPay;


add_filter( 'rcp_payment_gateways', function (array $gateways) {
	//$slug = RcpGateway::GATEWAY_ID; // not loaded yet
	$slug = 'verified_pay';
	$gateways[$slug] = array(
		'label'       => __('Credit Card (Verified-Pay)', 'vpay'), 	// Displayed on front-end registration form
		'admin_label' => __('Verified-Pay', 'vpay'), 		// Displayed in admin area
		'class'       => 'Vpay\VerifiedPay\RcpGateway' 		// Name of the custom gateway class
	);

	return $gateways;
} );




add_action( 'plugins_loaded', function () {
	if (class_exists('RCP_Payment_Gateway') === false) {
		return; // RestrictContentPro not installed
	}
	
	require_once VPAY__PLUGIN_DIR . 'integrations/RestrictContentPro/RcpGateway.php';
} );
?>