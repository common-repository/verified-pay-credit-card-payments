<?php
namespace Vpay\VerifiedPay;

class VerifiedPayAdmin {
	//const PAGE_HOOK = 'toplevel_page_verifiedpay'; // we don't have a toplevel menu
	const PAGE_HOOK = 'settings_page_verifiedpay';
	
	/** @var VerifiedPayAdmin */
	private static $instance = null;
	
	/**
	 * Name of the page hook when the menu is registered.
	 * For example: toplevel_page_verifiedpay
	 * @var string Page hook
	 */
	public $pageHook = '';
	
	/** @var TemplateEngine */
	public $tpl = null;
	
	/** @var VerifiedPay */
	protected $plugin;
	
	/** @var Settings */
	protected $settings = null;
	
	private function __construct(VerifiedPay $plugin) {
		if ($plugin === null)
			throw new \Error("Main plugin class must be provided in constructor of " . get_class($this));
		$this->plugin = $plugin;
	}
	
	public static function getInstance(VerifiedPay $plugin = null) {
		if (self::$instance === null)
			self::$instance = new self($plugin);
		return self::$instance;
	}
	
	public function init() {
		//$this->settings = $this->plugin->getSettings(); // settings class created after init, better use getInstance() if WC plugin with AdminSettings
		$this->settings = Settings::getInstance($this->plugin, $this);
		$this->settings->setPluginClasses($this->plugin, $this);
		$this->tpl = new TemplateEngine($this->settings);
		
		// init hooks
		//add_action( 'admin_init', array( self::$instance, 'baa' ) ); // fired on every admin page (also ajax)
		add_action( 'admin_menu', array( self::$instance, 'createMenu' ), 5 ); // Priority 5, so it's called before Jetpack's admin_menu.
		add_action( 'current_screen', array( $this, 'initCurrentScreen' ), 10, 1 );
		
		//add_action( 'admin_init', array( $this, 'loadAssets' ) ); // done after screen setup
		add_action( 'admin_init', array( $this, 'displayAdminNotices' ) );
		add_action( 'admin_init', array( $this, 'addPrivacyPolicyContent' ) );
		
		add_filter('removable_query_args', array($this, 'addRemovableAdminArgs'));
		//add_filter(VerifiedPay::HOOK_PREFIX . '_settings_change_detect_adblock', array($this, 'onAdBlockChange'), 10, 4);
		
		do_action(VerifiedPay::HOOK_PREFIX . '_admin_init', $this);
	}
	
	public function createMenu() {
		//*$this->registeredPageHooks[] = */add_menu_page( __( 'Verified-Pay', 'vpay' ), __( 'Verified-Pay', 'vpay' ), 'manage_options', 'verifiedpay', array(self::$instance, 'displaySettings'), plugins_url('/img/verified_16.png', VPAY__PLUGIN_DIR . 'verified-pay.php'), '55.5' );
		// alias for https://developer.wordpress.org/reference/functions/add_options_page/
		add_submenu_page( 'options-general.php' , __( 'Verified-Pay', 'vpay' ), __( 'Verified-Pay', 'vpay' ), 'manage_options', 'verifiedpay', array($this, 'displaySettings') );

		do_action(VerifiedPay::HOOK_PREFIX . '_admin_menu', $this);
	}
	
	public function getPageHook(): string {
		return $this->pageHook;
	}
	
	public function getTpl(): TemplateEngine {
		return $this->tpl;
	}
	
	public function displaySettings() {
		//global $wpdb;
		include VPAY__PLUGIN_DIR . 'tpl/admin/mainSettingsWrap.php';
	}
	
	public function showAllSettings() {
		include VPAY__PLUGIN_DIR . 'tpl/admin/mainSettings.php';
	}
	
	public function initCurrentScreen(\WP_Screen $screen) {
		// id: [id] => toplevel_page_cashtippr or cashtippr_page_cashtippr_shout <- this is always the hook
		if (strpos($screen->base, 'verifiedpay') === false)
			return;
		$this->pageHook = $screen->base;
		
		add_action( $this->pageHook . '_settings_page_boxes', array( $this, 'showAllSettings' ) );
		// as an alternative to listen on the screen hook we could register hooks for all sub menus here
		add_action( 'load-' . $this->pageHook, array( $this, 'addMetaBoxes' ) );
		$this->loadAssets();
	}
	
	public function displayAdminNotices() {
		// warn admin about missing or invalid settings
		$publicToken = $this->settings->get('publicToken');
		$secretToken = $this->settings->get('secretToken');
		if (empty($publicToken) || empty($secretToken)) {
			$tplVars = array(
					'msg' => __('You must enter your API keys to use the Verified-Pay plugin.', 'vpay'),
					'link' => admin_url() . 'admin.php?page=verifiedpay'
			);
			$notice = new AdminNotice($tplVars, 'error');
			$this->tpl->addAdminNotices($notice);
		}
		else if ($this->settings->get('wooEnabledBefore') !== true && class_exists(/*'WooCommerce'*/'WC_Payment_Gateway') !== false) {
			$this->settings->set('wooEnabledBefore', true);
			// notify WC integration can be enabled the 1st time it is configured fully (API keys)
			$tplVars = array(
					'msg' => __('Congratulations! You can now enable Verified-Pay for WooCommerce payments.', 'vpay'),
					'link' => admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=verifiedpay_gateway'
			);
			$notice = new AdminNotice($tplVars, 'error');
			$this->tpl->addAdminNotices($notice);
		}
		
		//$key = VerifiedPay::HOOK_PREFIX . '_notices'; // TODO would be better to use plugin-specific key everyhwere
		if (isset($_GET[VerifiedPay::HOOK_PREFIX . '_notices'])) {
			$notices = explode(',', $_GET[VerifiedPay::HOOK_PREFIX . '_notices']);
			foreach ($notices as $noticeData) {
				$notice = AdminNotice::urlDecode($noticeData);
				$this->tpl->addAdminNotices($notice);
			}
		}
		
		do_action(VerifiedPay::HOOK_PREFIX . '_admin_notices');
		add_action('admin_notices', array($this->tpl, 'showAdminNotices'));

		// admin tools
		if (is_admin() && isset($_GET['vpaydbg'])) {
			$debugFile = VPAY__PLUGIN_DIR . 'classes/debug/WooOrders.php';
			if (file_exists($debugFile))
				include_once $debugFile;
		}
	}
	
	public function loadAssets() {
		wp_enqueue_style( 'verifiedpay-admin', plugins_url( 'tpl/css/verifiedpay-admin.css', VPAY__PLUGIN_DIR . 'verified-pay.php' ), array(), VPAY_VERSION );
		wp_enqueue_script( 'verifiedpay-bundle', plugins_url( 'tpl/js/bundle.js', VPAY__PLUGIN_DIR . 'verified-pay.php' ), array('jquery'), VPAY_VERSION, false );
		add_action( "load-{$this->pageHook}", array( $this, 'addMetaboxScripts' ) );
	}
	
	public function addMetaboxScripts() {
		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'wp-lists' );
		wp_enqueue_script( 'postbox' );
	}
	
	public function addMetaBoxes(string $post_type/*, WP_Post $post*/) {
		if ($this->pageHook === static::PAGE_HOOK) {
			add_meta_box(
					'verifiedpay-account-settings',
					esc_html__( 'Account Settings', 'vpay' ),
					array( $this->tpl, 'showMetaboxAccount' ),
					$this->pageHook,
					'main'
				);
			add_meta_box(
					'verifiedpay-advanced-settings',
					esc_html__( 'Advanced Settings', 'vpay' ),
					array( $this->tpl, 'showMetaboxAdvanced' ),
					$this->pageHook,
					'main'
				);
		}
    }
    
    public function addRemovableAdminArgs(array $removable_query_args) {
    	array_push($removable_query_args, VerifiedPay::HOOK_PREFIX . '_notices');
    	return $removable_query_args;
    }
    
    public function addPrivacyPolicyContent() {
    	if ( ! function_exists( 'wp_add_privacy_policy_content' ) )
    		return;
    	$content = sprintf(
        	__( 'This website uses cookies to track recurring visitors and their previous payments.
				Additionally it sends personal data such as IP addresses to the API service at Verified-Pay. Privacy policy: https://verified-pay.com/terms',
        			'vpay' )
    	);
    	wp_add_privacy_policy_content('Verified-Pay', wp_kses_post( wpautop( $content, false ) ) );
    }
}
?>