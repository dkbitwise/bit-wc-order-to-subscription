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
<p class="bit_ots-heading">Reminder Emails Settings</p>
<form class="bitos_creat_subs" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
	<input type="hidden" name="action" value="bitos_email_settings">
	<input type="hidden" name="bitos_email_settings_nonce" value="<?php echo wp_create_nonce( 'bitos_email_settings_nonce_val' ) ?>">

	<div class="bitos-setting-container" id="bitos_settings_area">
		<!-- Tab links -->
		<label>Tigger a reminder email before: </label>
		<div class="tab">
			<button type="button" class="tablinks active" data-tab="Week">One Week</button>
			<button type="button" class="tablinks" data-tab="Month">One Month</button>
			<button type="button" class="tablinks" data-tab="Custom">Custom days</button>
		</div>

		<!-- Tab content Week -->
		<div id="Week" class="tabcontent">

			<div class="bit-left">
				<label>Enable/Disable</label>
			</div>
			<div class="bit-right">
				<label class="bit-label">
					<input type="checkbox" name="bit_ew_on" value="1" <?php echo ( $settings_data['bit_ew_on'] ) ? 'checked' : ''; ?> >
					<?php esc_html_e( 'Enable renewal reminder email to be sent before 7 days of expiry.', 'bit-ots' ) ?>
				</label>
			</div>

			<div class="bit-left">
				<label>Subject</label>
			</div>
			<div class="bit-right">
				<input type="text" value="<?php echo $settings_data['bit_ew_subject'] ?>" name="bit_ew_subject" placeholder="Before one week subject">
			</div>

			<div class="bit-left">
				<label>Email Body</label>
			</div>
			<div class="bit-right">
				<textarea rows="10" name="bit_ew_body" cols="62" placeholder="Before one week email content"><?php echo $settings_data['bit_ew_body']; ?></textarea>
			</div>

		</div>

		<!-- Tab content Month-->
		<div id="Month" class="tabcontent bitos-hide">

			<div class="bit-left">
				<label>Enable/Disable</label>
			</div>
			<div class="bit-right">
				<label class="bit-label">
					<input type="checkbox" name="bit_em_on" value="1" <?php echo ( $settings_data['bit_em_on'] ) ? 'checked' : ''; ?>>
					<?php esc_html_e( 'Enable renewal reminder email to be sent before 30 days of expiry.', 'bit-ots' ) ?>
				</label>
			</div>

			<div class="bit-left">
				<label>Subject</label>
			</div>
			<div class="bit-right">
				<input type="text" name="bit_em_subject" value="<?php echo $settings_data['bit_em_subject'] ?>" placeholder="Before one month Subject">
			</div>

			<div class="bit-left">
				<label>Email Body</label>
			</div>
			<div class="bit-right">
				<textarea rows="10" name="bit_em_body" cols="62" placeholder="Bwfore one month email content"><?php echo $settings_data['bit_em_body']; ?></textarea>
			</div>

		</div>

		<!-- Tab content Custom-->
		<div id="Custom" class="tabcontent bitos-hide">

			<div class="bit-left">
				<label>Enable/Disable</label>
			</div>
			<div class="bit-right">
				<label class="bit-label">
					<input type="checkbox" name="bit_ec_on" value="1" <?php echo ( $settings_data['bit_ec_on'] ) ? 'checked' : ''; ?>>
					<?php esc_html_e( 'Enable renewal reminder email to be sent before custom days of expiry.', 'bit-ots' ) ?>
				</label>
			</div>

			<div class="bit-left">
				<label>Interval (in Days)</label>
			</div>
			<div class="bit-right">
				<input type="text" name="bit_ec_int" placeholder="7" value="<?php echo $settings_data['bit_ec_int'] ?>">
			</div>

			<div class="bit-left">
				<label>Subject</label>
			</div>
			<div class="bit-right">
				<input type="text" name="bit_ec_subject" placeholder="Before custom days subject" value="<?php echo $settings_data['bit_ec_subject'] ?>">
			</div>

			<div class="bit-left">
				<label>Email Body</label>
			</div>
			<div class="bit-right">
				<textarea rows="8" name="bit_ec_body" cols="62" placeholder="Before custom days email content"><?php echo $settings_data['bit_ec_body']; ?></textarea>
			</div>

		</div>

	</div>

	<div class="bitos-row">
		<div class="bit-left">
			<label>Available shortcodes</label>
		</div>
		<div class="bit-right">
			<h5 class="bit-note">You can use following merge-tags in email subject and email body.</h5>
			<p>{{customer_name}} 		= Customer billing first name</p>
			<p>{{subscription_name}} 	= Name of package in subscripiton order</p>
			<p>{{subscription_id}} 		= The subscription order id</p>
			<p>{{order_total}} 			= The total renewal order amount to be paid.</p>
		</div>
		<div class="clear-both"></div>
	</div>

	<div class="bitos-row">
		<div class="bit-left">
			<label>Number of emails in a batch</label>
		</div>
		<div class="bit-right">
			<input type="text" name="bit_batch_count" placeholder="Number of emails can be sent in a batch" value="<?php echo $settings_data['bit_batch_count'] ?>">
		</div>
		<div class="clear-both"></div>

		<div class="bit-left">
			<label>Time to start sending emails.</label>
		</div>
		<div class="bit-right">
			<input id="bit_start_time" type="time" name="bit_start_time" value="<?php echo $settings_data['bit_start_time'] ?>">
		</div>
		<div class="clear-both"></div>
	</div>

	<div class="bitsa-email-action">
		<input class="button button-primary" type="submit" name="bitos_create_sbmit" value="Save">
	</div>
</form>
