<?php
/**
* Bitos Create subscription
*/ 
defined( 'ABSPATH' ) || exit; //Exit if accessed directly ?>
<hr class="wp-header-end">
<?php
$success = filter_input(INPUT_GET, 'success',FILTER_SANITIZE_STRING);
if ('yes' === $success) { ?>
	<div class="success notice updated"><p>Subscriptions for all orders created successfully.</p></div>
<?php }elseif ('no' === $success) { ?>
	<div class="notice notice-error"><p>Some subscriptions can not be created.</p></div>
<?php } ?>
<p class="bit_ots-heading">Creating Subscription for simple orders</h4>
<form class="bitos_creat_subs" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
	<input type="hidden" name="action" value="bitos_create_subs">
	<input type="hidden" name="bitos_create_subs_nonce" value="<?php echo wp_create_nonce('bitos_create_subs_nonce_val') ?>">
	<table class="bitsa-shortcodes" >
		<tbody>
			<tr>
				<p><?php esc_html_e('Enter subscription product id to create subscription','bit-ots') ?></p>
				<p><input type="text" name="bit_ots_prod_id"></p>
			</tr>
			<tr>
				<p><?php esc_html_e('Enter order(s) (if multiple add comma seperated)','bit-ots') ?></p>
				<p><input type="text" name="bit_ots_orders"></p>
			</tr>
		</tbody>
	</table>
	<div class="bitsa-crs-action">
		<input class="button button-primary" type="submit" name="bitos_create_sbmit" value="Create Subscription">
	</div>
</form>
