<?php
/*
 * Plugin Name: Paddle
 * Plugin URI: http://paddle.com
 * Description: Paddle Payment Gateway for WooCommerce
 * Version: 2.0
 * Author: Paddle.com
 * Author URI: http://paddle.com
 */

defined('ABSPATH') or die("Plugin must be run as part of wordpress");

function init_paddle_gateway_class() {

	/**
	 * Don't load extension if WooCommerce is not active
	 */
	if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
		return;
	}

	include_once('models/gateway.php');
	include_once('models/checkout.php');
	include_once('models/settings.php');
	$gateway = new Paddle_WC_Payment_Gateway();
	$gateway->add_hooks();

}

add_action('plugins_loaded', 'init_paddle_gateway_class');
