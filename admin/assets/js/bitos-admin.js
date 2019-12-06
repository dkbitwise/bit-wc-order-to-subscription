/**
 * Admin js
 */

(function ($) {
	$(document).ready(function () {
		$('.tablinks').on('click', function () {
			let evnt = $(this);
			let tab_name = $(evnt).attr('data-tab');
			$('.tablinks').removeClass('active');
			$(this).addClass('active');
			$('div.tabcontent').addClass('bitos-hide');
			$('div#' + tab_name).removeClass('bitos-hide');
		});
		//$('#bit_start_time').timeMask();
		$('#bit_start_time').timeMask("hh:mm:ss", {
			placeholder: "HH:MM:SS",
			insertMode: false,
			showMaskOnHover: false,
			hourFormat: 12
		});

		$('input[type=radio][name=bit_choose_input]').on('click', function () {
			let choice = $(this).val();
			console.log(choice);
			$('.bitos-choice').addClass('bitos-hide');
			$('.'+choice).removeClass('bitos-hide');
		});
	});
})(jQuery);