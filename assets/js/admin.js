/**
 * Admin JavaScript
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		// Initialize Select2 if available.
		if ($.fn.select2) {
			$('.oli-csp-select2').select2({
				width: '100%'
			});
		}

		// Add restriction button.
		$(document).on('click', '.oli-csp-add-restriction', function(e) {
			e.preventDefault();
			
			var $section = $(this).closest('.oli-csp-restriction-section');
			var type = $section.data('type');
			var $list = $section.find('.oli-csp-restrictions-list');
			var index = $list.find('.oli-csp-restriction-row').length;

			// Create new restriction row (simplified for now).
			var $newRow = $('<div class="oli-csp-restriction-row"></div>');
			$newRow.html('<h5>' + window.oliCspAdmin.i18n.addRule + ' #' + (index + 1) + '</h5>' +
				'<p><em>Save to configure this restriction.</em></p>' +
				'<button type="button" class="button button-secondary oli-csp-remove-restriction">' +
				window.oliCspAdmin.i18n.removeRule + '</button>');

			$list.append($newRow);
		});

		// Remove restriction button.
		$(document).on('click', '.oli-csp-remove-restriction', function(e) {
			e.preventDefault();
			
			if (confirm(window.oliCspAdmin.i18n.confirmDelete)) {
				$(this).closest('.oli-csp-restriction-row').remove();
			}
		});

		// Handle import report display.
		if ($('.oli-csp-import-report').length) {
			$('.oli-csp-import-report').each(function() {
				$(this).show();
			});
		}
	});

})(jQuery);
