<?php
/*
 * Plugin Name: Paddle
 * Plugin URI: http://paddle.com
 * Description: Paddle Payment Gateway for WooCommerce
 * Version: 1.1
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

	class Paddle_WC_Payment_Gateway extends WC_Payment_Gateway {

		const PADDLE_ROOT_URL = 'https://vendors.paddle.com/';
		const API_GENERATE_PAY_LINK_URL = 'api/2.0/product/generate_pay_link';
		const API_GET_PUBLIC_KEY_URL = 'api/2.0/user/get_public_key';
		const INTEGRATE_URL = 'vendor/external/integrate';
		const IMAGE_BOUNCE_PROXY_URL = 'https://checkout.paddle.com/image/';
		const SIGNUP_LINK = 'https://www.paddle.com/sell?utm_source=WooCommerce&utm_campaign=WooCommerce&utm_medium=WooCommerce&utm_term=sell';
		const CHECKOUT_SETUP_JS = 'http://paddle.s3.amazonaws.com/checkout/checkout-woocommerce.js';

		protected $supported_currencies;
		protected $paddle_vendor_id;
		protected $paddle_api_key;

		public function __construct() {
			// basic configuration
			$this->id = 'wcPaddlePaymentGateway';
			$this->has_fields = false;
			$this->supported_currencies = array('USD');

			// Checkout name visible in WooCommerce -> Settings -> Checkout
			$this->method_title = 'Paddle.com Payment Gateway';

			// init_form_fields() defines settings which are then loaded with init_settings()
			$this->init_form_fields();
			$this->init_settings();

			// front end visible on checkout page, from settings
			foreach (array_keys($this->form_fields) as $field) {
				$this->$field = isset($this->settings[$field]) ? $this->settings[$field] : $this->form_fields[$field]['default'];
			}

			// front end visible icon - hardcoded
			$this->icon = plugins_url('images/paddle.png', __FILE__);

			// allows admin settings to be saved in db, do not remove
			if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
				add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			} else {
				add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
			}

			// register gateway response listener
			add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'gateway_response'));

			// register receipt page
			add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

			// allow gateway to be used only if is valid
			if (!$this->is_valid_for_use()) {
				$this->enabled = false;
			}
		}

		/**
		 * Output for the order received page (payment page).
		 * @param int $order_id
		 */
		public function receipt_page($order_id) {
			// include paddle checkout js, this is used for popup checkout behaviour
			wp_enqueue_script('my-script', self::CHECKOUT_SETUP_JS, array('jquery'));

			echo '<p>' . __('Thank you for your order, please click the button below to pay with Paddle.', 'woocommerce-gateway-paddle-inline-checkout') . '</p>';
			echo '<button class="paddle_button button alt" href="' . $this->get_pay_url($order_id) . '" target="_blank">Pay Now!</button>&nbsp;';
		}

		/**
		 * Call paddle api and get pay.paddle.com link (which redirects to paddle checkout page)
		 * @global object $woocommerce
		 * @param int $order_id
		 * @return string
		 */
		protected function get_pay_url($order_id) {
			global $woocommerce;
			$order = new WC_Order($order_id);

			// data to be send to paddle gateway
			$data = array();
			$data['vendor_id'] = $this->paddle_vendor_id;
			$data['vendor_auth_code'] = $this->paddle_api_key;
			$data['price'] = $order->get_total();
			$data['return_url'] = $this->get_return_url($order);
			$data['title'] = $this->product_name;
			$data['image_url'] = $this->product_icon;
			$data['webhook_url'] = get_bloginfo('url') . '/?' . build_query(array(
					'wc-api' => strtolower(get_class($this)),
					'order_id' => $order_id
			));
			$data['discountable'] = 0;
			$data['quantity_variable'] = 0;
			$data['customer_email'] = $order->billing_email;
			$data['customer_postcode'] = $woocommerce->customer->postcode;
			$data['customer_country'] = $woocommerce->customer->country;
			$data['is_popup'] = 'true';
			// parent_url is an url to redirect to when close button on checkout popup is clicked
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
			$apiCallResponse = wp_remote_post(self::PADDLE_ROOT_URL . self::API_GENERATE_PAY_LINK_URL, array(
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
				if ($oApiResponse && $oApiResponse->success === true) {
					return $oApiResponse->response->url;
				} else {
					echo 'Something went wrong. Check if Paddle account is properly integrated.';
					if (is_object($oApiResponse)) {
						error_log('Paddle error. Error response from API. Method: ' . __METHOD__ . ' Errors: ' . print_r($oApiResponse->error, true));
					} else {
						error_log('Paddle error. Error response from API. Method: ' . __METHOD__ . ' Response: ' . print_r($apiCallResponse, true));
					}
					exit;
				}
			}
		}

		/**
		 * Check that the given url leads to an actual file
		 */
		protected function url_valid($url) {
			$curl = curl_init($url);
			curl_setopt($curl, CURLOPT_NOBODY, true);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
			curl_exec($curl);
			$info = curl_getinfo($curl);
			curl_close($curl);
			return $info['http_code'] == 200;
		}

		/**
		 * Custom validation function to check that the product icon is usable
		 * Called magically by woocommerce
		 * If the value is invalid in some way, it fixes minor issues.
		 * e.g. converting http to https
		 *
		 * @param string the name of the field to be validated
		 * @return string the validated/corrected url
		 */
		public function validate_product_icon_field($key) {
			if (!isset($_POST[$this->plugin_id . $this->id . '_' . $key]) || empty($_POST[$this->plugin_id . $this->id . '_' . $key])) {
				return '';
			}
			$image_url = $_POST[$this->plugin_id . $this->id . '_' . $key];
			if (!$this->url_valid($image_url)) {
				$this->errors[] = 'Product Icon url is not valid';
			} else if (substr($image_url, 0, 5) != 'https') {
				//confirmed that base url is valid; now need to make it secure
				$new_url = 'https' . substr($image_url, 4);
				if (!$this->url_valid($new_url)) {
					//image server does not allow secure connection, so bounce it off our proxy
					$vendor_id = $this->getPaddleVendorId();
					$key = $this->getPaddleVendorKey();
					openssl_public_encrypt($image_url, $urlcode, $key);
					$new_url = self::IMAGE_BOUNCE_PROXY_URL . $vendor_id . '/' . str_replace(array('+', '/'), array('-', '_'), base64_encode($urlcode));
					WC_Admin_Settings::add_message("Product Icon URL has been converted to use a secure proxy");
				}
				$image_url = $new_url;
			}

			return $image_url;
		}

		/**
		 * Helper function: add the contents of this->errors to the display queue
		 */
		public function display_errors() {
			foreach ($this->errors as $k => $error) {
				WC_Admin_Settings::add_error("Unable to save due to error: " . $error);
				unset($this->errors[$k]);
			}
		}

		/**
		 * Retrieve the vendor id if set, either as an option or a post string
		 *
		 * @return string vendor id or '' if absent
		 */
		public function getPaddleVendorId() {
			if (isset($_POST[$this->plugin_id . $this->id . '_paddle_vendor_id'])) {
				return $_POST[$this->plugin_id . $this->id . '_paddle_vendor_id'];
			}
			$options = get_option($this->plugin_id . $this->id . '_settings');
			if (isset($options['paddle_vendor_id'])) {
				return $options['paddle_vendor_id'];
			}
			return '';
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

		/**
		 * Save plugin settings (options) to db
		 * Static $saved variable prevents from being called twice (because it is somehow hooked to 2 actions, don't know how)
		 * @staticvar boolean $saved
		 * @return boolean
		 */
		public function process_admin_options() {
			static $saved = false;

			if (!$saved) {
				// validate, and prepare $this->sanitized_fields to be saved
				$this->validate_settings_fields();
				if (count($this->errors) > 0) {
					$this->display_errors();
					$saved = true;
					return false;
				}

				// get old options from db
				$arrOldOptions = get_option($this->plugin_id . $this->id . '_settings');
				$oldVendorId = $arrOldOptions['paddle_vendor_id'];

				// get new options from http post
				$vendorId = $_POST[$this->plugin_id . $this->id . '_paddle_vendor_id'];
				$vendorApiKey = $_POST[$this->plugin_id . $this->id . '_paddle_api_key'];

				/**
				 * Update vendor_public_key
				 */
				$this->sanitized_fields['vendor_public_key'] = $this->getPaddleVendorKey();

				// save options to db
				update_option($this->plugin_id . $this->id . '_settings', apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->sanitized_fields));
				$this->init_settings();
				$saved = true;
			}

			return $saved;
		}

		/**
		 * Retrieves from paddle api and returns vendor_public_key
		 * @param int $vendorId
		 * @param string $vendorApiKey
		 * @return string
		 */
		protected function get_vendor_public_key($vendorId, $vendorApiKey) {
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

		/**
		 * Check if gateway is valid for use
		 * @return bool
		 */
		private function is_valid_for_use() {
			return (
				$this->is_currency_supported() &&
				$this->is_paddle_account_integrated()
				);
		}

		/**
		 * Check if WooCoommerce currency is supported by gateway
		 * @return bool
		 */
		private function is_currency_supported() {
			return in_array(get_woocommerce_currency(), $this->supported_currencies);
		}

		/**
		 * Check if gateway is integrated with paddle vendor account
		 * @return bool
		 */
		private function is_paddle_account_integrated() {
			return (!empty($this->paddle_vendor_id) && !empty($this->paddle_api_key));
		}

		/**
		 * Admin settings fields setup
		 */
		public function init_form_fields() {
			$this->settings = get_option($this->plugin_id . $this->id . '_settings', null);
			if ($this->settings['paddle_api_key'] && $this->settings['paddle_vendor_id']) {
				$connection_button = '<p style=\'color:green\'>Your paddle account has already been connected</p>' .
					'<a class=\'button-primary open_paddle_popup\'>Reconnect your Paddle Account</a>';
			} else {
				$connection_button = '<a class=\'button-primary open_paddle_popup\'>Connect your Paddle Account</a>';
			}

			$this->form_fields = array(
				'enabled' => array(
					'title' => __('Enable/Disable', 'woocommerce'),
					'type' => 'checkbox',
					'label' => __('Enable', 'woocommerce'),
					'default' => 'yes'
				),
				'title' => array(
					'title' => __('Title', 'woocommerce'),
					'type' => 'text',
					'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
					'default' => __('Paddle', 'woocommerce')
				),
				'description' => array(
					'title' => __('Customer Message', 'woocommerce'),
					'type' => 'textarea',
					'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
					'default' => __('Pay using Visa, Mastercard, Amex or PayPal via Paddle', 'woocommerce')
				),
				'paddle_showlink' => array(
					'title' => 'Vendor Account',
					'content' => $connection_button . '<br /><p class = "description"><a href="#!" id=\'toggleVendorAccountEntry\'>Click here to enter your account details manually</a></p>',
					'type' => 'raw',
					'default' => ''
				),
				'paddle_vendor_id' => array(
					'title' => __('Paddle Vendor ID', 'woocommerce'),
					'type' => 'text',
					'description' => __('<a href="#" class="open_paddle_popup">Click here to integrate Paddle account.</a>', 'woocommerce'),
					'default' => '',
					'row_attributes' => array('style' => 'display:none')
				),
				'paddle_api_key' => array(
					'title' => __('Paddle API Key', 'woocommerce'),
					'type' => 'textarea',
					'description' => __('<a href="#" class="open_paddle_popup">Click here to integrate Paddle account.</a>', 'woocommerce'),
					'default' => '',
					'row_attributes' => array('style' => 'display:none')
				),
				'product_name' => array(
					'title' => __('Product Name'),
					'description' => __('The name of the product to use in the paddle checkout'),
					'type' => 'text',
					'default' => get_bloginfo('name') . ' Checkout'
				),
				'product_icon' => array(
					'title' => __('Product Icon'),
					'description' => __('The url of the icon to show next to the product name during checkout'),
					'type' => 'text',
					'default' => 'https://s3.amazonaws.com/paddle/default/default_product_icon.png'
				)
			);
		}

		/**
		 * Custom html generating method for inserting raw HTML in the output
		 * Called magically by woocommerce based on the type field in $this->form_fields
		 */
		public function generate_raw_html($key, $data) {
			$defaults = array(
				'title' => '',
				'disabled' => false,
				'type' => 'raw',
				'content' => '',
				'desc_tip' => false,
				'label' => $this->plugin_id . $this->id . '_' . $key
			);

			$data = wp_parse_args($data, $defaults);

			ob_start();
			?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr($data['label']); ?>"><?php echo wp_kses_post($data['title']); ?></label>
				</th>
				<td class="forminp">
					<fieldset>
						<?php echo $data['content']; ?>
					</fieldset>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}

		/**
		 * Extend woocommerce function to add our custom options
		 */
		public function generate_textarea_html($key, $data) {
			$row_html = parent::generate_textarea_html($key, $data);
			return $this->apply_custom_row_attributes($row_html, $data);
		}

		/**
		 * Extend woocommerce function to add our custom options
		 */
		public function generate_text_html($key, $data) {
			$row_html = parent::generate_text_html($key, $data);
			return $this->apply_custom_row_attributes($row_html, $data);
		}

		/**
		 * Custom function to allow adding our own attributes to the admin table rows
		 * Called by the above generate_X_html methods
		 */
		public function apply_custom_row_attributes($row_html, $data) {
			$row_html = trim($row_html);
			//$this->get_custom_attribute_html
			if (substr($row_html, 0, 3) != '<tr') {
				throw new Exception("Invalid row html generated");
			}
			if (!isset($data['row_attributes'])) {
				return $row_html;
			}
			$attributes = $data['row_attributes'];
			array_walk($attributes, function(&$value, $key) {
				$value = esc_attr($key) . '="' . esc_attr($value) . '"';
			});
			$attributes = implode(' ', $attributes);
			$row_html = '<tr ' . $attributes . ' ' . substr($row_html, 3);
			return $row_html;
		}

		/**
		 * Admin settings page
		 * Show settings only if WooCommerce currency is supported by paddle
		 */
		public function admin_options() {
			?><h3><?php _e('Paddle.com Payment Gateway Setup', 'woocommerce'); ?></h3>
			<div class="paddle updated below-h2">
				<p class="main"><strong>Get started with Paddle Checkout</strong></p>
				<span>Paddle provides a simple way to take payments for digital products on your woocommerce store</span>
				<p><a class="button button-primary" target="_blank" href="<?php echo self::SIGNUP_LINK; ?>">Sign Up For Free</a></p>
			</div>

			<?php
			if ($this->is_currency_supported()) :

				$integrationUrl = self::PADDLE_ROOT_URL . self::INTEGRATE_URL . '?' . http_build_query(array(
						'app_name' => 'WooCommerce Paddle Payment Gateway',
						'app_description' => 'WooCommerce Paddle Payment Gateway Plugin. Site name: ' . get_bloginfo('name'),
						'app_icon' => plugins_url('images/woo.png', __FILE__)
				));
				?>

				<script type='text/javascript'>
					jQuery(document).ready(function() {
						jQuery('.open_paddle_popup').click(function(event) {
							// don't reload admin page when popup is created
							event.preventDefault();

							// open paddle integration popup
							window.open('<?= $integrationUrl ?>', 'mywindow', 'location=no,status=0,scrollbars=0,width=800,height=600');

							// handle message sent from popup
							window.addEventListener('message', function(e) {
								var arrData = e.data.split(" ");
								jQuery('#woocommerce_wcPaddlePaymentGateway_paddle_vendor_id').val(arrData[0]);
								jQuery('#woocommerce_wcPaddlePaymentGateway_paddle_api_key').val(arrData[1]);
							});
						});
					});
				</script>

				<table class="form-table">
					<?php
					// Generate the HTML For the settings form.
					$this->generate_settings_html();
					?>
				</table><!--/.form-table-->

			<?php elseif (!$this->is_currency_supported()) : ?>

				<div class="inline error"><p><strong><?php _e('Gateway Disabled', 'woocommerce'); ?></strong>: <?php
							_e('Paddle does not support your store currency.', 'woocommerce');
							echo "<br />Your store currency is " . get_woocommerce_currency() . ", and we only support " . implode(', ', $this->supported_currencies);
							?></p></div>

				<?php
			endif;
		}

		/**
		 * This function is called when user places order with paddle chosen as the payment method
		 * Redirect to payment page (handled by receipt_page() method)
		 * @param int $order_id
		 * @return mixed
		 */
		public function process_payment($order_id) {
			$order = new WC_Order($order_id);

			if (version_compare(WOOCOMMERCE_VERSION, '2.1', '>=')) {
				return array(
					'result' => 'success',
					'redirect' => $order->get_checkout_payment_url(true)
				);
			} else {
				return array(
					'result' => 'success',
					'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
				);
			}
		}

		/**
		 * Check webhook_url signature
		 * Returns 1 if the signature is correct, 0 if it is incorrect, and -1 on error.
		 * @return int
		 */
		protected function check_webhook_signature() {
			// log error if vendor_public_key is not set
			$vendor_public_key = $this->getPaddleVendorKey();
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
			if ($this->check_webhook_signature()) {
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

	}

}

function add_paddle_gateway_class($methods) {
	$methods[] = 'Paddle_WC_Payment_Gateway';
	return $methods;
}

function add_paddle_gateway_scripts() {
	wp_register_script('paddle-helpers', plugins_url('js/paddle-helpers.js', __FILE__), array('jquery'));
	if ('woocommerce_page_wc-settings' == get_current_screen()->id) {
		wp_enqueue_script('paddle-helpers');
	}
}

add_action('plugins_loaded', 'init_paddle_gateway_class');
add_filter('woocommerce_payment_gateways', 'add_paddle_gateway_class');
add_action('admin_enqueue_scripts', 'add_paddle_gateway_scripts');
