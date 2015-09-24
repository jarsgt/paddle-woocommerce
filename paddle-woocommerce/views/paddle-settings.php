<?php
/**
 * Admin View: Paddle Settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/*
Set by caller:
$signup_link
$integrationUrl
$supported_currencies
$active_currency
*/
$currency_supported = in_array($active_currency, $supported_currencies);
?>
<div >
	<h3>Paddle.com Payment Gateway Setup</h3>
	<div class="paddle updated below-h2">
		<p class="main"><strong>Get started with Paddle Checkout</strong></p>
		<span>Paddle provides a simple way to take payments for digital products on your woocommerce store</span>
		<p><a class="button button-primary" target="_blank" href="<?php echo $signup_link; ?>">Sign Up For Free</a></p>
	</div>

	<?php if ($currency_supported) : ?>

		<script type='text/javascript'>
			jQuery(document).ready(function() {
				jQuery('.open_paddle_popup').click(function(event) {
					// don't reload admin page when popup is created
					event.preventDefault();

					// open paddle integration popup
					window.open('<?php echo $integrationUrl; ?>', 'mywindow', 'location=no,status=0,scrollbars=0,width=800,height=600');

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
			include('paddle-settings-table.php')
			?>
		</table><!--/.form-table-->

	<?php elseif (!$currency_supported) : ?>

		<div class="inline error"><p><strong><?php _e('Gateway Disabled', 'woocommerce'); ?></strong>: <?php
					_e('Paddle does not support your store currency.', 'woocommerce');
					echo "<br />Your store currency is " . $active_currency . ", and we only support " . implode(', ', $supported_currencies);
					?></p></div>

	<?php endif; ?>
</div>
