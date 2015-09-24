<?php
$settings = new Paddle_Settings();

?>
<form id='mainform' method='POST'>
	<table class="form-table"><tbody>
		<tr valign="top">
				<th class="titledesc" scope="row">
					<label for="woocommerce_wcPaddlePaymentGateway_paddle_showlink">Vendor Account</label>
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
					<input type="text" placeholder="" value="524" style="" id="woocommerce_wcPaddlePaymentGateway_paddle_vendor_id" name="woocommerce_wcPaddlePaymentGateway_paddle_vendor_id" class="input-text regular-input ">
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
					<textarea placeholder="" style="" id="woocommerce_wcPaddlePaymentGateway_paddle_api_key" name="woocommerce_wcPaddlePaymentGateway_paddle_api_key" type="textarea" class="input-text wide-input " cols="20" rows="3">667:c4ca4238a0b923820dcc509a6f75849b421826508fe4fc49c37f453dc09d8793</textarea>
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
					<input type="text" placeholder="" value="<?php echo 'todo'; ?>" style="" id="woocommerce_wcPaddlePaymentGateway_product_icon" name="woocommerce_wcPaddlePaymentGateway_product_icon" class="input-text regular-input ">
					<p class="description">The url of the icon to show next to the product name during checkout</p>
				</fieldset>
			</td>
		</tr>
	</tbody></table>
	<input type='submit' value='Save Settings' />
</form>
