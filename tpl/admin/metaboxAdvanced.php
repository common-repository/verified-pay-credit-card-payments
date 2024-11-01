        <h4><?php esc_html_e( 'Payment', 'vpay' ); ?></h4>
        <p>
            <label for="<?php $this->fieldId( 'before_pay_msg' ); ?>" class="ct-toblock">
		        <?php esc_html_e( 'Above pay button message:', 'vpay' ); ?>
            </label>
        </p>
        <p class="ct-input-wrap">
            <input type="text" name="<?php $this->fieldName( 'before_pay_msg' ); ?>" class="large-text" id="<?php $this->fieldId( 'before_pay_msg' ); ?>" placeholder="<?php echo esc_attr( $beforePayPlaceholder ); ?>" value="<?php echo esc_attr( $this->getFieldValue( 'before_pay_msg' ) ); ?>" />
        </p>
        <?php
        $reloadInfo = $this->makeInfo(
	        __( 'Some sales tracking plugins will only work properly if the WooCommerce order "Thank You" page is load after payment. If you are using such a plugin you can enable this setting.', 'vpay' ),
	        '',
	        false
        );

        $this->wrapFields(
	        array(
		        $this->makeCheckbox(
			        'reload_after_pay',
			        esc_html__( 'Reload payment page after payment', 'vpay' ) . ' ' . $reloadInfo,
			        '',
			        false
		        ),
	        ),
	        true
        );

        $payFrameInfo = $this->makeInfo(
	        __( 'Open the credit card payment form inside an iframe instead of opening a new window. This can increase conversion rate but might cause rendering problems with 3D Secure at some banks.', 'vpay' ),
	        '',
	        false
        );

        $this->wrapFields(
	        array(
		        $this->makeCheckbox(
			        'pay_iframe',
			        esc_html__( 'Enable iframe payment form', 'vpay' ) . ' ' . $payFrameInfo,
			        '',
			        false
		        ),
	        ),
	        true
        );

        $payRedirectInfo = $this->makeInfo(
	        __( 'Send the customer to the payment processeor page when he clicks "place order". This can not be used with the iframe and takes precedence.', 'vpay' ),
	        '',
	        false
        );

        $this->wrapFields(
	        array(
		        $this->makeCheckbox(
			        'redirect_on_checkout',
			        esc_html__( 'Redirect customers when pressing "pay"', 'vpay' ) . ' ' . $payRedirectInfo,
			        '',
			        false
		        ),
	        ),
	        true
        );

        $amexInfo = $this->makeInfo(
	        __( 'Show the American Express icon at the checkout page in addition to MasterCard and Visa.', 'vpay' ),
	        '',
	        false
        );

        $this->wrapFields(
	        array(
		        $this->makeCheckbox(
			        'show_amex_icon',
			        esc_html__( 'Show American Express icon', 'vpay' ) . ' ' . $amexInfo,
			        '',
			        false
		        ),
	        ),
	        true
        );

        $voucherStoreInfo = $this->makeInfo(
	        __( 'Send customers to our Voucher Store for checkout. This offers more payment options and better fraud checks.', 'vpay' ),
	        '',
	        false
        );

        $this->wrapFields(
	        array(
		        $this->makeCheckbox(
			        'voucher_store',
			        esc_html__( 'Checkout at voucher store', 'vpay' ) . ' ' . $voucherStoreInfo,
			        '',
			        false
		        ),
	        ),
	        true
        );

        $aiScoreInfo = $this->makeInfo(
	        __( 'Use AI trust score to determine customer risk and warn of possible early chargebacks.', 'vpay' ),
	        '',
	        false
        );

        $this->wrapFields(
	        array(
		        $this->makeCheckbox(
			        'ai_score',
			        esc_html__( 'Use AI trust score', 'vpay' ) . ' ' . $aiScoreInfo,
			        '',
			        false
		        ),
	        ),
	        true
        );
        ?>

        <p>
            <label for="<?php $this->fieldId( 'redirect_gateway_id' ); ?>" class="ct-toblock">
		        <?php esc_html_e( 'Redirect Gateway ID:', 'vpay' ); ?>
            </label>
        </p>
        <p class="ct-input-wrap">
            <input type="text" name="<?php $this->fieldName( 'redirect_gateway_id' ); ?>" class="large-text" id="<?php $this->fieldId( 'redirect_gateway_id' ); ?>" placeholder="<?php echo esc_attr( 'some_gateway_id' ); ?>" value="<?php echo esc_attr( $this->getFieldValue( 'redirect_gateway_id' ) ); ?>" />
        </p>
        <?php
        $this->description( __( 'If the payment at Verified Pay fails (not supported country, high customer risk score, ...), send the customer to this other payment method.', 'vpay' ) );
        ?>


        <h4><?php esc_html_e( 'Data', 'vpay' ); ?></h4>
		
		<?php
		$cookieInfo = $this->makeInfo(
			__( 'In countries within the EU you are required by law to inform your visitors that your website is using cookies. Enabling this will inform your visitors on their first visit with a message at the bottom of the page.', 'vpay' ),
			'',
			false
		);
		
		$this->wrapFields(
			array(
				$this->makeCheckbox(
					'show_cookie_consent',
					esc_html__( 'Show cookie consent dialog to new users', 'vpay' ) . ' ' . $cookieInfo,
					'',
					false
				),
			),
			true
		);
		?>
		<p>
			<label for="<?php $this->fieldId( 'cookie_consent_txt' ); ?>" class="ct-toblock">
				<?php esc_html_e( 'Cookie consent text:', 'vpay' ); ?>
			</label>
		</p>
		<p class="ct-input-wrap">
			<input type="text" name="<?php $this->fieldName( 'cookie_consent_txt' ); ?>" class="large-text" id="<?php $this->fieldId( 'cookie_consent_txt' ); ?>" placeholder="<?php echo esc_attr( $cookiePlaceholder ); ?>" value="<?php echo esc_attr( $this->getFieldValue( 'cookie_consent_txt' ) ); ?>" />
		</p>

		<hr>
		
		<?php 
		