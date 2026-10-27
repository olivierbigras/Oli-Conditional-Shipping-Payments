/**
 * Admin JavaScript for the restriction rule builder.
 */

(function ($) {
	'use strict';

	function oliCspConfig() {
		return window.oliCspAdmin || {};
	}

	function oliCspInitSelect($context) {
		var $selects = $context.find('.wc-enhanced-select, .wc-product-search, .oli-csp-select-wide');
		if (!$selects.length) {
			return;
		}

		if ($.fn.selectWoo) {
			$selects.filter(':not(.select2-hidden-accessible)').each(function () {
				var $el = $(this);
				var options = { width: '100%' };
				if ($el.hasClass('wc-product-search')) {
					options.minimumInputLength = 1;
				}
				$el.selectWoo(options);
			});
		} else if ($.fn.select2) {
			$selects.filter(':not(.select2-hidden-accessible)').select2({ width: '100%' });
		}

		if ($(document.body).triggerHandler) {
			$(document.body).trigger('wc-enhanced-select-init');
		}
	}

	function oliCspDestroySelect($context) {
		$context.find('.select2-hidden-accessible').each(function () {
			var $el = $(this);
			if ($el.data('select2')) {
				$el.select2('destroy');
			}
		});
	}

	function oliCspEscapeHtml(value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function oliCspNameBase($section) {
		var base = $section.find('.oli-csp-add-restriction').first().data('name-base');
		return base ? String(base) : 'oli_csp_restrictions';
	}

	function oliCspFindTemplate(selector, $context) {
		var $scope = $context.closest('.oli-csp-product-restrictions, .oli-csp-restrictions-wrapper, form').first();
		var $template = $scope.find(selector).first();
		if (!$template.length) {
			$template = $(selector).first();
		}
		return $template;
	}

	function oliCspCloneTemplate($template) {
		var node = $template.get(0);
		if (!node || !node.content) {
			return $();
		}
		return $(node.content.cloneNode(true));
	}

	function oliCspReplaceTokens($root, tokens) {
		$root.find('[name], [id], [for]').addBack('[name], [id], [for]').each(function () {
			var $el = $(this);
			['name', 'id', 'for'].forEach(function (attr) {
				var value = $el.attr(attr);
				if (!value) {
					return;
				}
				Object.keys(tokens).forEach(function (token) {
					value = value.split(token).join(tokens[token]);
				});
				$el.attr(attr, value);
			});
		});
	}

	function oliCspBuildOperatorHtml(namePrefix, operatorType, selected) {
		var config = oliCspConfig();
		if (operatorType === 'none') {
			return '<input type="hidden" class="oli-csp-condition-operator" name="' + oliCspEscapeHtml(namePrefix) + '[operator]" value="" data-field="operator" />';
		}

		var operators = (config.operators && config.operators[operatorType]) ? config.operators[operatorType] : {};
		var html = '<select class="oli-csp-condition-operator" name="' + oliCspEscapeHtml(namePrefix) + '[operator]" data-field="operator">';
		Object.keys(operators).forEach(function (key) {
			html += '<option value="' + oliCspEscapeHtml(key) + '"' + (key === selected ? ' selected' : '') + '>' + oliCspEscapeHtml(operators[key]) + '</option>';
		});
		html += '</select>';
		return html;
	}

	function oliCspBuildChoiceSelect(namePrefix, choices, multipleClass) {
		var html = '<select name="' + oliCspEscapeHtml(namePrefix) + '[value][]" multiple="multiple" class="wc-enhanced-select oli-csp-select-wide ' + multipleClass + '" data-field="value" data-multiple="1">';
		Object.keys(choices || {}).forEach(function (key) {
			html += '<option value="' + oliCspEscapeHtml(key) + '">' + oliCspEscapeHtml(choices[key]) + '</option>';
		});
		html += '</select>';
		return html;
	}

	function oliCspBuildValueHtml(namePrefix, type) {
		var config = oliCspConfig();
		var schema = (config.schema && config.schema[type]) ? config.schema[type] : { input: 'text', multiple: false };
		var i18n = config.i18n || {};
		var choices = config.choices || {};
		var input = schema.input;

		if (input === 'number') {
			return '<input type="number" step="0.01" class="oli-csp-input-number" name="' + oliCspEscapeHtml(namePrefix) + '[value]" value="" data-field="value" />';
		}
		if (input === 'yesno') {
			return '<select class="oli-csp-input-yesno" name="' + oliCspEscapeHtml(namePrefix) + '[value]" data-field="value">' +
				'<option value="yes">' + oliCspEscapeHtml(i18n.yes || 'Yes') + '</option>' +
				'<option value="no">' + oliCspEscapeHtml(i18n.no || 'No') + '</option>' +
				'</select>';
		}
		if (input === 'textarea') {
			return '<textarea class="oli-csp-input-textarea" name="' + oliCspEscapeHtml(namePrefix) + '[value]" rows="2" data-field="value" data-multiple="1" placeholder="' + oliCspEscapeHtml(i18n.postcodeHint || '') + '"></textarea>';
		}
		if (input === 'product') {
			return '<select class="wc-product-search oli-csp-input-product" multiple="multiple" name="' + oliCspEscapeHtml(namePrefix) + '[value][]" data-field="value" data-multiple="1" data-placeholder="' + oliCspEscapeHtml(i18n.searchProduct || '') + '" data-action="woocommerce_json_search_products_and_variations"></select>';
		}
		if (input === 'category') {
			return oliCspBuildChoiceSelect(namePrefix, choices.category, 'oli-csp-input-select');
		}
		if (input === 'tag') {
			return oliCspBuildChoiceSelect(namePrefix, choices.tag, 'oli-csp-input-select');
		}
		if (input === 'shipping_class') {
			return oliCspBuildChoiceSelect(namePrefix, choices.shipping_class, 'oli-csp-input-select');
		}
		if (input === 'country') {
			return oliCspBuildChoiceSelect(namePrefix, choices.country, 'oli-csp-input-select');
		}
		if (input === 'role') {
			return oliCspBuildChoiceSelect(namePrefix, choices.role, 'oli-csp-input-select');
		}
		if (input === 'shipping_method') {
			return oliCspBuildChoiceSelect(namePrefix, choices.shipping_method, 'oli-csp-input-select');
		}
		if (input === 'payment_gateway') {
			return oliCspBuildChoiceSelect(namePrefix, choices.payment_gateway, 'oli-csp-input-select');
		}

		return '<input type="text" class="oli-csp-input-text regular-text" name="' + oliCspEscapeHtml(namePrefix) + '[value]" value="" data-field="value" />';
	}

	function oliCspRefreshSummary($row) {
		var config = oliCspConfig();
		var title = $.trim($row.find('input[name$="[title]"]').val() || '');
		var $summary = $row.find('.oli-csp-restriction-summary-title').first();
		if (!$summary.length) {
			return;
		}
		if (title) {
			$summary.text(title);
			return;
		}
		var index = $row.parent().children('.oli-csp-restriction-row').index($row);
		var template = (config.i18n && config.i18n.restrictionLabel) ? config.i18n.restrictionLabel : 'Restriction #%d';
		$summary.text(template.replace('%d', String(index + 1)));
	}

	function oliCspAddOrSeparator($groups) {
		var i18n = oliCspConfig().i18n || {};
		if ($groups.children('.oli-csp-condition-group').length) {
			$groups.append('<p class="oli-csp-or-separator" aria-hidden="true">' + oliCspEscapeHtml(i18n.or || 'OR') + '</p>');
		}
	}

	$(document).ready(function () {
		oliCspInitSelect($(document).find('.oli-csp-restrictions-list, .oli-csp-product-restriction-sections'));

		$(document).on('click', '.oli-csp-add-restriction', function (e) {
			e.preventDefault();

			var $section = $(this).closest('.oli-csp-restriction-section');
			var type = String($section.data('type') || $(this).data('type') || '');
			var $list = $section.find('.oli-csp-restrictions-list');
			var $template = oliCspFindTemplate('template.oli-csp-restriction-template[data-type="' + type + '"]', $section);
			if (!$template.length) {
				return;
			}

			var index = $list.find('.oli-csp-restriction-row').length;
			var $clone = oliCspCloneTemplate($template);
			oliCspReplaceTokens($clone, {
				__index__: String(index),
				__type__: type
			});
			var $row = $clone.find('.oli-csp-restriction-row').first();
			$list.append($row);
			oliCspRefreshSummary($row);
			oliCspInitSelect($row);
		});

		$(document).on('click', '.oli-csp-remove-restriction', function (e) {
			e.preventDefault();
			var config = oliCspConfig();
			var message = (config.i18n && config.i18n.confirmDelete) ? config.i18n.confirmDelete : '';
			if (message && !window.confirm(message)) {
				return;
			}
			$(this).closest('.oli-csp-restriction-row').remove();
		});

		$(document).on('click', '.oli-csp-add-group', function (e) {
			e.preventDefault();

			var $row = $(this).closest('.oli-csp-restriction-row');
			var $section = $row.closest('.oli-csp-restriction-section');
			var $groups = $row.find('.oli-csp-condition-groups').first();
			var $template = oliCspFindTemplate('template.oli-csp-group-template', $section);
			if (!$template.length) {
				return;
			}

			var type = String($section.data('type') || '');
			var index = $row.parent().children('.oli-csp-restriction-row').index($row);
			var group = $groups.children('.oli-csp-condition-group').length;
			var $clone = oliCspCloneTemplate($template);
			oliCspReplaceTokens($clone, {
				__index__: String(index),
				__type__: type,
				__group__: String(group)
			});
			oliCspAddOrSeparator($groups);
			$groups.append($clone);
			oliCspInitSelect($groups.children('.oli-csp-condition-group').last());
		});

		$(document).on('click', '.oli-csp-remove-group', function (e) {
			e.preventDefault();
			var $group = $(this).closest('.oli-csp-condition-group');
			var $prev = $group.prev('.oli-csp-or-separator');
			var $next = $group.next('.oli-csp-or-separator');
			if ($prev.length) {
				$prev.remove();
			} else {
				$next.remove();
			}
			$group.remove();
		});

		$(document).on('click', '.oli-csp-add-condition', function (e) {
			e.preventDefault();

			var $group = $(this).closest('.oli-csp-condition-group');
			var $row = $group.closest('.oli-csp-restriction-row');
			var $section = $row.closest('.oli-csp-restriction-section');
			var $list = $group.find('.oli-csp-conditions').first();
			var $template = oliCspFindTemplate('template.oli-csp-condition-template', $section);
			if (!$template.length) {
				return;
			}

			var type = String($section.data('type') || '');
			var index = $row.parent().children('.oli-csp-restriction-row').index($row);
			var group = $row.find('.oli-csp-condition-group').index($group);
			var cond = $list.children('.oli-csp-condition').length;
			var $clone = oliCspCloneTemplate($template);
			oliCspReplaceTokens($clone, {
				__index__: String(index),
				__type__: type,
				__group__: String(group),
				__cond__: String(cond)
			});
			$list.append($clone);
			oliCspInitSelect($list.children('.oli-csp-condition').last());
		});

		$(document).on('click', '.oli-csp-remove-condition', function (e) {
			e.preventDefault();
			$(this).closest('.oli-csp-condition').remove();
		});

		$(document).on('change', '.oli-csp-condition-type', function () {
			var $cond = $(this).closest('.oli-csp-condition');
			var type = $(this).val();
			var config = oliCspConfig();
			var schema = (config.schema && config.schema[type]) ? config.schema[type] : { operators: 'numeric' };
			var name = String($(this).attr('name') || '').replace(/\[type\]$/, '');

			oliCspDestroySelect($cond.find('.oli-csp-condition-value'));
			$cond.find('.oli-csp-condition-operator').remove();
			$(oliCspBuildOperatorHtml(name, schema.operators, '')).insertAfter($(this));
			$cond.find('.oli-csp-condition-value').html(oliCspBuildValueHtml(name, type));
			oliCspInitSelect($cond);
		});

		$(document).on('input change', '.oli-csp-restriction-row input[name$="[title]"]', function () {
			oliCspRefreshSummary($(this).closest('.oli-csp-restriction-row'));
		});
	});
})(jQuery);
