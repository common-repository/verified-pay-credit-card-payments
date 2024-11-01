<?php
namespace Vpay\VerifiedPay;

class PaymentConfig {
	/** @var int  */
	public $accountId = 0;

	/** @var array  */
	public $gateways = array();

	public static function fromJson($json): PaymentConfig {
		$instance = new PaymentConfig();
		foreach ($json as $key => $value) {
			if (isset($instance->$key))
				$instance->$key = $value;
		}
		return $instance;
	}

	public function showPaypal(): bool {
		if (count($this->gateways) == 1 && $this->gateways[0]->name == "PAYPAL")
			return true;
		return false;
	}

	public function allowIframe(): bool {
		if (count($this->gateways) == 1 && $this->gateways[0]->frameHeight == 0)
			return false;
		return true;
	}

	public function getFrameHeight(): int {
		$max = 500;
		foreach ($this->gateways as $gw) {
			if ($gw->frameHeight > $max)
				$max = $gw->frameHeight;
		}
		return $max;
	}
}