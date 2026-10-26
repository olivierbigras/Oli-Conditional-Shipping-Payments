/**
 * Render restriction notices from Store API cart extensions on block checkout.
 *
 * Notices are never read from the WooCommerce session.
 */

(function () {
	'use strict';

	var NOTICE_STORE = 'core/notices';
	var CART_STORE = 'wc/store/cart';
	var renderedIds = [];
	var lastKey = '';

	function getConfig() {
		return window.oliCspCheckout || { namespace: 'oli-csp' };
	}

	function getWpData() {
		return window.wp && window.wp.data ? window.wp.data : null;
	}

	function hasShippingMethodsNoticeContainer() {
		return !!(
			document.querySelector('.wp-block-woocommerce-checkout-shipping-methods-block') ||
			document.querySelector('.wc-block-checkout__shipping-option') ||
			document.querySelector('[data-block-name="woocommerce/checkout-shipping-methods-block"]')
		);
	}

	function contextForArea(area) {
		var contexts = getConfig().contexts || {};

		if (area === 'shipping') {
			if (hasShippingMethodsNoticeContainer()) {
				return contexts.shipping || 'wc/checkout/shipping-methods';
			}
			return contexts.shippingFallback || 'wc/checkout';
		}
		if (area === 'payment') {
			return contexts.payment || 'wc/checkout/payments';
		}
		if (area === 'cart') {
			return contexts.cart || 'wc/cart';
		}
		return contexts.checkout || 'wc/checkout';
	}

	window.oliCspNoticeContextForArea = contextForArea;

	function defaultArea() {
		if (document.body && document.body.classList.contains('woocommerce-cart')) {
			return 'cart';
		}
		return 'checkout';
	}

	function getExtensionNotices() {
		var data = getWpData();
		if (!data || !data.select) {
			return [];
		}

		var cartStore = data.select(CART_STORE);
		if (!cartStore || typeof cartStore.getCartData !== 'function') {
			return [];
		}

		var cart = cartStore.getCartData();
		var namespace = getConfig().namespace;
		var ext = cart && cart.extensions ? cart.extensions[namespace] : null;
		if (!ext || !ext.notices || !ext.notices.length) {
			return [];
		}

		return ext.notices;
	}

	function removeRenderedNotices(dispatcher) {
		if (!dispatcher || typeof dispatcher.removeNotice !== 'function') {
			renderedIds = [];
			return;
		}

		renderedIds.forEach(function (item) {
			dispatcher.removeNotice(item.id, item.context);
		});
		renderedIds = [];
	}

	function syncNotices() {
		var data = getWpData();
		if (!data || !data.dispatch) {
			return;
		}

		var notices = getExtensionNotices();
		var key = JSON.stringify(notices) + '|' + contextForArea('shipping');
		if (key === lastKey) {
			return;
		}
		lastKey = key;

		var dispatcher = data.dispatch(NOTICE_STORE);
		if (!dispatcher || typeof dispatcher.createNotice !== 'function') {
			return;
		}

		removeRenderedNotices(dispatcher);

		notices.forEach(function (notice) {
			if (!notice || !notice.message) {
				return;
			}

			var area = notice.area || defaultArea();
			var context = contextForArea(area);
			var id = notice.id || ('oli-csp-' + context);

			dispatcher.createNotice('info', notice.message, {
				id: id,
				context: context,
				isDismissible: true,
				type: 'default'
			});

			renderedIds.push({ id: id, context: context });
		});
	}

	function start() {
		var data = getWpData();
		if (!data || typeof data.subscribe !== 'function') {
			return;
		}

		syncNotices();
		data.subscribe(syncNotices);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
