<?php
namespace Vpay\VerifiedPay;


class WcGateway extends \WC_Payment_Gateway {
	const DEBUG = true;
	const PLUGIN_ID = "verifiedpay_gateway";
	const MAX_FETCH_ORDERS = 200;
	const CHECK_CANCELLED_ORDERS_H = 72; // for how long we keep track of cancelled orders if a late payment arrives
	
	/** @var WcGateway */
	private static $instance = null;
	
	
	/** @var \WC_Session|\WC_Session_Handler */
    protected $session = null;
    
    /** @var bool */
    protected $paymentOptionsShowing = false;
	
	public function __construct(/*\WcGateway $gateway*/) {
		// this gets called from Woocommerce, so make sure we cache this instance
		//static::check_plugin_activation();
		if (self::$instance === null)
			self::$instance = $this;
		
		$this->id            		= static::PLUGIN_ID;
        $this->medthod_title 		= __('Verified-Pay Gateway', 'vpay');
        $this->has_fields    		= true;
		$verifiedPay = VerifiedPay::getInstance();
		$settings = $verifiedPay->getSettings();
		if ($settings->get('voucher_store') !== true) {
			/** @var PaymentConfig $gatwayConf */
			$gatwayConf = $settings->get('gatewayConf');
			$icon = 'img/cc_32.png';
			if ($gatwayConf && $gatwayConf->showPaypal())
				$icon = 'img/paypal.png';
			else if ($settings->get('show_amex_icon') === true)
				$icon = 'img/cc_32_all.png';
			$this->icon					= plugins_url( $icon, VPAY__PLUGIN_DIR . 'verified-pay.php' );
		}
        else {
	        $this->icon					= plugins_url( 'img/verified_32.png', VPAY__PLUGIN_DIR . 'verified-pay.php' );
        }
		$this->method_description = $this->getFrontendDefaultDescription();
        
        $this->init();
        
        $title = isset($this->settings['title']) ? $this->settings['title'] : $this->getFrontendDefaultTitle();
        $description = isset($this->settings['description']) ? $this->settings['description'] : $this->getFrontendDefaultDescription();
        $this->title       			= $title; // for frontend (user), also shown in WC settings
        $this->description 			= $description; // for frontend (user) // allows HTML descriptions
        //$this->order_button_text 	= "fooo..."; // TODO add option to replace the whole button HTML by overwriting parent functions
        
        $this->session 				= WC()->session; // null in WP admin
        //$this->cart    				= WC()->cart;
	}
	
	public static function getInstance(/*\WcGateway $gateway = null*/) {
		if (self::$instance === null)
			self::$instance = new self(/*$gateway*/);
		return self::$instance;
	}
	
	public function init() {
		$this->init_settings();
		$this->init_form_fields();
		
		// init hooks
		// note that functions must be public because they are called from event stack
		// call the main class which will call this plugin via our own action
		
		add_filter('woocommerce_settings_api_sanitized_fields_' . $this->id, array($this, 'onSettingsUpdate'));
		add_filter('woocommerce_order_is_paid_statuses', array($this, 'filterOrderPaidStatuses'), 10, 1);
		
		
		// WC gateway hooks
		//add_action('woocommerce_api_wc_coinpay', array($this, 'checkIpnResponse')); // called after payment if we use a 3rd party callback
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		
		// different WC plugins might overwrite the order details page. so register for all and only print data once with the first hook (far up on the page)
		add_action('woocommerce_order_details_before_order_table', array ($this, 'addPluginPaymentOptions' ), 100, 1);
		add_action('woocommerce_order_details_after_order_table', array ($this, 'addPluginPaymentOptions' ), 100, 1);
		add_action('woocommerce_order_details_before_order_table_items', array ($this, 'addPluginPaymentOptions' ), 100, 1);
		add_action('woocommerce_thankyou', array ($this, 'addPluginPaymentOptions' ), 100, 1);
		add_action('woocommerce_thankyou_' . $this->id, array ($this, 'addPluginPaymentOptions' ), 100, 1);
		
		// TODO email hooks for new order?
		
		add_filter(VerifiedPay::HOOK_PREFIX . '_js_config', array($this, 'addPluginFooterCode'));
		
		// Crons
		// moved to Cron class
		
		if (is_admin() === true) {
			// config checks
			//if (empty($this->pluginSettings->get('gateways')))
				//$this->addSettingsUpdateErrorMessage(__('Please add at least 1 gateway to use the SSG plugin.', 'vpay'));
			
			//$this->displayAdminNotices();
		}
	}
	
	public static function addWoocommerceGateway(array $load_gateways) {
		$load_gateways[] = '\\Vpay\\VerifiedPay\\WcGateway';
		return $load_gateways;
	}
	
	public static function setupCronHooks() {
	}
	
	public function init_settings() {
		parent::init_settings();
		$this->enabled  = ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'] ? 'yes' : 'no';
	}
	
	public function init_form_fields() {
		$this->form_fields = array(
				'enabled' => array(
						'title' 		=> __('Enable Verified-Pay Gateway', 'vpay'),
						'type'			=> 'checkbox',
						'description'	=> '',
						'default'		=> 'yes'
				),
				
				'title' => array(
						'title' 		=> __('Title', 'vpay'),
						'type'			=> 'text',
						'description'	=> __('The payment method title your customers will see on your shop.', 'vpay'),
						'default'		=> $this->getFrontendDefaultTitle(),
						'desc_tip'		=> true
				),
				'description' => array(
						'title' 		=> __('Description', 'vpay'),
						'type'			=> 'text',
						'description'	=> __('The payment method description your customers will see on your shop.', 'vpay'),
						'default'		=> $this->getFrontendDefaultDescription(),
						'desc_tip'		=> true
				),
				/*
				'paidMsg' => array(
						'title' 		=> __('Paid Message', 'vpay'),
						'type'			=> 'text',
						'description'	=> __('The message to show on the checkout page if the order has already been paid.', 'vpay'),
						'default'		=> __('Thanks for your payment.', 'vpay'),
						'desc_tip'		=> true
				),
				*/
		);
    }
    
    public function is_available() {
		// TODO add option to show only to selected countries?
    	// check if API key set in main plugin
	    $verifiedPay = VerifiedPay::getInstance();
		//$settings = $verifiedPay->getSettings(); // sometimes not ready here
	    $settings = Settings::getInstance($verifiedPay);
	    if (empty($settings->get('publicToken')) || empty($settings->get('secretToken')))
			return false;

    	return parent::is_available();
    }

	public function payment_fields() {
		$verifiedPay = VerifiedPay::getInstance();
		$settings = $verifiedPay->getSettings();
		if ($settings->get('voucher_store') !== true) {
			parent::payment_fields();
			return;
		}

		// echo esc_html($this->settings['description']); // or method_description
		include VPAY__PLUGIN_DIR . 'tpl/client/woocommerce/paySelectForm.php';
	}
    
    public function process_payment($order_id) {
    	if (!$this->session) { // shouldn't happen (and if it does the cart will be empty too)
    		wc_add_notice(esc_html("Your session has expired. You have not been charged. Please add your item(s) to the cart again.", "vpay"), 'error');
    		return;
    	}
	    $verifiedPay = VerifiedPay::getInstance();
	    $settings = $verifiedPay->getSettings();

	    if ($settings->get('voucher_store') === true) {
		    $valid = VerifiedPayGateway::isValidCouponCode($_POST['vp_voucherCode']);
		    if ($valid === false) {
			    wc_add_notice(esc_html("Your voucher code is invalid.", "vpay"), 'error');
			    return;
		    }
	    }

    	// "place order" has just been clicked. This is the moment the order has been created and we can instantiate an order object by ID
    	$this->clearCustomSessionVariables(); // ensure there is no plugin data left from the previous payment
    	$this->session->set("orderID", $order_id);
    	$order = new \WC_Order($order_id);

	    if ($settings->get('voucher_store') === true) {
		    $order->add_meta_data('vp_coupon', $_POST['vp_voucherCode'], true);
		    $this->setOrderDone($order, /*$payment->status*/'PAID');
	    }

	    $verifiedPay = VerifiedPay::getInstance();
	    $settings = $verifiedPay->getSettings();
		if ($settings->get('redirect_on_checkout') === true) {
			if ($this->ensureCustomerRegistered($order) === false) {
				wc_add_notice(esc_html("Error registering customer. Please contact support if this problem persists.", "vpay"), 'error');
				return;
			}
			$params = $this->addPaymentParamsToOrder($order);
			$params->returnUrl = $this->get_return_url($order);
			$gateway = $verifiedPay->getGateway();
			return array(
				'result' => 'success',
				'redirect' => $gateway->getPayPageUrl($params),
			);
		}

		return array(
				'result' => 'success',
				'redirect' => $this->get_return_url($order) // just redirect to the order details page, whe show our payment button(s) there
		);
	}
	
	public function onSettingsUpdate(array $settings) {
		return $settings;
	}

	public function filterOrderPaidStatuses(array $statuses) {
		if (in_array('awaiting-shipment', $statuses, true) === false)
			$statuses[] = 'awaiting-shipment'; // add 3PL since their plugin doesn't
		return $statuses;
	}
	public function addPluginPaymentOptions($order_id) {
		if ($this->paymentOptionsShowing === true)
			return;
		if (!$this->session)
			return; // shouldn't happen
		if (!$order_id) {
			if (!$this->session->get("orderID"))
				return;
			$order_id = $this->session->get("orderID");
		}
		$this->paymentOptionsShowing = true;
		try {
			$order = is_object($order_id) ? $order_id : new \WC_Order($order_id); // some hooks return the order object already
			$order_id = $order->get_id();
			$this->session->set("orderID", $order_id); // ensure it's the current order
		}
		catch (\Exception $e) { // invalid order exception
			echo esc_html('This order does not exist.', 'vpay') . '<br><br>';
			return;
		}
		if ($order->get_payment_method() !== static::PLUGIN_ID)
			return; // the user chose another payment method

		$status = $order->get_status();
		if ($status == 'failed') {
			//$order->set_status('pending', __('Customer returning on expired order. Updating status to pending.', 'vpay'));
			//$order->save();
			return;
		}
		//if ($order->is_paid() === true) { // moved up to show paid message
		if (in_array($status, array('pending', 'failed', 'cancelled')) === false) { // shipping plugins have other statuses not included in is_paid()
			//$msgConf = array('paid' => $this->settings['paidMsg']);
			$coupon = $order->get_meta('vp_coupon');
			if (empty($coupon))
				return;
			$msgConf = array(
				'coupon' => sprintf(__('Verified Pay e-Voucher: %s Redeemed ✅', 'vpay'), $coupon),
				'couponUrl' => sprintf('%s/pay-qr/%s', VerifiedPayGateway::API_ENDPOINT, $coupon),
				'paid' => '',
			);
			include VPAY__PLUGIN_DIR . 'tpl/client/woocommerce/paidMsg.php';
			return;
		}

		if ($this->ensureCustomerRegistered($order) === false) {
			echo esc_html('Error registering customer. Please contact support if this problem persists.', 'vpay') . '<br><br>';
			return;
		}
		$params = $this->addPaymentParamsToOrder($order);
		$verifiedPay = VerifiedPay::getInstance();
		$gateway = $verifiedPay->getGateway();
		$settings = $verifiedPay->getSettings();
		/** @var PaymentConfig $gatwayConf */
		$gatwayConf = $settings->get('gatewayConf');

		if ($settings->get('pay_iframe') !== true || ($gatwayConf && !$gatwayConf->allowIframe())) {
			// immediate redirect is done before (if enabled)
			$buttonConf = array(
				'beforePayMsg' => $settings->get('before_pay_msg'), // Since you chose to pay via credit card, please pay securely below:
				//'amountGatewayCurrency' => number_format($this->getGatewayCurrencyAmount($order, $gateway), 2),
				'url' => $gateway->getPayPageUrl($params),
//				'orderID' => $order_id, // not used with button
//				'amountGatewayCurrency' => '',
//				'gatewayCurrencyCode' => 'ZAR',
			);
			include VPAY__PLUGIN_DIR . 'tpl/client/woocommerce/payButton.php';
			return;
		}

		$frameCfg = array(
			'beforePayMsg' => $settings->get('before_pay_msg'),
			'url' => $gateway->getPayFrameUrl($params),
			'orderID' => $order_id,
			'frameHeight' => $gatwayConf ? $gatwayConf->getFrameHeight() : 800,
		);
		include VPAY__PLUGIN_DIR . 'tpl/client/woocommerce/payFrame.php';
	}
	
	public function addPluginFooterCode(array $cfg) {
		$order = null;
		try {
			$order = new \WC_Order($this->session->get("orderID"));
		}
		catch (\Exception $e) { // invalid order exception
		}
		if ($order !== null) {
			$cfg['woocommerce'] = array(
					'paymentPage' => $this->paymentOptionsShowing === true,
					'orderID' => $order->get_id(),
					'currency' => $order->get_currency(),
					'amount' => (float)$order->get_total(),
			);
		}
		return $cfg;
	}

	/*
	public function checkOrderPaid(\WC_Order $order): bool {
		// query gateway REST API
		$verifiedPay = VerifiedPay::getInstance();
		$gateway = $verifiedPay->getGateway();
		$payment = $gateway->GetPayment($order->get_id());
		if ($payment === null)
			return false;
		$order->add_meta_data('vp_coupon', $payment->coupon_code, true);
		if ($payment->status !== 'PAID') {
			if ($payment->status === 'EXPIRED') {
				$this->setOrderDone($order, $payment->status);
			}
			// TODO more states? see https://github.com/woocommerce/woocommerce/blob/master/includes/wc-order-functions.php#L86-L104
			return false;
		}

		$this->setOrderDone($order, $payment->status);
		return true;
	}
	*/

	public function setOrderDone(\WC_Order  $order, string $status): void {
		if ($order->get_meta('_vp_paid_auto') == true) {
			$order->add_order_note(__('Verified-Pay: Prevented already updated order to update to status: ', 'vpay') . $status);
			return; // prevent setting it to paid multiple times if admin overwrites it
		}
		// TODO only update on certain states (such as pending?) to be double sure

		$verifiedPay = VerifiedPay::getInstance();
		$gateway = $verifiedPay->getGateway();
		$link = $gateway->getPaymentAdminUrl($order->get_meta('_vp_tx_id'));
		$order->add_order_note(sprintf(__('Verified Pay Admin URL: %s', 'vpay'), $link));

		if ($status === 'PAID'/* || $status === 'REDEEMED'*/) {
			$order->add_meta_data('_vp_paid_auto', '1', true);
			$coupon = $order->get_meta('vp_coupon');
			$order->payment_complete( $coupon );
			$order->add_order_note(sprintf(__('Order paid via Verified-Pay using coupon: %s', 'vpay'), $coupon));
			//$order->set_status($this->getSuccessPaymentStatus(), sprintf(__('Order paid via Verified-Pay using coupon: %s', 'vpay'), $coupon));
		}
		else if ($status === 'EXPIRED') {
			$order->set_status('cancelled', __('Payment expired', 'vpay'));
		}
		else if ($status === 'DECLINED') {
			$settings = $verifiedPay->getSettings();
			//$order->set_status('failed', __('Payment declined by Verified-Pay', 'vpay'));
			if ($order->get_meta('_vp_declined') != '1') // only add once (callback might fire multiple times)
				$order->add_order_note(__('Payment declined by Verified-Pay', 'vpay'));
			$order->add_meta_data('_vp_declined', '1', true);
			$redirectID = $settings->get('redirect_gateway_id');
			if (!empty($redirectID))
				$order->set_payment_method($redirectID);
			//$order->save(); // needed for set_payment_method()
			$order->save_meta_data(); // shouldn't be needed
		}
		else {
			$order->add_order_note(__('Received Verified-Pay callback for order with unknown status: ', 'vpay') . $status);
			$order->save_meta_data(); // shouldn't be needed
		}

		$order->save();
		$this->clearCustomSessionVariables();
	}

	public function checkWoocommerceOrders(): void {
		$orders = array_merge($this->getPendingOrders(), $this->getCancelledOrders(static::CHECK_CANCELLED_ORDERS_H));
		$verifiedPay = VerifiedPay::getInstance();
		$gateway = $verifiedPay->getGateway();

		foreach ($orders as $order) {
			//if ($order->get_status() === $paidStatus)
			if ($order->is_paid() === true)
				continue;

			$verifiedTxId = $order->get_meta('_vp_tx_id');
			if (empty($verifiedTxId))
				continue;
			$payment = $gateway->getPayment($verifiedTxId);
			if (empty($payment))
				continue; // TODO return & record 404 response: set metadata for cancelled/expired to not check again after sooner time?
			if ($payment->status === 'PAID'/* || $payment->status === 'REDEEMED'*/ || $payment->status === 'EXPIRED') {
				$this->setOrderDone($order, $payment->status);
			}
		}
	}

	protected function getPendingOrders($addGateways = array()): array {
		// orders will be cancelled by Woocommerce after 1 hour. hook woocommerce_cancel_unpaid_order filter to change that
		// https://docs.woocommerce.com/wc-apidocs/source-function-wc_cancel_unpaid_orders.html#904
		$gateways = array(static::PLUGIN_ID);
		if (!empty($addGateways))
			$gateways = array_merge($gateways, $addGateways);
		$args = array(
			'status' => 'pending',
			'payment_method' => $gateways,
			'orderby' => 'modified',
			'order' => 'DESC',
			'limit' => static::MAX_FETCH_ORDERS*2, // TODO more within 1h?
		);
		$orders = wc_get_orders( $args );
		return $orders;
	}

	protected function getCancelledOrders(int $latestHours = 6, int $limit = -1): array {
		if ($limit === -1)
			$limit = static::MAX_FETCH_ORDERS;
		$args = array(
			'status' => 'cancelled',
			'payment_method' => array(static::PLUGIN_ID),
			//'created_via' => '',
			'date_created' => '>' . ( time() - $latestHours*HOUR_IN_SECONDS ), // get orders created within latest n hours
			'orderby' => 'modified',
			'order' => 'DESC',
			'limit' => $limit,
		);
		$orders = wc_get_orders( $args );
		return $orders;
	}

	protected function ensureCustomerRegistered(\WC_Order $order): bool {
		if (!empty($order->get_meta('_vc_customer_token')))
			return true;

		$verifiedPay = VerifiedPay::getInstance();
		$gateway = $verifiedPay->getGateway();
		$customer = new Customer();
		$customer->phone = $order->get_billing_phone();
		$customer->email = $order->get_billing_email();
		try {
			$registerRes = $gateway->registerCustomer( $customer );
			$order->add_meta_data('_vc_customer_token', $registerRes->Token, true);
			$order->save_meta_data();
			return true;
		} catch ( \Exception $e ) {
			echo esc_html(sprintf(__('Exception when registering customer: %s', 'vpay'), $e->getMessage())) . '<br><br>';
		}
		return false;
	}

	protected function payParamsFromWcOrder(\WC_Order $order): VerifiedPayPaymentParams {
		$params = new VerifiedPayPaymentParams($order->get_id(), $order->get_total(), $order->get_currency());
		$params->type = __('Order', 'vpay');
		$params->skipPhoneVerify = true;
		$params->customerToken = $order->get_meta('_vc_customer_token');

		$params->billingAddress = new Customer();
		$params->billingAddress->firstName = $order->get_billing_first_name();
		$params->billingAddress->lastName = $order->get_billing_last_name();
		$params->billingAddress->company = $order->get_billing_company();
		$params->billingAddress->addressLine1 = $order->get_billing_address_1();
		$params->billingAddress->addressLine2 = $order->get_billing_address_2();
		$params->billingAddress->city = $order->get_billing_city();
		$params->billingAddress->state = $order->get_billing_state();
		$params->billingAddress->postcode = $order->get_billing_postcode();
		$params->billingAddress->country = $order->get_billing_country();
		$params->billingAddress->phone = $order->get_billing_phone();
		$params->billingAddress->email = $order->get_billing_email();

		$params->shippingAddress = new Customer();
		$params->shippingAddress->firstName = $order->get_shipping_first_name();
		$params->shippingAddress->lastName = $order->get_shipping_last_name();
		$params->shippingAddress->company = $order->get_shipping_company();
		$params->shippingAddress->addressLine1 = $order->get_shipping_address_1();
		$params->shippingAddress->addressLine2 = $order->get_shipping_address_2();
		$params->shippingAddress->city = $order->get_shipping_city();
		$params->shippingAddress->state = $order->get_shipping_state();
		$params->shippingAddress->postcode = $order->get_shipping_postcode();
		$params->shippingAddress->country = $order->get_shipping_country();
		if (method_exists(\WC_Order::class, 'get_shipping_phone') === true)
			$params->shippingAddress->phone = $order->get_shipping_phone();
		else
			$params->shippingAddress->phone = $order->get_billing_phone();
		//$params->shippingAddress->email = $order->get_shipping_email(); // doesn't exist

		$items = $order->get_items();
		$tax = new \WC_Tax();
		foreach ($items as $item) {
			$product = new Product();
			$wcProduct = wc_get_product($item->get_product_id());
			if (!$wcProduct) {
				$product->name = $item->get_name();
				$params->products[] = $product;
				continue;
			}

			$product->sku = $wcProduct->get_sku();
			$product->name = $wcProduct->get_name();
			$product->price = $wcProduct->get_price();

			$taxes = $tax->get_rates($wcProduct->get_tax_class());
			if (!empty($taxes)) {
				$rates = array_shift($taxes);
				if (!empty($rates)) // take item rate
					$product->tax = array_shift($rates);
			}
			$params->products[] = $product;
		}

		$params->callbackUrl = sprintf('%swp-json/verifiedpay/v1/register-payment-cb', site_url('/'));

		return $params;
	}

	protected function addPaymentParamsToOrder(\WC_Order $order): VerifiedPayPaymentParams {
		$verifiedPay = VerifiedPay::getInstance();
		$gateway = $verifiedPay->getGateway();
		//$settings = $verifiedPay->getSettings();
		$params = $this->payParamsFromWcOrder($order);
		$verifiedTxId = $gateway->generateVerifiedTxId($params);
		$order->add_meta_data('_vp_tx_id', $verifiedTxId, true);
		$order->save_meta_data();

		return $params;
	}
	
	protected function clearCustomSessionVariables() {
		// shouldn't be needed since the whole session gets destroyed eventually, but let's be clean
		if (!$this->session)
			return;
		$keys = array(/*"orderID"*/); // don't remove the order ID because we need it to show success message
		foreach ($keys as $key) {
			//$this->session->__unset($key); // unreliable // TODO why?
			$this->session->set($key, null);
		}
	}
	
	protected function getFrontendDefaultTitle(): string {
		return __('Credit Card (Verified-Pay)', 'vpay');
	}
	
	protected function getFrontendDefaultDescription(): string {
		$verifiedPay = VerifiedPay::getInstance();
		$settings = $verifiedPay->getSettings();

		if ($settings->get('voucher_store') !== true)
			return __('Secure Credit Card payments online.', 'vpay');
		return __('Click here to buy your Voucher at over 5% discount.', 'vpay');
	}
}
?>