<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

/**
 * Class to initiate public functionalists
 * Class Bit_OTS_Public
 */
class Bit_OTS_Public {
	//An instance variable of the class to create object of this class.
	private static $ins = null;

	/**
	 * Bit_OTS_Public constructor.
	 */
	public function __construct() {
		//Changing the text 'Resubscribe' to 'Renew Now' using the woocommerce subscription filter
		add_filter( 'wcs_view_subscription_actions', [ $this, 'rename_subscription_actions_button_text' ], 10, 2 );

		//Changing the 'Cancelled' status text to 'Expired' using woocommerce subscription filter
		add_filter( 'wcs_subscription_statuses', [ $this, 'bitos_subscription_statuses' ], 10, 1 );

		//Adding parent notification for expired subscription
		add_action( 'buddyboss_inside_wrapper', [ $this, 'write_parent_notification_html' ] );

		//Including assets files like css and js
		add_action( 'wp_enqueue_scripts', [ $this, 'bitots_enqueue_script' ] );

		//Showing expired notice to student
		add_action( 'learndash-course-before', [ $this, 'expired_notice_to_student' ], 10, 3 );

		//Handling ajax request to send email by student to their parent.
		add_action( 'wp_ajax_bitots_send_email', [ $this, 'send_parent_email' ] );
	}

	/**
	 * Including assets files like css and js
	 */
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

	/**
	 * Adding parent notification for expired subscription
	 */
	public function write_parent_notification_html() {
		$notes_data          = Bit_OTS_Core()->admin->bitos_get_notes_settings();
		$user_id             = get_current_user_id();
		$user_data           = get_user_by( 'id', $user_id );
		$users_subscriptions = wcs_get_users_subscriptions( $user_id );
		$counter             = 0;
		foreach ( $users_subscriptions as $subscription ) {
			if ( $counter == 0 ) {
				$sub_id = $subscription->get_id();
			}
			$counter = $counter + 1;
		}
		$next_payment_date = get_post_meta( $sub_id, _schedule_next_payment, true );
		$group_expire_date = strtotime( $next_payment_date );
		$current_date      = time();
		$check_resubscribe = metadata_exists( 'post', $sub_id, '_subscription_resubscribe' );
		if ( $check_resubscribe == 1 ) {
			$group_ids = learndash_get_groups( true, $user_id );
			if ( ! empty( $group_ids ) && is_array( $group_ids ) ) {
				foreach ( $group_ids as $group_id ) {
					$user_ids = learndash_get_groups_user_ids( $group_id );
					foreach ( $user_ids as $user_id ) {
						update_user_meta( $user_id, 'user_expire_date', $group_expire_date );
					}
				}
			}
		}
		if ( in_array( 'group_leader', $user_data->roles, true ) ) {
			if ( $group_expire_date != '' ) {
				if ( $group_expire_date < $current_date ) {
					$subs_url = home_url( 'my-account/subscriptions' ); ?>

					<div class="bit_ots-message">
						<p class="bit-exp-std-msg">
							<?php echo $notes_data['bit_ots_expired_messages'] ?>
							<a class="bit_ots-button button" href="<?php echo $subs_url; ?>"><?php echo $notes_data['bit_ots_button_text'] ?></a>
						</p>
					</div>
					<?php
				}

			}
		}
	}

	/**
	 * Changing the text 'Resubscribe' to 'Renew Now' using the woocommerce subscription filter
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
	 * Changing the 'Cancelled' status text to 'Expired' using woocommerce subscription filter
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
	 * Creating an instance of this class
	 * @return Bit_OTS_Public|null
	 */
	public static function get_instance() {
		if ( null === self::$ins ) {
			self::$ins = new self;
		}

		return self::$ins;
	}

	/**
	 * Showing expired notice to student
	 * @param $post_id
	 * @param $course_id
	 * @param $user_id
	 */
	public function expired_notice_to_student( $post_id, $course_id, $user_id ) {
		if ( ! sfwd_lms_has_access( $course_id, $user_id ) ) {
			$notes_data = Bit_OTS_Core()->admin->bitos_get_notes_settings(); ?>
			<div class="bit_ots-message">
				<p class="bit-exp-std-msg"><?php esc_html_e( 'Your subscription has expired. To send a  reminder to parent.', 'bit-ots' ); ?>
					<a data-course_id="<?php echo esc_attr( $course_id ) ?>" data-stdnt_id="<?php echo esc_attr( $user_id ); ?>" id="bit_ots_stdt_eml" class="bit_ots-button button" href="javascript:void(0);"><?php esc_html_e( 'Click here', 'bit-ots' ); ?></a>
				</p>
			</div>
		<?php }
	}

	/**
	 * Handling ajax request to send email by student to their parent.
	 */
	public function send_parent_email() {
		check_ajax_referer( 'bitots_send_email', '_nonce' );
		$posted_data = array_map( 'sanitize_text_field', $_POST );
		$result      = array( 'status' => false, 'msg' => __( 'Reminder email to your parent has been sent successfully.', 'bit-ots' ) );
		$course_id   = isset( $posted_data['course_id'] ) ? $posted_data['course_id'] : 0;
		$stdnt_id    = isset( $posted_data['stdnt_id'] ) ? $posted_data['stdnt_id'] : 0;

		$sent           = Bit_OTS_Common::bitots_send_parent_email( $course_id, $stdnt_id );
		$result['sent'] = $sent;

		if ( $sent ) {
			$result['status'] = true;
		}
		wp_send_json( $result );
	}
}
