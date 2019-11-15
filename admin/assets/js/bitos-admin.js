/**
* Admin js
*/

(function ($) {
	$(document).ready(function(){
		$('.tablinks').on('click',function(){
			let evnt = $(this);
			let tab_name = $(evnt).attr('data-tab');
			$('.tablinks').removeClass('active');
			$(this).addClass('active');
			$('div.tabcontent').addClass('bitos-hide');
			$('div#'+tab_name).removeClass('bitos-hide');
		});
	});	
})(jQuery);