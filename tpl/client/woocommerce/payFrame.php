<?php
if (!defined('ABSPATH'))
	exit("denied");
?>
<?php if (isset($frameCfg['beforePayMsg']) &&!empty($frameCfg['beforePayMsg'])): ?>
    <div class="ct-prepay-msg"><?php echo $frameCfg['beforePayMsg'];?></div>
<?php endif;?>
<div class="ct-frame-pay ct-button-frame-<?php echo esc_attr($frameCfg['orderID']);?>">
	<iframe src="<?php echo esc_attr($frameCfg['url']);?>" scrolling="no" width="100%" height="<?php echo esc_attr($frameCfg['frameHeight']);?>"></iframe>
</div>