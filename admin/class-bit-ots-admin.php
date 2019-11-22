<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

/**
 * Class to initiate admin functionalities
 * Class Bit_OTS_Admin
 */
class Bit_OTS_Admin {
	private static $ins = null;
	private $option_key;
	public $logger;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 90 );

		//Admin enqueue scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_assets' ), 99 );
		add_action( 'admin_post_bitos_create_subs', array( $this, 'bitos_create_subs' ) );
		add_action( 'admin_post_bitos_email_settings', array( $this, 'bitos_update_email_settings' ) );

		$this->option_key = 'bit_os_email_settings';
	}

	/**
	 * @return Bit_OTS_Admin|null
	 */
	public static function get_instance() {
		if ( null === self::$ins ) {
			self::$ins = new self;
		}

		return self::$ins;
	}

	/**
	 * Registeting Bitwise menu
	 */
	public function register_admin_menu() {
		add_menu_page( __( 'Create Subscription', 'bit-ots' ), 'Create Subscription', 'manage_options', 'bit_ots', array(
			$this,
			'bit_ots_page',
		), 'dashicons-welcome-learn-more', 11 );

		add_submenu_page( 'bit_ots', __( 'Email Reminder', 'bit-ots' ), __( 'Email Reminder', 'bit-ots' ), 'manage_options', 'bit_os_email', array( $this, 'bitsa_email_reminder' ) );
	}

	public function bit_ots_page() {
		include_once __DIR__ . '/views/bitos-create-subscription.php';
	}

	/**
	 * Adding admin scripts
	 */
	public function admin_enqueue_assets() {
		if ( $this->is_bitos_page() ) {
			wp_enqueue_style( 'bitos-admin-style', BITOTS_PLUGIN_URL . '/admin/assets/css/bitos-admin.css', [], BITOTS_VERSION_DEV );
			wp_enqueue_script( 'bitos-admin-ajax', BITOTS_PLUGIN_URL . '/admin/assets/js/bitos-admin.js', [], BITOTS_VERSION_DEV );
			wp_enqueue_script( 'bitos-time-mask', BITOTS_PLUGIN_URL . '/admin/assets/js/jquery.timeMask.js', [], BITOTS_VERSION_DEV );
		}
	}

	public function is_bitos_page() {
		$bit_ots_page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING );
		if ( 'bit_ots' === $bit_ots_page || 'bit_os_email' === $bit_ots_page ) {
			return true;
		}

		return false;
	}

	public function bitos_create_subs() {
		$success = 'no';
		if ( isset( $_POST['bitos_create_subs_nonce'] ) && wp_verify_nonce( $_POST['bitos_create_subs_nonce'], 'bitos_create_subs_nonce_val' ) ) {
			$bit_os_orders   = isset( $_POST['bit_ots_orders'] ) ? $_POST['bit_ots_orders'] : '';
			$bit_ots_prod_id = isset( $_POST['bit_ots_prod_id'] ) ? absint( $_POST['bit_ots_prod_id'] ) : 0;
			if ( ! empty( $bit_os_orders ) && $bit_ots_prod_id > 0 ) {
				$bit_orders_ar = array_map( 'absint', explode( ",", $bit_os_orders ) );
				if ( is_array( $bit_orders_ar ) && count( $bit_orders_ar ) > 0 ) {
					//$order_product_id = 574;  //This is the simple product id in parent order.
					$result  = Bit_OTS_Common::bitots_create_subsription( $bit_orders_ar, $bit_ots_prod_id );
					$success = ( absint( count( $result ) ) === absint( count( $bit_orders_ar ) ) ) ? 'yes' : $success;
				}
			}
		}

		$redirect_url = add_query_arg( array(
			'page'    => 'bit_ots',
			'success' => $success,
		), admin_url( 'admin.php' ) );
		wp_redirect( $redirect_url );
		exit( 45 );
	}

	public function bitsa_email_reminder() {
		$settings_data = $this->bitos_get_email_settings();
		include_once __DIR__ . '/views/bitos-email-sections.php';
	}

	public function bitos_get_email_settings() {
		$db_data          = get_option( $this->option_key, [] );
		$default_settings = $this->get_default_settings();

		return wp_parse_args( $db_data, $default_settings );
	}

	public function bitos_update_email_settings() {
		$success = false;
		if ( isset( $_POST['bitos_email_settings_nonce'] ) && wp_verify_nonce( $_POST['bitos_email_settings_nonce'], 'bitos_email_settings_nonce_val' ) ) {
			$data = array();

			//Bit_OTS_Common::prd($_POST);

			$data['bit_ew_on']      = isset( $_POST['bit_ew_on'] ) ? $_POST['bit_ew_on'] : '';
			$data['bit_ew_subject'] = isset( $_POST['bit_ew_subject'] ) ? $_POST['bit_ew_subject'] : '';
			$data['bit_ew_body']    = isset( $_POST['bit_ew_body'] ) ? $_POST['bit_ew_body'] : '';

			$data['bit_em_on']      = isset( $_POST['bit_em_on'] ) ? $_POST['bit_em_on'] : '';
			$data['bit_em_subject'] = isset( $_POST['bit_ew_subject'] ) ? $_POST['bit_em_subject'] : '';
			$data['bit_em_body']    = isset( $_POST['bit_em_body'] ) ? $_POST['bit_em_body'] : '';

			$data['bit_ec_on']      = isset( $_POST['bit_ec_on'] ) ? $_POST['bit_ec_on'] : '';
			$data['bit_ec_subject'] = isset( $_POST['bit_ec_subject'] ) ? $_POST['bit_ec_subject'] : '';
			$data['bit_ec_body']    = isset( $_POST['bit_em_body'] ) ? $_POST['bit_ec_body'] : '';
			$data['bit_ec_int']     = isset( $_POST['bit_ec_int'] ) ? $_POST['bit_ec_int'] : '';

			$data['bit_batch_count'] = isset( $_POST['bit_batch_count'] ) ? $_POST['bit_batch_count'] : '';
			$data['bit_start_time']  = isset( $_POST['bit_start_time'] ) ? $_POST['bit_start_time'] : '';

			$data       = array_map( 'sanitize_text_field', $data );
			$final_data = wp_parse_args( $data, $this->get_default_settings() );
			update_option( $this->option_key, $final_data );

			$success = true;
		}

		$redirect_url = add_query_arg( array(
			'page'    => 'bit_os_email',
			'success' => $success,
		), admin_url( 'admin.php' ) );
		wp_redirect( $redirect_url );
		exit( 45 );
	}

	public function get_default_settings() {
		return array(
			'bit_ew_on'      => false,
			'bit_ew_subject' => 'Before one week subject',
			'bit_ew_body'    => 'Before one week email content',

			'bit_em_on'      => false,
			'bit_em_subject' => 'Before one month subject',
			'bit_em_body'    => 'Before one month email content',

			'bit_ec_on'      => false,
			'bit_ec_subject' => 'Before custom days subject',
			'bit_ec_body'    => 'Before custom days email content',
			'bit_ec_int'     => 10,

			'bit_batch_count' => 50,
			'bit_start_time'  => '01:00',
		);
	}

	/**
	 * Write a message to log if we're in "debug" mode.
	 *
	 * @param string $context
	 * @param string $message
	 */
	public function log( $message, $context = "Info" ) {
		if ( ! defined( 'BITWC_OTS_IS_DEV' ) || ( defined( 'BITWC_OTS_IS_DEV' ) && false === BITWC_OTS_IS_DEV ) ) {
			return;
		}
		if ( class_exists( 'WC_Logger' ) && ! is_a( $this->logger, 'WC_Logger' ) ) {
			$this->logger = new WC_Logger();
		}
		$log_message = $context . ' - ' . $message;

		if ( class_exists( 'WC_Logger' ) ) {
			$this->logger->add( 'bit_ots', $log_message );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( $log_message );
		}
	}
}
