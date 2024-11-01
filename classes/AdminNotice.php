<?php
namespace Vpay\VerifiedPay;

class AdminNotice {
	/** @var array Assoc array of variables */
	public $tplVars;
	/** @var string */
	public $noticeLevel;
	/** @var bool */
	public $dismissible;
	/** @var bool */
	public $echo;
	/** @var array An assoc array of allowed Html tags when escaping. Taken from $allowedposttags, see wp_kses_allowed_html() */
	protected static $allowdHtmlTags = array(
		'a'
		       => array(
			'href'     => true,
			'rel'      => true,
			'rev'      => true,
			'name'     => true,
			'target'   => true,
			'download' => array(
				'valueless' => 'y',
			),
		),
		'div' => array(
			'id'     => true,
			'class'  => true,
		),
		'p' => array(
			'id'     => true,
			'class'  => true,
		),
		'span' => array(
			'id'         => true,
			'class'      => true,
			'title'      => true,
			'data-desc'  => true,
		),
	);
	
	/**
     * Creates a notice for the top in the admin panel.
     * @param array $tplVars Assoc array of variables to be included in HTML. Keys: msg|link
     * @param string $noticeLevel The level (color) of the error: error, warning, success, or info
     * @param boolean $dismissible True if the error message can be closed by the user.
     * @param boolean $echo True to echo the message directly, false to return the string.
     */
	public function __construct(array $tplVars, string $noticeLevel, $dismissible = true, $echo = true) {
		$this->tplVars = $tplVars;
		$this->noticeLevel = $noticeLevel;
		$this->dismissible = $dismissible;
		$this->echo = $echo;
	}
	
	public function print() {
		$tplHtml = $this->getTemplate('adminNotice.php', $this->tplVars);
		$dismissibleClass = $this->dismissible === true ? ' is-dismissible' : '';
		$html = sprintf('<div class="notice notice-%s%s">
	          %s
	         </div>', $this->noticeLevel, $dismissibleClass, $tplHtml);
		if ($this->echo === false)
			return $html;
		echo wp_kses($html, static::$allowdHtmlTags);
	}
	
	public function urlEncode() {
		return static::base64UrlEncode(gzencode(json_encode($this)));
	}
	
	public static function urlDecode(string $str) {
		// using PHP unserialize() with user data is not safe!
		$stdClassObject = json_decode(gzdecode(static::base64UrlDecode($str)));
		return new AdminNotice($stdClassObject->tplVars, $stdClassObject->noticeLevel, $stdClassObject->dismissible, $stdClassObject->echo);
	}
	
	public static function base64UrlEncode($data) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_'), '=');
	}
	
	public static function base64UrlDecode($data) {
		return base64_decode( strtr( $data, '-_', '+/') . str_repeat('=', 3 - ( 3 + strlen( $data )) % 4 ));
	}

	/**
	 * Returns a html template file as string.
	 * @param string $templateFile The file name relative to the plugin's "tpl" directory.
	 * @param array $tplVars associative array with optional additional variables
	 * @return string the html
	 */
	public function getTemplate(string $templateFile, array $tplVars = array()): string {
		ob_start();
		include VPAY__PLUGIN_DIR . 'tpl/' . $templateFile;
		$html = ob_get_contents();
		ob_end_clean();
		return $html;
	}
}
?>