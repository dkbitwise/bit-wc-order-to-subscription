<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

/**
 * This class contain handle common functions for backend and frontend
 * Class Bit_OTS_Common
 */
class Bit_OTS_Common {
	//Time to start the background process
	public static $start_time = 0;

	//Variable to hold the plugins settings throughout this class.
	public static $bit_os_settings = [];

	//Initiating the plugins statically
	public static function init() {
		//Scheduling the email to be sent at set interval from settings
		add_action( 'wp', [ __CLASS__, 'setup_schedule_to_email_reminder' ] );

		//Sending reminder emails.
		add_action( 'bitos_send_reminder_email', [ __CLASS__, 'bitos_send_reminder_email_function' ], 9999 );

		self::$bit_os_settings = Bit_OTS_Core()->admin->bitos_get_email_settings();
	}

	/**
	 * Creating subscription by passing simple order id(s) and the subscription product id.
	 *
	 * @param array $simple_orders
	 * @param $sub_product_id
	 *
	 * @return array
	 */
	public static function bitots_create_subsription( $simple_orders = array(), $sub_product_id ) {
		$result = [];
		if ( $sub_product_id < 1 ) {
			return $result;
		}
		Bit_OTS_Core()->admin->log( "Sub product id: $sub_product_id, Simple order ids: " . print_r( $simple_orders, true ) );
		foreach ( $simple_orders as $user_id => $order_id ) {
			$bit_order = wc_get_order( $order_id );
			if ( $bit_order instanceof WC_Order ) { //Validate the supplied order is a valid woocommerce order
				$user_id      = $bit_order->get_customer_id();
				$subs_created = $bit_order->get_meta( '_bit_subscription_created' );
				Bit_OTS_Core()->admin->log( "User id: $user_id, Subs created: $subs_created for order id: $order_id, Subs product id: $sub_product_id" );
				if ( ( ! empty( $subs_created ) && $subs_created > 0 ) || $user_id < 1 ) {
					continue;
				}

				$prod_obj = wc_get_product( $sub_product_id );
				if ( $prod_obj instanceof WC_Product_Subscription ) { //Validating the new subscription product
					$stripe_cust_id = $bit_order->get_meta( '_stripe_customer_id', true );
					$stripe_src_id  = $bit_order->get_meta( '_stripe_source_id', true );
					$transaction_id = $bit_order->get_transaction_id();

					$args = array(
						'product'        => $prod_obj,
						'order'          => $bit_order,
						'order_id'       => $order_id,
						'user_id'        => $user_id,
						'transaction_id' => $transaction_id,
						'amt'            => $prod_obj->get_sale_price(),
					);

					Bit_OTS_Core()->admin->log( "stripe_cust_id: $stripe_cust_id, stripe_src_id: $stripe_src_id, Transaction id: $transaction_id, Regular amount: " . print_r( $args['amt'], true ) );

					//Calling function to create subscription and handling the results.
					$subscription = self::_create_new_subscription( $args, 'completed' );
					if ( $subscription instanceof WC_Subscription ) { //Validating created subscription
						$subscription->update_meta_data( '_stripe_customer_id', $stripe_cust_id );
						$subscription->update_meta_data( '_stripe_source_id', $stripe_src_id );
						$subscription->save();

						$subscription_id = $subscription->get_id();
						self::validate_course_access( $subscription, $sub_product_id );

						//Updating the order meta to avoid double subscription creation.
						$bit_order->update_meta_data( '_bit_subscription_created', $subscription_id );
						$bit_order->save();

						$result[ $subscription_id ] = ( empty( $stripe_cust_id ) || empty( $stripe_src_id ) );
					}
				} else {
					Bit_OTS_Core()->admin->log( "Given product: $sub_product_id is not a subscription product." );
				}
			}
		}

		return $result;
	}

	/**
	 * Creating subscription after getting parsed argument and the simple order status.
	 *
	 * @param $args
	 * @param $order_status
	 *
	 * @return bool|WC_Order|WC_Subscription|WP_Error
	 * @throws WC_Data_Exception
	 */
	public static function _create_new_subscription( $args, $order_status ) {
		// create a subscription
		global $next_payment_datetime;
		$product         = $args['product'];
		$order_id        = $args['order_id'];
		$order           = $args['order'];
		$current_user_id = $args['user_id'];
		$transaction_id  = $args['transaction_id'];
		$start_date      = $order->get_date_created()->date( 'Y-m-d H:i:s' ); //current_time( 'mysql' );

		$period       = WC_Subscriptions_Product::get_period( $product );
		$interval     = WC_Subscriptions_Product::get_interval( $product );
		$trial_period = WC_Subscriptions_Product::get_trial_period( $product );
		$trial_length = WC_Subscriptions_Product::get_trial_length( $product );

		//Woocommerce subscription function to create the actual subscription
		$subscription = wcs_create_subscription( array(
			'start_date'       => $start_date,
			'order_id'         => $order_id,
			'billing_period'   => $period,
			'billing_interval' => $interval,
			'customer_note'    => $order->get_customer_note(),
			'customer_id'      => $current_user_id,
		) );

		if ( is_wp_error( $subscription ) ) {
			return false;
		}

		//Handling course access and expiration after creating subscription from simple order
		if ( ! empty( $current_user_id ) && ! empty( $subscription ) ) {
			// link subscription product & copy address details
			$product->set_price( $args['amt'] );
			$subscription_item_id = $subscription->add_product( $product, 1 ); // $args
			$subscription         = wcs_copy_order_address( $order, $subscription );

			//Calculating next payment date for renewal
			$next_payment_datetime = wcs_add_time( $interval, $period, strtotime( $start_date ) );
			if ( ! empty( $trial_period ) && $trial_length > 0 ) {
				$next_payment_datetime = wcs_add_time( $trial_length, $trial_period, $next_payment_datetime );
			}

			//Expiring course access if subscription interval reached.
			$group_ids = learndash_get_groups( true, $current_user_id );
			if ( ! empty( $group_ids ) && is_array( $group_ids ) ) {
				foreach ( $group_ids as $group_id ) {
					$user_ids = learndash_get_groups_user_ids( $group_id );
					foreach ( $user_ids as $user_id ) {
						update_user_meta( $user_id, 'user_expire_date', $next_payment_datetime );
					}
				}
			}

			if ( $next_payment_datetime > strtotime( date( 'Y-m-d H:i:s' ) ) ) {
				// set subscription dates
				$trial_end_date    = WC_Subscriptions_Product::get_trial_expiration_date( $product->get_id(), $start_date );
				$next_payment_date = WC_Subscriptions_Product::get_first_renewal_payment_date( $product->get_id(), $start_date );
				$end_date          = WC_Subscriptions_Product::get_expiration_date( $product->get_id(), $start_date );

				$subscription->update_dates( array(
					'trial_end'    => $trial_end_date,
					'next_payment' => $next_payment_date,
					'end'          => $end_date,
				) );

				if ( WC_Subscriptions_Product::get_trial_length( $product->get_id() ) > 0 ) {
					wc_add_order_item_meta( $subscription_item_id, '_has_trial', 'true' );
				}
				// save trial period for PayPal
				if ( ! empty( $trial_period ) ) {
					update_post_meta( $subscription->get_id(), '_trial_period', $trial_period );
				}

				//Updating the payment method in subscription order as it was in simple order
				$subscription->set_payment_method( $order->get_payment_method() );
				$subscription->set_payment_method_title( $order->get_payment_method_title() );

				if ( ! empty( $current_user_id ) ) {
					update_post_meta( $subscription->get_id(), '_customer_user', $current_user_id );
				}

				//Updating subscription status based on simple order status
				if ( 'completed' === $order_status ) {
					$subscription->payment_complete( $transaction_id );
				} else {
					$subscription->update_status( $order_status );
				}
			} else { //Add expiration date and next payment dates
				$next_payment_date = WC_Subscriptions_Product::get_first_renewal_payment_date( $product->get_id(), $start_date );
				$subscription->update_meta_data( '_schedule_next_payment', $next_payment_date );
				$subscription->update_meta_data( '_schedule_end', $next_payment_date );
				$subscription->update_meta_data( 'bit_expiration_date', $next_payment_datetime );
				$subscription->update_meta_data( '_schedule_end', $next_payment_date );
			}

			$subscription->calculate_totals();
			$subscription->save();

			return $subscription;
		}

		return false;
	}

	/**
	 * Setup wp schedule event to check and send reminder emails.
	 */
	public static function setup_schedule_to_email_reminder() {
		if ( false === wp_next_scheduled( 'bitos_send_reminder_email' ) ) {
			$time = self::$bit_os_settings['bit_start_time'];
			$time = ( 5 === strlen( $time ) ) ? $time . ':00' : $time;
			wp_schedule_event( strtotime( $time ), 'daily', 'bitos_send_reminder_email' );
		}
	}

	/**
	 * Scheduled task for sending mail on different intervals
	 */
	public static function bitos_send_reminder_email_function() {
		self::$start_time = time();

		$bit_os_settings = self::$bit_os_settings;

		$bit_ew_on = $bit_os_settings['bit_ew_on'];
		$bit_em_on = $bit_os_settings['bit_em_on'];
		$bit_ec_on = $bit_os_settings['bit_ec_on'];

		//Return if no settings is enabled to send reminder emails form admin settings
		if ( ! $bit_ew_on && ! $bit_em_on && ! $bit_ec_on ) {
			return;
		}

		//Get all subscriptions to get their expiration dates and email reminder notification.
		$subscriptions = wcs_get_subscriptions( [ 'subscriptions_per_page' => - 1 ] );
		foreach ( $subscriptions as $sub_id => $subscription ) {
			$batch_count = get_option( 'bitsa_os_batch_count', 0 );
			if ( true === self::time_exceeded() || true === self::memory_exceeded() ) {
				update_option( 'bitsa_os_batch_count', 0 );
				break;
			}

			//Sending 50 emails in a batch process
			if ( $batch_count > 49 ) {
				update_option( 'bitsa_os_batch_count', 0 );
				break;
			}

			$next_payment_date = $subscription->calculate_date( 'next_payment' );
			$current_date      = date( 'Y-m-d H:i:s' );
			$days_to_pay       = self::dateDiffInDays( $current_date, $next_payment_date );
			$billing_email     = $subscription->get_billing_email();

			Bit_OTS_Core()->admin->log( "Next payment date for subscripiton id: $sub_id is: $next_payment_date, Days to pay: $days_to_pay, bit_ew_on: $bit_ew_on, bit_em_on: $bit_em_on, bit_ec_on: $bit_ec_on" );

			if ( $days_to_pay > 0 ) {
				//Sending one week before email reminder
				if ( $bit_ew_on && $days_to_pay < 8 ) {
					$bit_lst_ew = $subscription->get_meta( '_bit_lst_ew' );
					$lst_diff   = empty( $bit_lst_ew ) ? 0 : self::dateDiffInDays( $current_date, $bit_lst_ew );
					if ( 0 === $lst_diff || $lst_diff > 7 ) {
						self::bit_os_send_week_email( $billing_email, $subscription );
						$batch_count += 1;
						$subscription->update_meta_data( '_bit_lst_ew', $current_date );
					}
				}

				//Sending reminder, one month before expiration
				if ( $bit_em_on && $days_to_pay < 31 ) {
					$bit_lst_em = $subscription->get_meta( '_bit_lst_em' );
					$lst_diff   = empty( $bit_lst_em ) ? 0 : self::dateDiffInDays( $current_date, $bit_lst_em );
					if ( 0 === $lst_diff || $lst_diff > 30 ) {
						self::bit_os_send_month_email( $billing_email, $subscription );
						$batch_count += 1;
						$subscription->update_meta_data( '_bit_lst_em', $current_date );
					}
				}

				//Sending custom days reminder emails
				$bit_ec_int = $bit_os_settings['bit_ec_int'];
				if ( $bit_ec_on && $days_to_pay < $bit_ec_int ) {
					$bit_lst_ec = $subscription->get_meta( '_bit_lst_ec' );
					$lst_diff   = empty( $bit_lst_ec ) ? 0 : self::dateDiffInDays( $current_date, $bit_lst_ec );
					if ( 0 === $lst_diff || $lst_diff > $bit_ec_int ) {
						self::bit_os_send_custom_email( $billing_email, $subscription );
						$batch_count += 1;
						$subscription->update_meta_data( '_bit_lst_ec', $current_date );
					}
				}
				update_option( 'bitsa_os_batch_count', $batch_count );
				$subscription->save();
			}
		}
	}

	/**
	 * Function to send scheduled email reminders.
	 *
	 * @param $billing_email
	 * @param $subscription
	 */
	public static function bit_os_send_week_email( $billing_email, $subscription ) {
		$subscription_id = $subscription->get_id();
		$bit_os_settings = self::$bit_os_settings;
		$subject         = $bit_os_settings['bit_ew_subject'];
		$body            = $bit_os_settings['bit_ew_body'];
		$sent            = self::bits_os_send_email( $billing_email, $subject, $body, $subscription );
		Bit_OTS_Core()->admin->log( "Weekly email sent for subscription id: $subscription_id, Billing email: $billing_email, subject: $subject and Email body: $body, Sent: " . print_r( $sent, true ) );
	}

	/**
	 * Function to send monthly email reminder
	 *
	 * @param $billing_email
	 * @param $subscription
	 */
	public static function bit_os_send_month_email( $billing_email, $subscription ) {
		$subscription_id = $subscription->get_id();
		$bit_os_settings = self::$bit_os_settings;
		$subject         = $bit_os_settings['bit_em_subject'];
		$body            = $bit_os_settings['bit_em_body'];
		$sent            = self::bits_os_send_email( $billing_email, $subject, $body, $subscription );
		Bit_OTS_Core()->admin->log( "Monthly email sent for subscription id: $subscription_id, Billing email: $billing_email, subject: $subject and Email body: $body, Sent: " . print_r( $sent, true ) );
	}

	/**
	 * Schedule to send custom days email reminder
	 *
	 * @param $billing_email
	 * @param $subscription_id
	 */
	public static function bit_os_send_custom_email( $billing_email, $subscription ) {
		$subscription_id = $subscription->get_id();
		Bit_OTS_Core()->admin->log( "Custom email sent for subscription id: $subscription_id, Billing email: $billing_email" );
		$bit_os_settings = self::$bit_os_settings;
		$subject         = $bit_os_settings['bit_ec_subject'];
		$body            = $bit_os_settings['bit_ec_body'];
		$sent            = self::bits_os_send_email( $billing_email, $subject, $body, $subscription );
		Bit_OTS_Core()->admin->log( "Custom email sent for subscription id: $subscription_id, Billing email: $billing_email, subject: $subject and Email body: $body, Sent: " . print_r( $sent, true ) );
	}

	/**
	 * Actaul function to send all emails which are scheduled.
	 *
	 * @param $to
	 * @param $subject
	 * @param $body
	 *
	 * @return bool
	 */
	public static function bits_os_send_email( $to, $subject, $body, $subscription ) {
		Bit_OTS_Core()->admin->log( "Sending email to: $to with subject: $subject" );

		$email_subject = self::bitos_decode_merge_tags( $subject, $subscription );
		$email_body    = self::bitos_decode_merge_tags( $body, $subscription );

		$mailer = WC()->mailer();
		ob_start();
		$mailer->email_header( $email_subject );
		echo $email_body;
		$mailer->email_footer();
		$email_body            = ob_get_clean();
		$email_abstract_object = new WC_Email();
		$email_body            = apply_filters( 'woocommerce_mail_content', $email_abstract_object->style_inline( wptexturize( $email_body ) ) );

		return wp_mail( $to, $email_subject, $email_body );
	}

	/**
	 * Start sending batch emails again after 20 second after completing one batch
	 * @return bool
	 */
	public static function time_exceeded() {
		$finish = self::$start_time + 20; // 20 seconds
		$return = false;
		if ( time() >= $finish ) {
			$return = true;
		}

		return $return;
	}

	/**
	 * Check if memory exceeded in background process
	 * @return bool
	 */
	public static function memory_exceeded() {
		$memory_limit   = self::get_memory_limit() * 0.9; // 90% of max memory
		$current_memory = memory_get_usage( true );
		$return         = false;

		if ( $current_memory >= $memory_limit ) {
			$return = true;
		}

		return $return;
	}

	/**
	 * Find memory limit of the server
	 * @return float|int
	 */
	public static function get_memory_limit() {
		if ( function_exists( 'ini_get' ) ) {
			$memory_limit = ini_get( 'memory_limit' );
		} else {
			// Sensible default.
			$memory_limit = '128M';
		}
		if ( ! $memory_limit || - 1 === $memory_limit || '-1' === $memory_limit ) {
			// Unlimited, set to 32GB.
			$memory_limit = '32G';
		}

		return self::convert_hr_to_bytes( $memory_limit ) * 1024 * 1024;
	}

	/**
	 * Converts a shorthand byte value to an integer byte value.
	 *
	 * @param $value
	 *
	 * @return int|mixed
	 */
	public static function convert_hr_to_bytes( $value ) {
		if ( function_exists( 'wp_convert_hr_to_bytes' ) ) {
			return wp_convert_hr_to_bytes( $value );
		}
		$value = strtolower( trim( $value ) );
		$bytes = (int) $value;

		if ( false !== strpos( $value, 'g' ) ) {
			$bytes *= GB_IN_BYTES;
		} elseif ( false !== strpos( $value, 'm' ) ) {
			$bytes *= MB_IN_BYTES;
		} elseif ( false !== strpos( $value, 'k' ) ) {
			$bytes *= KB_IN_BYTES;
		}
		// Deal with large (float) values which run into the maximum integer size.
		return min( $bytes, PHP_INT_MAX );
	}

	/**
	 * Find the difference between two date in days
	 *
	 * @param $start_date
	 * @param $end_date
	 *
	 * @return float
	 */
	public static function dateDiffInDays( $start_date, $end_date ) {
		//Calculating the difference in timestamps
		$diff = strtotime( $end_date ) - strtotime( $start_date );

		return ( round( $diff / 86400 ) );
	}


	/**
	 * Decoding merge tags for sending email templates
	 *
	 * @param $content
	 *
	 * @return mixed
	 */
	public static function bitos_decode_merge_tags( $content, $subscription ) {
		$subscription_id = $subscription->get_id();
		$content         = str_replace( '{{customer_name}}', self::get_customer_name( $subscription ), $content );
		$content         = str_replace( '{{course_name}}', self::get_course_name( $subscription ), $content );
		$content         = str_replace( '{{order_total}}', self::get_order_total( $subscription ), $content );
		$content         = str_replace( '{{subscription_id}}', $subscription_id, $content );

		return $content;
	}

	/**
	 * Finding customer name form the subscription object
	 *
	 * @param $subscription_id
	 *
	 * @return string
	 */
	public static function get_customer_name( $subscription ) {
		/*$subscr = new WC_Subscription();
		$f_name = $subscr->get_formatted_order_total()*/
		return ( $subscription instanceof WC_Subscription ) ? $subscription->get_billing_first_name() : '';
	}

	/**
	 * Finding the course name
	 *
	 * @param $subscription_id
	 *
	 * @return string
	 */
	public static function get_course_name( $subscription ) {
		return '';
	}

	/**
	 * Get the subscription total to be sent in email reminder
	 *
	 * @param $subscription_id
	 *
	 * @return string
	 */
	public static function get_order_total( $subscription ) {
		return ( $subscription instanceof WC_Subscription ) ? $subscription->get_formatted_order_total() : '';
	}

	/**
	 * Validate course access by users
	 *
	 * @param $subscription
	 * @param $sub_product_id
	 */
	public static function validate_course_access( $subscription, $sub_product_id ) {
		global $wpdb, $next_payment_datetime;
		if ( $subscription instanceof WC_Subscription ) {
			$sub_status = $subscription->get_status(); //print_r($subscription);
			Bit_OTS_Core()->admin->log( "Subscription status: $sub_status" );
			if ( 'active' !== $sub_status ) {
				$user_id   = $subscription->get_customer_id();
				$group_ids = learndash_get_groups( true, $user_id );
				if ( ! empty( $group_ids ) && is_array( $group_ids ) ) {
					foreach ( $group_ids as $group_id ) {
						$user_ids = learndash_get_groups_user_ids( $group_id );
						foreach ( $user_ids as $user_id ) {
							update_user_meta( $user_id, 'user_expire_date', $next_payment_datetime );
						}
					}
				}
			}
		}

	}


	/**
	 * Send parent reminder email by student
	 *
	 * @param $to
	 * @param $subject
	 * @param $body
	 *
	 * @return bool
	 */
	public static function bitots_send_parent_email( $course_id, $stdnt_id ) {
		Bit_OTS_Core()->admin->log( "Sending parent email to parent from student id: $stdnt_id and with course id: $course_id" );

		$stdnt_user = get_user_by( 'ID', $stdnt_id );

		$email_subject = sprintf( __( "Email from student: %s for renewal of the course: %s", 'bit-ots' ), $stdnt_user->user_email, get_the_title( $course_id ) );
		$email_body    = sprintf( __( 'Reminder email from student: %s for the renewal of course: %s', 'bit-ots' ), $stdnt_user->user_email, get_the_title( $course_id ) );

		//Get student group ids for matching the current group
		$stdnts_grps_ids = learndash_get_users_group_ids( $stdnt_id );
		$stdnts_grps_ids = is_array( $stdnts_grps_ids ) ? $stdnts_grps_ids : [];
		foreach ( $stdnts_grps_ids as $group_id ) {
			//Get student group leaders
			$group_leaders = learndash_get_groups_administrators( $group_id );
			foreach ( $group_leaders as $group_leader ) {
				if ( ! $group_leader instanceof WP_User ) {
					return;
				}
				if ( ! in_array( 'group_leader', $group_leader->roles, true ) ) {
					return;
				}
				$parent_email = $group_leader->user_email;
				if ( is_email( $parent_email ) ) {
					break 2;
				}
			}
		}

		//using woocommerce standard mailer class to send parent reminder emails.
		$mailer = WC()->mailer();
		ob_start();
		$mailer->email_header( $email_subject ); //Get WC email header
		echo $email_body;
		$mailer->email_footer(); //Get WC email footer
		$email_body            = ob_get_clean();
		$email_abstract_object = new WC_Email();
		$email_body            = apply_filters( 'woocommerce_mail_content', $email_abstract_object->style_inline( wptexturize( $email_body ) ) );

		return wp_mail( $parent_email, $email_subject, $email_body );
	}
}

Bit_OTS_Common::init();
