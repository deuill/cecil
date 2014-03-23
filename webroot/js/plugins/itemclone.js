/**
 * Item clone plugin for jQuery and Cecil.
 *
 * Cecil, the de facto CMS for the Sleepy web application framework.
 * Copyright 2012 - 2014, Alex Palaistras (http://thoughtmonster.org)
 */
;define((function ($, window, document, undefined) {
	$.fn.itemClone = function() {
		var selector = this.selector;

		$(document).on('click', selector, function() {
			var target = $(this).data('master');
			var placeholder = $(this).data('placeholder');
			var clone = $(target).clone().css('opacity', 0);

			$(document).trigger('itemclone.start');
			clone.removeClass('hidden').addClass('js-clone-item');
			clone.removeAttr('id');

			if (typeof $(window).data('clone-index') == 'undefined') {
				$(window).data('clone-index', 1);
			} else {
				$(window).data('clone-index', $(window).data('clone-index') + 1);
			}

			clone.data('index', 'clone-' + $(window).data('clone-index'));
			clone.html(clone.html().replace(/\{index\}/g, clone.data('index')));

			var parent = $(placeholder).parents('.js-clone-item').data('index');

			if (typeof parent == 'undefined') {
				clone.html(clone.html().replace(/\{parent\}/g, 0));
			} else {
				clone.html(clone.html().replace(/\{parent\}/g, parent));
			}

			if ($(placeholder).data('direction') === 'above') {
				clone.insertBefore($(placeholder));
			} else {
				clone.insertAfter($(placeholder));	
			}

			var height = clone.height();

			clone.height(0);
			clone.animate({height: height, opacity: 1}, function() {
				clone.css('height', 'auto');
				$(document).trigger('itemclone.end');
			});
		});

		return this;
	};
})(jQuery, window, document));