<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Functions used by plugins
 */
if ( ! class_exists( 'Bit_OTS_Dependencies' ) ) {
	require_once 'class-bit-ots-dependencies.php';
}

/**
 * WC Detection
 */
if ( ! function_exists( 'bit_ots_is_woocommerce_active' ) ) {
	function bit_ots_is_woocommerce_active() {
		return Bit_OTS_Dependencies::woocommerce_active_check();
	}

}
