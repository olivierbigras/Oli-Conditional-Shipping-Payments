/**
 * Render restriction notices from Store API cart extensions on block checkout.
 *
 * Notices are never read from the WooCommerce session and never go through
 * WooCommerce's notice banner, which always speaks politely.
 */

(function (root, factory) {
	var api = factory();

	if (typeof module === 'object' && module.exports) {
		module.exports = api;
	}

	if (root) {
		root.oliCspCheckoutBlocks = api;
		root.oliCspNoticeContextForArea = api.contextForArea;
		root.oliCspSpeakPolitenessForArea = api.speakPolitenessForArea;
		root.oliCspAnnounceNoticeOnce = api.announceNoticeOnce;

		if (!root.oliCspCheckoutBlocksSkipAutoStart && typeof document !== 'undefined') {
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', function () {
					api.start();
				});
			} else {
				api.start();
			}
		}
	}
})(typeof window !== 'undefined' ? window : (typeof globalThis !== 'undefined' ? globalThis : this), function () {
	'use strict';

	var CART_STORE = 'wc/store/cart';
	var DEFAULT_SPEAK_DELAY = 500;
	var announcedIds = {};
	var lastVisualKey = '';
	var announceTimer = null;
	var pendingSignature = '';
	var started = false;
	var unsubscribe = null;

	function getConfig() {
		return (typeof window !== 'undefined' && window.oliCspCheckout) ? window.oliCspCheckout : { namespace: 'oli-csp' };
	}

	function getWpData() {
		return (typeof window !== 'undefined' && window.wp && window.wp.data) ? window.wp.data : null;
	}

	function getSpeakDelay() {
		var delay = getConfig().speakDelay;
		if (typeof delay === 'number' && delay >= 0) {
			return delay;
		}
		return DEFAULT_SPEAK_DELAY;
	}

	function defaultArea() {
		if (typeof document !== 'undefined' && document.body && document.body.classList && document.body.classList.contains('woocommerce-cart')) {
			return 'cart';
		}
		return 'checkout';
	}

	function slotSelectorsForArea(area) {
		var slots = getConfig().slots || {};
		if (slots[area] && slots[area].length) {
			return slots[area];
		}

		if (area === 'shipping') {
			return [
				'.wp-block-woocommerce-checkout-shipping-methods-block',
				'.wc-block-checkout__shipping-option',
				'[data-block-name="woocommerce/checkout-shipping-methods-block"]'
			];
		}
		if (area === 'payment') {
			return [
				'.wp-block-woocommerce-checkout-payment-block',
				'.wc-block-checkout__payment-method',
				'[data-block-name="woocommerce/checkout-payment-block"]'
			];
		}
		if (area === 'cart') {
			return ['.wp-block-woocommerce-cart', '.wc-block-cart'];
		}
		return ['.wp-block-woocommerce-checkout', '.wc-block-checkout'];
	}

	function hasShippingMethodsNoticeContainer() {
		if (typeof document === 'undefined' || !document.querySelector) {
			return false;
		}

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

	function speakPolitenessForArea(area) {
		var speak = getConfig().speak || {};

		if (area === 'shipping') {
			return speak.shipping || 'assertive';
		}

		return speak[area] || 'polite';
	}

	function findSlot(area) {
		if (typeof document === 'undefined' || !document.querySelector) {
			return null;
		}

		var selectors = slotSelectorsForArea(area);
		var i;
		for (i = 0; i < selectors.length; i++) {
			var slot = document.querySelector(selectors[i]);
			if (slot) {
				return slot;
			}
		}

		if (area !== 'checkout') {
			return findSlot('checkout');
		}

		return null;
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
		var namespace = getConfig().namespace || 'oli-csp';
		var ext = cart && cart.extensions ? cart.extensions[namespace] : null;
		if (!ext || !ext.notices || !ext.notices.length) {
			return [];
		}

		return ext.notices;
	}

	function getShippingRateSignature() {
		var data = getWpData();
		if (!data || !data.select) {
			return '';
		}

		var cartStore = data.select(CART_STORE);
		if (!cartStore || typeof cartStore.getCartData !== 'function') {
			return '';
		}

		var cart = cartStore.getCartData();
		if (!cart || typeof cart.shippingRates === 'undefined') {
			return '';
		}

		try {
			return JSON.stringify(cart.shippingRates);
		} catch (e) {
			return '';
		}
	}

	function isCartBusy() {
		var data = getWpData();
		if (!data || !data.select) {
			return false;
		}

		var cartStore = data.select(CART_STORE);
		if (!cartStore) {
			return false;
		}

		var checks = [
			'isAddressLoading',
			'isCustomerDataUpdating',
			'hasPendingItems',
			'isShippingRateBeingSelected',
			'isCalculating'
		];
		var i;
		for (i = 0; i < checks.length; i++) {
			if (typeof cartStore[checks[i]] === 'function' && cartStore[checks[i]]()) {
				return true;
			}
		}

		return false;
	}

	function cartSignature(notices) {
		return JSON.stringify({
			notices: notices || getExtensionNotices(),
			rates: getShippingRateSignature(),
			busy: isCartBusy()
		});
	}

	function noticeSpeakKey(notice) {
		if (!notice) {
			return '';
		}
		return notice.id || ((notice.area || '') + '|' + (notice.message || ''));
	}

	function forgetStaleAnnouncements(notices) {
		var current = {};
		(notices || []).forEach(function (notice) {
			var key = noticeSpeakKey(notice);
			if (key) {
				current[key] = true;
			}
		});
		Object.keys(announcedIds).forEach(function (key) {
			if (!current[key]) {
				delete announcedIds[key];
			}
		});
	}

	function announceNoticeOnce(notice) {
		var key = noticeSpeakKey(notice);
		if (!key || !notice.message || announcedIds[key]) {
			return false;
		}

		announcedIds[key] = true;
		if (typeof window !== 'undefined' && window.wp && window.wp.a11y && typeof window.wp.a11y.speak === 'function') {
			var politeness = speakPolitenessForArea(notice.area || defaultArea());
			window.wp.a11y.speak(notice.message, politeness);
		}

		return true;
	}

	function removeInlineNotices() {
		if (typeof document === 'undefined' || !document.querySelectorAll) {
			return;
		}

		var nodes = document.querySelectorAll('.oli-csp-inline-notice');
		var i;
		for (i = 0; i < nodes.length; i++) {
			if (nodes[i].parentNode) {
				nodes[i].parentNode.removeChild(nodes[i]);
			}
		}
	}

	function buildNoticeElement(notice) {
		var wrap = document.createElement('div');
		wrap.className = 'wc-block-components-notice-banner is-info oli-csp-inline-notice';
		wrap.setAttribute('data-oli-csp-area', notice.area || 'checkout');
		wrap.setAttribute('role', 'status');

		var content = document.createElement('div');
		content.className = 'wc-block-components-notice-banner__content';
		content.textContent = notice.message;
		wrap.appendChild(content);

		return wrap;
	}

	function renderInlineNotices(notices) {
		if (typeof document === 'undefined') {
			return;
		}

		removeInlineNotices();

		(notices || []).forEach(function (notice) {
			if (!notice || !notice.message) {
				return;
			}

			var area = notice.area || defaultArea();
			var slot = findSlot(area);
			if (!slot) {
				return;
			}

			var el = buildNoticeElement({
				id: notice.id,
				message: notice.message,
				area: area
			});

			if (slot.firstChild) {
				slot.insertBefore(el, slot.firstChild);
			} else {
				slot.appendChild(el);
			}
		});
	}

	function speakReadyNotices(notices) {
		forgetStaleAnnouncements(notices);
		(notices || []).forEach(function (notice) {
			if (notice && notice.message) {
				announceNoticeOnce(notice);
			}
		});
	}

	function cancelAnnouncement() {
		if (announceTimer) {
			clearTimeout(announceTimer);
			announceTimer = null;
		}
	}

	function scheduleAnnouncement(notices) {
		cancelAnnouncement();

		if (isCartBusy()) {
			return;
		}

		pendingSignature = cartSignature(notices);
		announceTimer = setTimeout(function () {
			announceTimer = null;
			if (isCartBusy()) {
				return;
			}

			var current = getExtensionNotices();
			if (cartSignature(current) !== pendingSignature) {
				return;
			}

			speakReadyNotices(current);
		}, getSpeakDelay());
	}

	function syncNotices() {
		var notices = getExtensionNotices();
		var visualKey = JSON.stringify(notices);
		if (visualKey !== lastVisualKey) {
			lastVisualKey = visualKey;
			renderInlineNotices(notices);
		}

		scheduleAnnouncement(notices);
	}

	function start() {
		var data = getWpData();
		if (started || !data || typeof data.subscribe !== 'function') {
			return;
		}

		started = true;
		syncNotices();
		unsubscribe = data.subscribe(syncNotices);
	}

	function reset() {
		cancelAnnouncement();
		announcedIds = {};
		lastVisualKey = '';
		pendingSignature = '';
		started = false;
		if (typeof unsubscribe === 'function') {
			unsubscribe();
		}
		unsubscribe = null;
		removeInlineNotices();
	}

	return {
		DEFAULT_SPEAK_DELAY: DEFAULT_SPEAK_DELAY,
		start: start,
		reset: reset,
		syncNotices: syncNotices,
		scheduleAnnouncement: scheduleAnnouncement,
		renderInlineNotices: renderInlineNotices,
		announceNoticeOnce: announceNoticeOnce,
		speakPolitenessForArea: speakPolitenessForArea,
		contextForArea: contextForArea,
		slotSelectorsForArea: slotSelectorsForArea,
		noticeSpeakKey: noticeSpeakKey,
		forgetStaleAnnouncements: forgetStaleAnnouncements,
		isCartBusy: isCartBusy,
		getExtensionNotices: getExtensionNotices,
		getSpeakDelay: getSpeakDelay
	};
});
