<?php
if (!defined('ABSPATH'))
	exit("denied");
?>
<div id="ct-scoped-content">
    <style type="text/css" scoped>
        iframe {
		display: none;
	}
    </style>
    <?php if (isset($buttonConf['beforePayMsg']) &&!empty($buttonConf['beforePayMsg'])): ?>
	<div class="ct-prepay-msg"><?php echo $buttonConf['beforePayMsg'];?></div>
    <?php endif;?>
	<form action="<?php echo esc_attr($buttonConf['url']);?>" method="POST" target="_blank">
	  <button id="ct-payment-button" class="checkout-button button alt wc-forward" type="submit"><?php echo esc_html__('Pay Now', 'vpay');?></button>
	</form>
	<br><br>
</div>