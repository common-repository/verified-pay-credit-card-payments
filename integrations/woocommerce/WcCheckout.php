<?php
namespace Vpay\VerifiedPay;

class WcCheckout {
	public function __construct() {
		// /?wc-ajax=update_order_review
		add_filter('woocommerce_available_payment_gateways', [$this, 'filterGateways'], 9999991, 1);
		// TODO add multiple and send chosen params back
		// TODO trigger on phone?
		// TODO first page load
	}

	public function filterGateways(array $gateways): array {
		if ( is_admin() ) {
			return $gateways;
		}

		$settings = Settings::getInstance();
		if ($settings->get('ai_score') && WC()->session && isset($_POST['post_data'])) { // woocommerce_checkout_update_order_review $post_data
			$dataRaw = wp_unslash($_POST['post_data']);
			$data = [];
			if (is_string($dataRaw))
				parse_str($dataRaw, $data);
			else
				$data = $dataRaw;
			$params = new TrustScoreParams();
			$params->ip = \WC_Geolocation::get_ip_address();
			//$params->referer = $dataRaw['_wp_http_referer']; // relative path only
			if (!empty($data['wc_order_attribution_referrer']))
				$params->referer = $data['wc_order_attribution_referrer'];
			else if (!empty($data['wc_order_attribution_session_entry']))
				$params->referer = $data['wc_order_attribution_session_entry'];
			if (!empty($data['wc_order_attribution_user_agent']))
				$params->userAgent = $data['wc_order_attribution_user_agent'];
			$params->billingAddress = Customer::fromArray($data, 'billing_');
			$params->shippingAddress = Customer::fromArray($data, 'shipping_');

			$trust = $this->checkTrust($params);
			if ($trust) {
				$addCond = $trust->trust_score < VerifiedPayGateway::VPAY_SCORE_RECENT/* && !empty($trust->gateway)*/;
				$removeCond = $trust->trust_score >= VerifiedPayGateway::VPAY_SCORE_RISKY;
				$found = false;
				/**
				 * @var  string $key
				 * @var  \WC_Payment_Gateway $gateway
				 */
				foreach ($gateways as $key => $gateway) {
					if ($key !== WcGateway::PLUGIN_ID)
						continue;
					$found = true;
					break;
				}
				if (!$found && $addCond)
					$gateways[WcGateway::PLUGIN_ID] = WcGateway::getInstance();
				else if ($found && $removeCond)
					unset($gateways[WcGateway::PLUGIN_ID]);
				if ($addCond && !empty($trust->gateway)) {
					$gateways[WcGateway::PLUGIN_ID]->icon = plugins_url( sprintf( 'img/%s.png', strtolower( $trust->gateway ) ), VPAY__PLUGIN_DIR . 'verified-pay.php' );
					if (!empty($trust->title) && stripos($gateways[WcGateway::PLUGIN_ID]->title, $trust->title) === false) {
						$gateways[ WcGateway::PLUGIN_ID ]->title = $trust->title;
						if ( ! empty( $trust->description ) ) {
							$gateways[ WcGateway::PLUGIN_ID ]->description = $trust->description;
						}
					}
				}
				else
					$this->keepGatewayDefaults($gateways, $trust);
			}
			else
				$this->keepGatewayDefaults($gateways, $trust);
		}

		return $gateways;
	}

	/**
	 * @param \WC_Payment_Gateway[] $gateways
	 * @param $trust
	 *
	 * @return void
	 */
	protected function keepGatewayDefaults(array $gateways, $trust): void {
		if (!isset($gateways[WcGateway::PLUGIN_ID]))
			return;
		$settings = Settings::getInstance();

		/** @var PaymentConfig $gatwayConf */
		//$gatwayConf = $settings->get('gatewayConf');
		$img = $settings->get('show_amex_icon') === true ? 'cc_32_all' : 'cc_32';
		if ($trust && !empty($trust->gateway))
			$img = strtolower( $trust->gateway );
		$gateways[WcGateway::PLUGIN_ID]->icon = plugins_url( sprintf( 'img/%s.png', $img ), VPAY__PLUGIN_DIR . 'verified-pay.php' );
		$gateways[WcGateway::PLUGIN_ID]->title = $gateways[WcGateway::PLUGIN_ID]->settings['title'];
		$gateways[WcGateway::PLUGIN_ID]->description = $gateways[WcGateway::PLUGIN_ID]->settings['description'];
	}

	protected function checkTrust(TrustScoreParams $params) {
		$vpay = VerifiedPay::getInstance();
		$gateway = $vpay->getGateway();
		try {
			$trust = $gateway->getTrustScore($params);
			if (empty($trust))
				throw new \Exception('Received invalid trust');
			return $trust;
		}
		catch (\Exception $e) {
			VerifiedPay::notifyErrorExt('Error checking customer trust', $e->getMessage(), null, true);
		}
		return null;
	}
}