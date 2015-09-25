<?php

class Paddle_Checkout {

	public static function add_hooks() {
		add_filter('woocommerce_checkout_before_customer_details', ['Paddle_Checkout', 'inject_checkout_javascript']);
		add_action( 'template_redirect', ['Paddle_Checkout', 'intercept_url_ajax']);
	}

	public static function inject_checkout_javascript($value) {
		$order_url = get_site_url().'/'.Paddle_WC_Payment_Gateway::AJAX_URL_ORDER;
		$domain = rtrim(Paddle_WC_Payment_Gateway::PADDLE_CHECKOUT_ROOT_URL, '/');
		echo <<<SCRIPT
<!-- Paddle Checkout CSS -->
<style type='text/css'>
#paddle-checkout-popup-background {
	width: 100%;
	height: 100%;
	z-index: 800;
	position: fixed;
	text-align: center;
	top: 0;
	left: 0;
	opacity: 0.4;
	background-color: black;
}
#paddle-checkout-popup-holder {
	width: 100%;
	height: 100%;
	z-index: 900;
	position: fixed;
	text-align: center;
	top: 0;
	left: 0;
}
#paddle-checkout-popup {
	width: 600px;
	height: 600px;
	margin-top: 50px;
	display: inline-block;
}
#paddle-checkout-popup iframe {
	width: 100%;
	height: 100%;
}
</style>
<!-- Paddle Checkout JS -->
<script type='text/javascript'>
jQuery(document).ready(function(){
	jQuery('#page_wrapper').append(
		jQuery('<div>')
			.prop('id', 'paddle-checkout-popup-background')
			.css('display', 'none')
		).append(
		jQuery('<div>')
			.prop('id', 'paddle-checkout-popup-holder')
			.css('display', 'none')
			.append(
				jQuery('<div>')
					.prop('id', 'paddle-checkout-popup')
					.append('<iframe>')
				)
		);
	jQuery('#paddle-checkout-popup-holder').click(function(){
		jQuery('#paddle-checkout-popup-background').hide();
		jQuery('#paddle-checkout-popup-holder').hide();
	});
	jQuery('#checkout_buttons button[id!=paypal_submit]').click(function(event){
		event.preventDefault();
		jQuery.post(
			'$order_url',
			jQuery('form.checkout').serializeArray()
		).done(function(data){
			data = JSON.parse(data);
			if(data.result = 'success') {
				jQuery('#paddle-checkout-popup-background').show();
				jQuery('#paddle-checkout-popup-holder').show();
				jQuery('#paddle-checkout-popup iframe').attr('src', data.checkout_url);
			} else {
				var msg = 'Errors: ';
				for( var i in data.errors ) {
					var errmsg = data.errors[i];
					errmsg = errmsg + ',';
					msg = msg + errmsg;
				}
				alert(msg);
			}
		});
	});
	window.addEventListener("message", function(event) {
		if(event.origin.indexOf('$domain') == -1) return;
		switch(event.data.action) {
			case 'complete':

				break;
			case 'close':
				jQuery('#paddle-checkout-popup-background').hide();
				jQuery('#paddle-checkout-popup-holder').hide();
				break;
		}
	}, false);
});
</script>
SCRIPT;
		return $value;
	}

	public static function intercept_url_ajax() {
		$page_url = $_SERVER['REQUEST_URI'];

		if(strpos($page_url, Paddle_WC_Payment_Gateway::AJAX_URL_ORDER) !== false) {
			http_response_code(200);
			// Intercept before attempting to take payment
			// The action will call exit()
			add_action('woocommerce_checkout_order_processed', ['Paddle_Checkout', 'checkout_redirect']);
			// Create the order
			WC()->checkout()->process_checkout();
			if(wc_notice_count( 'error' ) > 0) {
				// Errors prevented completion
				echo json_encode(array(
					'result' => 'failure',
					'errors' => WC()->session->get( 'wc_notices', array() )
				));
			} else {
				// Something preevented completion, but, no errors apparently.
				echo json_encode(array(
					'result' => 'failure',
					'errors' => array('Unknown Error')
				));
			}
			exit();
		}
	}

	public static function checkout_redirect($order_id) {
		$url = static::get_pay_url($order_id);
		echo json_encode(array(
			'result' => 'success',
			'order_id' => $order_id,
			'checkout_url' => $url
		));
		exit();
	}

	public static function get_webhook_url($order) {
		return Paddle_WC_Payment_Gateway::get_checkout_return_url().'?order_id='.$order->id;
	}

	public static function get_return_url($order) {
		$return_url = $order->get_checkout_order_received_url();
		if ( is_ssl() || get_option('woocommerce_force_ssl_checkout') == 'yes' ) {
			$return_url = str_replace( 'http:', 'https:', $return_url );
		}
		return apply_filters( 'woocommerce_get_return_url', $return_url );
	}

	public static function get_pay_url($order_id) {
		global $woocommerce;
		$settings = Paddle_Settings::instance();
		$order = new WC_Order($order_id);

		// data to be send to paddle gateway
		$data = array();
		$data['vendor_id'] = $settings->get('paddle_vendor_id');
		$data['vendor_auth_code'] = $settings->get('paddle_api_key');
		$data['prices'] = array(get_woocommerce_currency().':'.$order->get_total());
		$data['return_url'] = static::get_return_url($order);
		$data['title'] = $settings->get('product_name');
		$data['image_url'] = $settings->get('product_icon');
		$data['webhook_url'] = static::get_webhook_url($order);
		$data['discountable'] = 0;
		$data['quantity_variable'] = 0;
		$data['customer_email'] = $order->billing_email;
		$data['customer_postcode'] = $woocommerce->customer->postcode;
		$data['customer_country'] = $woocommerce->customer->country;
		$data['is_popup'] = 'true';
		// parent_url is an url to redirect to when close button on checkout popup is clicked
		// Scheme, hostname, and port must match the page the popup appears on
		if (version_compare(WOOCOMMERCE_VERSION, '2.1.0', '>=')) {
			$data['parent_url'] = $order->get_checkout_order_received_url();
		} else {
			$data['parent_url'] = $order->get_checkout_payment_url($on_checkout = true);
		}
		// paypal_cancel_url is an url to redirect to when 'cancel' link is clicked in paypal
		if (version_compare(WOOCOMMERCE_VERSION, '2.1', '>=')) {
			$data['paypal_cancel_url'] = $order->get_checkout_payment_url(true);
		} else {
			$data['paypal_cancel_url'] =  add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))));
		}
		$data['popupCompleted'] = 'default';

		// get pay link from paddle api and redirect there

		$post_url = Paddle_WC_Payment_Gateway::PADDLE_ROOT_URL . Paddle_WC_Payment_Gateway::API_GENERATE_PAY_LINK_URL;
		$apiCallResponse = wp_remote_post($post_url, array(
			'method' => 'POST',
			'timeout' => 45,
			'httpversion' => '1.1',
			'blocking' => true,
			'body' => $data,
			'sslverify' => false
			)
		);

		if (is_wp_error($apiCallResponse)) {
			echo json_encode(array(
				'result' => 'failure',
				'errors' => array('Something went wrong. Unable to get API response.')
			));
			error_log('Paddle error. Unable to get API response. Method: ' . __METHOD__ . ' Error message: ' . $apiCallResponse->get_error_message());
			exit;
		} else {
			$oApiResponse = json_decode($apiCallResponse['body']);
			if ($oApiResponse && $oApiResponse->success === true) {
				return $oApiResponse->response->url;
			} else {
				echo json_encode(array(
					'result' => 'failure',
					'errors' => array('Something went wrong. Check if Paddle account is properly integrated.')
				));
				if (is_object($oApiResponse)) {
					error_log('Paddle error. Error response from API. Method: ' . __METHOD__ . ' Errors: ' . print_r($oApiResponse->error, true));
				} else {
					error_log('Paddle error. Error response from API. Method: ' . __METHOD__ . ' Response: ' . print_r($apiCallResponse, true));
				}
				exit;
			}
		}
	}

}
