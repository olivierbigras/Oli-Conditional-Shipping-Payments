/**
 * Block checkout notice renderer: one speak after cart settle, no createNotice.
 */

'use strict';

const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

const SCRIPT = path.resolve(__dirname, '../../assets/js/checkout-blocks.js');

function createNode(tag) {
	const node = {
		tagName: String(tag || 'div').toUpperCase(),
		className: '',
		id: '',
		attrs: {},
		childNodes: [],
		parentNode: null,
		_text: '',
		get textContent() {
			if (this.childNodes.length) {
				return this.childNodes.map((child) => child.textContent).join('');
			}
			return this._text;
		},
		set textContent(value) {
			this._text = String(value);
			this.childNodes = [];
		},
		setAttribute(name, value) {
			this.attrs[name] = String(value);
			if (name === 'id') {
				this.id = String(value);
			}
			if (name === 'class') {
				this.className = String(value);
			}
		},
		getAttribute(name) {
			return Object.prototype.hasOwnProperty.call(this.attrs, name) ? this.attrs[name] : null;
		},
		appendChild(child) {
			child.parentNode = this;
			this.childNodes.push(child);
			return child;
		},
		insertBefore(child, ref) {
			child.parentNode = this;
			const index = ref ? this.childNodes.indexOf(ref) : -1;
			if (index === -1) {
				this.childNodes.push(child);
			} else {
				this.childNodes.splice(index, 0, child);
			}
			return child;
		},
		removeChild(child) {
			this.childNodes = this.childNodes.filter((item) => item !== child);
			child.parentNode = null;
			return child;
		},
		matches(selector) {
			if (selector.charAt(0) === '#') {
				return this.id === selector.slice(1);
			}
			if (selector.charAt(0) === '.') {
				return this.className.split(/\s+/).filter(Boolean).includes(selector.slice(1));
			}
			if (selector.charAt(0) === '[' && selector.slice(-1) === ']') {
				const body = selector.slice(1, -1);
				const eq = body.indexOf('=');
				if (eq === -1) {
					return this.attrs[body] != null;
				}
				const key = body.slice(0, eq);
				const raw = body.slice(eq + 1).replace(/^["']|["']$/g, '');
				return this.attrs[key] === raw;
			}
			return this.tagName === selector.toUpperCase();
		},
		querySelectorAll(selector) {
			const found = [];
			const walk = (current) => {
				current.childNodes.forEach((child) => {
					if (child.matches(selector)) {
						found.push(child);
					}
					walk(child);
				});
			};
			walk(this);
			return found;
		},
		querySelector(selector) {
			return this.querySelectorAll(selector)[0] || null;
		},
	};

	node.classList = {
		contains(name) {
			return node.className.split(/\s+/).filter(Boolean).includes(name);
		},
	};

	return node;
}

function createDocument() {
	const document = createNode('document');
	const body = createNode('body');
	const shipping = createNode('div');
	shipping.className = 'wp-block-woocommerce-checkout-shipping-methods-block';
	const payment = createNode('div');
	payment.className = 'wp-block-woocommerce-checkout-payment-block';
	const assertive = createNode('div');
	assertive.id = 'a11y-speak-assertive';
	assertive.setAttribute('id', 'a11y-speak-assertive');
	const polite = createNode('div');
	polite.id = 'a11y-speak-polite';
	polite.setAttribute('id', 'a11y-speak-polite');

	document.body = body;
	document.appendChild(body);
	body.appendChild(shipping);
	body.appendChild(payment);
	body.appendChild(assertive);
	body.appendChild(polite);

	document.createElement = (tag) => createNode(tag);
	document.getElementById = (id) => document.querySelector('#' + id);
	document.readyState = 'complete';

	return {
		document,
		shipping,
		payment,
		assertive,
		polite,
	};
}

function createA11y(assertive, polite) {
	const calls = [];
	return {
		calls,
		speak(message, politeness) {
			calls.push({ message, politeness });
			assertive.textContent = '';
			polite.textContent = '';
			const target = politeness === 'assertive' ? assertive : polite;
			target.textContent = message;
		},
	};
}

function loadApi() {
	delete require.cache[require.resolve(SCRIPT)];
	global.oliCspCheckoutBlocksSkipAutoStart = true;
	return require(SCRIPT);
}

function wait(ms) {
	return new Promise((resolve) => {
		setTimeout(resolve, ms);
	});
}

function setupCheckout(options) {
	const delay = options.speakDelay != null ? options.speakDelay : 500;
	const dom = createDocument();
	const a11y = createA11y(dom.assertive, dom.polite);
	const listeners = [];
	const state = {
		notices: options.notices || [],
		busy: !!options.busy,
		shippingRates: options.shippingRates || [{ rate_id: 'free_shipping:1' }],
	};

	global.window = global;
	global.document = dom.document;
	global.window.document = dom.document;
	global.window.oliCspCheckout = {
		namespace: 'oli-csp',
		speakDelay: delay,
	};
	global.window.wp = {
		a11y,
		data: {
			select(store) {
				if (store !== 'wc/store/cart') {
					return null;
				}
				return {
					getCartData() {
						return {
							extensions: {
								'oli-csp': { notices: state.notices },
							},
							shippingRates: state.shippingRates,
						};
					},
					isAddressLoading() {
						return state.busy;
					},
					isCustomerDataUpdating() {
						return false;
					},
					hasPendingItems() {
						return false;
					},
				};
			},
			subscribe(fn) {
				listeners.push(fn);
				return function unsubscribe() {
					const index = listeners.indexOf(fn);
					if (index !== -1) {
						listeners.splice(index, 1);
					}
				};
			},
		},
	};

	const api = loadApi();
	api.reset();

	return {
		api,
		a11y,
		dom,
		state,
		notify() {
			listeners.slice().forEach((fn) => {
				fn();
			});
		},
	};
}

const SHIPPING_NOTICE = {
	id: 'oli-csp-shipping-10001',
	message: 'QA: Flat indisponible pour 100*',
	area: 'shipping',
};

const PAYMENT_NOTICE = {
	id: 'oli-csp-payment-90210',
	message: 'QA: COD masqué',
	area: 'payment',
};

test('createNotice is not used', () => {
	const source = fs.readFileSync(SCRIPT, 'utf8');
	assert.equal(source.includes('createNotice('), false);
	assert.equal(source.includes('core/notices'), false);
	assert.match(source, /oli-csp-inline-notice/);
});

test('one speak per restriction change', async () => {
	const harness = setupCheckout({
		speakDelay: 40,
		notices: [SHIPPING_NOTICE],
	});

	harness.api.start();
	await wait(80);
	assert.equal(harness.a11y.calls.length, 1);
	assert.equal(harness.a11y.calls[0].message, SHIPPING_NOTICE.message);

	harness.notify();
	await wait(80);
	assert.equal(harness.a11y.calls.length, 1, 're-render must not speak again');

	harness.state.notices = [PAYMENT_NOTICE];
	harness.notify();
	await wait(80);
	assert.equal(harness.a11y.calls.length, 2);
	assert.equal(harness.a11y.calls[1].message, PAYMENT_NOTICE.message);
	assert.equal(harness.a11y.calls[1].politeness, 'polite');

	harness.api.reset();
});

test('10001 keeps the assertive restriction text 2s after input, without reload', async () => {
	const harness = setupCheckout({
		speakDelay: 500,
		busy: true,
		notices: [],
		shippingRates: [],
	});

	harness.api.start();
	assert.equal(harness.a11y.calls.length, 0);

	harness.state.notices = [SHIPPING_NOTICE];
	harness.state.shippingRates = [{ rate_id: 'free_shipping:1' }];
	harness.notify();
	assert.ok(
		harness.dom.shipping.querySelectorAll('.oli-csp-inline-notice').length > 0,
		'inline shipping notice is visible immediately'
	);

	await wait(200);
	harness.a11y.speak('1 shipping option was found.', 'polite');
	assert.equal(harness.dom.assertive.textContent, '');
	assert.equal(harness.dom.polite.textContent, '1 shipping option was found.');

	harness.state.busy = false;
	harness.notify();

	await wait(2000);
	assert.equal(harness.dom.assertive.textContent, SHIPPING_NOTICE.message);
	assert.equal(harness.a11y.calls.filter((call) => call.message === SHIPPING_NOTICE.message).length, 1);
	assert.equal(
		harness.a11y.calls.filter((call) => call.message === SHIPPING_NOTICE.message)[0].politeness,
		'assertive'
	);

	harness.api.reset();
});

test('90210 payment notice is announced politely', async () => {
	const harness = setupCheckout({
		speakDelay: 40,
		notices: [PAYMENT_NOTICE],
	});

	harness.api.start();
	await wait(80);
	assert.equal(harness.dom.polite.textContent, PAYMENT_NOTICE.message);
	assert.equal(harness.a11y.calls[0].politeness, 'polite');
	assert.ok(harness.dom.payment.querySelectorAll('.oli-csp-inline-notice').length > 0);

	harness.api.reset();
});
