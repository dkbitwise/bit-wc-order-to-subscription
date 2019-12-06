<?php
/**
 * Bitos Create subscription
 */
defined( 'ABSPATH' ) || exit; //Exit if accessed directly ?>
<hr class="wp-header-end">
<?php
$success = filter_input( INPUT_GET, 'success', FILTER_SANITIZE_STRING );
if ( 'yes' === $success ) { ?>
	<div class="success notice updated"><p>Subscriptions for all orders created successfully.</p></div>
<?php } elseif ( 'no' === $success ) { ?>
	<div class="notice notice-error"><p>Some subscriptions can not be created.</p></div>
<?php } ?>
<h4 class="bit_ots-heading"><?php esc_html_e( 'Creating subscription for simple orders', 'bit-ots' ); ?></h4>
<form class="bitos_creat_subs" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
	<input type="hidden" name="action" value="bitos_create_subs">
	<input type="hidden" name="bitos_create_subs_nonce" value="<?php echo wp_create_nonce( 'bitos_create_subs_nonce_val' ) ?>">
	<table class="bitsa-shortcodes">
		<tbody>
		<tr>
			<td><p><?php esc_html_e( 'Enter subscription product id to create subscription', 'bit-ots' ) ?></p>
				<p><input type="text" name="bit_ots_prod_id"></p></td>
		</tr>

		<tr>
			<td>
				<p>
					<label for="bitos-order-ids"><input type="radio" value="bitos-order-ids" name="bit_choose_input" checked><?php esc_html_e( 'I have order ids', 'bit-ots' ); ?></label>
					<label for="bitos-email-ids"><input type="radio" value="bitos-email-ids" name="bit_choose_input"><?php esc_html_e( 'I have email ids', 'bit-ots' ); ?></label>
				</p>
			</td>
		</tr>

		<tr class="bitos-choice bitos-order-ids">
			<td>
				<p><?php esc_html_e( 'Enter order(s) (if multiple add comma seperated)', 'bit-ots' ) ?></p>
				<p><input type="text" name="bit_ots_orders" placeholder="Enter order id(s)"></p>
			</td>
		</tr>
		<tr class="bitos-choice bitos-email-ids bitos-hide">
			<td>
				<p><label><?php esc_html_e( 'Enter Email id(s) (if multiple add comma seperated)', 'bit-ots' ) ?></label></p>
				<p><textarea name="bit_ots_email_ids" rows="5" cols="64" placeholder="Enter email id(s)"></textarea></p>
			</td>
		</tr>
		</tbody>
	</table>
	<div class="bitsa-crs-action">
		<input class="button button-primary" type="submit" name="bitos_create_sbmit" value="Create Subscription">
	</div>
</form>
