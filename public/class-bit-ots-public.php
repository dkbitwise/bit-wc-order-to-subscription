<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

/**
 * Class to initiate public functionalists
 * Class Bit_OTS_Public
 */
class Bit_OTS_Public {
	private static $ins = null;

	/**
	 * Bit_OTS_Public constructor.
	 */
	public function __construct() {
		add_action( 'wp', array( $this, 'bitots_create_subsription' ), 10, 1 );
		add_filter( 'wcs_view_subscription_actions', [ $this, 'rename_subscription_actions_button_text' ], 10, 2 );
		add_filter( 'wcs_subscription_statuses', [ $this, 'bitos_subscription_statuses' ], 10, 1 );
		add_action( 'buddyboss_inside_wrapper', [ $this, 'write_parent_notification_html' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'bitots_enqueue_script' ] );
		add_action( 'learndash-course-before', [ $this, 'expired_notice_to_student' ], 10, 3 );
		add_action( 'wp_ajax_bitots_send_email', [ $this, 'send_parent_email' ] );
	}

	public function bitots_enqueue_script() {

		wp_register_script( 'bitos_public', BITOTS_PLUGIN_URL . '/assets/js/bitots-public.js', array( 'jquery' ), BITOTS_VERSION_DEV, true );
		$localized_data = array(
			'ajaxurl'                      => admin_url( 'admin-ajax.php' ),
			'ajax_nonce_bitots_send_email' => wp_create_nonce( 'bitots_send_email' ),
			'success_msg'                  => __( 'Email reminder to parent sent successfully.', 'bit-ots' ),
			'error_msg'                    => __( 'Some error occurred, please refresh page and try again!!', 'bit-ots' ),
		);
		wp_localize_script( 'bitos_public', 'bitots', $localized_data );
		wp_enqueue_script( 'bitos_public' );
		wp_enqueue_style( 'bitos-public-css', BITOTS_PLUGIN_URL . '/assets/css/bitos-public.css', [], BITOTS_VERSION_DEV );
	}

	public function write_parent_notification_html() {
		$notes_data = Bit_OTS_Core()->admin->bitos_get_notes_settings();
		$user_id    = get_current_user_id();
		$user_data  = get_user_by( 'id', $user_id );

		if ( ! $user_data instanceof WP_User ) {
			return;
		}

		if ( ! in_array( 'group_leader', $user_data->roles, true ) ) {
			return;
		}
		$subs_status = 'active';
		$subs        = wcs_get_subscriptions( [ 'customer_id' => $user_id ] );
		foreach ( $subs as $sub_id => $sub ) {
			if ( 'active' !== $sub->get_status() ) {
				$subs_status = $sub->get_status();
			}
		}
		if ( 'active' === $subs_status ) {
			return;
		}
		$subs_url = home_url( 'my-account/subscriptions' ); ?>

		<div class="bit_ots-message">
			<p class="bit-exp-std-msg">
				<?php echo $notes_data['bit_ots_expired_messages'] ?>
				<a class="bit_ots-button button" href="<?php echo $subs_url; ?>"><?php echo $notes_data['bit_ots_button_text'] ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * @param $actions
	 * @param $subscription
	 *
	 * @return array
	 */
	public function rename_subscription_actions_button_text( $actions, $subscription ) {
		if ( is_array( $actions ) && isset( $actions['resubscribe'] ) ) {
			$actions['resubscribe']['name'] = __( 'Renew Subscription', 'bit-ots' );
		}

		return $actions;
	}

	/**
	 * @param $statuses
	 *
	 * @return array
	 */
	public function bitos_subscription_statuses( $statuses ) {
		if ( is_array( $statuses ) && isset( $statuses['wc-cancelled'] ) ) {
			$statuses['wc-cancelled'] = __( 'Expired Subscription', 'bit-ots' );
		}

		return $statuses;
	}

	/**
	 * Creating subscription
	 *
	 * @param $wp_args
	 */
	public function bitots_create_subsription( $wp_args ) {
		$create_subs = filter_input( INPUT_GET, 'create_subscription', FILTER_SANITIZE_STRING );
		if ( 'yes' === $create_subs ) {
			$simple_orders    = array( 638, 640, 575 );  //Enter here all the simple order ids to convert them to renewal order.
			$order_product_id = 574;  //This is the simple product id in parent order.
			Bit_OTS_Common::bitots_create_subsription( $simple_orders, $order_product_id );
		}
	}

	/**
	 * @return Bit_OTS_Public|null
	 */
	public static function get_instance() {
		if ( null === self::$ins ) {
			self::$ins = new self;
		}

		return self::$ins;
	}

	public function expired_notice_to_student( $post_id, $course_id, $user_id ) {
		if ( ! sfwd_lms_has_access( $course_id, $user_id ) ) {
			$notes_data = Bit_OTS_Core()->admin->bitos_get_notes_settings(); ?>
			<div class="bit_ots-message">
				<p class="bit-exp-std-msg"><?php echo $notes_data['bit_ots_expired_messages'] ?><?php esc_html_e( ' To send reminder to parent.', 'bit-ots' ); ?>
					<a data-course_id="<?php echo esc_attr( $course_id ) ?>" data-stdnt_id="<?php echo esc_attr( $user_id ); ?>" id="bit_ots_stdt_eml" class="bit_ots-button button" href="javascript:void(0);"><?php esc_html_e( 'Click here', 'bit-ots' ); ?></a>
				</p>
			</div>
		<?php }
	}

	public function send_parent_email() {
		check_ajax_referer( 'bitots_send_email', '_nonce' );
		$posted_data = array_map( 'sanitize_text_field', $_POST );
		$result      = array( 'status' => false,'msg' => __('Reminder email to your parent has been sent successfully.','bit-ots') );
		$course_id   = isset( $posted_data['course_id'] ) ? $posted_data['course_id'] : 0;
		$stdnt_id    = isset( $posted_data['stdnt_id'] ) ? $posted_data['stdnt_id'] : 0;

		$sent = Bit_OTS_Common::bitots_send_parent_email( $course_id, $stdnt_id );
		$result['sent'] = $sent;

		if ( $sent ) {
			$result['status'] = true;
		}
		wp_send_json( $result );
	}
}
