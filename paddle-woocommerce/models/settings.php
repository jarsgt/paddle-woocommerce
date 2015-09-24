<?php

class Paddle_Settings
{

	const PLUGIN_ID = 'wcPaddlePaymentPlugin';

	private $settings = array(
		'paddle_vendor_id' => '',
		'paddle_api_key' => '',
		'product_icon' => ''
	);

	public $is_connected;
	public $settings_saved = false;

	public static function instance()
	{
		static $object = null;
		if($object === null) $object = new static();
		return $object;
	}

	public function __construct()
	{
		// Load settings
		$this->settings = get_option(static::PLUGIN_ID . '_settings', null);
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
		$this->settings['paddle_vendor_id'] = $_POST['woocommerce_wcPaddlePaymentGateway_paddle_vendor_id'];
		$this->settings['paddle_api_key'] = $_POST['woocommerce_wcPaddlePaymentGateway_paddle_api_key'];
		$this->settings['product_icon'] = $_POST['woocommerce_wcPaddlePaymentGateway_product_icon'];
		update_option(static::PLUGIN_ID . '_settings', $this->settings);
		$this->settings_saved = true;
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
