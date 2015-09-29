<?php

class Paddle_Checkout {

	public static function add_hooks() {
		$settings = Paddle_Settings::instance();
		add_filter($settings->get('checkout_hook'), ['Paddle_Checkout', 'inject_checkout_javascript']);
		add_action( 'template_redirect', ['Paddle_Checkout', 'intercept_url_ajax']);
	}

	public static function inject_checkout_javascript($value) {
		if(wp_is_mobile()) {
			static::output_mobile_js();
		} else {
			static::output_popup_js();
		}
		return $value;
	}

	public static function output_mobile_js() {
		$order_url = get_site_url().'/'.Paddle_WC_Payment_Gateway::AJAX_URL_ORDER;
		echo <<<SCRIPT
<!-- Paddle Checkout JS -->
<script type='text/javascript'>
jQuery(document).ready(function(){
	jQuery('#checkout_buttons button[id!=paypal_submit]').click(function(event){
		event.preventDefault();
		jQuery.post(
			'$order_url',
			jQuery('form.checkout').serializeArray()
		).done(function(data){
			data = JSON.parse(data);
			if(data.result == 'success') {
				window.location.href = data.checkout_url;
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
});
</script>
SCRIPT;
	}

	public static function output_popup_js() {
		$order_url = get_site_url().'/'.Paddle_WC_Payment_Gateway::AJAX_URL_ORDER;
		$domain = rtrim(Paddle_WC_Payment_Gateway::PADDLE_CHECKOUT_ROOT_URL, '/');
		$settings = Paddle_Settings::instance();
		$css = $settings->get('button_css');
		$css = json_encode($css); // So that we can safely embed it in javascript
		echo <<<SCRIPT
<!-- Paddle Checkout CSS -->
<style type='text/css'>
#paddle-checkout-popup-background {
	width: 100%;
	height: 100%;
	z-index: 800;
	position: fixed;
	text-align: center;
	top: 0px;
	left: 0px;
	right: 0px;
	bottom: 0px;
}
#paddle-checkout-popup-holder {
	width: 100%;
	height: 100%;
	z-index: 99999;
	position: fixed;
	text-align: center;
	top: 0px;
	left: 0px;
	right: 0px;
	bottom: 0px;
}
#paddle-checkout-popup {
	width: 100%;
    height: 100%;
    display: inline-block;
    z-index: 99999;
    position: fixed;
    top: 0px;
    left: 0px;
    right: 0px;
    bottom: 0px;
}
#paddle-checkout-popup iframe {
	z-index: 99999;
	display: block;
	background-color: transparent;
	border: 0px none transparent;
	overflow-x: hidden;
	overflow-y: auto;
	visibility: visible;
	margin: 0px;
	padding: 0px;
	-webkit-tap-highlight-color: transparent;
	position: fixed;
	left: 0px;
	top: 0px;
	width: 100%;
	height: 100%;
}
</style>
<!-- Paddle Checkout JS -->
<script type='text/javascript'>
// Custom version of the spin.js library (with renamed parameters)
(function(root, factory) {

  /* CommonJS */
  if (typeof exports == 'object')  module.exports = factory()

  /* AMD module */
  else if (typeof define == 'function' && define.amd) define(factory)

  /* Browser global */
  else root.PaddleSpinner = factory()
}
(this, function() {
  "use strict";

  var prefixes = ['webkit', 'Moz', 'ms', 'O'] /* Vendor prefixes */
    , animations = {} /* Animation rules keyed by their name */
    , useCssAnimations /* Whether to use CSS animations or setTimeout */

  /**
   * Utility function to create elements. If no tag name is given,
   * a DIV is created. Optionally properties can be passed.
   */
  function createEl(tag, prop) {
    var el = document.createElement(tag || 'div')
      , n

    for(n in prop) el[n] = prop[n]
    return el
  }

  /**
   * Appends children and returns the parent.
   */
  function ins(parent /* child1, child2, ...*/) {
    for (var i=1, n=arguments.length; i<n; i++)
      parent.appendChild(arguments[i])

    return parent
  }

  /**
   * Insert a new stylesheet to hold the @keyframe or VML rules.
   */
  var sheet = (function() {
    var el = createEl('style', {type : 'text/css'})
    ins(document.getElementsByTagName('head')[0], el)
    return el.sheet || el.styleSheet
  }())

  /**
   * Creates an opacity keyframe animation rule and returns its name.
   * Since most mobile Webkits have timing issues with animation-delay,
   * we create separate rules for each line/segment.
   */
  function addAnimation(alpha, trail, i, lines) {
    var name = ['opacity', trail, ~~(alpha*100), i, lines].join('-')
      , start = 0.01 + i/lines * 100
      , z = Math.max(1 - (1-alpha) / trail * (100-start), alpha)
      , prefix = useCssAnimations.substring(0, useCssAnimations.indexOf('Animation')).toLowerCase()
      , pre = prefix && '-' + prefix + '-' || ''

    if (!animations[name]) {
      sheet.insertRule(
        '@' + pre + 'keyframes ' + name + '{' +
        '0%{opacity:' + z + '}' +
        start + '%{opacity:' + alpha + '}' +
        (start+0.01) + '%{opacity:1}' +
        (start+trail) % 100 + '%{opacity:' + alpha + '}' +
        '100%{opacity:' + z + '}' +
        '}', sheet.cssRules.length)

      animations[name] = 1
    }

    return name
  }

  /**
   * Tries various vendor prefixes and returns the first supported property.
   */
  function vendor(el, prop) {
    var s = el.style
      , pp
      , i

    prop = prop.charAt(0).toUpperCase() + prop.slice(1)
    for(i=0; i<prefixes.length; i++) {
      pp = prefixes[i]+prop
      if(s[pp] !== undefined) return pp
    }
    if(s[prop] !== undefined) return prop
  }

  /**
   * Sets multiple style properties at once.
   */
  function css(el, prop) {
    for (var n in prop)
      el.style[vendor(el, n)||n] = prop[n]

    return el
  }

  /**
   * Fills in default values.
   */
  function merge(obj) {
    for (var i=1; i < arguments.length; i++) {
      var def = arguments[i]
      for (var n in def)
        if (obj[n] === undefined) obj[n] = def[n]
    }
    return obj
  }

  /**
   * Returns the absolute page-offset of the given element.
   */
  function pos(el) {
    var o = { x:el.offsetLeft, y:el.offsetTop }
    while((el = el.offsetParent))
      o.x+=el.offsetLeft, o.y+=el.offsetTop

    return o
  }

  /**
   * Returns the line color from the given string or array.
   */
  function getColor(color, idx) {
    return typeof color == 'string' ? color : color[idx % color.length]
  }

  // Built-in defaults

  var defaults = {
    lines: 12,            // The number of lines to draw
    length: 7,            // The length of each line
    width: 5,             // The line thickness
    radius: 10,           // The radius of the inner circle
    rotate: 0,            // Rotation offset
    corners: 1,           // Roundness (0..1)
    color: '#000',        // #rgb or #rrggbb
    direction: 1,         // 1: clockwise, -1: counterclockwise
    speed: 1,             // Rounds per second
    trail: 100,           // Afterglow percentage
    opacity: 1/4,         // Opacity of the lines
    fps: 20,              // Frames per second when using setTimeout()
    zIndex: 2e9,          // Use a high z-index by default
    className: 'PaddleSpinner', // CSS class to assign to the element
    top: '50%',           // center vertically
    left: '50%',          // center horizontally
    position: 'fixed'  // element position
  }

  /** The constructor */
  function PaddleSpinner(o) {
    this.opts = merge(o || {}, PaddleSpinner.defaults, defaults)
  }

  // Global defaults that override the built-ins:
  PaddleSpinner.defaults = {}

  merge(PaddleSpinner.prototype, {

    /**
     * Adds the PaddleSpinner to the given target element. If this instance is already
     * spinning, it is automatically removed from its previous target b calling
     * stop() internally.
     */
    spin: function(target) {
      this.stop()

      var self = this
        , o = self.opts
        , el = self.el = css(createEl(0, {className: o.className}), {position: o.position, width: 0, zIndex: o.zIndex})
        , mid = o.radius+o.length+o.width

      css(el, {
        left: o.left,
        top: o.top
      })
        
      if (target) {
        target.insertBefore(el, target.firstChild||null)
      }

      el.setAttribute('role', 'progressbar')
      self.lines(el, self.opts)

      if (!useCssAnimations) {
        // No CSS animation support, use setTimeout() instead
        var i = 0
          , start = (o.lines - 1) * (1 - o.direction) / 2
          , alpha
          , fps = o.fps
          , f = fps/o.speed
          , ostep = (1-o.opacity) / (f*o.trail / 100)
          , astep = f/o.lines

        ;(function anim() {
          i++;
          for (var j = 0; j < o.lines; j++) {
            alpha = Math.max(1 - (i + (o.lines - j) * astep) % f * ostep, o.opacity)

            self.opacity(el, j * o.direction + start, alpha, o)
          }
          self.timeout = self.el && setTimeout(anim, ~~(1000/fps))
        })()
      }
      return self
    },

    /**
     * Stops and removes the PaddleSpinner.
     */
    stop: function() {
      var el = this.el
      if (el) {
        clearTimeout(this.timeout)
        if (el.parentNode) el.parentNode.removeChild(el)
        this.el = undefined
      }
      return this
    },

    /**
     * Internal method that draws the individual lines. Will be overwritten
     * in VML fallback mode below.
     */
    lines: function(el, o) {
      var i = 0
        , start = (o.lines - 1) * (1 - o.direction) / 2
        , seg

      function fill(color, shadow) {
        return css(createEl(), {
          position: 'absolute',
          width: (o.length+o.width) + 'px',
          height: o.width + 'px',
          background: color,
          boxShadow: shadow,
          transformOrigin: 'left',
          transform: 'rotate(' + ~~(360/o.lines*i+o.rotate) + 'deg) translate(' + o.radius+'px' +',0)',
          borderRadius: (o.corners * o.width>>1) + 'px'
        })
      }

      for (; i < o.lines; i++) {
        seg = css(createEl(), {
          position: 'absolute',
          top: 1+~(o.width/2) + 'px',
          transform: o.hwaccel ? 'translate3d(0,0,0)' : '',
          opacity: o.opacity,
          animation: useCssAnimations && addAnimation(o.opacity, o.trail, start + i * o.direction, o.lines) + ' ' + 1/o.speed + 's linear infinite'
        })

        if (o.shadow) ins(seg, css(fill('#FFF', '0 0 1px ' + '#FFF'), {top: 1+'px'}))
        ins(el, ins(seg, fill(getColor(o.color, i), '0 0 1px rgba(255,255,255,.1)')))
      }
      return el
    },

    /**
     * Internal method that adjusts the opacity of a single line.
     * Will be overwritten in VML fallback mode below.
     */
    opacity: function(el, i, val) {
      if (i < el.childNodes.length) el.childNodes[i].style.opacity = val
    }

  })


  function initVML() {

    /* Utility function to create a VML tag */
    function vml(tag, attr) {
      return createEl('<' + tag + ' xmlns="urn:schemas-microsoft.com:vml" class="spin-vml">', attr)
    }

    // No CSS transforms but VML support, add a CSS rule for VML elements:
    sheet.addRule('.spin-vml', 'behavior:url(#default#VML)')

    PaddleSpinner.prototype.lines = function(el, o) {
      var r = o.length+o.width
        , s = 2*r

      function grp() {
        return css(
          vml('group', {
            coordsize: s + ' ' + s,
            coordorigin: -r + ' ' + -r
          }),
          { width: s, height: s }
        )
      }

      var margin = -(o.width+o.length)*2 + 'px'
        , g = css(grp(), {position: 'absolute', top: margin, left: margin})
        , i

      function seg(i, dx, filter) {
        ins(g,
          ins(css(grp(), {rotation: 360 / o.lines * i + 'deg', left: ~~dx}),
            ins(css(vml('roundrect', {arcsize: o.corners}), {
                width: r,
                height: o.width,
                left: o.radius,
                top: -o.width>>1,
                filter: filter
              }),
              vml('fill', {color: getColor(o.color, i), opacity: o.opacity}),
              vml('stroke', {opacity: 0}) // transparent stroke to fix color bleeding upon opacity change
            )
          )
        )
      }

      if (o.shadow)
        for (i = 1; i <= o.lines; i++)
          seg(i, -2, 'progid:DXImageTransform.Microsoft.Blur(pixelradius=1,makeshadow=1,shadowopacity=.2)')

      for (i = 1; i <= o.lines; i++) seg(i)
      return ins(el, g)
    }

    PaddleSpinner.prototype.opacity = function(el, i, val, o) {
      var c = el.firstChild
      o = o.shadow && o.lines || 0
      if (c && i+o < c.childNodes.length) {
        c = c.childNodes[i+o]; c = c && c.firstChild; c = c && c.firstChild
        if (c) c.opacity = val
      }
    }
  }

  var probe = css(createEl('group'), {behavior: 'url(#default#VML)'})

  if (!vendor(probe, 'transform') && probe.adj) initVML()
  else useCssAnimations = vendor(probe, 'animation')

  return PaddleSpinner

}));

// Spinner Options/ Style
var opts = {
  lines: 9, // The number of lines to draw
  length: 0, // The length of each line
  width: 7, // The line thickness
  radius: 16, // The radius of the inner circle
  corners: 1, // Corner roundness (0..1)
  rotate: 0, // The rotation offset
  direction: 1, // 1: clockwise, -1: counterclockwise
  color: '#000', // #rgb or #rrggbb or array of colors
  speed: 1.2, // Rounds per second
  trail: 60, // Afterglow percentage
  shadow: true, // Whether to render a shadow
  hwaccel: false, // Whether to use hardware acceleration
  className: 'spinner', // The CSS class to assign to the spinner
  zIndex: 2e9, // The z-index (defaults to 2000000000)
  top: '50%', // Top position relative to parent
  left: '50%' // Left position relative to parent
};

jQuery(document).ready(function(){
	jQuery('body').append('<div id="paddleLoader" style="display:none;"></div>');
	var target = document.getElementById('paddleLoader');
	var spinner = new PaddleSpinner(opts).spin(target);
	
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
				)
		);
	jQuery('#paddle-checkout-popup-holder').click(closePopup);
	function closePopup(){
		jQuery('#paddle-checkout-popup-background').hide();
		jQuery('#paddle-checkout-popup-holder').hide();
		jQuery($css).closest('form').removeClass( 'processing' ).unblock();
	}
	jQuery($css).click(function(event){
		event.preventDefault();

		var form = jQuery( event.target ).closest('form');

		if ( form.is( '.processing' ) ) return false;

		form.addClass( 'processing' ).block({
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6
			}
		});

		jQuery('#paddleLoader').fadeIn(150);
		jQuery.post(
			'$order_url',
			jQuery('form.checkout').serializeArray()
		).done(function(data){
			jQuery( 'body' ).trigger( 'update_checkout' );
			data = JSON.parse(data);
			if(data.result == 'success') {
				jQuery('#paddleLoader').fadeOut(100);
				jQuery('#paddle-checkout-popup-background').show();
				jQuery('#paddle-checkout-popup-holder').show();
				jQuery('#paddle-checkout-popup iframe').remove();
				jQuery('#paddle-checkout-popup').append(
					jQuery('<iframe>')
						.attr('src', data.checkout_url)
						.attr('frameborder', 0)
						.attr('allowtransparency', 'true')
						.css({opacity:0})
						.load(function() {
							jQuery('#paddleLoader').fadeOut(100);
							jQuery('#paddle-checkout-popup iframe').animate({opacity:1});
						})
				);
			} else {
				jQuery('#paddleLoader').hide();
				form.removeClass( 'processing' ).unblock();
			}
		});
	});
	window.addEventListener("message", function(event) {
		if(event.origin.indexOf('$domain') == -1) return;
		switch(event.data.action) {
			case 'complete':

				break;
			case 'close':
				closePopup();
				break;
		}
	}, false);
});
</script>
SCRIPT;
	}

	public static function intercept_url_ajax() {
		$page_url = $_SERVER['REQUEST_URI'];

		if(strpos($page_url, Paddle_WC_Payment_Gateway::AJAX_URL_ORDER) !== false) {
			http_response_code(200);
			// Intercept before attempting to take payment
			// Clear the notices, so that we ignore errors that have now been fixed
			wc_clear_notices();
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
		$data['is_popup'] = wp_is_mobile() ? 'false' : 'true';
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
