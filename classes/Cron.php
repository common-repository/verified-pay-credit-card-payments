<?php
namespace Vpay\VerifiedPay;

class Cron {
	const CLEANUP_TRANSACTIONS_H = 25;
	const CHECK_TRANSACTIONS_WEB_MIN = 4;
	
	public static $cron_events = array (
			'verifiedpay_cleanup_transactions',
			'verifiedpay_update_config',
		);
	public static $cron_events_hourly = array(
			'verifiedpay_check_payments',
		);
	
	/** @var VerifiedPay */
	protected $plugin;
	/** @var Settings */
	protected $settings;
	
	public function __construct(VerifiedPay $plugin, Settings $settings) {
		$this->plugin = $plugin;
		$this->settings = $settings;
		
		// init cron events
		add_action( 'verifiedpay_check_payments', array ($this, 'checkPayments' ) );
		add_action( 'verifiedpay_cleanup_transactions', array ($this, 'cleanupTransactions' ) );
		add_action( 'verifiedpay_update_config', array ($this, 'updateConfig' ) );

		// filters
		add_filter(VerifiedPay::HOOK_PREFIX . '_settings_update', [$this, 'scheduleConfigUpdate'], 10, 1);

		$lastCheck = get_option('_verifiedpay_last_check_transactions', 0);
		if ($lastCheck + static::CHECK_TRANSACTIONS_WEB_MIN*60 <= time()) {
			wp_schedule_single_event(time()+rand(0, 10), 'verifiedpay_check_payments'); // ensure we don't queue multiple checks
		}
	}

	public function checkPayments() {
		update_option('_verifiedpay_last_check_transactions', time(), true);
		foreach ( static::$cron_events_hourly as $cron_event ) { // TODO remove in later version
			$timestamp = wp_next_scheduled ( $cron_event );
			if (!$timestamp)
				wp_schedule_event(time(), 'hourly', $cron_event);
		}

		if (class_exists('Vpay\VerifiedPay\WcGateway') === true) { // check if WooCommerce installed
			$wcGateway = WcGateway::getInstance();
			$wcGateway->checkWoocommerceOrders();
		}
	}
	
	public function cleanupTransactions() {
		//global $wpdb;
		
		// cleanup the dir with QR codes
		$cacheDir = VPAY__PLUGIN_DIR . 'data/temp/qr/';
		$files = scandir($cacheDir);
		if ($files === false) {
			VerifiedPay::notifyErrorExt('Error scanning qr code dir to cleanup', "cache dir: $cacheDir");
			return;
		}
		// cleanup by age, oldest creation/changed time first
		$deletionTime = time() - static::CLEANUP_TRANSACTIONS_H*HOUR_IN_SECONDS;
		foreach ($files as $file) {
			if (empty($file) || $file[0] === '.')
				continue;
			$filePath = $cacheDir . '/' . $file;
			$lastChanged = filectime($filePath);
			if ($lastChanged < $deletionTime)
				@unlink($filePath);
		}
	}

	public function scheduleConfigUpdate(array $settings) {
		wp_schedule_single_event(time()+rand(0, 10), 'verifiedpay_update_config');
		return $settings;
	}

	public function updateConfig() {
		$gateway = $this->plugin->getGateway();
		try {
			$config = $gateway->getPaymentConfig('woocommerce'); // TODO plain WP
			if (empty($config))
				throw new \Exception('Received empty config');
			$this->settings->set('gatewayConf', $config);
		}
		catch (\Exception $e) {
			VerifiedPay::notifyErrorExt('Error updating gateway config', $e->getMessage());
		}
	}
}

?>