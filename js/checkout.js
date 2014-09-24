function paddleCheckout_init() {
	if(navigator.userAgent.indexOf('Safari') != -1 && navigator.userAgent.indexOf('Chrome') == -1) {
		var is_popup = false;
	} else {
		var is_popup = true;
	}

	jQuery('.paddle_button').each(function() {
		if(jQuery(this).data('theme') != 'none') {
			switch(jQuery(this).data('theme')) {
				case undefined:
				case '':
				case 'default':
				case 'green':
					jQuery(this).addClass('paddle_styled_button').addClass('green');
					break;
				case 'light':
					jQuery(this).addClass('paddle_styled_button').addClass('light');
					break;
				case 'dark':
					jQuery(this).addClass('paddle_styled_button').addClass('dark');
					break;
			}

			switch(jQuery(this).data('size')) {
				case undefined:
				case 'normal':
					jQuery(this).addClass('normal');
					break;
				case 'large':
					jQuery(this).addClass('large');
					break;
			}
		}
	});

	// Unbind any click events from checkout buttons before we bind our click handler (allows for multiple checkout.js instances on a page)
	jQuery('.paddle_button').unbind('click').click(function() {
		var Paddle = {
			product: jQuery(this).data('product'),
			success: jQuery(this).data('success'),
			queryString: {
				popup: is_popup,
				passthrough: jQuery(this).data('passthrough'),
				parentURL: window.location.href
			}
		};

		// Parse the URL of this page, and update it so it is the URL without any # parameters.
		Paddle.queryString.parentURL = Paddle.queryString.parentURL.replace(window.location.hash, '');
		Paddle.queryString.parentURL = Paddle.queryString.parentURL.replace('#', '');

		// If a _checkoutComplete or _checkoutClose URL var is already set, remove it.
		if(window.location.hash.substring(1) == '_paddle_checkoutComplete') { window.location.hash = ''; }
		if(window.location.hash.substring(1) == '_paddle_checkoutClose') { window.location.hash = ''; }

		// If 'data-success' is empty, we just use the 'default' checkout success.
		// Otherwise, we're going to redirect to the URL set in 'data-success'
		if(!Paddle.success) {
			Paddle.queryString.popupCompleted = 'default';
		} else {
			Paddle.queryString.popupCompleted = 'js';
		}

		if(Paddle.product) {
			Paddle.checkout = 'https://pay.paddle.com/checkout/'+Paddle.product+'/?';
			Paddle.checkout += jQuery.param(Paddle.queryString);
		} else {
			Paddle.checkout = jQuery(".paddle_button").attr('href') + "?popup=" + is_popup;
		}
		// Remove any other instances of checkout, then show preloader and initiate frame.
		if(is_popup) {
			// If we're a "popup" checkout, then load a frame.
			if(!jQuery('.paddle_frame').size()) {
				jQuery('.paddle_checkout_frame').remove();
				jQuery('body').append('<div class="paddle_frame paddle_frame_loader"><div class="paddle_spinner"><img src="https://paddle.s3.amazonaws.com/checkout/assets/loader.gif" alt="Loading..." border="0" /></div></div><iframe id="pf_'+Paddle.product+'" class="paddle_frame paddle_checkout_frame" frameborder="0" allowtransparency="true" style="z-index: 99999; display: block; background-color: transparent; border: 0px none transparent; overflow-x: hidden; overflow-y: auto; visibility: visible; margin: 0px; padding: 0px; -webkit-tap-highlight-color: transparent; position: fixed; left: 0px; top: 0px; width: 100%; height: 100%;" src="'+Paddle.checkout+'"></iframe>');
				// Hide the frame until it's loaded, then fade in. (Stops white iframe flash)
				jQuery('#pf_'+Paddle.product+'').hide();
				jQuery('#pf_'+Paddle.product+'').load(function() {
					jQuery('#pf_'+Paddle.product+'').fadeIn(250);
				});
			} else {
				jQuery('.paddle_frame').hide().fadeIn(350);
			}

		} else {
			// If we're not a "popup" checkout (eg. safari) then just visit the checkout URL.
			window.location.href = Paddle.checkout;
		}

		// Listen for URL hash change.
		jQuery(window).on('hashchange', function() {
			// Set the value of hash to the hash parameter without the '#'
			var hash = window.location.hash;
			hash = hash.replace('#', '');
			hash = hash.replace('!', '');

			if(hash == '_paddle_checkoutComplete') {
				// Redirect to success if that's our desired action. Otherwise do nothing.
				if(Paddle.queryString.popupCompleted == 'js') {
					if(Paddle.success) {
						// Fade out & remove the elements from the page on _checkoutComplete call (make the redirect look nicer/ transition)
						jQuery('.paddle_frame, .paddle_frame_loader').fadeOut(350, function() {
							jQuery('.paddle_frame, .paddle_frame_loader').remove();
						});

						// Redirect to 'success' URL
						window.location.href=Paddle.success;
					}
				}
			} else if(hash == '_paddle_checkoutClose') {
				// Fade out & remove the elements from the page on _checkoutClose call
				jQuery('.paddle_frame, .paddle_frame_loader').fadeOut(350, function() {
					jQuery('.paddle_frame, .paddle_frame_loader').remove();
				});
			}

			// Clear the hash value once we've used it
			window.location.hash = '!';
		});

		// Listen for a call to close the popup
		window.addEventListener("message", function(event) {
			if(event.data == "closeme") {
				sessionStorage.setItem('reloaded', true);
				jQuery('.paddle_frame').hide();
				jQuery('.paddle_spinner').hide();//don't show this if we fade back in
			}
		}, false);
	});
}

jQuery(document).ready(function() {
	// Load stylesheet for button theming.
	jQuery('head').append('<link rel="stylesheet" type="text/css" href="https://paddle.s3.amazonaws.com/checkout/checkout.css">');

	// Preload static elements we're going to use when popup is opened.
	jQuery('body').append('<div class="paddle_preload" style="display:none;"><img src="https://paddle.s3.amazonaws.com/checkout/assets/bg.png" border="0" width="1" height="1" style="display:none;" /><img src="https://paddle.s3.amazonaws.com/checkout/assets/loader.gif" border="0" width="1" height="1" style="display:none;" /></div>');

	// Loop through checkout buttons on the page and style them according to their 'data' attributes.
	paddleCheckout_init();

	//trigger the popup or redirect
	if(!sessionStorage.getItem('reloaded')) {
		jQuery('.paddle_button').click();
	}
});
