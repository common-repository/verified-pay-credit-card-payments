<?php
if (!defined('ABSPATH'))
	exit("denied");
?>
<p>
  <?php if (empty($tplVars['link'])): ?>
  		<?php esc_html_e($tplVars['msg'], 'vpay'); ?>
  <?php else: ?>	
  		<a href="<?php echo $tplVars['link']; ?>"><?php esc_html_e($tplVars['msg'], 'vpay'); ?></a>
  <?php endif; ?>		
</p>