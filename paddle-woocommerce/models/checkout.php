<?php

class Paddle_Checkout {

	public static function add_hooks() {
		add_filter('woocommerce_checkout_before_customer_details', ['Paddle_Checkout', 'inject_checkout_javascript']);
		add_action( 'template_redirect', ['Paddle_Checkout', 'intercept_url_ajax']);
	}

	public static function inject_checkout_javascript($value) {
		$url = get_site_url().'/'.Paddle_WC_Payment_Gateway::AJAX_URL;
		echo <<<SCRIPT
<!-- Paddle Checkout CSS -->
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
.btn {
	color: green;
}
</style>
<!-- Paddle Checkout JS -->
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
		if(strpos($page_url, Paddle_WC_Payment_Gateway::AJAX_URL) !== false) {
		var_dump($_POST);
			http_response_code(200);
			echo 'http://www.example.com';
			exit();
		}
	}
}
