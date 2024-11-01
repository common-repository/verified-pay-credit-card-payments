<?php
if (function_exists('pre_print_r') === false) {
	function pre_print_r($expression, $return = false) {
		$output = "<pre>" . esc_html(print_r($expression, true)) . "</pre><br>\n";
		if ($return)
			return $output;
		echo esc_html($output);
	}
}

if (function_exists('debugPrint') === false) {
	function debugPrint($name, $obj) {
		echo "<pre>$name:\r\n" . esc_html(print_r($obj, true)) . "\r\n</pre>";
	}
}

if (function_exists('return_bytes') === false) {
	/**
	 * Converts shorthand memory notation value to bytes
	 * From http://php.net/manual/en/function.ini-get.php
	 *
	 * @param $val Memory size shorthand notation string
	 */
	function return_bytes(string $val): int {
		$val = trim($val);
		$last = strtolower($val[strlen($val)-1]);
		$val = substr($val, 0, -1);
		switch($last) {
			// The 'G' modifier is available since PHP 5.1.0
			case 'g':
				$val *= 1024;
			case 'm':
				$val *= 1024;
			case 'k':
				$val *= 1024;
		}
		return $val;
	}
}
?>