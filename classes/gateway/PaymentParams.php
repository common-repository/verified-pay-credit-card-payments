<?php
namespace Vpay\VerifiedPay;

class VerifiedPayPaymentParams {
	/** @var int */
	public $postID = 0;

	/** @var float */
	public $amount = 0.0;

	/** @var string */
	public $currency = '';

	/** @var string
	 * Usually: Order|Post
	 * A custom type if $postID relates to another object (for example User-ID). Only used for display purpose on invoice history.
	 */
	public $type = '';

	/** @var string */
	public $returnUrl = '';

	/** @var string */
	public $callbackUrl = '';

	/** @var bool Whether to do phone verification before payment. Gateway can override this. */
	public $skipPhoneVerify = false;

	/** @var string */
	public $customerToken = '';

	/** @var Customer */
	public $billingAddress = null;

	/** @var Customer */
	public $shippingAddress = null;

	/** @var Product[] */
	public $products = array();

	public function __construct(int $postID, float $amount, string $currency) {
		$this->postID = $postID;
		$this->amount = $amount;
		$this->currency = $currency;
	}
}

class TrustScoreParams {
	/** @var Customer */
	public $billingAddress = null;

	/** @var Customer */
	public $shippingAddress = null;

	/** @var Product[] */
	public $products = array();

	/** @var string  */
	public $ip = '';
	/** @var string  */
	public $referer = '';
	/** @var string  */
	public $userAgent = '';
	/** @var string  */
	public $acceptLanguage = '';

	public function __construct() {
		$this->userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
		$this->acceptLanguage = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
		//$this->ip = getIp();
	}

	public function getID(): string {
		$data = json_encode($this);
		return hash('sha256', $data);
	}
}

class Customer {
	/** @var string */
	public $firstName = '';

	/** @var string */
	public $lastName = '';

	/** @var string */
	public $company = '';

	/** @var string */
	public $addressLine1 = '';

	/** @var string */
	public $addressLine2 = '';

	/** @var string */
	public $city = '';

	/** @var string */
	public $postcode = '';

	/** @var string */
	public $state = '';

	/** @var string */
	public $country = '';

	/** @var string */
	public $phone = '';

	/** @var string */
	public $email = '';

	/** @var string */
	//public $notes = '';

	public static function fromArray(array $data, string $prefix = ''): Customer {
		$customer = new Customer();
		$fields = [
			$prefix . 'first_name' => 'first_name',
			$prefix . 'last_name' => 'last_name',
			$prefix . 'company' => 'company',
			$prefix . 'address_1' => 'address_1',
			$prefix . 'address_2' => 'address_2',
			$prefix . 'city' => 'city',
			$prefix . 'postcode' => 'postcode',
			$prefix . 'state' => 'state',
			$prefix . 'country' => 'country',
			$prefix . 'phone' => 'phone',
			$prefix . 'email' => 'email',
			//$prefix . 'notes' => 'notes,
		];
		foreach ($fields as $field => $const) {
			if (!isset($data[$field]))
				continue;
			switch ($const) {
				case 'first_name':
					$customer->firstName = trim($data[$field]);
					break;
				case 'last_name':
					$customer->lastName = trim($data[$field]);
					break;
				case 'company':
					$customer->company = trim($data[$field]);
					break;
				case 'address_1':
					$customer->addressLine1 = trim($data[$field]);
					break;
				case 'address_2':
					$customer->addressLine2 = trim($data[$field]);
					break;
				case 'city':
					$customer->city = trim($data[$field]);
					break;
				case 'postcode':
					$customer->postcode = trim($data[$field]);
					break;
				case 'state':
					$customer->state = trim($data[$field]);
					break;
				case 'country':
					$customer->country = trim($data[$field]);
					break;
				case 'phone':
					$customer->phone = trim($data[$field]);
					break;
				case 'email':
					$customer->email = trim($data[$field]);
					break;
			}
		}
		return $customer;
	}

	public function toArray(string $prefix = ''): array {
		return [
			$prefix . 'first_name' => $this->firstName,
			$prefix . 'last_name' => $this->lastName,
			$prefix . 'company' => $this->company,
			$prefix . 'address_line_1' => $this->addressLine1,
			$prefix . 'address_line_2' => $this->addressLine2,
			$prefix . 'city' => $this->city,
			$prefix . 'postcode' => $this->postcode,
			$prefix . 'state' => $this->state,
			$prefix . 'country' => $this->country,
			$prefix . 'phone' => $this->phone,
			$prefix . 'email' => $this->email,
			//$prefix . 'notes' => $this->notes,
		];
	}
}

class Product {
	/** @var string */
	public $sku = '';

	/** @var string */
	public $name = '';

	/** @var float */
	public $price = 0.0;

	/** @var float */
	public $tax = 0.0;
}
?>