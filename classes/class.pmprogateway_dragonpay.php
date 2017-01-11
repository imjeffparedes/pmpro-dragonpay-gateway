<?php
	//load classes init method
	add_action('init', array('PMProGateway_dragonpay', 'init'));

	/**
	 * PMProGateway_gatewayname Class
	 *
	 * Handles dragonpay integration.
	 *
	 */
	class PMProGateway_dragonpay extends PMProGateway
	{
		function PMProGateway($gateway = NULL)
		{
			$this->gateway = $gateway;
			return $this->gateway;
		}										

		/**
		 * Run on WP init
		 *
		 * @since 1.8
		 */
		static function init()
		{
			//make sure DragonPay is a gateway option
			add_filter('pmpro_gateways', array('PMProGateway_dragonpay', 'pmpro_gateways'));

			
			//add fields to payment settings
			add_filter('pmpro_payment_options', array('PMProGateway_dragonpay', 'pmpro_payment_options'));
			add_filter('pmpro_payment_option_fields', array('PMProGateway_dragonpay', 'pmpro_payment_option_fields'), 10, 2);

			//code to add at checkout
			$gateway = pmpro_getGateway();
			if($gateway == "dragonpay")
			{
				add_filter('pmpro_include_billing_address_fields', '__return_false');
				add_filter('pmpro_include_payment_information_fields', '__return_false');
				add_filter('pmpro_required_billing_fields', array('PMProGateway_dragonpay', 'pmpro_required_billing_fields'));

				add_action('pmpro_checkout_before_processing', array('PMProGateway_dragonpay', 'pmpro_checkout_before_processing'));
				add_filter('pmpro_checkout_default_submit_button', array('PMProGateway_dragonpay', 'pmpro_checkout_default_submit_button'));
				add_action('pmpro_checkout_after_form', array('PMProGateway_dragonpay', 'pmpro_checkout_after_form'));
				add_action('pmpro_checkout_after_user_fields', 'pmpro_checkout_after_user_fields');
				//added on v2
				add_filter('pmpro_checkout_before_change_membership_level', array('PMProGateway_dragonpay', 'pmpro_checkout_before_change_membership_level'), 10, 2);

				
			}
		}
    


		/**
		 * Make sure dragonpay is in the gateways list
		 *
		 * @since 1.8
		 */
		static function pmpro_gateways($gateways)
		{
			if(empty($gateways['dragonpay']))
				$gateways['dragonpay'] = __('DragonPay', 'pmpro');

			return $gateways;
		}

		/**
		 * Get a list of payment options that the dragonpay gateway needs/supports.
		 *
		 * @since 1.8
		 */
		static function getGatewayOptions()
		{
			$options = array(
				'dragonpay_merchant_id',
				'dragonpay_secret_key',
				'sslseal',
				'nuclear_HTTPS',
				'gateway_environment',
				'currency',
				'use_ssl',
				'tax_state',
				'tax_rate',
				'dragonpay_instructions'
			);

			return $options;
		}

		/**
		 * Set payment options for payment settings page.
		 *
		 * @since 1.8
		 */
		static function pmpro_payment_options($options)
		{
			//get dragonpay options
			$dragonpay_options = PMProGateway_dragonpay::getGatewayOptions();

			//merge with others.
			$options = array_merge($dragonpay_options, $options);

			return $options;
		}

		/**
		 * Display fields for dragonpay options.
		 *
		 * @since 1.8
		 */
		static function pmpro_payment_option_fields($values, $gateway)
		{
		?>
			<tr class="pmpro_settings_divider gateway gateway_dragonpay" <?php if($gateway != "dragonpay") { ?>style="display: none;"<?php } ?>>
				<td colspan="2">
					<?php _e('DragonPay Settings', 'pmpro'); ?>
				</td>
			</tr>
			<tr class="gateway gateway_dragonpay" <?php if($gateway != "dragonpay") { ?>style="display: none;"<?php } ?>>
				<?php // dragonpay custom pamyment settings here ?>
				<th scope="row" valign="top">
					<label for="dragonpay_merchant_id"><?php _e('DragonPay Merchant ID', 'pmpro');?>:</label>
				</th>
				<td>
					<input id="dragonpay_merchant_id" name="dragonpay_merchant_id" value="<?php echo esc_attr($values['dragonpay_merchant_id']); ?>" />
				</td>
			</tr>
			<tr class="gateway gateway_dragonpay" <?php if($gateway != "dragonpay") { ?>style="display: none;"<?php } ?>>
				<?php // dragonpay custom pamyment settings here ?>
				<th scope="row" valign="top">
					<label for="dragonpay_secret_key"><?php _e('DragonPay Secret Key', 'pmpro');?>:</label>
				</th>
				<td>
					<input id="dragonpay_secret_key" name="dragonpay_secret_key" value="<?php echo esc_attr($values['dragonpay_secret_key']); ?>" />
			
				</td>
			</tr>
			<tr class="gateway gateway_dragonpay" <?php if($gateway != "dragonpay") { ?>style="display: none;"<?php } ?>>
				<th scope="row" valign="top">
					<label for="dragonpay_instructions"><?php _e('DragonPay Instructions', 'pmpro');?></label>					
				</th>
				<td>
					<textarea id="dragonpay_instructions" name="dragonpay_instructions" rows="3" cols="80"><?php echo esc_textarea($values['dragonpay_instructions'])?></textarea>
					<p><small><?php _e('Who to address the checkout to. Where to mail it. Shown on checkout, confirmation, and invoice pages.', 'pmpro');?></small></p>
				</td>
			</tr>	
		<?php
		}
		/**
		 * Remove required billing fields
		 *
		 * @since 1.8
		 */
		static function pmpro_required_billing_fields($fields)
		{

			unset($fields['bfirstname']);
			unset($fields['blastname']);
			unset($fields['baddress1']);
			unset($fields['bcity']);
			unset($fields['bstate']);
			unset($fields['bzipcode']);
			unset($fields['bphone']);
			unset($fields['bemail']);
			unset($fields['bcountry']);
			unset($fields['CardType']);
			unset($fields['AccountNumber']);
			unset($fields['ExpirationMonth']);
			unset($fields['ExpirationYear']);
			unset($fields['CVV']);

			return $fields;
		}

		/**
		 * Swap in our submit buttons.
		 *
		 * @since 1.8
		 */
		static function pmpro_checkout_default_submit_button($show)
		{
			global $gateway, $pmpro_requirebilling;

			//show our submit buttons
			?>
			<span id="pmpro_dragonpay_checkout" <?php if(($gateway != "dragonpay") || !$pmpro_requirebilling) { ?>style="display: none;"<?php } ?>>
				<input type="hidden" name="submit-checkout" value="1" />
				<input type="image" class="pmpro_btn-submit-checkout" value="Check Out with DragonPay &raquo;" width="150px" src="http://www.upcatreview.com/dragonpay/logo_dragonpay.png" />
			</span>

			<span id="pmpro_submit_span" style="display: none;">
				<input type="hidden" name="submit-checkout" value="1" />
				<input type="submit" class="pmpro_btn pmpro_btn-submit-checkout" value="<?php if($pmpro_requirebilling) { _e('Submit and Check Out', 'pmpro'); } else { _e('Submit and Confirm', 'pmpro');}?> &raquo;" />
			</span>
		
			<?php

			//don't show the default
			return false;
		}

		/**
		 * Instead of change membership levels, send users to DragonPay to pay.
		 *
		 * @since 1.8
		 */
		static function pmpro_checkout_before_change_membership_level($user_id, $morder)
		{
			global $discount_code_id, $wpdb;
						
			//if no order, no need to pay
			if(empty($morder))
				return;
							
			$morder->user_id = $user_id;				
			$morder->saveOrder();
			
			//save discount code use
			if(!empty($discount_code_id))
				$wpdb->query("INSERT INTO $wpdb->pmpro_discount_codes_uses (code_id, user_id, order_id, timestamp) VALUES('" . $discount_code_id . "', '" . $user_id . "', '" . $morder->id . "', now())");	
			
			do_action("pmpro_before_send_to_paypal_standard", $user_id, $morder);
			
			$morder->Gateway->sendToDragonPay($morder);
		}

		/**
		 * Review and Confirmation code.
		 *
		 * @since 1.8
		 */
		static function pmpro_checkout_confirmed($pmpro_confirmed)
		{
			global $pmpro_msg, $pmpro_msgt, $pmpro_level, $current_user;

			//check their last order
			$order = new MemberOrder();
			$order->getLastMemberOrder($current_user->ID, NULL);		//NULL here means any status

			if(!$order or empty($order) ){


				$pmpro_msg = $order->error;
				$pmpro_msgt = "pmpro_error";

				$pmpro_confirmed=false;
				return false;
			}
			if($this->confirm($order))
			{
				$pmpro_confirmed = true;
			}
			else
			{

				$pmpro_msg = $order->error;
				$pmpro_msgt = "pmpro_error";
			}

			return $pmpro_confirmed;

		}

		/**
		 * Scripts for checkout page.
		 *
		 * @since 1.8
		 */
		static function pmpro_checkout_after_form()
		{
		?>
		<script>
			<!--
			//choosing payment method
			jQuery('input[name=gateway]').click(function() {
				if(jQuery(this).val() == 'dragonpay')
				{

					jQuery('#pmpro_billing_address_fields').hide();
					jQuery('#pmpro_payment_information_fields').hide();
					jQuery('#pmpro_submit_span').hide();
					jQuery('#pmpro_dragonpay_checkout').show();

					jQuery('#pmpro_paypalexpress_checkout').hide();
				}
				else
				{

					jQuery('#pmpro_dragonpay_checkout').hide();
					jQuery('#pmpro_paypalexpress_checkout').hide();
					jQuery('#pmpro_billing_address_fields').show();
					jQuery('#pmpro_payment_information_fields').show();
					jQuery('#pmpro_submit_span').show();
				}
			});

			//select the radio button if the label is clicked on
			jQuery('a.pmpro_radio').click(function() {
				jQuery(this).prev().click();
			});
			-->
		</script>
		<?php
		}


		/**
		 * Show instructions on checkout page
		 * Moved here from pages/checkout.php
		 * @since 1.8.9.3
		 */
		static function pmpro_checkout_after_user_fields() {
			global $gateway;
			global $pmpro_level;

			if($gateway == "dragonpay" && !pmpro_isLevelFree($pmpro_level)) {
				$instructions = pmpro_getOption("dragonpay_instructions");
				echo '<div class="pmpro_dragonpay_instructions">' . wpautop($dragonpay_instructions) . '</div>';
			}
		}


		/**
		 * Process checkout.
		 *
		 */
		function process(&$order)
		{
			if(empty($order->code))
				$order->code = $order->getRandomCode();			
			
			//clean up a couple values
			$order->payment_type = "DragonPay";
			$order->CardType = "";
			$order->cardtype = "";
			
			//just save, the user will go to DragonPay to pay
			$order->status = "review";														
			$order->saveOrder();

			return true;
		}
			
		function sendToDragonPay(&$order)
		{						
			global $pmpro_currency;			
			
			//taxes on initial amount
			$initial_payment = $order->InitialPayment;
			$initial_payment_tax = $order->getTaxForPrice($initial_payment);
			$initial_payment = round((float)$initial_payment + (float)$initial_payment_tax, 2);
			
			//taxes on the amount
			$amount = $order->PaymentAmount;
			$amount_tax = $order->getTaxForPrice($amount);			
			$amount = round((float)$amount + (float)$amount_tax, 2);			
			
		
			if(pmpro_isLevelRecurring($order->membership_level))
			{
				//DragonPay does not allow recurring for now
				$dragonpay_url = $order->Gateway->getPaymentURI($order);
			}
			else
			{
				$dragonpay_url = $order->Gateway->getPaymentURI($order);
				
			}						
				
			
			//wp_die(str_replace("&", "<br />", $paypal_url));
			
			wp_redirect($dragonpay_url);
			exit;
		}


		
		function cancel(&$order)
		{


			//simulate a successful cancel			
			$order->updateStatus("cancelled");					
			return true;
		}	

		//Redirect to DragonPay Payment Portal
		function getPaymentURI($order)
		{
			

			if(empty($order->code))
				$order->code = $order->getRandomCode();
			
			$txnid = $order->code;
			$merchant  = pmpro_getOption("dragonpay_merchant_id");
			$passwd = pmpro_getOption("dragonpay_secret_key");
			$ccy="PHP";
			$description="ReviewMasters payment for ".$order->membership_level->name." membership.";
			$amount=number_format((float)$order->subtotal, 2, '.', ''); 
			$email=$order->Email;

			$digest_str = "$merchant:$txnid:$amount:$ccy:$description:$email:$passwd";
			$digest = sha1($digest_str);
			$params = "merchantid=" . ($merchant) .
				"&txnid=" .  ($txnid) . 
				"&amount=" . ($amount) .
				"&ccy=" . ($ccy) .
				"&description=" . urlencode($description) .
				"&email=" . urlencode($email) .
				"&level=" . ($order->membership_level->id) .
				"&digest=" . ($digest);
			
			$url = 'https://gw.dragonpay.ph/Pay.aspx';

			$environment = pmpro_getOption("gateway_environment");
			if("sandbox" === $environment || "beta-sandbox" === $environment)
			{
				$url = 'http://test.dragonpay.ph/Pay.aspx';
			}


			$location = "$url?$params";
			 return $location;
			
		}


		function getErrorFromCode($code)
		{
			$error_messages = array(
				"000" => "Success",
				"101" => "Invalid payment gateway id",
				"102" => "Incorrect secret key",
				"103" => "Invalid reference number",
				"104" => "Unauthorized access",
				"105" => "Invalid token",
				"106" => "Currency not supported",
				"107" => "Transaction cancelled",
				"108" => "Insufficient funds",
				"109" => "Transaction limit exceeded",
				"110" => "Error in operation",
				"111" => "Invalid parameters",
				"201" => "Invalid Merchant Id",
				"202" => "Invalid Merchant Password"		
			);
			
			if(isset($error_messages[$code]))
				return $error_messages[$code];
			else
				return "Unknown error.";
		}

	}