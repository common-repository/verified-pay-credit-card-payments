<?php
namespace Vpay\VerifiedPay;
require_once VPAY__PLUGIN_DIR . 'classes/gateway/PaymentConfig.php';
require_once VPAY__PLUGIN_DIR . 'classes/gateway/PaymentParams.php';

class VerifiedPayGateway {
	const API_ENDPOINT = 'https://verified-pay.com';
	//const API_ENDPOINT = 'http://danielmac.local:2929';
	const TIMEOUT_SEC = 10;
	
	const FRAME_URL = '/pay-frame?token=%s&tx_id=%s&amount=%s&currency=%s&desc=%s&return=%s&callback=%s&skip_phone_verify=%s&customer_token=%s&bill_first_name=%s&bill_last_name=%s&bill_last_company=%s&bill_address_line_1=%s&bill_address_line_2=%s&bill_city=%s&bill_postcode=%s&bill_state=%s&bill_country=%s&bill_phone=%s&bill_email=%s&products=%s';
	const PAGE_URL = '/pay?token=%s&tx_id=%s&amount=%s&currency=%s&desc=%s&return=%s&callback=%s&skip_phone_verify=%s&customer_token=%s&bill_first_name=%s&bill_last_name=%s&bill_last_company=%s&bill_address_line_1=%s&bill_address_line_2=%s&bill_city=%s&bill_postcode=%s&bill_state=%s&bill_country=%s&bill_phone=%s&bill_email=%s&products=%s';

	const VPAY_SCORE_TRUSTED = 1;
	const VPAY_SCORE_RECENT = 60;
	const VPAY_SCORE_RISKY = 110;
	
	/** @var string */
	protected $publicToken = '';
	/** @var string */
	protected $secretToken = '';
	/** @var string */
	protected $siteUrl = '';
	/** @var string */
	protected $host = '';

	/** @var array  */
	protected $trustScoreCache = array(); // (ID, score)
	
	public function __construct(string $publicToken, string $secretToken, string $siteUrl = '') {
		// TODO reject/warn if values are empty?
		$this->publicToken = $publicToken;
		$this->secretToken = $secretToken;
		$this->siteUrl = $siteUrl;
		if (!empty($this->siteUrl)) {
			$urlParts = @parse_url($this->siteUrl);
			if ($urlParts !== false)
				$this->host = str_replace('www.', '', $urlParts['host']);
		}
		
		//$payment = $this->getPayment('your-id');
		//pre_print_r("payment");
		//pre_print_r($payment);
		//$customer = $this->registerCustomer(new \WC_Order($_GET['order']));
		//pre_print_r($customer);
	}

	public function generateVerifiedTxId(VerifiedPayPaymentParams $params): string {
		return sprintf('%d-%s', $params->postID, $this->hashParams($params));
	}
	
	/**
	 * Gets a pay frame URL for the specified parameters.
	 * VerifiedPayPaymentParams $params
	 * @return string
	 */
	public function getPayFrameUrl(VerifiedPayPaymentParams $params): string {
		return $this->addPaymentParamsToUrl(static::API_ENDPOINT . static::FRAME_URL, $params);
	}
	
	/**
	 * Get the full-page payment URL for the user to make a payment.
	 * @param VerifiedPayPaymentParams $params
	 * @return string
	 */
	public function getPayPageUrl(VerifiedPayPaymentParams $params): string {
		// TODO better use form params ?
		return $this->addPaymentParamsToUrl(static::API_ENDPOINT . static::PAGE_URL, $params);
	}

	protected function addPaymentParamsToUrl(string $url, VerifiedPayPaymentParams $params): string {
		$type = $params->type;
		if (empty($type))
			$type = __('Post', 'vpay');

		// token=%s&tx_id=%s&amount=%.2f&currency=USD&desc=%s&skip_phone_verify=1&customer_token=%s&bill_first_name=%s&bill_last_name=%s&bill_address_line_1=%s&bill_city=%s&bill_country=%s
		//$verifiedTxId = sprintf("%s-%d-%s", $params->postID, time(), static::getRandomString(4));
		$verifiedTxId = $this->generateVerifiedTxId($params);
		$currency = strtoupper($params->currency);
		//$desc = trim(sprintf("%s %d @ %s", $type, $params->postID, $this->host));
		//$desc = sprintf(__("e-Voucher purchase for order %d at %s", 'vpay'), $params->postID, $this->host);
		$desc = sprintf(__("Payment for order %d at %s", 'vpay'), $params->postID, $this->host);
		$amountFormat = number_format($params->amount, 2, '.', '');
		$bill = $params->billingAddress;
		$url = sprintf($url, $this->publicToken, $verifiedTxId, $amountFormat, $currency, static::urlEncode($desc),
			static::urlEncode($params->returnUrl), static::urlEncode($params->callbackUrl),
			'1', $params->customerToken, static::urlEncode($bill->firstName), static::urlEncode($bill->lastName), static::urlEncode($bill->company), static::urlEncode($bill->addressLine1), static::urlEncode($bill->addressLine2),
			static::urlEncode($bill->city), static::urlEncode($bill->postcode), static::urlEncode($bill->state), static::urlEncode($bill->country), static::urlEncode($bill->phone), static::urlEncode($bill->email),
			static::base64UrlEncode(json_encode($params->products)),
		);

		return $url;
	}
	
	/**
	 * Gets a pay frame URL with placeholders: {verifiedTxId}, {amount}, {currency}, {desc}
	 * @return string
	 */
	/*
	public function getNamedPayFrameUrl(): string {
		$url = sprintf(static::API_ENDPOINT . static::FRAME_URL, $this->publicToken, '{verifiedTxId}', '{amount}', '{currency}', '{desc}');
		return $url;
	}
	*/
	
	/**
	 * Returns the internal payment ID (or PostID when creating here) from the Verified-Pay reference/description.
	 * @param string $payReference
	 * @return int
	 */
	public function getPaymentId(string $payReference): int {
		$stop = mb_strpos($payReference, '@');
		if ($stop === false)
			return 0;
		$firstPart = mb_substr($payReference, 0, $stop);
		$id = preg_replace("/[^0-9]+/", "", $firstPart);
		return intval($id);
	}

	public function getMerchantID(): int {
		$tokenParts = explode('-', $this->publicToken, 2);
		return (int)$tokenParts[0];
	}
	
	public function getPublicToken(): string {
		return $this->publicToken;
	}
	
	public function getSecretToken(): string {
		return $this->secretToken;
	}
	
	/**
	 * Returns the payment with Associated ID (Post/Order ID). It returns null if no such payment exists.
	 * For a list of properties see: https://verified-pay.com/pub/docs/#get-a-single-payment
	 * @param int|string $txID
	 * @return object
	 */
	public function getPayment($txID) {
		$checkUrl = sprintf("%s/api/v1/get-payment/%s", static::API_ENDPOINT, $txID);
		$res = wp_remote_get($checkUrl, array(
				'timeout' => static::TIMEOUT_SEC,
				'headers' => array(
						'Accept' => 'application/json',
						'Authorization' => $this->secretToken,
				),
		));
		if ($res instanceof \WP_Error) {
			return null;
		}
		$body = wp_remote_retrieve_body($res);
		$json = json_decode($body);
		if (empty($json) || !isset($json->data) || !isset($json->data->id)) {
			return null;
		}
		
		return $json->data;
	}

	/**
	 * Register a customer with the processor. This is needed before submitting a payment.
	 * @param \WC_Order $order
	 * @return object the customer
	 * @throws \Exception An error string if the API returned an error HTTP status code.
	 */
	public function registerCustomer(Customer $customer) {
		// WC_Order has more props than WC_Customer or WP_User
		$checkUrl = sprintf("%s/api/v1/register-customer", static::API_ENDPOINT);
		$res = wp_remote_post($checkUrl, array(
			'timeout' => static::TIMEOUT_SEC,
			'headers' => array(
				'Accept' => 'application/json',
				'Authorization' => $this->secretToken,
			),
			'body' => array(
				'phone_nr' => $customer->phone,
				'email' => $customer->email,
				'referral_id' => $this->getMerchantID(),
				'notes' => '',
				'skip_verify' => true,
				/*
				'bill_first_name' => $order->get_billing_first_name(),
				'bill_last_name' => $order->get_billing_last_name(),
				'bill_address_line_1' => $order->get_billing_address_1(),
				'bill_address_line_2' => $order->get_billing_address_2(),
				'bill_city' => $order->get_billing_city(),
				'bill_postcode' => $order->get_billing_postcode(),
				'bill_state' => $order->get_billing_state(),
				'bill_country' => $order->get_billing_country(),
				'bill_phone' => $order->get_billing_phone(),
				*/
			),
		));
		if ($res instanceof \WP_Error) {
			return null;
		}
		$body = wp_remote_retrieve_body($res);
		$json = json_decode($body);
		//$lastMod = wp_remote_retrieve_header($res, 'last-modified');
		//$status = wp_remote_retrieve_response_code($res);
		if (empty($json) || !isset($json->customer) || !isset($json->customer->ID)) {
			if ($json && isset($json->error))
				throw new \Exception($json->error);
			return null;
		}

		return $json->customer;
	}

	public function getPaymentConfig(string $cms = "") {
		$checkUrl = sprintf("%s/api/v1/pay-cnf", static::API_ENDPOINT);
		$res = wp_remote_post($checkUrl, array(
			'timeout' => static::TIMEOUT_SEC,
			'headers' => array(
				'Accept' => 'application/json',
				'Authorization' => $this->secretToken,
			),
			'body' => [
				'cms' => $cms,
				'url' => $this->siteUrl,
			],
		));
		if ($res instanceof \WP_Error) {
			throw new \Exception($res->get_error_message(), (int)$res->get_error_code());
		}
		$body = wp_remote_retrieve_body($res);
		$json = json_decode($body);
		if (empty($json) || !isset($json->accountId)) {
			if ($json && isset($json->error))
				throw new \Exception($json->error);
			throw new \Exception('unknown response getting pay conf: ' . print_r($json, true));
		}

		return PaymentConfig::fromJson($json);
	}

	public function getTrustScore(TrustScoreParams $params) {
		$id = $params->getID();
		if (isset($this->trustScoreCache[$id]))
			return $this->trustScoreCache[$id];

		$req = [
			'url' => $this->siteUrl,
			'ip' => $params->ip,
			'referer' => $params->referer,
			'user_agent' => $params->userAgent,
			'accept_language' => $params->acceptLanguage,
		];
		if ($params->billingAddress)
			$req = array_merge($req, $params->billingAddress->toArray('bill_'));
		if ($params->shippingAddress)
			$req = array_merge($req, $params->shippingAddress->toArray('ship_'));
		$checkUrl = sprintf("%s/api/v1/pay-risk", static::API_ENDPOINT);
		$res = wp_remote_post($checkUrl, array(
			'timeout' => static::TIMEOUT_SEC,
			'headers' => array(
				'Accept' => 'application/json',
				'Authorization' => $this->secretToken,
			),
			'body' => $req,
		));
		if ($res instanceof \WP_Error) {
			throw new \Exception($res->get_error_message(), (int)$res->get_error_code());
		}
		$body = wp_remote_retrieve_body($res);
		$json = json_decode($body);
		if (empty($json) || !isset($json->trust_score)) {
			if ($json && isset($json->error))
				throw new \Exception($json->error);
			throw new \Exception('unknown response getting trust score: ' . print_r($json, true));
		}

		$this->trustScoreCache[$id] = $json;
		return $json;
	}

	public function getPaymentAdminUrl(string $token): string {
		$accountID = (int)explode('-', $this->publicToken, 2)[0];
		return sprintf("%s/pmt/%s-%d", static::API_ENDPOINT, $token, $accountID);

	}

	protected static function urlEncode(string $url): string {
		$url = str_replace('#', '', $url);
		return urlencode($url);
	}

	protected static function base64UrlEncode($data) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_'), '=');
	}

	protected static function base64UrlDecode($data) {
		return base64_decode( strtr( $data, '-_', '+/') . str_repeat('=', 3 - ( 3 + strlen( $data )) % 4 ));
	}

	public static function isValidCouponCode(string $coupon): bool {
		$matched = preg_match("/^[a-zA-Z0-9]{16}$/", $coupon) === 1;
		if ($matched === false) // publicly display coupons are with dashes
			$matched = preg_match("/^[a-zA-Z0-9]{4}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{4}$/", $coupon) === 1;
		return $matched;
	}

	protected function hashParams(VerifiedPayPaymentParams $params): string {
		//return substr(hash('sha256', $params->postID . $this->secretToken), 0, 12);
		$objStr = print_r($params, true);
		return substr(hash('sha256', $objStr . $this->secretToken), 0, 12);
	}
	
	protected static function getRandomString($len) {
		$chars = '1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
		$max = strlen($chars)-1;
		mt_srand();
		$random = '';
		for ($i = 0; $i < $len; $i++)
			$random .= $chars[mt_rand(0, $max)];
		return $random;
	}
}
?>