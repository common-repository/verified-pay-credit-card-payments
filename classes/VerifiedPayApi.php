<?php
namespace Vpay\VerifiedPay;

class VerifiedPayApiRes {
	public $error = false;
	public $errorMsg = '';
	public $data = array();
	
	public function setError(string $msg/*, int $code*/) {
		$this->error = true;
		$this->errorMsg = $msg;
	}
}

class VerifiedPayApi {
	/** @var VerifiedPayApi */
	private static $instance = null;
	/** @var VerifiedPay */
	protected $plugin;
	
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
		// init hooks
		/*
		$amountParam = array(
						'required' => true,
						'type' => 'number', // valid types: array, boolean, integer, number, string
						'sanitize_callback' => array( self::$instance, 'sanitizeFloatParam' ),
						'description' => __( 'The amount that has been paid.', 'vpay' ),
					);
		$postIDParam = array(
						'required' => true,
						'type' => 'string',
						'sanitize_callback' => array( self::$instance, 'sanitizeStringParam' ),
						'description' => __( 'The Post ID of the WP post (or page) this payment was made.', 'vpay' ),
					);
		$txIDParam = array(
						'required' => true,
						'type' => 'string',
						'sanitize_callback' => array( self::$instance, 'sanitizeStringParam' ),
						'description' => __( 'The transaction ID of the gateway.', 'vpay' ),
					);
		$typeParam = array(
						'required' => false,
						'type' => 'string',
						'default' => 'WP', // see rest_get_allowed_schema_keywords()
						'sanitize_callback' => array( self::$instance, 'sanitizeStringParam' ),
						'validate_callback' => array( self::$instance, 'validatePaymentTypeParam' ),
						'description' => __( 'The payment type: WP|WC|RCP', 'vpay' ),
					);
		*/
		register_rest_route( 'verifiedpay/v1', '/register-payment-cb', array(
			array(
				'methods' => \WP_REST_Server::CREATABLE,
				'permission_callback' => array( self::$instance, 'apiPermissionCallback' ),
				'callback' => array( self::$instance, 'registerPayment' ),
				'args' => array(
//					'amount' => $amountParam,
//					'currency' => $currencyParam,
//					'postID' => $postIDParam,
//					'txID' => $txIDParam,
//					'type' => $typeParam,
				)
			)
		) );

		$idsParam = array(
			'required' => true,
			'type' => 'array',
			'sanitize_callback' => array( self::$instance, 'sanitizeArrayParam' ),
			'description' => __( 'The order IDs to return.', 'vpay' ),
		);
		register_rest_route( 'verifiedpay/v1', '/get-orders', array(
			array(
				'methods' => \WP_REST_Server::READABLE,
				'permission_callback' => array( self::$instance, 'serverPermissionCallback' ),
				'callback' => array( self::$instance, 'getOrders' ),
				'args' => array(
					'ids' => $idsParam,
				)
			)
		) );

		$limitParam = array(
			'required' => true,
			'type' => 'number',
			'sanitize_callback' => array( self::$instance, 'sanitizeIntParam' ),
			'description' => __( 'The max number of orders to return.', 'vpay' ),
		);
		$offsetParam = array(
			'required' => true,
			'type' => 'number',
			'sanitize_callback' => array( self::$instance, 'sanitizeIntParam' ),
			'description' => __( 'The number of orders to skip from results.', 'vpay' ),
		);
		$sortColParam = array(
			'required' => false,
			'type' => 'string',
			'sanitize_callback' => array( self::$instance, 'sanitizeStringParam' ),
			'description' => __( 'The attribute to sort orders by.', 'vpay' ),
			'default' => 'ID',
		);
		$sortParam = array(
			'required' => false,
			'type' => 'string',
			'sanitize_callback' => array( self::$instance, 'sanitizeStringParam' ),
			'description' => __( 'The direction to sort orders: ASC|DESC', 'vpay' ),
			'default' => 'ASC',
		);
		register_rest_route( 'verifiedpay/v1', '/list-orders', array(
			array(
				'methods' => \WP_REST_Server::READABLE,
				'permission_callback' => array( self::$instance, 'serverPermissionCallback' ),
				'callback' => array( self::$instance, 'listOrders' ),
				'args' => array(
					'limit' => $limitParam,
					'offset' => $offsetParam,
					'sort_col' => $sortColParam,
					'sort' => $sortParam,
				)
			)
		) );
	}
	
	public function registerPayment(\WP_REST_Request $request) {
		/*
		$type = $request->get_param('type');
		if ($type === 'WC')
			return $this->registerWoocommercePayment($request);
		else if ($type === 'RCP')
			return $this->registerRestrictContentProPayment($request);
		*/
		$json = $request->get_json_params();
		$response = new VerifiedPayApiRes();
		$settings = $this->plugin->getSettings();
		if (!isset($json['token']) || $json['token'] !== $settings->get('secretToken')) {
			$response->error = true;
			$response->errorMsg = 'Invalid token';
		}
		else if (!isset($json['payment']) || !isset($json['payment']['tx_id']) || !isset($json['payment']['status']))  {
			$response->error = true;
			$response->errorMsg = 'Missing TX ID';
		}
		else {
			$txIdParts = explode('-', $json['payment']['tx_id'], 2);
			return $this->registerWoocommercePayment((int)$txIdParts[0], $json['payment']);
		}
		
		$wpRes = rest_ensure_response($response);
		$this->addNoCacheHeaders($wpRes);
		return $wpRes;
	}

	public function getOrders(\WP_REST_Request $request) {
		$ids     = $request->get_param('ids');
		$orders = [];
		foreach ($ids as $id) {
			$id = (int)$id;
			$order = wc_get_order($id);
			if ($order)
				$orders[] = $this->formatOrder($order);
		}

		$response = new VerifiedPayApiRes();
		$response->data = $orders;
		$wpRes = rest_ensure_response($response);
		$this->addNoCacheHeaders($wpRes);
		return $wpRes;
	}

	public function listOrders(\WP_REST_Request $request) {
		$limit = $request->get_param('limit');
		$memoryLimit = @ini_get('memory_limit');
		if (empty($memoryLimit))
			$limit = min($limit, 100);
		$memoryLimitBytes = return_bytes($memoryLimit);
		if ($memoryLimitBytes < 64*1024*1024)
			$limit = min($limit, 100);
		//@set_time_limit(0);

		$orders = wc_get_orders([
			'limit' => $limit,
			'offset' => $request->get_param('offset'),
			'orderby' => $request->get_param('sort_col'),
			'order' => $request->get_param('sort'),
		]);
		foreach ($orders as &$order) {
			if ($order instanceof \WC_Order) // skip \WC_Order_Refund
				$order = $this->formatOrder($order);
		}

		$response = new VerifiedPayApiRes();
		$response->data = $orders;
		$wpRes = rest_ensure_response($response);
		$this->addNoCacheHeaders($wpRes);
		return $wpRes;
	}
	
	public function apiPermissionCallback(\WP_REST_Request $request) {
		return true; // everyone can access this for now
	}

	public function serverPermissionCallback(\WP_REST_Request $request) {
		$settings = $this->plugin->getSettings();
		$auth = $request->get_header('X-Auth');
		if (empty($auth) || $auth !== $settings->get('secretToken'))
			return false;
		return true;
	}
	
	public function sanitizeStringParam( $value, \WP_REST_Request $request, $param ) {
		return trim( $value );
	}
	
	public function sanitizeFloatParam( $value, \WP_REST_Request $request, $param ) {
		return (float)trim( $value );
	}
	
	public function sanitizeIntParam( $value, \WP_REST_Request $request, $param ) {
		return (int)trim( $value );
	}

	public function sanitizeArrayParam( $value, \WP_REST_Request $request, $param ) {
		return (array)$value;
	}
	
	public function validatePaymentTypeParam( $value, \WP_REST_Request $request, $param ) {
		$type = trim( $value );
		switch ($type) {
			case 'WP':
			case 'WC':
				return true;
		}
		return new \WP_Error("Invalid value for 'type' parameter.");
	}
	
	protected function registerWoocommercePayment(int $orderID, array $paymentArr) {
		$response = new VerifiedPayApiRes();
		if (class_exists('Vpay\VerifiedPay\WcGateway') === false) {
			$response->setError('WooCommerce is not installed.');
			$wpRes = rest_ensure_response($response);
			$this->addNoCacheHeaders($wpRes);
			return $wpRes;
		}
		
		$order = null;
		try {
			//$orderID = $request->get_param('postID');
			//$orderID = explode('-', $orderID); // there is only 1 order per page, so 2nd param (counter is 0)
			$order = new \WC_Order($orderID);
		}
		catch (\Exception $e) { // invalid order exception
			$response->setError('There is no order with this ID');
			$wpRes = rest_ensure_response($response);

			$this->addNoCacheHeaders($wpRes);
			return $wpRes;
		}
		
		$wcGateway = WcGateway::getInstance(); // TODO check status and prevent updating again?
		//$order->add_meta_data('_vp_payment_token', $paymentArr['tx_id'], true);
		$order->add_meta_data('vp_coupon', $paymentArr['coupon_code'], true);
		$wcGateway->setOrderDone($order, $paymentArr['status']);
		
		$wpRes = rest_ensure_response($response);
		$this->addNoCacheHeaders($wpRes);
		return $wpRes;
	}

	protected function formatOrder(\WC_Order $order): array {
		// see get_formatted_item_data()
		//$orderArr = (array)$order;
		$orderArr = [
			'id' => $order->get_id(),
			//'number' => $order->get_order_number(),
			'status' => $order->get_status(),
			'currency' => $order->get_currency(),
			'total' => $order->get_total(),
			'total_tax' => $order->get_total_tax(),
			'payment_method' => $order->get_payment_method(),
			'payment_method_title' => $order->get_payment_method_title(),
			'created_via' => $order->get_created_via(),
			'billing' => $order->get_address('billing'),
			'shipping' => $order->get_address('shipping'),
			'date_created' => 0,
			'date_modified' => 0,
			'date_paid' => 0,
			'date_completed' => 0,
			'customer_id' => $order->get_customer_id(),
			'customer_ip_address' => $order->get_customer_ip_address(),
			'customer_user_agent' => $order->get_customer_user_agent(),
			'origin' => [ // see output_origin_column()
				'source_type' => $order->get_meta('_wc_order_attribution_source_type'),
				'utm_source' => $order->get_meta('_wc_order_attribution_utm_source'),
			],
			'vpay' => [
				//'token' => $order->get_meta('_vp_payment_token'),
				'token' => $order->get_meta('_vp_tx_id'),
				'coupon_code' => $order->get_meta('vp_coupon'), // only for paid
			],
		];
		$created = $order->get_date_created();
		if ($created)
			$orderArr['date_created'] = $created->getTimestamp();
		$modified = $order->get_date_modified();
		if ($modified)
			$orderArr['date_modified'] = $modified->getTimestamp();
		$paid = $order->get_date_paid();
		if ($paid)
			$orderArr['date_paid'] = $paid->getTimestamp();
		$completed = $order->get_date_completed();
		if ($completed)
			$orderArr['date_completed'] = $completed->getTimestamp();
		$orderArr['items'] = [];
		foreach ($order->get_items() as $item) {
			$orderArr['items'][] = [
				'id' => $item->get_id(),
				'name' => $item->get_name(),
				'product_id' => $item->get_product_id(),
				'variation_id' => $item->get_variation_id(),
				'quantity' => $item->get_quantity(),
				'total' => $item->get_total(),
				'total_tax' => $item->get_total_tax(),
				//'taxes' => $item->get_taxes(),
				//'meta_data' => $item->get_meta_data(),
			];
		}

		$insights = $order->get_meta('_os_insights');
		$orderArr['meta_data'] = [
			'geo_insights' => $insights ? $insights : null,
		];
		return $orderArr;
	}
	
	protected function registerRestrictContentProPayment(\WP_REST_Request $request) {
		$response = new VerifiedPayApiRes();
		// is processed in process_webhooks() of their implementation
		erifiedPay::notifyErrorExt("Received unexpected Ajax callback for RestrictContentPro", "");
		$wpRes = rest_ensure_response($response);
		$this->addNoCacheHeaders($wpRes);
		return $wpRes;
	}
	
	protected function addNoCacheHeaders(\WP_REST_Response $wpRes) {
		$wpRes->header('Cache-Control', 'no-cache, private, must-revalidate, max-stale=0, post-check=0, pre-check=0, no-store');
	}
}
?>