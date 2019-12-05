<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

/**
 * This class contain handle common functions for backend and frontend
 * Class Bit_OTS_Common
 */
class Bit_OTS_Common {
	public static $start_time = 0;
	public static $bit_os_settings = [];

	public static function init() {
		//add_action('init',[__CLASS__,'bitsa_register_session']);		
		add_action( 'wp', [ __CLASS__, 'setup_schedule_to_email_reminder' ] );
		add_action( 'bitos_send_reminder_email', [ __CLASS__, 'bitos_send_reminder_email_function' ], 9999 );
		//add_filter( 'cron_schedules', [ __CLASS__, 'bitos_custom_schedule_interval' ], 10, 1 );

		self::$bit_os_settings = Bit_OTS_Core()->admin->bitos_get_email_settings();
	}

	public static function bitos_custom_schedule_interval( $schedules ) {
		if ( ! isset( $schedules["5min"] ) ) {
			$schedules["5min"] = array(
				'interval' => 5 * 60,
				'display'  => __( 'Once every 5 minutes' )
			);
		}
		if ( ! isset( $schedules["30min"] ) ) {
			$schedules["30min"] = array(
				'interval' => 30 * 60,
				'display'  => __( 'Once every 30 minutes' )
			);
		}

		return $schedules;
	}

	public static function bitsa_register_session() {
		if ( ! session_id() ) {
			session_start();
			$_SESSION['bit_ots_sess'] = isset( $_SESSION['bit_ots_sess'] ) ? $_SESSION['bit_ots_sess'] : array();
		}
	}

	/**
	 * Creating subscription
	 */
	public static function bitots_create_subsription( $simple_orders = array(), $sub_product_id ) {
		$result = [];
		if ( $sub_product_id < 1 ) {
			return $result;
		}
		Bit_OTS_Core()->admin->log( "Sub product id: $sub_product_id, Simple order ids: " . print_r( $simple_orders, true ) );
		foreach ( $simple_orders as $user_id => $order_id ) {
			$bit_order = wc_get_order( $order_id );
			if ( $bit_order instanceof WC_Order ) {
				$user_id      = $bit_order->get_customer_id();
				$subs_created = $bit_order->get_meta( '_bit_subscription_created' );
				Bit_OTS_Core()->admin->log( "User id: $user_id, Subs created: $subs_created for order id: $order_id, Subs product id: $sub_product_id" );
				if ( ( ! empty( $subs_created ) && $subs_created > 0 ) || $user_id < 1 ) {
					continue;
				}

				$prod_obj = wc_get_product( $sub_product_id );
				if ( $prod_obj instanceof WC_Product_Subscription ) {
					$stripe_cust_id = $bit_order->get_meta( '_stripe_customer_id', true );
					$stripe_src_id  = $bit_order->get_meta( '_stripe_source_id', true );
					$transaction_id = $bit_order->get_transaction_id();

					$args = array(
						'product'        => $prod_obj,
						'order'          => $bit_order,
						'order_id'       => $order_id,
						'user_id'        => $user_id,
						'transaction_id' => $transaction_id,
						'amt'            => $prod_obj->get_regular_price(),
					);

					Bit_OTS_Core()->admin->log( "stripe_cust_id: $stripe_cust_id, stripe_src_id: $stripe_src_id, Transaction id: $transaction_id, Regular amount: " . print_r( $args['amt'], true ) );

					$subscription = self::_create_new_subscription( $args, 'completed' );
					if ( $subscription instanceof WC_Subscription ) {
						$subscription->update_meta_data( '_stripe_customer_id', $stripe_cust_id );
						$subscription->update_meta_data( '_stripe_source_id', $stripe_src_id );
						$subscription->save();

						$subscription_id = $subscription->get_id();

						$bit_order->update_meta_data( '_bit_subscription_created', $subscription_id );
						$bit_order->save();

						$result[ $subscription_id ] = ( empty( $stripe_cust_id ) || empty( $stripe_src_id ) );
					}
				}
			}
		}

		return $result;
	}

	public static function _create_new_subscription( $args, $order_status ) {
		// create a subscription

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

		if ( ! empty( $current_user_id ) && ! empty( $subscription ) ) {
			// link subscription product & copy address details
			$product->set_price( $args['amt'] );
			$subscription_item_id = $subscription->add_product( $product, 1 ); // $args
			$subscription         = wcs_copy_order_address( $order, $subscription );

			$next_payment_datetime = wcs_add_time( $interval, $period, strtotime( $start_date ) );
			if ( ! empty( $trial_period ) && $trial_length > 0 ) {
				$next_payment_datetime = wcs_add_time( $trial_length, $trial_period, $next_payment_datetime );
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

				$subscription->set_payment_method( $order->get_payment_method() );
				$subscription->set_payment_method_title( $order->get_payment_method_title() );

				if ( ! empty( $current_user_id ) ) {
					update_post_meta( $subscription->get_id(), '_customer_user', $current_user_id );
				}

				if ( 'completed' === $order_status ) {
					$subscription->payment_complete( $transaction_id );
				} else {
					$subscription->update_status( $order_status );
				}
			}

			$subscription->calculate_totals();
			$subscription->save();

			return $subscription;
		}

		return false;
	}

	public static function setup_schedule_to_email_reminder() {
		if ( false === wp_next_scheduled( 'bitos_send_reminder_email' ) ) {
			$time = self::$bit_os_settings['bit_start_time'];
			$time = ( 5 === strlen( $time ) ) ? $time . ':00' : $time;
			//wp_schedule_event( time(), 'daily', 'bitos_send_reminder_email' );
			wp_schedule_event( strtotime( $time ), 'daily', 'bitos_send_reminder_email' );
			//wp_schedule_event( time(), '5min', 'bitos_send_reminder_email' );
		}
	}

	/**
	 * Scheduled task for sending mail.
	 */
	public static function bitos_send_reminder_email_function() {
		self::$start_time = time();

		$bit_os_settings = self::$bit_os_settings;

		$bit_ew_on = $bit_os_settings['bit_ew_on'];
		$bit_em_on = $bit_os_settings['bit_em_on'];
		$bit_ec_on = $bit_os_settings['bit_ec_on'];

		if ( ! $bit_ew_on && ! $bit_em_on && ! $bit_ec_on ) {
			return;
		}

		$subscriptions = wcs_get_subscriptions( [ 'subscriptions_per_page' => - 1 ] );
		foreach ( $subscriptions as $sub_id => $subscription ) {
			$batch_count = get_option( 'bitsa_os_batch_count', 0 );
			if ( true === self::time_exceeded() || true === self::memory_exceeded() ) {
				update_option( 'bitsa_os_batch_count', 0 );
				break;
			}

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
				if ( $bit_ew_on && $days_to_pay < 8 ) {
					$bit_lst_ew = $subscription->get_meta( '_bit_lst_ew' );
					$lst_diff   = empty( $bit_lst_ew ) ? 0 : self::dateDiffInDays( $current_date, $bit_lst_ew );
					if ( 0 === $lst_diff || $lst_diff > 7 ) {
						self::bit_os_send_week_email( $billing_email, $subscription );
						$batch_count += 1;
						$subscription->update_meta_data( '_bit_lst_ew', $current_date );
					}
				}

				if ( $bit_em_on && $days_to_pay < 31 ) {
					$bit_lst_em = $subscription->get_meta( '_bit_lst_em' );
					$lst_diff   = empty( $bit_lst_em ) ? 0 : self::dateDiffInDays( $current_date, $bit_lst_em );
					if ( 0 === $lst_diff || $lst_diff > 30 ) {
						self::bit_os_send_month_email( $billing_email, $subscription );
						$batch_count += 1;
						$subscription->update_meta_data( '_bit_lst_em', $current_date );
					}
				}

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
	 * To print data inside pre tags for debugging
	 */
	public static function pr( $data ) {
		echo "<pre>";
		print_r( $data );
		echo "</pre>";
	}

	/**
	 * To print data inside pre tags for debugging with a die
	 */
	public static function prd( $data, $msg = 'die12345' ) {
		echo "<pre>";
		print_r( $data );
		echo "</pre>";
		die( $msg );
	}

	/**
	 * Decoding merge tags
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
	 * @param $subscription_id
	 *
	 * @return string
	 */
	public static function get_course_name( $subscription ) {
		return '';
	}

	/**
	 * @param $subscription_id
	 *
	 * @return string
	 */
	public static function get_order_total( $subscription ) {
		return ( $subscription instanceof WC_Subscription ) ? $subscription->get_formatted_order_total() : '';
	}
}

Bit_OTS_Common::init();
