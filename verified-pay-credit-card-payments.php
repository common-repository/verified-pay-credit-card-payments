<?php
/*
 * Plugin Name: Verified-Pay: Credit Card payments
 * Plugin URI: https://verified-pay.com/
 * Description: WooCommerce and RestrictContentPro gateway to accept Credit Cards in your store. All customer's identity is verified using OTP (text message) to prevent fraud.
 * Version: 1.1.5
 * Author: vpay
 * License: GPLv3
 * Text Domain: vpay
 */

use Vpay\VerifiedPay\DatabaseMigration;
use Vpay\VerifiedPay\VerifiedPay;
use Vpay\VerifiedPay\VerifiedPayAdmin;
use Vpay\VerifiedPay\VerifiedPayApi;

// Make sure we don't expose any info if called directly
if (! defined( 'ABSPATH' )) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit ();
}

define ( 'VPAY_VERSION', '1.1.5' );
define ( 'VPAY__MINIMUM_WP_VERSION', '4.9' );
define ( 'VPAY__PLUGIN_DIR', plugin_dir_path ( __FILE__ ) );

if (PHP_VERSION_ID < 70000) {
	load_plugin_textdomain ( 'vpay' );
	$escapeHtml = false;
	$message = '<strong>' . esc_html__ ( 'You need PHP v7.0 or higher to use this plugin.', 'vpay' ) . '</strong> ' . esc_html__ ( 'Please update in your hosting provider\'s control panel or contact your hosting provider.', 'vpay' );
	include VPAY__PLUGIN_DIR . 'tpl/message.php';
	exit();
}

register_activation_hook ( __FILE__, array (
		'Vpay\VerifiedPay\VerifiedPay',
		'plugin_activation' 
) );
register_deactivation_hook ( __FILE__, array (
		'Vpay\VerifiedPay\VerifiedPay',
		'plugin_deactivation' 
) );

require_once (VPAY__PLUGIN_DIR . 'data.php');
require_once (VPAY__PLUGIN_DIR . 'functions.php');
require_once (VPAY__PLUGIN_DIR . 'classes/VerifiedPay.php');
require_once (VPAY__PLUGIN_DIR . 'classes/VerifiedPayApi.php');
require_once (VPAY__PLUGIN_DIR . 'api.php');
VerifiedPayApi::getInstance(VerifiedPay::getInstance());

DatabaseMigration::checkAndMigrate();

add_action ( 'init', array (
	VerifiedPay::getInstance(),
	'init'
) );

add_action ( 'rest_api_init', array (
	VerifiedPayApi::getInstance(),
	'init'
) );

if (is_admin ()/* || (defined ( 'WP_CLI' ) && WP_CLI)*/) {
	require_once (VPAY__PLUGIN_DIR . 'classes/VerifiedPayAdmin.php');
	VerifiedPayAdmin::getInstance(VerifiedPay::getInstance());
	add_action ( 'init', array (
		VerifiedPayAdmin::getInstance(),
		'init'
	) );
}


// WooCommerce and other integrations
require_once (VPAY__PLUGIN_DIR . 'integrations/RestrictContentPro/restrict-entry.php');
require_once (VPAY__PLUGIN_DIR . 'integrations/woocommerce/wc-entry.php');
?>