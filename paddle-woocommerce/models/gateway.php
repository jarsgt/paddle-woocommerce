<?php

class Paddle_WC_Payment_Gateway {

	const AJAX_URL = 'paddle/geturl';

	const PADDLE_ROOT_URL = 'https://vendors.paddle.com/';
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

}
