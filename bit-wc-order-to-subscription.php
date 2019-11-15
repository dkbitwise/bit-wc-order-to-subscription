<?php
/**
 * Plugin Name: Bitwise WC Order to Subscription
 * Plugin URI:  https://dev4.bitwise.academy/
 * Description: Converting existing simple product orders to subscriptions
 * Version:     1.0.0
 * Author:      Dinesh Kumar Yadav
 * Author URI:  https://dev4.bitwise.academy/
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: bit-ots
 * Domain Path: /languages
 *
 * WC requires at least: 3.0
 * WC tested up to: 3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Bit_OTS_Core' ) ) {

	class Bit_OTS_Core {
		/**
		 * @var Bit_OTS_Core
		 */
		public static $_instance = null;

		/**
		 * @var Bit_OTS_Admin
		 */
		public $admin;

		/**
		 * @var Bit_OTS_Public
		 */
		public $public;
		
		/**
		 * @var bool Dependency check property
		 */
		private $is_dependency_exists = true;
		/**
		 * Bit_OTS_Core constructor.
		 */
		public function __construct() {
			/**
			 * Load important variables and constants
			 */
			$this->define_plugin_properties();

			/**
			 * Load dependency classes like woo-functions.php
			 */
			$this->load_dependencies_support();

			/**
			 * Run dependency check to check if dependency available
			 */
			$this->do_dependency_check();
			/**
			 * Initiates and load hooks
			 */
			if ( true === $this->is_dependency_exists ) {
				$this->load_hooks();
			}
		}

		/**
		 * Defining constants
		 */
		public function define_plugin_properties() {
			define( 'BITOTS_VERSION', '2.0.11' );
			define( 'BITOTS_MIN_WC_VERSION', '3.0.0' );
			define( 'BITOTS_SLUG', 'bitots' );
			define( 'BITOTS_FULL_NAME', __( 'Bitwise WC Order to Subscription', 'bit-wc-cc' ) );
			define( 'BITOTS_PLUGIN_FILE', __FILE__ );
			define( 'BITOTS_PLUGIN_DIR', __DIR__ );
			add_action( 'plugins_loaded', array( $this, 'load_wp_dependent_properties' ), 1 );

			( defined( 'BITOTS_IS_DEV' ) && true === BITOTS_IS_DEV ) ? define( 'BITOTS_VERSION_DEV', time() ) : define( 'BITOTS_VERSION_DEV', BITOTS_VERSION );
		}

		public function load_wp_dependent_properties() {
			define( 'BITOTS_PLUGIN_URL', untrailingslashit( plugin_dir_url( BITOTS_PLUGIN_FILE ) ) );
			define( 'BITOTS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
		}

		public function load_dependencies_support() {
			/** Setting up WooCommerce Dependency Classes */
			require_once( __DIR__ . '/woo-includes/woo-functions.php' );
		}

		public function do_dependency_check() {
			if ( ! bit_ots_is_woocommerce_active() ) {
				add_action( 'admin_notices', array( $this, 'wc_not_installed_notice' ) );
				$this->is_dependency_exists = false;
			}
		}

		public function load_hooks() {
			/**
			 * Initialize Localization
			 */
			add_action( 'init', array( $this, 'localization' ) );
			add_action( 'plugins_loaded', array( $this, 'load_classes' ), 1 );
		}

		public function load_classes() {
			if ( bit_ots_is_woocommerce_active() && class_exists( 'WooCommerce' ) ) {
				global $woocommerce;
				if ( ! version_compare( $woocommerce->version, BITOTS_MIN_WC_VERSION, '>=' ) ) {
					add_action( 'admin_notices', array( $this, 'wc_version_check_notice' ) );

					return false;
				}

				/**
				 * Loads the Admin file
				 */
				require __DIR__ . '/admin/class-bit-ots-admin.php';
				$this->public = Bit_OTS_Admin::get_instance();
				
				/**
				 * Loads the Public file
				 */
				require __DIR__ . '/public/class-bit-ots-public.php';
				$this->public = Bit_OTS_Public::get_instance();

				/**
				 * Loads the common class
				 */
				require __DIR__ . '/includes/class-bit-ots-common.php';
			}

			return null;
		}

		public static function get_instance() {
			if ( null === self::$_instance ) {
				self::$_instance = new self;
			}

			return self::$_instance;
		}

		public function localization() {
			load_plugin_textdomain( 'bit-ots', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
		}

		public function wc_version_check_notice() { ?>
            <div class="error">
                <p>
					<?php
					/* translators: %1$s: Min required woocommerce version */
					printf( __( '<strong> Attention: </strong>Bitwise WC Orders to Subscriptions requires WooCommerce version %1$s or greater. Kindly update the WooCommerce plugin.', 'bit-ots' ), esc_attr( BITOTS_MIN_WC_VERSION ) ); ?>
                </p>
            </div>
			<?php
		}

		public function wc_not_installed_notice() {	?>
            <div class="error">
                <p>
					<?php
					echo __( '<strong> Attention: </strong>WooCommerce is not installed or activated. Bitwise WC Orders to Subscriptions is a WooCommerce Extension and would only work if WooCommerce is activated. Please install the WooCommerce Plugin first.', 'bit-ots' );
					?>
                </p>
            </div>
			<?php
		}
	}
}
if ( ! function_exists( 'Bit_OTS_Core' ) ) {
	/**
	 * Global Common function to load all the classes
	 * @return Bit_OTS_Core
	 */
	function Bit_OTS_Core() {
		return Bit_OTS_Core::get_instance();
	}
}

$GLOBALS['Bit_OTS_Core'] = Bit_OTS_Core();
