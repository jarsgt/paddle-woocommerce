<?php

class Paddle_WC_Payment_Gateway {

	const AJAX_URL_ORDER = 'paddle/geturl/order';
	const AJAX_URL_CHECKOUT = 'paddle/geturl/checkout';
	const CHECKOUT_RETURN_URL = 'paddle/geturl/return';
	const API_GENERATE_PAY_LINK_URL = 'api/2.0/product/generate_pay_link';
	const API_GET_PUBLIC_KEY_URL = 'api/2.0/user/get_public_key';
	const PADDLE_ROOT_URL = 'https://staging-vendors.paddle.com/';
	const PADDLE_CHECKOUT_ROOT_URL = 'https://staging-checkout.paddle.com/';
	const INTEGRATE_URL = 'vendor/external/integrate';
	const SIGNUP_LINK = 'https://www.paddle.com/sell?utm_source=WooCommerce&utm_campaign=WooCommerce&utm_medium=WooCommerce&utm_term=sell';

	private $supported_currencies = array('USD', 'GBP', 'EUR');

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 9 );
		if(!empty($_POST)) {
			$settings = Paddle_Settings::instance();
			$settings->save_form();
		}
	}

	public function add_admin_menu() {
		add_menu_page('Paddle Settings', 'Paddle', 'manage_woocommerce', 'wc-paddle-s', array($this, 'admin_page'));

	}

	public function add_hooks() {
		add_action('admin_head', ['Paddle_WC_Payment_Gateway', 'inject_admin_javascript']);
		add_action( 'template_redirect', ['Paddle_WC_Payment_Gateway', 'intercept_return_url']);
		// Only add checkout hooks if we are integrated, AND currency is supported
		$settings = Paddle_Settings::instance();
		if(in_array(get_woocommerce_currency(), $this->supported_currencies) && $settings->is_connected ) {
			Paddle_Checkout::add_hooks();
		}
	}

	public function admin_page() {
		$signup_link = static::SIGNUP_LINK;
		$supported_currencies = $this->supported_currencies;
		$active_currency = get_woocommerce_currency();
		$integrationUrl = self::PADDLE_ROOT_URL . self::INTEGRATE_URL . '?' . http_build_query(array(
				'app_name' => 'WooCommerce Paddle Payment Gateway',
				'app_description' => 'WooCommerce Paddle Payment Gateway Plugin. Site name: ' . get_bloginfo('name'),
				'app_icon' => plugins_url('images/woo.png', __FILE__)
		));
		include_once( dirname(__FILE__).'/../views/paddle-settings.php' );
	}

	public static function inject_admin_javascript($value) {
		echo <<<SCRIPT
<!-- Paddle Admin JS -->
<script type='text/javascript'>
jQuery(document).ready(function(){
	jQuery('#toggleVendorAccountEntry').click(function(){
		var row = jQuery(this).closest('tr');
		row.next().show();
		row.next().next().show();
		row.hide();
	});
});
</script>
SCRIPT;
		return $value;
	}

	/**
	 * Retrieves from paddle api and returns vendor_public_key
	 * @param int $vendorId
	 * @param string $vendorApiKey
	 * @return string
	 */
	protected static function get_vendor_public_key($vendorId, $vendorApiKey) {
		// data to be send to paddle gateway
		$data = array();
		$data['vendor_id'] = $vendorId;
		$data['vendor_auth_code'] = $vendorApiKey;

		$apiCallResponse = wp_remote_get(self::PADDLE_ROOT_URL . self::API_GET_PUBLIC_KEY_URL, array(
			'method' => 'POST',
			'timeout' => 45,
			'httpversion' => '1.1',
			'blocking' => true,
			'body' => $data,
			'sslverify' => false
			)
		);

		if (is_wp_error($apiCallResponse)) {
			echo 'Something went wrong. Unable to get API response.';
			error_log('Paddle error. Unable to get API response. Method: ' . __METHOD__ . ' Error message: ' . $apiCallResponse->get_error_message());
			exit;
		} else {
			$oApiResponse = json_decode($apiCallResponse['body']);

			if ($oApiResponse->success === true) {
				return $oApiResponse->response->public_key;
			} else {
				echo 'Something went wrong. Make sure that Paddle Vendor Id and Paddle Api Key are correct.';
				error_log('Paddle error. Error response from API. Errors: ' . print_r($oApiResponse->error, true));
				exit;
			}
		}
	}

	public static function get_checkout_return_url() {
		return get_site_url().'/'.Paddle_WC_Payment_Gateway::CHECKOUT_RETURN_URL;
	}

	/**
	 * Get the vendor key, querying if necessary
	 *
	 * Attempts to retrieve the vendors public key, and if it's not set,
	 * then it calls the padle server to retrieve it.
	 *
	 * @uses get_vendor_public_key to get the vendor key from paddle servers
	 * @return string vendor key or '' if not set
	 */
	public static function getPaddleVendorKey() {
		$settings = Paddle_Settings::instance();
		return static::get_vendor_public_key($settings->get('paddle_vendor_id'), $settings->get('paddle_api_key'));
	}

	/**
	 * Check webhook_url signature
	 * Returns 1 if the signature is correct, 0 if it is incorrect, and -1 on error.
	 * @return int
	 */
	protected static function check_webhook_signature() {
		// log error if vendor_public_key is not set
		$vendor_public_key = static::getPaddleVendorKey();
		if (!$vendor_public_key) {
			error_log('Paddle error. Unable to verify webhook callback - vendor_public_key is not set.');
			return -1;
		}

		// copy get input to separate variable to not modify superglobal array
		$arrWebhookData = $_POST;
		foreach ($arrWebhookData as $k => $v) {
			$arrWebhookData[$k] = stripslashes($v);
		}

		// pop signature from webhook data
		$signature = base64_decode($arrWebhookData['p_signature']);
		unset($arrWebhookData['p_signature']);

		// check signature and return result
		ksort($arrWebhookData);
		$data = serialize($arrWebhookData);
		return openssl_verify($data, $signature, $vendor_public_key, OPENSSL_ALGO_SHA1);
	}

	/**
	 * Gateway response handler
	 * Validate webhook response and complete order
	 * This method is called when webhook_url is called by paddle
	 * Link must content: wc-api=paddle_wc_payment_gateway
	 * (this is registered in clas construct)
	 * Returns HTTP 200 if OK, 500 otherwise
	 */
	public function gateway_response() {
		if (static::check_webhook_signature()) {
			$order_id = $_GET['order_id'];
			if (is_numeric($order_id) && (int) $order_id == $order_id) {
				$order = new WC_Order($order_id);
				if (is_object($order) && $order instanceof WC_Order) {
					$order->payment_complete();
					status_header(200);
					exit;
				} else {
					error_log('Paddle error. Unable to complete payment - order ' . $order_id . ' does not exist');
				}
			} else {
				error_log('Paddle error. Unable to complete payment - order_id is not integer.');
			}
		} else {
			error_log('Paddle error. Unable to verify webhook callback - bad signature.');
		}
		status_header(500);
	}

	public static function intercept_return_url() {
		$page_url = $_SERVER['REQUEST_URI'];

		if(strpos($page_url, Paddle_WC_Payment_Gateway::CHECKOUT_RETURN_URL) !== false) {
			static::gateway_response();
			exit();
		}
	}

}
