<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

/**
 * Class to initiate admin functionalities
 * Class Bit_OTS_Admin
 */
class Bit_OTS_Admin {
	private static $ins = null;
	//Key to save settings in option table
	private $option_key;

	//Key to save notifications settings in option table
	private $notes_option_key;

	//Logging the flow to debug in case of error
	public $logger;

	/**
	 * Bit_OTS_Admin constructor.
	 */
	public function __construct() {
		//Create the amdin menu
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 90 );

		//Admin enqueue assets like css and js
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_assets' ), 99 );

		//Calling creating subscriptions functions on submitting the admin form
		add_action( 'admin_post_bitos_create_subs', array( $this, 'bitos_create_subs' ) );

		//Updating schedule email settings in option table on form submission
		add_action( 'admin_post_bitos_email_settings', array( $this, 'bitos_update_email_settings' ) );

		//Updating notification settings in option table on form submission
		add_action( 'admin_post_bitos_notification_settings', array( $this, 'bitos_notification_settings' ) );

		//Enable/disable the logging from query params
		add_action( 'admin_init', [ $this, 'enable_disable_logging' ] );

		//Adding expiry date in subscription edit page
		add_action( 'wcs_subscription_schedule_after_billing_schedule', [ $this, 'add_expiry_date' ], 10, 1 );

		//Option keys to save settings
		$this->option_key       = 'bit_os_email_settings';
		$this->notes_option_key = 'bitos_notes_settings';
	}

	/**
	 * Creating an instance of this class
	 * @return Bit_OTS_Admin|null
	 */
	public static function get_instance() {
		if ( null === self::$ins ) {
			self::$ins = new self;
		}

		return self::$ins;
	}

	/**
	 * Registering Bitwise menu and submenus
	 */
	public function register_admin_menu() {
		add_menu_page( __( 'Create Subscription', 'bit-ots' ), 'Create Subscription', 'manage_options', 'bit_ots', array( $this, 'bit_ots_page' ), 'dashicons-welcome-learn-more', 11 );
		add_submenu_page( 'bit_ots', __( 'Email Reminder', 'bit-ots' ), __( 'Email Reminder', 'bit-ots' ), 'manage_options', 'bit_os_email', array( $this, 'bitsa_email_reminder' ) );
		add_submenu_page( 'bit_ots', __( 'Notification settings', 'bit-ots' ), __( 'Notification settings', 'bit-ots' ), 'manage_options', 'bitots_notification_settings', array(
			$this,
			'notification_settings'
		) );
	}

	/**
	 * Including create subscription form template
	 */
	public function bit_ots_page() {
		include_once __DIR__ . '/views/bitos-create-subscription.php';
	}

	/*
	 * Including notification settings template
	 */
	public function notification_settings() {
		$notes_data = $this->bitos_get_notes_settings();
		include_once __DIR__ . '/views/bitos-notification-settings.php';
	}

	/**
	 * Adding admin assets like css and js files
	 */
	public function admin_enqueue_assets() {
		if ( $this->is_bitos_page() ) {
			wp_enqueue_style( 'bitos-admin-style', BITOTS_PLUGIN_URL . '/admin/assets/css/bitos-admin.css', [], BITOTS_VERSION_DEV );
			wp_enqueue_script( 'bitos-admin-ajax', BITOTS_PLUGIN_URL . '/admin/assets/js/bitos-admin.js', [], BITOTS_VERSION_DEV );
			wp_enqueue_script( 'bitos-time-mask', BITOTS_PLUGIN_URL . '/admin/assets/js/jquery.timeMask.js', [], BITOTS_VERSION_DEV );
		}
	}

	/**
	 * Function to check if it page created by this plugin
	 * @return bool
	 */
	public function is_bitos_page() {
		$bit_ots_page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING );
		if ( 'bit_ots' === $bit_ots_page || 'bit_os_email' === $bit_ots_page ) {
			return true;
		}

		return false;
	}

	/**
	 * Handling the subscription create form posting in admin
	 */
	public function bitos_create_subs() {
		$success = 'no';
		Bit_OTS_Core()->admin->log( "Start creating subscriptions with posted data: " . print_r( $_POST, true ) );
		if ( isset( $_POST['bitos_create_subs_nonce'] ) && wp_verify_nonce( $_POST['bitos_create_subs_nonce'], 'bitos_create_subs_nonce_val' ) ) {
			$bit_choose_input = isset( $_POST['bit_choose_input'] ) ? wc_clean( $_POST['bit_choose_input'] ) : '';
			$bit_ots_prod_id  = isset( $_POST['bit_ots_prod_id'] ) ? absint( $_POST['bit_ots_prod_id'] ) : 0;
			$bit_orders_ar    = [];
			if ( 'bitos-order-ids' === $bit_choose_input && $bit_ots_prod_id > 0 ) {
				$bit_os_orders = isset( $_POST['bit_ots_orders'] ) ? $_POST['bit_ots_orders'] : '';
				if ( ! empty( $bit_os_orders ) ) {
					$bit_orders_ar = array_map( 'absint', explode( ",", $bit_os_orders ) );
				}
			} elseif ( 'bitos-email-ids' === $bit_choose_input && $bit_ots_prod_id > 0 ) {
				$bitos_email_ids = isset( $_POST['bit_ots_email_ids'] ) ? $_POST['bit_ots_email_ids'] : '';
				if ( ! empty( $bitos_email_ids ) ) {
					$bit_emails_ar = wc_clean( explode( ",", $bitos_email_ids ) );
					Bit_OTS_Core()->admin->log( "Product id: $bit_ots_prod_id and emails: " . print_r( $bit_emails_ar, true ) );
					if ( is_array( $bit_emails_ar ) && count( $bit_emails_ar ) > 0 ) {
						foreach ( $bit_emails_ar as $bit_email ) {
							$bit_orders = wc_get_orders( [ 'customer' => $bit_email ] );
							if ( is_array( $bit_orders ) && count( $bit_orders ) > 0 ) {
								$first_order = $bit_orders[0];
								if ( $first_order instanceof WC_Order ) {
									$bit_orders_ar[] = $first_order->get_id();
								}
							}
						}
					}
				}
			}

			//Calling create subscription function from common file for each individual order
			Bit_OTS_Core()->admin->log( "Product id: $bit_ots_prod_id, Choice: $bit_choose_input and orders: " . print_r( $bit_orders_ar, true ) );
			if ( is_array( $bit_orders_ar ) && count( $bit_orders_ar ) > 0 ) {
				$result = Bit_OTS_Common::bitots_create_subsription( $bit_orders_ar, $bit_ots_prod_id );
				Bit_OTS_Core()->admin->log( "Subs creating result: " . print_r( $result, true ) );
				$success = ( absint( count( $result ) ) === absint( count( $bit_orders_ar ) ) ) ? 'yes' : $success;
			}
		}
		//Redirect on the same page after completing the subscription creation.
		$redirect_url = add_query_arg( array(
			'page'    => 'bit_ots',
			'success' => $success,
		), admin_url( 'admin.php' ) );
		wp_redirect( $redirect_url );
		exit( 45 );
	}

	/**
	 * Including email reminder setting template
	 */
	public function bitsa_email_reminder() {
		$settings_data = $this->bitos_get_email_settings();
		include_once __DIR__ . '/views/bitos-email-sections.php';
	}

	/**
	 * Function to return email setting when called
	 * @return array
	 */
	public function bitos_get_email_settings() {
		$db_data          = get_option( $this->option_key, [] );
		$default_settings = $this->get_default_settings();

		return wp_parse_args( $db_data, $default_settings );
	}

	/**
	 * Function to return notification setting when called
	 * @return array
	 */
	public function bitos_get_notes_settings() {
		$db_data          = get_option( $this->notes_option_key, [] );
		$default_settings = $this->get_default_notes_settings();

		return wp_parse_args( $db_data, $default_settings );
	}

	/**
	 * Updating email settings on submitting the admin form
	 */
	public function bitos_update_email_settings() {
		$success = false;
		if ( isset( $_POST['bitos_email_settings_nonce'] ) && wp_verify_nonce( $_POST['bitos_email_settings_nonce'], 'bitos_email_settings_nonce_val' ) ) {
			$data = array();

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

	/**
	 * Return default form settings
	 * @return array
	 */
	public function get_default_settings() {
		return array(
			'bit_ew_on'       => false,
			'bit_ew_subject'  => 'Before one week subject',
			'bit_ew_body'     => 'Before one week email content',
			'bit_em_on'       => false,
			'bit_em_subject'  => 'Before one month subject',
			'bit_em_body'     => 'Before one month email content',
			'bit_ec_on'       => false,
			'bit_ec_subject'  => 'Before custom days subject',
			'bit_ec_body'     => 'Before custom days email content',
			'bit_ec_int'      => 10,
			'bit_batch_count' => 50,
			'bit_start_time'  => '01:00',
		);
	}

	/**
	 * Return default notification form settings
	 * @return array
	 */
	public function get_default_notes_settings() {
		return array(
			'bit_ots_button_text'      => 'My Subscription',
			'bit_ots_expired_messages' => 'Your subscription has expired. Please visit the Subscriptions page to renew.',
		);
	}

	/**
	 * Enable or disable the logging using query params
	 */
	public function enable_disable_logging() {
		$enable = filter_input( INPUT_GET, 'bitos_enable_logging', FILTER_SANITIZE_STRING );
		if ( ! empty( $enable ) && in_array( $enable, [ 'yes', 'no' ], true ) ) {
			update_option( 'bitos_logging_enabled', $enable );
		}
	}

	/**
	 * Write a message to log in WC log tab if logging is enabled
	 *
	 * @param string $context
	 * @param string $message
	 */
	public function log( $message, $context = "Info" ) {
		$logging_enabled = get_option( 'bitos_logging_enabled', false );
		if ( empty( $logging_enabled ) || 'yes' !== $logging_enabled ) {
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

	/**
	 * Updating notification settings on submitting the admin form
	 */
	public function bitos_notification_settings() {
		$success = 'no';
		if ( isset( $_POST['bitos_create_notification_settings'] ) && wp_verify_nonce( $_POST['bitos_create_notification_settings'], 'bitos_notification_settings_nonce_val' ) ) {

			$data = array();

			$data['bit_ots_button_text']      = isset( $_POST['bit_ots_button_text'] ) ? $_POST['bit_ots_button_text'] : '';
			$data['bit_ots_expired_messages'] = isset( $_POST['bit_ots_expired_messages'] ) ? $_POST['bit_ots_expired_messages'] : '';

			$data       = array_map( 'sanitize_text_field', $data );
			$final_data = wp_parse_args( $data, $this->get_default_notes_settings() );
			update_option( $this->notes_option_key, $final_data );

			$success = 'yes';
		}

		$redirect_url = add_query_arg( array(
			'page'    => 'bitots_notification_settings',
			'success' => $success,
		), admin_url( 'admin.php' ) );
		wp_redirect( $redirect_url );
		exit( 45 );
	}

	/**
	 * //Adding expiry date in subscription edit page
	 * @param $subscription
	 */
	public function add_expiry_date( $subscription ) {
		$expiry_date = $subscription->get_meta( 'bit_expiration_date' );
		if ( ! empty( $expiry_date ) && 'active' !== $subscription->get_status() ) { ?>
			<div id="subscription-bit-expiry-date" class="date-fields">
				<strong><?php esc_html_e( 'Expiration Date:', 'bit-ots' ); ?></strong>
				<?php echo esc_html( date( 'F j, Y', $expiry_date ) ); ?>
			</div>
		<?php }
	}
}
