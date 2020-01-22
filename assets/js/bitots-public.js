/**
 * Front end js
 */
(function ($) {
	$(document).ready(function () {
		$('#bit_ots_stdt_eml').on('click', function () {
			let course_id, stdnt_id, ajaxurl;
			ajaxurl = bitots.ajaxurl;
			course_id = $(this).attr('data-course_id');
			stdnt_id = $(this).attr('data-stdnt_id');

			let data = {
				'action': 'bitots_send_email',
				'course_id': course_id,
				'stdnt_id': stdnt_id,
				'_nonce': bitots.ajax_nonce_bitots_send_email
			};

			jQuery.post(ajaxurl, data, function (resp) {
				console.log(resp);
				if (true === resp.status) {
					$('.bit_ots-message').html('<span class="sent-info">Reminder email successfully sent to your parent.</span>')
				} else {
					$('.bit_ots-message').html('<span class="sent-info">There are some error, please try again later!.</span>')
				}
			});
		});
	});
})(jQuery);
