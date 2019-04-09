<?php
/**
 * Plugin Name: Test Stripe Webgility
 * Plugin URI: https://ghettifish.com
 * Description: Take credit card payments on your store using Stripe.
 * Author: Ghettifish LLC.
 * Author URI: https://woocommerce.com/
 * Version: 4.1.14
 * Requires at least: 4.4
 * Tested up to: 5.0
 * WC requires at least: 2.6
 * WC tested up to: 3.5
 * Text Domain: test-stripe-webgility
 * Domain Path: /languages
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce fallback notice.
 *
 * @since 4.1.2
 * @return string
 */

function webgility_stripe_missing_wc_notice() {
	/* translators: 1. URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Webgility Stripe requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-gateway-stripe' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

add_action( 'plugins_loaded', 'webgility_gateway_stripe_init' );

function webgility_gateway_stripe_init() {
	//load_plugin_textdomain( 'woocommerce-gateway-stripe', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'webgility_stripe_missing_wc_notice' );
		return;
	}

	if ( ! class_exists( 'WB_Stripe' ) ) :
		/**
		 * Required minimums and constants
		 */
		define( 'WB_STRIPE_VERSION', '4.1.14' );
		define( 'WB_STRIPE_MIN_PHP_VER', '5.6.0' );
		define( 'WB_STRIPE_MIN_WC_VER', '2.6.0' );
		define( 'WB_STRIPE_MAIN_FILE', __FILE__ );
		define( 'WB_STRIPE_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
		define( 'WB_STRIPE_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

		class WB_Stripe {

			/**
			 * @var Singleton The reference the *Singleton* instance of this class
			 */
			private static $instance;

			/**
			 * Returns the *Singleton* instance of this class.
			 *
			 * @return Singleton The *Singleton* instance.
			 */
			public static function get_instance() {
				if ( null === self::$instance ) {
					self::$instance = new self();
				}
				return self::$instance;
			}

			/**
			 * Private clone method to prevent cloning of the instance of the
			 * *Singleton* instance.
			 *
			 * @return void
			 */
			private function __clone() {}

			/**
			 * Private unserialize method to prevent unserializing of the *Singleton*
			 * instance.
			 *
			 * @return void
			 */
			private function __wakeup() {}

			/**
			 * Protected constructor to prevent creating a new instance of the
			 * *Singleton* via the `new` operator from outside of this class.
			 */
			private function __construct() {
				add_action( 'admin_init', array( $this, 'install' ) );
				$this->init();
			}

			/**
			 * Init the plugin after plugins_loaded so environment variables are set.
			 *
			 * @since 1.0.0
			 * @version 4.0.0
			 */
			public function init() {

                include_once dirname( __FILE__ ) . '/includes/class-wb-stripe-api.php';
                
                require_once dirname( __FILE__ ) . '/includes/abstract-wb-gateway-stripe.php';
				require_once dirname( __FILE__ ) . '/includes/class-wb-stripe-payment-gateway.php';
				require_once dirname( __FILE__ ) . '/includes/class-wb-stripe-logger.php';
				require_once dirname( __FILE__ ) . '/includes/class-wb-stripe-helper.php';
				require_once dirname( __FILE__ ) . '/includes/class-wb-stripe-payment-tokens.php';
				require_once dirname( __FILE__ ) . '/includes/class-wb-stripe-customer.php';
				require_once dirname( __FILE__ ) . '/includes/class-wb-stripe-exception.php';
				require_once dirname( __FILE__ ) . '/includes/class-wb-stripe-webhook-handler.php';
				require_once dirname( __FILE__ ) . '/includes/admin/customer-mgmt.php';
				 
				add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );

				if ( version_compare( WC_VERSION, '3.4', '<' ) ) {
					add_filter( 'woocommerce_get_sections_checkout', array( $this, 'filter_gateway_order_admin' ) );
				}
			}

			/**
			 * Updates the plugin version in db
			 *
			 * @since 3.1.0
			 * @version 4.0.0
			 */
			public function update_plugin_version() {
				delete_option( 'wb_stripe_version' );
				update_option( 'wb_stripe_version', WB_STRIPE_VERSION );
			}

			/**
			 * Handles upgrade routines.
			 *
			 * @since 3.1.0
			 * @version 3.1.0
			 */
			public function install() {
				if ( ! is_plugin_active( plugin_basename( __FILE__ ) ) ) {
					return;
				}

				if ( ! defined( 'IFRAME_REQUEST' ) && ( WB_STRIPE_VERSION !== get_option( 'wb_stripe_version' ) ) ) {
					do_action( 'webgility_stripe_updated' );

					if ( ! defined( 'WB_STRIPE_INSTALLING' ) ) {
						define( 'WB_STRIPE_INSTALLING', true );
					}

					$this->update_plugin_version();
				}
			}

			/**
			 * Adds plugin action links.
			 *
			 * @since 1.0.0
			 * @version 4.0.0
			 */
			public function plugin_action_links( $links ) {
				$plugin_links = array(
					'<a href="admin.php?page=wc-settings&tab=checkout&section=webgility_stripe">' . esc_html__( 'Settings', 'webgility-gateway-stripe' ) . '</a>',
					'<a href="https://docs.woocommerce.com/document/stripe/">' . esc_html__( 'Docs', 'woocommerce-gateway-stripe' ) . '</a>',
					'<a href="https://woocommerce.com/contact-us/">' . esc_html__( 'Support', 'woocommerce-gateway-stripe' ) . '</a>',
				);
				return array_merge( $plugin_links, $links );
			}

			/**
			 * Add the gateways to WooCommerce.
			 *
			 * @since 1.0.0
			 * @version 4.0.0
			 */
			public function add_gateways( $methods ) {
                $methods[] = 'WB_Gateway_Stripe';

				return $methods;
			}

			/**
			 * Modifies the order of the gateways displayed in admin.
			 *
			 * @since 4.0.0
			 * @version 4.0.0
			 */
			public function filter_gateway_order_admin( $sections ) {
				unset( $sections['webgility_stripe'] );

				$sections['webgility_stripe']            = 'Stripe';

				return $sections;
			}
		}

		WB_Stripe::get_instance();
	endif;
}
