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

	class Paddle_WC_Payment_Gateway {

		const AJAX_URL = 'paddle/geturl';

		public static function inject_checkout_javascript($value) {
			$url = get_site_url().'/'.static::AJAX_URL;
			echo <<<SCRIPT
<style type='text/css'>
#paddle-checkout-popup {
	width: 800px;
	height: 400px;
	position: fixed;
	top: 20%;
	left: 45%;
}
#paddle-checkout-popup iframe {
	width: 100%;
	height: 100%;
}
</style>
<script type='text/javascript'>
jQuery(document).ready(function(){
	jQuery('#page_wrapper').append(
		jQuery('<div>')
			.prop('id', 'paddle-checkout-popup')
			.css('display', 'none')
			.append('<iframe>')
		);
	jQuery('#checkout_buttons button[id!=paypal_submit]').click(function(event){
		event.preventDefault();
		jQuery.ajax('$url', {
			data: jQuery('form.checkout').serializeArray()
		}).done(function(data){
			jQuery('#paddle-checkout-popup').show();
			jQuery('#paddle-checkout-popup iframe').attr('src', data);
		});
	});
});
</script>
SCRIPT;
			return $value;
		}

		public static function intercept_url_ajax() {
			$page_url = $_SERVER['REQUEST_URI'];
			if(strpos($page_url, static::AJAX_URL) !== false) {
				http_response_code(200);
				echo 'http://www.example.com';
				exit();
			}
		}

	}

}

add_action('plugins_loaded', 'init_paddle_gateway_class');
add_filter('woocommerce_checkout_before_customer_details', ['Paddle_WC_Payment_Gateway', 'inject_checkout_javascript']);
add_action( 'template_redirect', ['Paddle_WC_Payment_Gateway', 'intercept_url_ajax']);

