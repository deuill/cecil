/**
 * Main JS file for Cecil.
 *
 * Cecil, the de facto CMS for the Sleepy web application framework.
 * Copyright 2012 - 2014, Alex Palaistras (http://thoughtmonster.org)
 */

var plugins = [
	'plugins/console', 'plugins/easing', 'plugins/history', 'plugins/jqueryui',
	'plugins/chosen', 'plugins/datetimepicker', 'plugins/jwysiwyg', 'plugins/itemclone',
	'plugins/mediapreview', 'plugins/tagsinput', 'plugins/nestedsortable'
];

require(plugins, function() {
	// Enable dynamic page loading with History.js.
	History.Adapter.bind(window, 'statechange', function() {
		var State = History.getState();

		if (State.data.internal) {
			var target = State.data.target;
			var container = $(target);
			if (container.length === 0) {
				var prevState = History.savedStates[History.savedStates.length - 2];
				if (prevState.length === 0) {
					window.location.href = State.url;
				}

				target = prevState.data.target;
				container = $(target);
			}

			var oldHeight = container.outerHeight();
			container.css('height', oldHeight).css('overflow', 'hidden');
			container.animate({opacity: 0}, 200, function() {
				container.load(State.url + ' ' + target + ' > *', function(data) {
					var btnToolbar = $('.panel-heading .btn-toolbar');
					if (btnToolbar.html() != $(data).find('.panel-heading .btn-toolbar').html()) {
						btnToolbar.animate({opacity: 0}, 200, function() {
							btnToolbar.html($(data).find('.panel-heading .btn-toolbar').html());
							btnToolbar.animate({opacity: 1}, 200);
						});
					}

					container.css('height', '');
					var newHeight = container.outerHeight();
					container.css('height', oldHeight);

					container.animate({height: newHeight}, 200, function() {
						container.css('height', '').css('overflow', '');
					});

					container.animate({opacity: 1}, 200);
					document.title = $(data).filter('title').text();
					$(document).trigger('page.load');
				});
			});
		} else {
			window.location.href = State.url;
		}
	});

	// Enable sortable selection lists.
	$(document).on('page.load', function() {
		$('.js-sortable').sortable({
			handle:		'.js-sort-handle',
			items:		'> .js-sort-item',
			scroll:		false,
			opacity:	0.5,
			revert:		true
		});
	});

	// Sortable data list.
	$(document).on('click', '.js-reorder-list', function() {
		var $this = $(this);

		$this.button('toggle');

		if ($this.hasClass('active')) {
			$this.addClass('btn-success');
			$this.children('i').removeClass('fa-bars').addClass('fa-check');

			$('.js-sortable-disabled').addClass('js-sortable');
			$('.js-sortable > .js-sort-item-disabled').slideUp(300);
			$('.js-sort-item .js-sort-item-disabled').fadeOut(300);

			var options = {
				handle:					'.js-sort-handle',
				items:					'.js-sort-item',
				disableNestingClass:	'js-sort-item-disabled',
				toleranceElement:		'> .js-sort-handle',
				tolerance:				'pointer',
				maxLevels:				3,
				scroll:					false,
				doNotClear:				true,
				opacity:				0.5,
				revert:					true
			};

			if ($('.js-sortable').hasClass('js-sortable-nested')) {
				$('.js-sortable').nestedSortable(options);
			} else {
				$('.js-sortable').sortable(options);
			}
		} else {
			$this.removeClass('btn-success');
			$this.children('i').removeClass('fa-check').addClass('fa-bars');

			$('.js-sortable > .js-sort-item-disabled').slideDown(300);
			$('.js-sortable .js-sort-item').removeAttr('style');
			$('.js-sort-item .js-sort-item-disabled').fadeIn(300);

			if ($('.js-sortable').hasClass('js-sortable-nested')) {
				$('.js-sortable').nestedSortable('destroy');
			} else {
				$('.js-sortable').sortable('destroy');
			}

			$('.js-sortable').removeClass('js-sortable');

			$('.form-order-items').prepend('<input type="hidden" name="action[reorder]" class="js-reorder-action">');
			$.post($('.form-order-items').attr('action'), $('.form-order-items').serialize());
			$('.form-order-items').children('.js-reorder-action:first').remove();
		}
	});

	// Load Chosen for custom select forms.
	$(document).on('page.load', function() {
		$('select:not(.js-no-chosen):visible').chosen({
			disable_search_threshold: 15,
			allow_single_deselect: true
		});
	});

	// Enable date-time picker for date inputs.
	$(document).on('page.load', function() {
		$('.js-datetimepicker').datetimepicker({
			autoclose: true,
			todayBtn: true
		});

		$('.js-datepicker').datetimepicker({
			autoclose: true,
			todayBtn: true,
			minView: 2
		});

		$('.js-timepicker').datetimepicker({
			autoclose: true,
			maxView: 1
		});
	});

	// Load jwysiwyg for WYSIWYG forms.
	$(document).on('page.load', function() {
		$('.js-wysiwyg').wysiwyg({
			css: '/css/include/wysiwyg.css',
			initialContent: '',
			controls: {
				highlight: {visible: false},
				subscript: {visible: false},
				superscript: {visible: false},
				insertHorizontalRule: {visible: false},
				code: {visible: false},
				createLink: {visible: true},
				insertImage: {visible: true},
				insertTable: {visible: true},
				h1: {visible: false},
				h2: {visible: false},
				h3: {visible: false},
				html: {visible: true}
			}
		});
	});

	// Enable tagsinput for lists.
	$(document).on('page.load', function() {
		var height = $('.js-tag-list').outerHeight();
		$('.js-tag-list').tagsinput();
		$('.js-tag-list + .bootstrap-tagsinput').css('min-height', (height - 7) + 'px');

		var items = $('.js-tag-list').text().split(',');
		if (items.length > 0) {
			for (var i = 0; i < items.length; i++) {
				$('.js-tag-list').tagsinput('add', items[i]);
			}
		}

		$('.js-tag-list + .bootstrap-tagsinput').sortable({
			containment: 'parent',
			items:		'> .tag',
			scroll:		false,
			opacity:	0.5,
			revert:		true,
			stop:		function() {
				var tags = [];
				$('.js-tag-list + .bootstrap-tagsinput .tag').each(function() {
					tags.push($(this).text());
				});

				$('.js-tag-list').tagsinput('removeAll');
				for (var i = 0; i < tags.length; i++) {
					$('.js-tag-list').tagsinput('add', tags[i]);
				}
			}
		});
	});

	// Media preview plugin for live previews of images.
	$('.js-media-preview').mediaPreview();

	// Support for buttons that clone and insert items in lists based on a master template.
	$('.js-clone').itemClone();
	$(document).on('itemclone.end', function() {
		$(document).trigger('page.load');
	});

	$(document).trigger('page.load');
});

$(document).ready(function() {
	// Generic modal content insertion support.
	$(document).on('click', '.js-open-modal', function() {
		var target = $(this).data('target');
		var href = $(this).data('href');
		var replace = $(this).data('replace');

		if ($(target).find('.modal-body').is(':empty') || replace !== false) {
			$(target).find('.modal-body').empty();
			$(target).find('.modal-body').load(href, function() {
				$(document).trigger('page.load');
			});
		}
	});

	// Generic content load support.
	$(document).on('click change', '.js-content-load', function(event) {
		var that = $(this);
		var data = {internal: true};

		switch (event.type) {
		case "click":
			if ($(event.target).is('a')) {
				data.url = that.attr('href');
				data.target = that.data('target');

				if (History.enabled) {
					event.preventDefault();
				}
			}
			break;
		case "change":
			if ($(event.target).is('select')) {
				data.url = that.find(':selected').data('url');
				data.target = that.data('target');

				if (typeof data.target == 'undefined' || !History.enabled) {
					window.location.href = data.url;
				}
			}
			break;
		}

		if (data.url) {
			History.replaceState(data, document.title, data.url);
		}
	});

	// Support form posting.
	$(document).on('click', '.js-post-form', function() {
		var form = $(this).closest('form');
		$('<input />').attr('type', 'hidden')
			.attr('name', $(this).data('name'))
			.attr('value', $(this).data('value'))
			.appendTo(form);

		form.submit();
	});

	// Support for a button that removes content from the DOM.
	$(document).on('click', '.js-remove', function() {
		$(this).closest('.js-remove-item').animate({opacity: 0}, function() {
			$(this).remove();
		});
	});

	// Module icon selection.
	$(document).on('click', '.module-icons .js-select', function(event) {
		var icon = $(this).children().data('icon');

		$('.module-info .icon-box input').val(icon);
		$('.module-info .icon-box i').attr('class', 'fa fa-' + icon);
	});

	// Options toggle in module list.
	$(document).on('click', '.js-toggle-options', function() {
		var target = $(this).data('target');
		var type = $(target).data('type');
		var name = $(target).data('name');
		var url = $(target).data('url');
		var id = $(target).data('id');

		if ($(target).find('.modal-body').children().length === 0) {
			$(target).find('.modal-body').load(url + '/' + name + '/' + type + '/' + id, function() {
				$(target).on('shown.bs.modal', function() {
					$(document).trigger('page.load');
				});
			});
		}
	});

	$(document).on('change', '.js-change-options', function() {
		var target = $(this).data('target');
		var type = $(this).children(':selected').text();
		var name = $(target).data('name');
		var url = $(target).data('url');

		$(target).data('type', type);

		if ($(target).find('.modal-body').children().length !== 0) {
			$(target).find('.modal-body').load(url + '/' + name + '/' + type, function() {
				$(target).on('shown.bs.modal', function() {
					$(document).trigger('page.load');
				});
			});
		}
	});
});