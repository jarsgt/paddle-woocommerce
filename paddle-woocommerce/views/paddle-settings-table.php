<?php
$settings = Paddle_Settings::instance();

?>
<form id='mainform' method='POST'>
	<?php if($settings->settings_saved): ?>
	<div class='updated'>
		Settings Saved!
	</div>
	<?php endif; ?>
	<table class="form-table"><tbody>
		<tr valign="top">
				<th class="titledesc" scope="row">
					Vendor Account
				</th>
				<td class="forminp">
					<fieldset>
					<?php if($settings->is_connected): ?>
						<p style="color:green">Your paddle account has already been connected</p>
						<a class="button-primary open_paddle_popup">Reconnect your Paddle Account</a><br>
					<?php else: ?>
						<a class="button-primary open_paddle_popup">Connect your Paddle Account</a><br>
					<?php endif; ?>
						<p class="description"><a id="toggleVendorAccountEntry" href="#!">Click here to enter your account details manually</a></p>
					</fieldset>
				</td>
			</tr>
			<tr valign="top" style="display:none">
			<th class="titledesc" scope="row">
				<label for="woocommerce_wcPaddlePaymentGateway_paddle_vendor_id">Paddle Vendor ID</label>
							</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span>Paddle Vendor ID</span></legend>
					<input type="text" placeholder="" value="<?php echo $settings->get('paddle_vendor_id'); ?>" style="" id="woocommerce_wcPaddlePaymentGateway_paddle_vendor_id" name="woocommerce_wcPaddlePaymentGateway_paddle_vendor_id" class="input-text regular-input ">
					<p class="description"><a class="open_paddle_popup" href="#">Click here to integrate Paddle account.</a></p>
				</fieldset>
			</td>
		</tr><tr valign="top" style="display:none">
			<th class="titledesc" scope="row">
				<label for="woocommerce_wcPaddlePaymentGateway_paddle_api_key">Paddle API Key</label>
							</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span>Paddle API Key</span></legend>
					<textarea placeholder="" style="" id="woocommerce_wcPaddlePaymentGateway_paddle_api_key" name="woocommerce_wcPaddlePaymentGateway_paddle_api_key" type="textarea" class="input-text wide-input " cols="20" rows="3"><?php echo $settings->get('paddle_api_key'); ?></textarea>
					<p class="description"><a class="open_paddle_popup" href="#">Click here to integrate Paddle account.</a></p>
				</fieldset>
			</td>
		</tr><tr valign="top">
			<th class="titledesc" scope="row">
				<label for="woocommerce_wcPaddlePaymentGateway_product_icon">Product Icon</label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span>Product Icon</span></legend>
					<input type="text" placeholder="" value="<?php echo $settings->get('product_icon'); ?>" style="" id="woocommerce_wcPaddlePaymentGateway_product_icon" name="woocommerce_wcPaddlePaymentGateway_product_icon" class="input-text regular-input ">
					<p class="description">The url of the icon to show next to the product name during checkout</p>
				</fieldset>
			</td>
		</tr>
	</tbody></table>
	<input type='submit' class='button button-primary' value='Save Settings' />
</form>
