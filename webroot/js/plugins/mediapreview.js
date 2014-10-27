/**
 * Media preview plugin for jQuery and Cecil.
 *
 * Cecil, the de facto CMS for the Sleepy web application framework.
 * Copyright 2012 - 2014, Alex Palaistras (http://deuill.org)
 */
;define((function ($, window, document, undefined) {
	$.fn.mediaPreview = function() {
		var selector = this.selector;

		$(document).on('change', selector, function(event) {
			var files = event.target.files;
			var placeholder = $(this).data('placeholder');

			$(placeholder).siblings('.media-preview').remove();

			for (var i = 0; i < files.length; i++) {
				var file = files[i];

				// Previews for images.
				if (file.type.match(/image.*/)) {
					var preview = $(placeholder).clone();
					preview.css('opacity', 0).removeAttr('id');
					preview.removeClass('hidden').addClass('media-preview');
					preview.insertAfter($(placeholder));

					var reader = new FileReader();
					reader.onload = (function(i) {
						return function(e) {
							var image = new Image();
							image.src = e.target.result;
							image.onload = function() {
								$(i).css('background-image', 'url(' + image.src + ')');
								$(i).animate({'opacity': 0.6}, 400);
							};
						};
					})(preview);

					reader.readAsDataURL(file);
				}
			}
		});

		return this;
	};
})(jQuery, window, document));