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
<p class="bit_ots-heading">Reminder Emails Settings</h4>
<form class="bitos_creat_subs" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
	<input type="hidden" name="action" value="bitos_email_settings">
	<input type="hidden" name="bitos_email_settings_nonce" value="<?php echo wp_create_nonce('bitos_email_settings_nonce_val') ?>">
	
	<div class="bitos-setting-container" id="bitos_settings_area">
        <!-- Tab links -->
		<div class="tab">
		  <button type="button" class="tablinks active" data-tab="Weekly">Weekly</button>
		  <button type="button" class="tablinks" data-tab="Monthly">Monthly</button>
		</div>

        <!-- Tab content Weekly -->
		<div id="Weekly" class="tabcontent">
		  
		  <div class="bit-left">
		  	<label>Interval (in Days)</label>
		  </div>
		  <div class="bit-right">
		  	<input type="text" name="bit_e_int_week" placeholder="7" value="<?php echo $settings_data['bit_e_int_week'] ?>">
		  </div>

		  <div class="bit-left">
		  	<label>Subject</label>
		  </div>
		  <div class="bit-right">
		  	<input type="text" value="<?php echo $settings_data['bit_e_sub_week'] ?>" name="bit_e_sub_week" placeholder="Weekly Subject">
		  </div>

		  <div class="bit-left">
		  	<label>Email Body</label>
		  </div>
		  <div class="bit-right">
		  	<textarea rows="10" name="bit_e_body_week" cols="62" placeholder="Weekly Email content"><?php echo $settings_data['bit_e_body_week']; ?></textarea>
		  </div>

		</div>

		<!-- Tab content Monthly-->
		<div id="Monthly" class="tabcontent bitos-hide">

		  <div class="bit-left">
		  	<label>Interval (in Days)</label>
		  </div>
		  <div class="bit-right">
		  	<input type="text" value="<?php echo $settings_data['bit_e_int_month'] ?>" name="bit_e_int_month" placeholder="30">
		  </div>

		  <div class="bit-left">
		  	<label>Subject</label>
		  </div>
		  <div class="bit-right">
		  	<input type="text" name="bit_e_sub_month" value="<?php echo $settings_data['bit_e_sub_month'] ?>" placeholder="Monthly Subject">
		  </div>

		  <div class="bit-left">
		  	<label>Email Body</label>
		  </div>
		  <div class="bit-right">
		  	<textarea rows="10" name="bit_e_body_month" cols="62" placeholder="Monthly Email content"><?php echo $settings_data['bit_e_body_month']; ?></textarea>
		  </div>

		</div>
    </div>

	<div class="bitsa-email-action">
		<input class="button button-primary" type="submit" name="bitos_create_sbmit" value="Save">
	</div>
</form>
