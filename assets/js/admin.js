/**
 * Admin JavaScript
 */

(function($) {
	'use strict';

	function oliCspInitSelect($context) {
		var $selects = $context.find('.wc-enhanced-select, .oli-csp-select-wide');
		if (!$selects.length) {
			return;
		}
		if ($.fn.selectWoo) {
			$selects.filter(':not(.select2-hidden-accessible)').selectWoo({
				width: '100%'
			});
		} else if ($.fn.select2) {
			$selects.filter(':not(.select2-hidden-accessible)').select2({
				width: '100%'
			});
		}
	}

	$(document).ready(function() {
		oliCspInitSelect($(document).find('.oli-csp-restrictions-list, .oli-csp-product-restriction-sections'));

		$(document).on('click', '.oli-csp-add-restriction', function(e) {
			e.preventDefault();

			var $section = $(this).closest('.oli-csp-restriction-section');
			var $list = $section.find('.oli-csp-restrictions-list');
			var template = $section.find('template.oli-csp-restriction-template').get(0);
			if (!template || !window.oliCspAdmin) {
				return;
			}

			var index = $list.find('.oli-csp-restriction-row').length;
			var $newRow = $(template.content.cloneNode(true)).find('.oli-csp-restriction-row').first();
			$newRow.find('[name]').each(function() {
				var name = $(this).attr('name');
				if (name) {
					$(this).attr('name', name.replace(/__index__/g, String(index)));
				}
			});
			$newRow.find('h5').text(
				window.oliCspAdmin.i18n.restrictionLabel.replace('%d', String(index + 1))
			);
			$list.append($newRow);
			oliCspInitSelect($newRow);
		});

		$(document).on('click', '.oli-csp-remove-restriction', function(e) {
			e.preventDefault();

			if (!window.oliCspAdmin) {
				return;
			}

			if (window.confirm(window.oliCspAdmin.i18n.confirmDelete)) {
				$(this).closest('.oli-csp-restriction-row').remove();
			}
		});
	});

})(jQuery);
