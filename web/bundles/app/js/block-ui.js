/**
 * Lightweight jQuery Block UI class
 */

jQuery.fn.center = function() {
	this.css("position", "absolute");
	this.css("top", Math.max(0,
			(($(window).height() - $(this).outerHeight()) / 2)
					+ $(window).scrollTop())
			+ "px");
	this.css("left", Math.max(0,
			(($(window).width() - $(this).outerWidth()) / 2)
					+ $(window).scrollLeft())
			+ "px");
	return this;
};

BlockUI = (function($) {
	var id = 'UIBlockDIV';

	function init(label) {
		if ($('#' + id).length)
			return;

		label = 'undefined' == typeof label ? 'Please wait...' : label;

		$('body').append('<div class="block-ui block-ui-wall block-ui-off"></div>');

		$('body').append('<div class="block-ui block-ui-label block-ui-off">' + label + '</div>');

		$('.block-ui-wall').css('opacity', 0.3);
	}
	function blockUI() {
		$('.block-ui').removeClass('block-ui-off');
	}
	function unblockUI() {
		$('.block-ui').addClass('block-ui-off');
	}

	return {
		init : init,
		block : blockUI,
		unblock : unblockUI
	};
})(jQuery);
