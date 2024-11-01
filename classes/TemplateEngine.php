<?php
namespace Vpay\VerifiedPay;

class TemplateEngine {
	/** @var Settings */
	protected  $settings;
	
	/** @var string */
	protected static  $dateFormat = '';
	/** @var string */
	protected static $timeFormat = '';
	/** @var array An associative array of all WP pages with ID as key and title as value. */
	protected static $pageNames = null;
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
							'input' => array(
								'id'         => true,
								'class'      => true,
								'type'      => true,
								'disabled'  => true,
								'value' => true,
								'name' => true,
								'checked' => true,
								'size' => true,
								'tabindex' => true,
								'maxlength' => true,
								'autocomplete' => true,
							),
		);
	
	protected $adminNotices = array();
	
	/** @var array Keep track of all checkboxes. In HTML forms they are not present on submit when unchecked. */
	protected static $allCheckboxes = array();
	/** @var array Keep track of all multi-select inputs. In HTML forms they are not present on submit no value is selected. */
	protected static $allMultiselect = array();
	/** @var array Keep track of all checkbox lists emulating multiselect inputs. In HTML forms they are not present on submit if checkbox is unchecked. */
	protected static $allMulticheck = array();
	
	public function __construct(Settings $settings) {
		$this->settings = $settings;
	}
    
    public function showMetaboxAccount($post = null, $args = array()) {
		$accountTokenPlaceholder = __("123-abcfkq", 'vpay');
    	include VPAY__PLUGIN_DIR . 'tpl/admin/metaboxAccount.php';
    }
    
    public function showMetaboxAdvanced($post = null, $args = array()) {
	    $beforePayPlaceholder = __("text for your customers on payment page...", 'vpay');
    	$cookiePlaceholder = __("your cookie consent text to your visitors...", 'vpay');
    	include VPAY__PLUGIN_DIR . 'tpl/admin/metaboxAdvanced.php';
    }
    
    /**
     * Adds an admin notice to be shown.
     * @param AdminNotice $notice
     */
    public function addAdminNotices(AdminNotice $notice) {
    	$this->adminNotices[] = $notice;
    }
    
    /**
     * Display all admin notices added with addAdminNotices(). Must be called from
     * the 'admin_notices' action.
     */
    public function showAdminNotices() {
    	static $shown = false;
    	if ($shown === true)
    		return; // prevent againt queuing this function as multiple actions
    	$shown = true;
    	foreach ($this->adminNotices as $notice) {
    		$this->showAdminNotice($notice);
    	}
    }
    
    /**
     * Show a notice at the top in the admin panel.
     * @param AdminNotice $notice The notice to show.
     * @return string|void The html scting with the notice or nothing if $echo is true
     */
    public function showAdminNotice(AdminNotice $notice) {
    	$notice->print();
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
	
	/**
	 * Helper function that constructs name attributes for use in form fields.
	 *
	 * Other page implementation classes may wish to construct and use a
	 * get_field_id() method, if the naming format needs to be different.
	 *
	 * @param string $name Field name base
	 * @return string Full field name
	 */
	public function getFieldName( $name ) {
		return sprintf( '%s[%s]', $this->settings->settingsField, $name );
	}

	/**
	 * Echo constructed name attributes in form fields.
	 *
	 * @param string $name Field name base
	 */
	public function fieldName( $name ) {
		echo \esc_attr( $this->getFieldName( $name ) );
	}

	/**
	 * Helper function that constructs id attributes for use in form fields.
	 *
	 * @param string $id Field id base
	 * @return string Full field id
	 */
	public function getFieldId( $id ) {
		return sprintf( '%s[%s]', $this->settings->settingsField, $id );
	}

	/**
	 * Echo constructed id attributes in form fields.
	 *
	 * @param string $id Field id base.
	 * @param boolean $echo Whether to escape echo or just return.
	 * @return string Full field id
	 */
	public function fieldId( $id, $echo = true ) {

		if ( $echo ) {
			echo \esc_attr( $this->getFieldId( $id ) );
		} else {
			return $this->getFieldId( $id );
		}
	}

	/**
	 * Helper function that returns a setting value from this form's settings
	 * field for use in form fields.
	 * Fetches blog option.
	 *
	 * @param string $key Field key
	 * @return string Field value
	 */
	public function getFieldValue( $key ) {
		return $this->getOption( $key/*, $this->settings->settingsField*/ );
	}

	/**
	 * Echo a setting value from this form's settings field for use in form fields.
	 *
	 * @param string $key Field key
	 */
	public function fieldValue( $key ) {
		echo \esc_attr( $this->getFieldValue( $key ) );
	}
	
	/**
	 * Helper function that returns a user's setting value.
	 * @param string $key the setting name
	 * @param int $userID
	 * @param bool $single
	 * @return mixed|boolean|string|array
	 */
	public function getUserFieldValue( string $key, int $userID, bool $single = true ) {
		return get_user_meta($userID, $key, $single);
	}
	
	/**
	 * Echo a user's setting value
	 * @param string $key the setting name
	 * @param int $userID
	 * @param bool $single
	 */
	public function userFieldValue( string $key, int $userID, bool $single = true ) {
		echo \esc_attr( $this->getUserFieldValue( $key, $userID, $single ) );
	}
	
	/**
	 * Return plugin options from the plugin options database.
	 *
	 * @param string  $key       Option name.
	 * @param boolean $use_cache Optional. Whether to use the cache value or not. Defaults to true.
	 * @return mixed The value of this $key in the database.
	 */
	public function getOption( $key, $use_cache = true ) {
		return $this->settings->get($key, $use_cache);
	}

	/**
	 * Echo or return a chechbox fields wrapper.
	 *
	 * @param string $input The input to wrap. Should already be escaped.
	 * @param boolean $echo Whether to escape echo or just return.
	 * @param array $allowedHtml HTML tags that are allowed to be displayed (defaults to only links).
	 * @return string|void Wrapped $input.
	 */
	public function wrapFields( $input = '', $echo = false, $allowedHtml = array() ) {

		if ( is_array( $input ) )
			$input = implode( PHP_EOL, $input );

		if ( $echo ) {
			if (empty($allowedHtml))
				$allowedHtml = static::$allowdHtmlTags;
			echo '<div class="overs-fields">' . wp_kses($input, $allowedHtml) . '</div>';
		} else {
			return '<div class="overs-fields">' . $input . '</div>';
		}
	}
	
	/**
	 * Returns a chechbox wrapper.
	 *
	 * @param string $field_id    The option ID. Must be within the plugin settings.
	 * @param string $label       The checkbox description label.
	 * @param string $description Addition description to place beneath the checkbox.
	 * @param bool   $escape      Whether to escape the label and description.
	 * @param bool   $disabled    Whether to disable the input.
	 * @return string HTML checkbox output.
	 */
	public function makeCheckbox( $field_id = '', $label = '', $description = '', $escape = true, $disabled = false ) {
		static::$allCheckboxes[$field_id] = true;
		if ( $escape ) {
			$description = \esc_html( $description );
			$label = \esc_html( $label );
		}

		$description = $description ? '<p class="description ct-option-spacer">' . $description . '</p>' : '';

		$output = '<span class="ct-toblock">'
					. '<label for="'
						. $this->getFieldId( $field_id ) . '" '
						. ( $disabled ? 'class=ct-disabled ' : '' )
					. '>'
						. '<input '
							. 'type="checkbox" '
							. ( $disabled ? 'class=ct-disabled disabled ' : '' )
							. 'name="' . $this->getFieldName( $field_id ) . '" '
							. 'id="' . $this->getFieldId( $field_id ) . '" '
							. ( $disabled ? '' : $this->getIsConditionalChecked( $field_id ) . ' ' )
							. 'value="1" '
							. \checked( $this->getFieldValue( $field_id ), true, false ) .
						' />'
						. $label
					. '</label>'
				. '</span>'
				. $description
				;

		return $output;
	}

	/**
	 * Return a text field wrapper.
	 * 
	 * @param string $field_id The option ID. Must be within the plugin settings.
	 * @param string $label The checkbox description label.
	 * @param string $description Addition description to place beneath the checkbox.
	 * @param string $placeholder The text field placeholder.
	 * @param bool $escape Whether to escape the label and description.
	 * @return string HTML text field output.
	 */
	public function makeTextfield( $field_id = '', $label = '', $description = '', $placeholder = '', $escape = true ) {

		if ( $escape ) {
			$description = \esc_html( $description );
			$label = \esc_html( $label );
			$placeholder = \esc_attr( $placeholder );
		}

		$description = $description ? '<p class="description ct-option-spacer">' . $description . '</p>' : '';

		$output = '<p>'
					. '<label for="' . $this->fieldId( $field_id ) . '" class="ct-toblock">'
						. '<strong>' . $label . '</strong>'
					. '</label>'
				. '</p>'
				. '<p>'
					. '<input type="text" name="' . $this->fieldName( $field_id ) . '"'
						. ' class="large-text" id="' . $this->fieldId( $field_id ) . '"'
						. ' placeholder="' . $placeholder . '"'
						. ' value="' . $this->getFieldValue( $field_id ) . '"'
					. ' />'
				. '</p>'
				. $description
				;

		return $output;
	}
	
	/**
	 * Return a select box wrapper.
	 * 
	 * @param string $field_id The option ID. Must be within the plugin settings.
	 * @param string $label The checkbox description label.
	 * @param string $description Addition description to place beneath the select.
	 * @param array $options An associative array with option names (keys) and select display values.
	 * @param array $selectOptions An associative array with options for this select item's behaviour. Supported Values:
	 * 		multiselect (bool, default false)
	 * 		multicheck (bool, default false)
	 * 		class (string, default empty)
	 * @param bool $escape Whether to escape the label.
	 * @return string HTML text field output.
	 */
	public function makeSelect( $field_id = '', $label = '', $options = array(), $selectOptions = array(), $escape = true ) {
		$selectOptionsDefaults = array(
				'multiselect' => false,
				'class' => '',
		);
		$selectOptions = wp_parse_args($selectOptions, $selectOptionsDefaults);
		if ($selectOptions['multiselect'] === true)
			static::$allMultiselect[$field_id] = true;
		else if ($selectOptions['multicheck'] === true)
			static::$allMulticheck[$field_id] = true;
		if ( $escape ) {
			$label = \esc_html( $label );
		}
		
		$select_options = '';
		$selectedAll = $this->getFieldValue($field_id);
		if (is_array($selectedAll) === false)
			$selectedAll = array($selectedAll); // to be compatible with multiselect (numeric array with select keys as values)
		
		// multiselect as checkboxes
		// TODO doesn't show properly if there is only 1 entry
		if (isset($selectOptions['multicheck']) && $selectOptions['multicheck'] === true) {
			foreach ( $options as $value => $name ) {
				$select_options .= vsprintf(
					'<label class="select-label"><input type="checkbox" name="%s[%s]" value="%s"%s> %s</label>',
					[
						esc_attr($field_id),
						esc_attr( $value ),
						esc_attr( $value ),
						checked( $this->getSelectedValue($selectedAll, $value), esc_attr( $value ), false ),
						esc_html( $name ),
					]
				);
			}
			
			$output = vsprintf('<div class="ct-input-wrapper">
						<div class="ct-input-label-side">%s</div>
                        	<div class="ct-input-value-side">
                            	<div>
									<button type="button" id="check-all" class="btn button">%s</button>
	                            	<button type="button" id="uncheck-all" class="btn button">%s</button>
        	                	</div>
                            <div class="ct-select-wrapper es-check">%s</div>
                        </div>
                    </div>', [
                    		$label,
                    		__('Check All', 'vpay'),
                    		__('Uncheck All', 'vpay'),
                    		$select_options,
                    ]);
			return $output;
		}
		
		foreach ( $options as $value => $name ) {
			$select_options .= vsprintf(
				'<option value="%s" %s>%s</option>',
				[
					esc_attr( $value ),
					selected( $this->getSelectedValue($selectedAll, $value), esc_attr( $value ), false ),
					esc_html( $name ),
				]
			);
		}

		$class = $selectOptions['class'];
		if ($selectOptions['multiselect'] === true && strpos($class, 'multiselect') === false)
			$class .= ' multiselect';
		$class = trim($class);
		$output = vsprintf(
			'<label for="%1$s">%2$s</label>
			<select name="%3$s%7$s" id="%1$s"%5$s%6$s>%4$s</select>',
			[
				$this->getFieldId($field_id),
				$label,
				$this->getFieldName( $field_id ),
				$select_options,
				$selectOptions['multiselect'] === true ? ' multiple="multiple"' : '',
				empty($class) ? '' : 'class="' . $class . '"',
				$selectOptions['multiselect'] === true ? '[]' : '',
			]
		);
		
		return $output;
	}

	/**
	 * Return a wrapped question mark.
	 *
	 * @param string $description The descriptive on-hover title.
	 * @param string $link The non-escaped link.
	 * @param bool $echo Whether to echo or return.
	 * @param array $allowedHtml HTML tags that are allowed to be displayed (defaults to only links).
	 * @return string HTML checkbox output if $echo is false.
	 */
	public function makeInfo( $description = '', $link = '', $echo = true, $allowedHtml = array() ) {

		if ( $link ) {
			$output = sprintf(
				'<a href="%1$s" class="ct-tooltip-item" target="_blank" rel="nofollow noreferrer noopener" title="%2$s" data-desc="%2$s">[?]</a>',
				\esc_url( $link, array( 'http', 'https' ) ),
				\esc_attr( $description )
			);
		} else {
			$output = sprintf(
				'<span class="ct-tooltip-item" title="%1$s" data-desc="%1$s">[?]</span>',
				\esc_attr( $description )
			);
		}

		$output = sprintf( '<span class="ct-tooltip-wrap">%s</span>', $output );

		if ( $echo ) {
			if (empty($allowedHtml))
				$allowedHtml = static::$allowdHtmlTags;
			echo wp_kses($output, $allowedHtml);
		} else {
			return $output;
		}
	}
	
	/**
	 * Mark up content in description wrap.
	 * Escapes all HTML, so `<` gets changed to `&lt;` and displays correctly.
	 *
	 * @param string $content Content to be wrapped in the description wrap.
	 * @param bool $block Whether to wrap the content in <p> tags.
	 * @return string Content wrapped int he description wrap.
	 */
	public function description( $content, $block = true ) {
		$this->descriptionNoesc( \esc_html( $content ), $block );
	}

	/**
	 * Mark up content in description wrap.
	 * @removed as unsafe. Please use
	 *
	 * @param string $content Content to be wrapped in the description wrap. Expected to be escaped.
	 * @param bool $block Whether to wrap the content in <p> tags.
	 * @param array $allowedHtml HTML tags that are allowed to be displayed (defaults to only links).
	 * @return string Content wrapped int he description wrap.
	 */
	public function descriptionNoesc( $content, $block = true, $allowedHtml = array() ) {
		if (empty($allowedHtml))
			$allowedHtml = static::$allowdHtmlTags;
		$output = '<span class="description">' .$content . '</span>';
		echo wp_kses($block ? '<p>' . $output . '</p>' : $output, $allowedHtml);
	}
	
	/**
	 * Helper function that constructs id attributes for use in form fields.
	 *
	 * @param string $key The option name which returns boolean.
	 */
	public function getIsConditionalChecked( $key ) {
		return $this->isConditionalChecked( $key, $this->settings->settingsField, true, false );
	}

	/**
	 * Returns the HTML class wrap for warning/default Checkbox options.
	 *
	 * This function does nothing special. But is merely a simple wrapper.
	 * Just like code_wrap.
	 *
	 * @param string $key required The option name which returns boolean.
	 * @param string $setting optional The settings field
	 * @param bool $wrap optional output class="" or just the class name.
	 * @param bool $echo optional echo or return the output.
	 *
	 * @return string Empty on echo or The class with an optional wrapper.
	 */
	public function isConditionalChecked( $key, $setting = '', $wrap = true, $echo = true ) {
		$class = '';
		$default = $this->isDefaultChecked( $key, $setting, false, false );
		$warned = $this->isWarningChecked( $key, $setting, false, false );

		if ( '' !== $default && '' !== $warned ) {
			$class = $default . ' ' . $warned;
		} elseif ( '' !== $default ) {
			$class = $default;
		} elseif ( '' !== $warned ) {
			$class = $warned;
		}

		if ( $echo ) {
			if ( $wrap ) {
				printf( 'class="%s"', \esc_attr( $class ) );
			} else {
				echo \esc_attr( $class );
			}
		} else {
			if ( $wrap )
				return sprintf( 'class="%s"', $class );

			return $class;
		}
	}
	
	/**
	 * Returns the HTML class wrap for default Checkbox options.
	 *
	 * This function does nothing special. But is merely a simple wrapper.
	 * Just like code_wrap.
	 *
	 * @param string $key required The option name which returns boolean.
	 * @param string $setting optional The settings field
	 * @param bool $wrap optional output class="" or just the class name.
	 * @param bool $echo optional echo or return the output.
	 */
	public function isDefaultChecked( $key, $setting = '', $wrap = true, $echo = true ) {
		$class = '';
		$default = $this->settings->getDefaultSettings( $key, $setting );

		if ( 1 === $default )
			$class = 'ct-default-selected';

		if ( $echo ) {
			if ( $wrap ) {
				printf( 'class="%s"', \esc_attr( $class ) );
			} else {
				echo \esc_attr( $class );
			}
		} else {
			if ( $wrap )
				return sprintf( 'class="%s"', $class );

			return $class;
		}
	}

	/**
	 * Returns the HTML class wrap for warning Checkbox options.
	 *
	 * @param string $key required The option name which returns boolean.
	 * @param string $setting optional The settings field
	 * @param bool $wrap optional output class="" or just the class name.
	 * @param bool $echo optional echo or return the output.
	 * @return string Empty on echo or The class with an optional wrapper.
	 */
	public function isWarningChecked( $key, $setting = '', $wrap = true, $echo = true ) {
		$class = '';
		$warned = $this->settings->getWarnedSettings( $key, $setting );

		if ( 1 === $warned )
			$class = 'ct-warning-selected';

		if ( $echo ) {
			if ( $wrap ) {
				printf( 'class="%s"', \esc_attr( $class ) );
			} else {
				echo \esc_attr( $class );
			}
		} else {
			if ( $wrap )
				return sprintf( 'class="%s"', $class );

			return $class;
		}
	}
	
	/**
	 * Return the names of all checkboxes in the current HTML form.
	 * @param bool $asString
	 * @return array|string
	 */
	public function getAllCheckboxes(bool $asString) { // TODO make this compatible with multi-level form fields, see getFieldName()
		if ($asString === false)
			return static::$allCheckboxes;
		return implode(',', array_keys(static::$allCheckboxes));
	}
	
	/**
	 * Return the names of all multi-select inputs in the current HTML form.
	 * @param bool $asString
	 * @return array|string
	 */
	public function getAllMultiselect(bool $asString) { // TODO make this compatible with multi-level form fields, see getFieldName()
		if ($asString === false)
			return static::$allMultiselect;
		return implode(',', array_keys(static::$allMultiselect));
	}
	
	/**
	 * Return the names of all checkboxes emulating multi-select inputs in the current HTML form.
	 * @param bool $asString
	 * @return array|string
	 */
	public function getAllMultiCheck(bool $asString) {
		if ($asString === false)
			return static::$allMulticheck;
		return implode(',', array_keys(static::$allMulticheck));
	}
	
	public static function getDate(int $unixTimestamp, bool $echo = true) {
		if (static::$dateFormat === '')
			static::$dateFormat = get_option( 'date_format' );
		$date = date_i18n(static::$dateFormat, $unixTimestamp);
		if ($echo === false)
			return $date;
		echo \esc_html($date);
	}
	
	public static function getTime(int $unixTimestamp, bool $echo = true) {
		if (static::$timeFormat === '')
			static::$timeFormat = get_option( 'time_format' );
		$date = date_i18n(static::$timeFormat, $unixTimestamp);
		if ($echo === false)
			return $date;
		echo \esc_html($date);
	}
	
	public static function getPageNames(): array {
		if (static::$pageNames !== null)
			return static::$pageNames;
		static::$pageNames = array();
		$pages = get_pages(); // also has option for sorting etc, use default alphabetical by title
		foreach ($pages as $page) {
			static::$pageNames[$page->ID] = $page->post_title;
		}
		return static::$pageNames;
	}
	
	/**
	 * Helper function that returns the value of the selected value if it is part of $selectedAll. Returns empty string otherwise.
	 * @param array $selectedAll
	 * @param string $value
	 * @return string
	 */
	protected function getSelectedValue(array $selectedAll, string $value): string {
		foreach ($selectedAll as $selected) {
			if ($selected === $value)
				return $selected;
		}
		return "";
	}
}
?>