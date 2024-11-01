<?php
namespace Vpay\VerifiedPay;

//require_once VPAY__PLUGIN_DIR . 'classes/autoload.php'; // better call this at the end of this file if needed (or outside) to avoid circular loading
require_once VPAY__PLUGIN_DIR . 'classes/Settings.php';
require_once VPAY__PLUGIN_DIR . 'classes/Sanitizer.php';
// TODO move some classes to Admin class if we really don't need them here
require_once VPAY__PLUGIN_DIR . 'classes/TemplateEngine.php';
require_once VPAY__PLUGIN_DIR . 'classes/DatabaseMigration.php';
require_once VPAY__PLUGIN_DIR . 'classes/AdminNotice.php';
//require_once VPAY__PLUGIN_DIR . 'classes/Payment.php';
require_once VPAY__PLUGIN_DIR . 'classes/Cron.php';
require_once VPAY__PLUGIN_DIR . 'classes/gateway/VerifiedPayGateway.php';


class VerifiedPay {
	const DEBUG = true;
	const SESSION_LIFETIME_SEC = 365 * DAY_IN_SECONDS;
	const HOOK_PREFIX = 'verifiedpay';
	const CONSENT_COOKIE_NAME = 'vp-ck';
	const GATEWAY_FRAME = '<iframe src="%s" scrolling="no" style="overflow: hidden" width="400" height="800"></iframe>';
	
	/** @var VerifiedPay */
	private static $instance = null;
	private static $prefix = "vpa_";
	/** @var \WC_Log_Handler_File */
    protected static  $logger = null;
    /** @var bool */
    protected static $initDone = false;
	
	/** @var Settings */
	protected $settings;
	/** @var array URL parts of this WP site as the result of: parse_url(site_url('/')) */
	protected $siteUrlParts = null;
	/** @var Sanitizer */
	protected $sanitizer = null;
	/** @var Cron */
	protected $cron = null;
	
	/** @var VerifiedPayGateway */
	protected $gateway = null;
	
	private function __construct() {
	}
	
	public static function getInstance() {
		if (self::$instance === null)
			self::$instance = new self ();
		return self::$instance;
	}
	
	public static function getTableName($tableName) {
		global $wpdb;
		return $wpdb->prefix . self::$prefix . $tableName;
	}
	
	public function init() {
		if (static::$initDone === true)
			return;
		static::$initDone = true;
		
		$siteUrl = site_url('/');
		$this->siteUrlParts = parse_url($siteUrl);
		//if (CashtipprAdmin::adminLoaded() === true) // if we are not on an admin page we don't include the source
		if (class_exists('VerifiedPay', false) === true) // must be delayed until init call so that source is ready
			$this->settings = Settings::getInstance($this, VerifiedPayAdmin::getInstance($this));
		else
			$this->settings = Settings::getInstance($this);
		$this->sanitizer = new Sanitizer();

		$this->cron = new Cron($this, $this->settings);

		// load gatway after settings
		$this->gateway = new VerifiedPayGateway($this->settings->get('publicToken'), $this->settings->get('secretToken'), $siteUrl);
		
		// init hooks
		// note that functions must be public because they are called from event stack
		add_action( 'wp_enqueue_scripts', array (self::$instance, 'addPluginScripts' ) );
		add_action( 'wp_footer', array(self::$instance, 'addFooterCode') );

		do_action('vpay_init_done', $this);
	}
	
	public static function plugin_activation() {
		if (version_compare ( $GLOBALS ['wp_version'], VPAY__MINIMUM_WP_VERSION, '<' )) {
			load_plugin_textdomain ( 'vpay' );
			$message = '<strong>' . sprintf ( esc_html__ ( '%s plugin %s requires WordPress %s or higher.', 'vpay' ), get_class(), VPAY_VERSION, VPAY__MINIMUM_WP_VERSION ) . '</strong> ' . sprintf ( __ ( 'Please <a href="%1$s">upgrade WordPress</a> to a current version.', 'vpay' ), 'https://codex.wordpress.org/Upgrading_WordPress' );
			static::bailOnActivation ( $message, false );
		}
		
		// create tables
		//$tables = get_option('verifiedpay_tables', array());
		//update_option('verifiedpay_tables', $tables);
		
		// ensure directories exist
		$dataDirs = array(
			VPAY__PLUGIN_DIR . 'data',
			VPAY__PLUGIN_DIR . 'data/temp',
		);
		foreach ($dataDirs as $dir) {
			if (file_exists($dir) === true)
				continue;
			if (mkdir($dir) === false) { // TODO even though we don't create php files, using WP filesystem API would still be better
				load_plugin_textdomain ( 'vpay' );
				$message = '<strong>' . esc_html__ ( 'Error creating data folder.', 'vpay' ) . '</strong> ' . sprintf ( __ ( 'Please ensure that your WordPress installation has write permissions on the /plugins folder (0755) or create this folder manually with permissions 0755: %s', 'vpay' ), $dir );
				static::bailOnActivation ( $message, false );
			}
		}
		
		foreach ( Cron::$cron_events as $cron_event ) {
			$timestamp = wp_next_scheduled ( $cron_event );
			if (!$timestamp)
				wp_schedule_event(time(), 'daily', $cron_event);
		}
		foreach ( Cron::$cron_events_hourly as $cron_event ) {
			$timestamp = wp_next_scheduled ( $cron_event );
			if (!$timestamp)
				wp_schedule_event(time(), 'hourly', $cron_event);
		}

		// int woocommerce_gateway_order: verifiedpay_gateway lower value to put front? see set_gateway_top_of_list()
		
		//if (!get_option ( 'verifiedpay_version' )) { // first install
		//}
		update_option ( 'verifiedpay_version', VPAY_VERSION );
	}
	
	public static function plugin_deactivation() {
		//global $wpdb;
		// Remove any scheduled cron jobs.
		$events = array_merge(Cron::$cron_events, Cron::$cron_events_hourly);
		foreach ( $events as $cron_event ) {
			$timestamp = wp_next_scheduled ( $cron_event );
			if ($timestamp) {
				wp_unschedule_event ( $timestamp, $cron_event );
			}
		}
		
		// tables are only being dropped on uninstall. also verifiedpay_settings
		//delete_option('verifiedpay_version'); // done on uninstall
	}
	
	public function addFooterCode() {
		$cfg = array(
			'cookieLifeDays' => ceil(static::SESSION_LIFETIME_SEC / DAY_IN_SECONDS),
			'cookiePath' => $this->siteUrlParts['path'],
			'siteUrl' => $this->getSiteUrl(),
			'gatewayOrigin' => VerifiedPayGateway::API_ENDPOINT,
			'reloadAfterPay' => $this->settings->get('reload_after_pay'),
			//'frameUrl' => $this->gateway !== null ? $this->gateway->getNamedPayFrameUrl() : '',
			'tr' => array(
					'order' => __('Order', 'vpay'),
					'post' => __('Post', 'vpay'),
				)
		);
		if ($this->settings->get('show_cookie_consent') === true && !isset($_COOKIE[static::CONSENT_COOKIE_NAME])) {
			// TODO add option to only show this to specific countries
			// from get_the_privacy_policy_link()
			//pre_print_r("COOKIE consent");
			$policy_page_id = (int)get_option( 'wp_page_for_privacy_policy' );
			$privacyPageTitle = $policy_page_id ? get_the_title( $policy_page_id ) : __('Privacy Policy', 'vpay');
			include VPAY__PLUGIN_DIR . 'tpl/cookieConfirm.php';
		}
		$cfg = apply_filters(static::HOOK_PREFIX . '_js_config', $cfg);
		echo '<script type="text/javascript">var verifiedPayCfg = ' . json_encode($cfg) . ';</script>';
	}
	
	public function addPluginScripts() {
		wp_enqueue_style( 'verifiedpay', plugins_url( 'tpl/css/verifiedpay.css', VPAY__PLUGIN_DIR . 'verified-pay.php' ), array(), VPAY_VERSION );
		wp_enqueue_script( 'verifiedpay-bundle', plugins_url( 'tpl/js/bundle.js', VPAY__PLUGIN_DIR . 'verified-pay.php' ), array('jquery'), VPAY_VERSION, true );
	}
	
	public function getSettings(): Settings {
		/** Fatal error: Uncaught Error: Return value of Vpay\VerifiedPay\VerifiedPay::getSettings() must be an instance of Vpay\VerifiedPay\Settings, null returned
		 *Vpay\VerifiedPay\VerifiedPay::getSettings()
		 * wp-content/plugins/verified-pay-credit-card-payments/integrations/woocommerce/WcGateway.php:31
		 * Vpay\VerifiedPay\WcGateway::__construct()
		 * wp-content/plugins/woocommerce/includes/class-wc-payment-gateways.php:97
		 * WC_Payment_Gateways::init()
		 * wp-content/plugins/woocommerce/includes/class-wc-payment-gateways.php:70
		 */
		//return $this->settings;
		return empty($this->settings) ? Settings::getInstance($this) : $this->settings;
	}
	
	public static function notifyErrorExt($subject, $error, $data = null, bool $silent = false) {
		global $wpdb;
		if (defined('WC_LOG_DIR') === true) {
			if (static::$logger === null)
				static::$logger = new \WC_Log_Handler_File();
			$logMsg = $subject . "\r\n" . print_r($error, true);
			if ($data !== null)
				$logMsg .= "\r\n" . print_r($data, true);
			static::$logger->handle(time(), 'error', $logMsg, array('source' => static::HOOK_PREFIX));
		}
		if (static::DEBUG === false || $silent === true)
			return;
		$table = static::getTableName("messages_system");
		if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
			pre_print_r($subject);
			pre_print_r($error);
			if ($data !== null)
				pre_print_r($data);
			return; // table doesn't exist
		}
		if (!is_string($error))
			$error = print_r($error, true);
		$rowCount = $wpdb->insert($table, array(
				'sender' => 'SystemError',
				'subject' => $subject,
				'text' => $error,
				'data' => $data !== null ? serialize($data) : null,
				'site' => strtolower(get_bloginfo('name'))
		));
	}
	
	public function getSiteUrl(array $query = array()) {
		$url = $this->siteUrlParts['scheme'] . '://' . $this->siteUrlParts['host'];
		if (isset($this->siteUrlParts['port']))
			$url .= $this->siteUrlParts['port'];
		$url .= $this->siteUrlParts['path'];
		$first = true;
		foreach ($query as $key => $value) {
			$url .= $first === true ? '?' : '&';
			$url .= $key . '=' . urlencode($value);
			$first = false;
		}
		return $url;
	}
	
	public function getCurrentUrl() {
		global $wp;
		return home_url( add_query_arg( array(), $wp->request ) );
	}
	
	public function getGateway(): VerifiedPayGateway {
		return $this->gateway;
	}
	
	protected static function bailOnActivation($message, $escapeHtml = true, $deactivate = true) {
		include VPAY__PLUGIN_DIR . 'tpl/message.php';
		if ($deactivate) {
			$plugins = get_option ( 'active_plugins' );
			$thisPlugin = plugin_basename ( VPAY__PLUGIN_DIR . 'verified-pay.php' );
			$update = false;
			foreach ( $plugins as $i => $plugin ) {
				if ($plugin === $thisPlugin) {
					$plugins [$i] = false;
					$update = true;
				}
			}
			
			if ($update) {
				update_option ( 'active_plugins', array_filter ( $plugins ) );
			}
		}
		exit ();
	}
}
?>