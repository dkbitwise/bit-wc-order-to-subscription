<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
/**
 * Class to initiate public functionalities
 * Class Bit_OTS_Public
 */
class Bit_OTS_Public {
	private static $ins = null;
	public function __construct(){
		add_action('wp',array($this,'bitots_create_subsription'),10,1);
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

	/**
	* Creating subscription
	*/
	public function bitots_create_subsription($wp_args){
		$create_subs = filter_input(INPUT_GET, 'create_subscription',FILTER_SANITIZE_STRING);
		if ('yes' === $create_subs) {
			$simple_orders = array(638,640,575);  //Enter here all the simple order ids to convert them to renewal order.
			$order_product_id = 574;  //This is the simple product id in parent order.
			Bit_OTS_Common::bitots_create_subsription($simple_orders, $order_product_id);
		}	
	}
}