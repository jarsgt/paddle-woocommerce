<?php

class Paddle_Settings
{

	const PLUGIN_ID = 'wcPaddlePaymentPlugin';

	private $settings = array(
		'paddle_vendor_id' => '',
		'paddle_api_key' => '',
		'product_icon' => '',
		'product_name' => '',
		'checkout_hook' => 'woocommerce_checkout_before_customer_details',
		'button_css' => '#checkout_buttons button[id!=paypal_submit]'
	);

	public $is_connected;
	public $settings_saved = false;

	public static function instance()
	{
		if(!isset($GLOBALS['wc_paddle_settings'])) {
			$GLOBALS['wc_paddle_settings'] = new static();
		}
		return $GLOBALS['wc_paddle_settings'];
	}

	public function __construct()
	{
		// Load settings
		$this->settings = array_merge($this->settings, get_option(static::PLUGIN_ID . '_settings', []));
		$this->is_connected = ($this->settings['paddle_api_key'] && $this->settings['paddle_vendor_id']);
	}

	public function getOptions()
	{
		return $this->settings;
	}

	public function get($key)
	{
		return $this->settings[$key];
	}

	public function save_form() {
		$changed = false;
		foreach($this->settings as $key => $value) {
			if(isset($_POST['woocommerce_wcPaddlePaymentGateway_'.$key])) {
				$this->settings[$key] = $_POST['woocommerce_wcPaddlePaymentGateway_'.$key];
				$changed = true;
			}
		}
		if($changed) {
			update_option(static::PLUGIN_ID . '_settings', $this->settings);
			$this->settings_saved = true;
		}
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
	public function getPaddleVendorKey() {
		$vendorId = $this->getPaddleVendorId();
		$options = get_option($this->plugin_id . $this->id . '_settings');
		if (isset($options['vendor_public_key']) && $options['vendor_public_key'] && $options['paddle_vendor_id'] == $vendorId) {
			return $options['vendor_public_key'];
		}
		if (isset($_POST[$this->plugin_id . $this->id . '_paddle_api_key'])) {
			$vendorApiKey = $_POST[$this->plugin_id . $this->id . '_paddle_api_key'];
			return $this->get_vendor_public_key($vendorId, $vendorApiKey);
		}
		return '';
	}
}
