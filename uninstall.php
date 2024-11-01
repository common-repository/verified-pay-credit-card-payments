<?php
/**
 * Verified-Pay Uninstall
 *
 * Uninstall and delete all stored session & payment data from all users.
 */
namespace Vpay\VerifiedPay;

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once (plugin_dir_path ( __FILE__ ) . 'data.php');

class VerifiedPayUninstall {
	public function __construct() {
	}
	
	public function uninstall() {
		global $wpdb, $wp_version;
		
		// Only remove all user session + payment data if this is set to true.
		// This is to prevent data loss when deleting the plugin from the backend
		// and to ensure only the site owner can perform this action.
		if (VerifiedPayData::REMOVE_ALL_DATA !== true)
			return;
		
		//wp_clear_scheduled_hook( 'woocommerce_scheduled_sales' );
		
		//$table = VerifiedPay::getTableName('payments'); // we don't have that class loaded
		$tables = get_option('verifiedpay_tables', array());
		foreach ($tables as $table) {
			$wpdb->query( "DROP TABLE IF EXISTS $table" );
		}
		
		delete_option('verifiedpay_tables');
		delete_option('verifiedpay_settings');
		delete_option('verifiedpay_version');
		
		// No post data to delete.
		
		// Clear any cached data that has been removed.
		wp_cache_flush();
	}
}

$uninstall = new VerifiedPayUninstall();
$uninstall->uninstall();
