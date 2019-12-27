<?php
/**
 * Bitos notification settings
 */
defined( 'ABSPATH' ) || exit; //Exit if accessed directly ?>
<hr class="wp-header-end">
<?php
$success = filter_input( INPUT_GET, 'success', FILTER_SANITIZE_STRING );
if ( 'yes' === $success ) { ?>
	<div class="success notice updated"><p>Notification settings are saved successfully.</p></div>
<?php } ?>
<h4 class="bit_ots-heading"><?php esc_html_e( 'Notification settings', 'bit-ots' ); ?></h4>
<form class="bitos_notification_settings" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
	<input type="hidden" name="action" value="bitos_notification_settings">
	<input type="hidden" name="bitos_create_notification_settings" value="<?php echo wp_create_nonce( 'bitos_notification_settings_nonce_val' ) ?>">
	<table class="bitsa-shortcodes">
		<tbody>
		<tr>
			<td><p><?php esc_html_e( 'Enter the button link test', 'bit-ots' ) ?></p>
				<p><input type="text" name="bit_ots_button_text" placeholder="My Subscription" value="<?php echo $notes_data['bit_ots_button_text']?>"></p>
			</td>
		</tr>

		<tr class="bitos-expired_message">
			<td>
				<p><label><?php esc_html_e( 'Message shown on expired subscription', 'bit-ots' ) ?></label></p>
				<p><textarea name="bit_ots_expired_messages" rows="5" cols="64" placeholder="Enter the message"><?php echo $notes_data['bit_ots_expired_messages'] ?></textarea></p>
			</td>
		</tr>
		</tbody>
	</table>
	<div class="bitsa-notes_settings-action">
		<input class="button button-primary" type="submit" name="bitos_nots_sbmit" value="Save">
	</div>
</form>
