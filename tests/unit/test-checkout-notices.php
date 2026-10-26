<?php
/**
 * Checkout notice delivery tests (classic fragment + Store API payload).
 *
 * @package Oli_Conditional_Shipping_Payments
 */

use PHPUnit\Framework\TestCase;

/**
 * Class Test_Oli_CSP_Checkout_Notices
 */
class Test_Oli_CSP_Checkout_Notices extends TestCase {

	/**
	 * Load classes that do not need WordPress at file scope.
	 */
	protected function setUp(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
		}

		if ( ! class_exists( 'Oli_CSP_Restriction_Handler' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/class-oli-csp-restriction-handler.php';
		}

		if ( ! class_exists( 'Oli_CSP_Store_API_Integration' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/class-oli-csp-store-api-integration.php';
		}
	}

	/**
	 * Classic notices live in a replaceable container and appear once.
	 */
	public function test_classic_container_renders_each_message_once() {
		$message = 'QA: Flat indisponible pour 100*';
		$html    = Oli_CSP_Restriction_Handler::build_notice_container_html( array( $message, $message ), 'checkout' );

		$this->assertStringContainsString( 'oli-csp-checkout-notices', $html );
		$this->assertSame( 1, substr_count( $html, 'class="oli-csp-checkout-notices"' ) );
		$this->assertSame( 1, substr_count( $html, $message ) );
		$this->assertSame( 1, substr_count( $html, 'woocommerce-info' ) );
	}

	/**
	 * Reloading/rebuilding the container does not accumulate copies.
	 */
	public function test_classic_container_rebuild_does_not_duplicate() {
		$messages = array( 'QA: Flat indisponible pour 100*' );
		$first    = Oli_CSP_Restriction_Handler::build_notice_container_html( $messages, 'checkout' );
		$second   = Oli_CSP_Restriction_Handler::build_notice_container_html( $messages, 'checkout' );

		$this->assertSame( $first, $second );
		$this->assertSame( 1, substr_count( $second, 'QA: Flat indisponible pour 100*' ) );
	}

	/**
	 * Cart uses a distinct replaceable container.
	 */
	public function test_classic_cart_container_class() {
		$html = Oli_CSP_Restriction_Handler::build_notice_container_html( array( 'Cart notice' ), 'cart' );
		$this->assertStringContainsString( 'oli-csp-cart-notices', $html );
		$this->assertStringNotContainsString( 'oli-csp-checkout-notices', $html );
	}

	/**
	 * Restriction notices must not be stored in the WooCommerce session.
	 */
	public function test_restriction_notices_are_not_session_backed() {
		$this->assertFalse( Oli_CSP_Restriction_Handler::persist_notices_in_session() );
		$this->assertFalse( method_exists( 'Oli_CSP_Restriction_Handler', 'maybe_add_runtime_notices' ) );
		$this->assertFalse( method_exists( 'Oli_CSP_Store_API_Integration', 'add_checkout_notices' ) );
	}

	/**
	 * Block checkout reads notices from Store API extension data, not session.
	 */
	public function test_store_api_extension_payload_contains_notices() {
		$payload = Oli_CSP_Store_API_Integration::format_store_api_notices(
			array(
				array(
					'message' => 'QA: Flat indisponible pour 100*',
					'area'    => 'shipping',
				),
				array(
					'message' => '<strong>QA: COD masqué</strong>',
					'area'    => 'payment',
				),
			)
		);

		$this->assertArrayHasKey( 'notices', $payload );
		$this->assertCount( 2, $payload['notices'] );
		$this->assertSame( 'QA: Flat indisponible pour 100*', $payload['notices'][0]['message'] );
		$this->assertSame( 'shipping', $payload['notices'][0]['area'] );
		$this->assertSame( 'QA: COD masqué', $payload['notices'][1]['message'] );
		$this->assertSame( 'payment', $payload['notices'][1]['area'] );
		$this->assertStringStartsWith( 'oli-csp-', $payload['notices'][0]['id'] );
		$this->assertNotSame( $payload['notices'][0]['id'], $payload['notices'][1]['id'] );
	}

	/**
	 * Empty Store API payload is a list, never a session write.
	 */
	public function test_store_api_extension_payload_empty() {
		$payload = Oli_CSP_Store_API_Integration::format_store_api_notices( array() );
		$this->assertSame( array( 'notices' => array() ), $payload );
	}

	/**
	 * String notices default to the checkout area.
	 */
	public function test_store_api_accepts_plain_strings() {
		$payload = Oli_CSP_Store_API_Integration::format_store_api_notices( array( 'Hello' ) );
		$this->assertSame( 'checkout', $payload['notices'][0]['area'] );
		$this->assertSame( 'Hello', $payload['notices'][0]['message'] );
	}

	/**
	 * Shipping notices use wc/checkout/shipping-methods, not wc/checkout/shipping.
	 */
	public function test_notice_context_mapping() {
		$this->assertSame(
			'wc/checkout/shipping-methods',
			Oli_CSP_Store_API_Integration::notice_context_for_area( 'shipping' )
		);
		$this->assertSame(
			'wc/checkout/shipping-methods',
			Oli_CSP_Store_API_Integration::notice_context_for_area(
				'shipping',
				array( 'wc/checkout/shipping-methods', 'wc/checkout/payments' )
			)
		);
		$this->assertSame(
			'wc/checkout',
			Oli_CSP_Store_API_Integration::notice_context_for_area( 'shipping', array() )
		);
		$this->assertSame(
			'wc/checkout',
			Oli_CSP_Store_API_Integration::notice_context_for_area(
				'shipping',
				array( 'wc/checkout', 'wc/checkout/payments' )
			)
		);
		$this->assertSame(
			'wc/checkout/payments',
			Oli_CSP_Store_API_Integration::notice_context_for_area( 'payment' )
		);
		$this->assertSame(
			'wc/checkout/payments',
			Oli_CSP_Store_API_Integration::notice_context_for_area(
				'payment',
				array( 'wc/checkout' )
			)
		);
		$this->assertSame( 'wc/cart', Oli_CSP_Store_API_Integration::notice_context_for_area( 'cart' ) );
		$this->assertSame( 'wc/checkout', Oli_CSP_Store_API_Integration::notice_context_for_area( 'checkout' ) );
		$this->assertSame( 'wc/checkout', Oli_CSP_Store_API_Integration::notice_context_for_area( 'unknown' ) );
	}

	/**
	 * Block checkout script depends on wp-a11y for screen-reader announcements.
	 */
	public function test_block_script_depends_on_wp_a11y() {
		$deps = Oli_CSP_Store_API_Integration::checkout_script_dependencies( false );

		$this->assertContains( 'wp-a11y', $deps );
		$this->assertContains( 'wp-data', $deps );
		$this->assertContains( 'wp-notices', $deps );
		$this->assertNotContains( 'wc-blocks-data-store', $deps );
	}

	/**
	 * Block renderer maps shipping to a rendered notice context.
	 */
	public function test_block_js_uses_rendered_shipping_notice_context() {
		$js = file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/checkout-blocks.js' );

		$this->assertNotFalse( $js );
		$this->assertStringContainsString( "'wc/checkout/shipping-methods'", $js );
		$this->assertStringContainsString( "'wc/checkout/payments'", $js );
		$this->assertStringContainsString( "contexts.shippingFallback || 'wc/checkout'", $js );
		$this->assertDoesNotMatchRegularExpression( "/['\"]wc\\/checkout\\/shipping['\"]/", $js );
	}

	/**
	 * Block renderer announces each new notice once via wp.a11y.speak.
	 */
	public function test_block_js_announces_each_notice_once() {
		$js = file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/checkout-blocks.js' );

		$this->assertNotFalse( $js );
		$this->assertStringContainsString( 'wp.a11y.speak', $js );
		$this->assertStringContainsString( 'announceNoticeOnce', $js );
		$this->assertStringContainsString( 'announcedIds', $js );
		$this->assertStringContainsString( 'forgetStaleAnnouncements', $js );
		$this->assertStringContainsString( 'if (!key || !notice.message || announcedIds[key])', $js );
	}
}
