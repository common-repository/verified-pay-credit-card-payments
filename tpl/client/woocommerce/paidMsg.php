<?php
if (!defined('ABSPATH'))
	exit("denied");
?>
<p><?php esc_html_e('Your order has been fully paid.', 'vpay');?></p>
<?php if (isset($msgConf['coupon']) && !empty($msgConf['coupon'])):?>
    <p><a target="_blank" href="<?php echo esc_attr($msgConf['couponUrl']);?>"><?php echo esc_html($msgConf['coupon']);?></a></p>
<?php endif;?>
<?php if (isset($msgConf['paid']) && !empty($msgConf['paid'])):?>
<p class="ct-prepay-msg">
  <?php echo esc_html($msgConf['paid']);?>
</p>
<?php endif;?>