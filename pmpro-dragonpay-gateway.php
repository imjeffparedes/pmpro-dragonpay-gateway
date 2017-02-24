<?php
/*
Plugin Name: DragonPay Gateway for Paid Memberships Pro
Description: Add DragonPay in Payment Options plus Multiple Payment Settings for each Membership Levels. 
Version: v1
Author: Jeff Paredes

*/

define("PMPRO_DRAGONPAY_DIR", dirname(__FILE__));

//load payment gateway class
require_once(PMPRO_DRAGONPAY_DIR . "/classes/class.pmprogateway_dragonpay.php");

require_once(PMPRO_DRAGONPAY_DIR . "/includes/services.php");	//services loaded by AJAX and via webhook, etc
require_once(PMPRO_DRAGONPAY_DIR . "/includes/filters.php");				//filters, hacks, etc, moved into the plugin			


// quitely exit if PMPro isn't active
if (! defined('PMPRO_DRAGONPAY_DIR') )
	return;

//add dragonpay as a valid gateway
function pmpropbdp_pmpro_valid_gateways($gateways)
{

    $gateways[] = "check";
    $gateways[] = "dragonpay";
    return $gateways;
}
add_filter("pmpro_valid_gateways", "pmpropbdp_pmpro_valid_gateways");



/*
	Handle pending dragonpay payments
*/
//add pending as a default status when editing orders
function pmpropbdp_pmpro_order_statuses($statuses)
{
	if(!in_array('pending', $statuses))
	{
		$statuses[] = 'pending';
	}
	
	return $statuses;
}
add_filter('pmpro_order_statuses', 'pmpropbdp_pmpro_order_statuses');

//set dragonpay orders to pending until they are paid
function pmpropbdp_pmpro_dragonpay_status_after_checkout($status) 
{	

	return "pending"; 
}


add_filter("pmpro_dragonpay_status_after_checkout", "pmpropbdp_pmpro_dragonpay_status_after_checkout");


/*
 * Check if a member's status is still pending, i.e. they haven't made their first check payment.
 *
 * @return bool If status is pending or not.
 * @param user_id ID of the user to check.
 * @since .5
 */
function pmpropbdp_isMemberPending($user_id)
{
	global $pmpropbdp_pending_member_cache;
		
	//check the cache first
	if(isset($pmpropbdp_pending_member_cache[$user_id]))
		return $pmpropbdp_pending_member_cache[$user_id];
	
	//no cache, assume they aren't pending
	$pmpropbdp_pending_member_cache[$user_id] = false;
	
	//check their last order
	$order = new MemberOrder();
	$order->getLastMemberOrder($user_id, NULL);		//NULL here means any status
		
	if(!empty($order))
	{
		if($order->status == "pending")
		{
			//for recurring levels, we should check if there is an older successful order
			$membership_level = pmpro_getMembershipLevelForUser($user_id);
			if(pmpro_isLevelRecurring($membership_level))
			{			
				//unless the previous order has status success and we are still within the grace period
				$paid_order = new MemberOrder();
				$paid_order->getLastMemberOrder($user_id, 'success', $order->membership_id);
				
				if(!empty($paid_order) && !empty($paid_order->id))
				{					
					//how long ago is too long?
					$options = pmpropbdp_getOptions($membership_level->id);
					$cutoff = strtotime("- " . $membership_level->cycle_number . " " . $membership_level->cycle_period, current_time("timestamp")) - ($options['cancel_days']*3600*24);
					
					//too long ago?
					if($paid_order->timestamp < $cutoff)
						$pmpropbdp_pending_member_cache[$user_id] = true;
					else
						$pmpropbdp_pending_member_cache[$user_id] = false;
					
				}
				else
				{
					//no previous order, this must be the first
					$pmpropbdp_pending_member_cache[$user_id] = true;
				}								
			}
			else
			{
				//one time payment, so only interested in the last payment
				$pmpropbdp_pending_member_cache[$user_id] = true;
			}
		}
	}
	
	return $pmpropbdp_pending_member_cache[$user_id];
}

/*
	In case anyone was using the typo'd function name.
*/
function pmprobpc_isMemberPending($user_id) { return pmpropbdp_isMemberPending($user_id); }

//if a user's last order is pending status, don't give them access
function pmpropbdp_pmpro_has_membership_access_filter($hasaccess, $mypost, $myuser, $post_membership_levels)
{
	//if they don't have access, ignore this
	if(!$hasaccess)
		return $hasaccess;
	
	//if this isn't locked by level, ignore this
	if(empty($post_membership_levels))
		return $hasaccess;
	
	$hasaccess = ! pmpropbdp_isMemberPending($myuser->ID);
	
	return $hasaccess;
}
add_filter("pmpro_has_membership_access_filter", "pmpropbdp_pmpro_has_membership_access_filter", 10, 4);

/*
	Some notes RE pending status.
*/
//add note to account page RE waiting for check to clear
function pmpropbdp_pmpro_account_bullets_bottom()
{
	//get invoice from DB
	if(!empty($_REQUEST['invoice']))
	{
	    $invoice_code = $_REQUEST['invoice'];

	    if (!empty($invoice_code))
	    	$pmpro_invoice = new MemberOrder($invoice_code);
	}
	
	//no specific invoice, check current user's last order
	if(empty($pmpro_invoice) || empty($pmpro_invoice->id))
	{
		$pmpro_invoice = new MemberOrder();
		$pmpro_invoice->getLastMemberOrder(NULL, array('success', 'pending', 'cancelled', ''));
	}
	


	if(!empty($pmpro_invoice) && !empty($pmpro_invoice->id))
	{


		if($pmpro_invoice->status == "pending" && $pmpro_invoice->gateway == "dragonpay")
		{
			

			if(!empty($_REQUEST['invoice']))
			{

				?>
				<li>
					<?php						
						if(pmpropbdp_isMemberPending($pmpro_invoice->user_id))
							_e('<strong>Membership pending.</strong> We are still waiting for payment of this invoice.', 'pmpropbdp');
						else						
							_e('<strong>Important Notice:</strong> We are still waiting for payment of this invoice.', 'pmpropbdp');
					?>
				</li>
				<?php
			}
			else
			{
				?>
				<li><?php						
						if(pmpropbdp_isMemberPending($pmpro_invoice->user_id))
							printf(__('<strong>Membership pending.</strong> We are still waiting for payment for <a href="%s">your latest invoice</a>.', 'pmpropbdp'), pmpro_url('invoice', '?invoice=' . $pmpro_invoice->code));
						else
							printf(__('<strong>Important Notice:</strong> We are still waiting for payment for <a href="%s">your latest invoice</a>.', 'pmpropbdp'), pmpro_url('invoice', '?invoice=' . $pmpro_invoice->code));
					?>
				</li>
				<?php
			}
		}
	}
}
add_action('pmpro_account_bullets_bottom', 'pmpropbdp_pmpro_account_bullets_bottom');
add_action('pmpro_invoice_bullets_bottom', 'pmpropbdp_pmpro_account_bullets_bottom');



/***********************
Add On
************************/

//toggle payment method when discount code is updated
function pmpropbdc_pmpro_applydiscountcode_return_js() {
	?>
	pmpropbdc_togglePaymentMethodBox();
	<?php
}


/*
	Force dragonpay or check gateway if pbdc_setting is 2
*/
function pmpropbdc_pmpro_get_gateway($gateway)
{
	global $pmpro_level;
	
	if(!empty($pmpro_level) || !empty($_REQUEST['level']))
	{
		if(!empty($pmpro_level))
			$level_id = $pmpro_level->id;
		else
			$level_id = intval($_REQUEST['level']);
		
		$options = pmpropbdc_getOptions($level_id);
		    	
    	if($options['setting'] == 3)
    		$gateway = "dragonpay";
	}	
	
	return $gateway;
}

add_filter('pmpro_get_gateway', 'pmpropbdc_pmpro_get_gateway');
add_filter('option_pmpro_gateway', 'pmpropbdc_pmpro_get_gateway');
				


/*
	Need to remove some filters added by the check gateway.
	The default gateway will have it's own idea RE this.
*/
function pmpropbdc_init_include_billing_address_fields()
{
	//make sure PMPro is active
	if(!function_exists('pmpro_getGateway'))
		return;

	//billing address and payment info fields
	if(!empty($_REQUEST['level']))
	{
		$level_id = intval($_REQUEST['level']);
		$options = pmpropbdc_getOptions($level_id);		
		$default_gateway = pmpro_getOption('gateway');    
    	if($options['setting'] == 3 || $options['setting'] == 1 || $options['setting'] == 2)
		{
			//hide billing address and payment info fields
			add_filter('pmpro_include_billing_address_fields', '__return_false', 20);
			add_filter('pmpro_include_payment_information_fields', '__return_false', 20);
			if($default_gateway!='dragonpay' ){
				add_filter('pmpro_checkout_default_submit_button', array('pmprogateway_dragonpay', 'pmpro_checkout_default_submit_button'));
				add_action('pmpro_checkout_after_form', array('pmprogateway_dragonpay', 'pmpro_checkout_after_form'));
			}
		} else {
			//keep paypal buttons, billing address fields/etc at checkout
			if($default_gateway == 'paypalexpress') {
				add_filter('pmpro_checkout_default_submit_button', array('PMProGateway_paypalexpress', 'pmpro_checkout_default_submit_button'));
				add_action('pmpro_checkout_after_form', array('PMProGateway_paypalexpress', 'pmpro_checkout_after_form'));
			} elseif($default_gateway == 'paypalstandard') {
				add_filter('pmpro_checkout_default_submit_button', array('PMProGateway_paypalstandard', 'pmpro_checkout_default_submit_button'));
			} elseif($default_gateway == 'twocheckout') {
				//undo the filter to change the checkout button text
				remove_filter('pmpro_checkout_default_submit_button', array('PMProGateway_twocheckout', 'pmpro_checkout_default_submit_button'));
			} elseif($default_gateway == 'dragonpay') {
				//hide billing address and payment info fields
				add_filter('pmpro_include_billing_address_fields', '__return_false', 20);
				add_filter('pmpro_include_payment_information_fields', '__return_false', 20);
			}  
			else {
				//onsite checkouts
				if(class_exists('PMProGateway_' . $default_gateway) && method_exists('PMProGateway_' . $default_gateway, 'pmpro_include_billing_address_fields'))
					add_filter('pmpro_include_billing_address_fields', array('PMProGateway_' . $default_gateway, 'pmpro_include_billing_address_fields'));
				else
					add_filter('pmpro_include_billing_address_fields', '__return_true', 20);
			}
		}
	}

	//instructions at checkout
	remove_filter('pmpro_checkout_after_payment_information_fields', array('PMProGateway_check', 'pmpro_checkout_after_payment_information_fields'));
	add_filter('pmpro_checkout_after_payment_information_fields', 'pmpropbdc_pmpro_checkout_after_payment_information_fields');
}
add_action('init', 'pmpropbdc_init_include_billing_address_fields', 20);

/*
	Show instructions on the checkout page.
*/
function pmpropbdc_pmpro_checkout_after_payment_information_fields() {
	global $gateway, $pmpro_level;

	$options = pmpropbdc_getOptions($pmpro_level->id);

	if(!empty($options) && $options['setting'] > 0 && !pmpro_isLevelFree($pmpro_level)) {
		$instructions = pmpro_getOption("instructions");
		if($gateway != 'check')
			$hidden = 'style="display:none;"';
		else
			$hidden = '';
		echo '<div class="pmpro_check_instructions" ' . $hidden . '>' . wpautop($instructions) . '</div>';
	}
}

/*
	Add settings to the edit levels page
*/
//show the checkbox on the edit level page
function pmpro_membership_level_after_other_settings()
{	
	$level_id = intval($_REQUEST['edit']);
	$options = pmpropbdc_getOptions($level_id);	
?>
<h3 class="topborder"><?php _e('Multiple Payment Settings', 'pmpropbdc');?></h3>
<p>Change this setting to allow or disallow the pay by dragonpay or check option for this level.</p>
<table>
<tbody class="form-table">
	<tr>
		<th scope="row" valign="top"><label for="pbdc_setting">Allow Multiple Payment:</label></th>
		<td>
			<select id="pbdc_setting" name="pbdc_setting">
				<option value="0" <?php selected($options['setting'], 0);?>>No. Use the default gateway only.</option>
				<option value="1" <?php selected($options['setting'], 1);?>>Yes. Users choose by default gateway, DragonPay or Check.</option>

				<option value="2" <?php selected($options['setting'], 2);?>>Yes. Users choose between default gateway and DragonPay.</option>
				<option value="3" <?php selected($options['setting'], 3);?>>No. Use DragonPay only.</option>
			</select>
		</td>
	</tr>
	<tr class="pbdc_recurring_field">
		<th scope="row" valign="top"><label for="pbdc_renewal_days">Send Renewal Emails:</label></th>
		<td>
			<input type="text" id="pbdc_renewal_days" name="pbdc_renewal_days" size="5" value="<?php echo esc_attr($options['renewal_days']);?>" /> days before renewal.
		</td>
	</tr>
	<tr class="pbdc_recurring_field">
		<th scope="row" valign="top"><label for="pbdc_reminder_days">Send Reminder Emails:</label></th>
		<td>
			<input type="text" id="pbdc_reminder_days" name="pbdc_reminder_days" size="5" value="<?php echo esc_attr($options['reminder_days']);?>" /> days after a missed payment.
		</td>
	</tr>
	<tr class="pbdc_recurring_field">
		<th scope="row" valign="top"><label for="pbdc_cancel_days">Cancel Membership:</label></th>
		<td>
			<input type="text" id="pbdc_cancel_days" name="pbdc_cancel_days" size="5" value="<?php echo esc_attr($options['cancel_days']);?>" /> days after a missed payment.
		</td>
	</tr>
	<script>
		function togglepbdcRecurringOptions() {
			if(jQuery('#pbdc_setting').val() > 0 && jQuery('#recurring').is(':checked')) { 
				jQuery('tr.pbdc_recurring_field').show(); 
			} else {
				jQuery('tr.pbdc_recurring_field').hide(); 
			}
		}
		
		jQuery(document).ready(function(){
			//hide/show recurring fields on page load
			togglepbdcRecurringOptions();
			
			//hide/show recurring fields when pbdc or recurring settings change
			jQuery('#pbdc_setting').change(function() { togglepbdcRecurringOptions() });
			jQuery('#recurring').change(function() { togglepbdcRecurringOptions() });
		});
	</script>
</tbody>
</table>
<?php
}
add_action('pmpro_membership_level_after_other_settings', 'pmpro_membership_level_after_other_settings');

//save pay by check settings when the level is saved/added
function pmpropbdc_pmpro_save_membership_level($level_id)
{
	//get values
	if(isset($_REQUEST['pbdc_setting']))
		$pbdc_setting = intval($_REQUEST['pbdc_setting']);
	else
		$pbdc_setting = 0;
		
	$renewal_days = intval($_REQUEST['pbdc_renewal_days']);
	$reminder_days = intval($_REQUEST['pbdc_reminder_days']);
	$cancel_days = intval($_REQUEST['pbdc_cancel_days']);
	
	//build array
	$options = array(
		'setting' => $pbdc_setting,
		'renewal_days' => $renewal_days,
		'reminder_days' => $reminder_days,
		'cancel_days' => $cancel_days,
	);
	
	//save
	delete_option('pmpro_pay_by_dragonpay_options_' . $level_id);
	delete_option('pmpro_pay_by_dragonpay_options_' . $level_id);
	add_option('pmpro_pay_by_dragonpay_options_' . intval($level_id), $options, "", "no");
}
add_action("pmpro_save_membership_level", "pmpropbdc_pmpro_save_membership_level");

/*
	Helper function to get options.
*/
function pmpropbdc_getOptions($level_id)
{
	if($level_id > 0)
	{
		//option for level, check the DB
		$options = get_option('pmpro_pay_by_dragonpay_options_' . $level_id, false);
		if(empty($options))
		{
			//check for old format to convert (_setting_ without an s)
			$options = get_option('pmpro_pay_by_dragonpay_options_' . $level_id, false);						
			if(!empty($options))
			{
				delete_option('pmpro_pay_by_dragonpay_options_' . $level_id);
				$options = array('setting'=>$options, 'renewal_days'=>'', 'reminder_days'=>'', 'cancel_days'=>'');
				add_option('pmpro_pay_by_dragonpay_options_' . $level_id, $options, NULL, 'no');
			}
			else
			{
				//default
				$options = array('setting'=>0, 'renewal_days'=>'', 'reminder_days'=>'', 'cancel_days'=>'');
			}
		}
	}
	else
	{
		//default for new level
		$options = array('setting'=>0, 'renewal_days'=>'', 'reminder_days'=>'', 'cancel_days'=>'');
	}
	
	return $options;
}
/*
	Add pay by  multiple option
*/
//add option to checkout along with JS
function pmpropbdc_checkout_boxes()
{
	global $gateway, $pmpro_level, $pmpro_review;
	$gateway_setting = pmpro_getOption("gateway");

	$options = pmpropbdc_getOptions($pmpro_level->id);

	//only show if the main gateway is check and setting is all options [1]
	//show dragonpay and check only if the default gateway is check


	if($options['setting']==1 ){
	//pay by deafult , dragonpay or check
		?>
		<table id="pmpro_payment_method" class="pmpro_checkout top1em" width="100%" cellpadding="0" cellspacing="0" border="0" <?php if(!empty($pmpro_review)) { ?>style="display: none;"<?php } ?>>
				<thead>
						<tr>
								<th><?php _e('Choose Your Payment Method', 'pmpropbdc');?></th>
						</tr>
				</thead>
				<tbody>
						<tr>
								<td>
										<div>
											<?php if( !($gateway == "dragonpay")  ) { ?>

												<input type="radio" name="gateway" value="<?php echo $gateway_setting;?>"  <?php if($gateway == "paypalexpress" || $gateway == "paypalstandard" || $gateway == "check"||$gateway == "twocheckout" ) { ?>checked="checked"<?php } ?>/>
														<?php if($gateway_setting == "paypalexpress" || $gateway_setting == "paypalstandard") { ?>
															<a href="javascript:void(0);" class="pmpro_radio">Pay with PayPal</a> &nbsp;
														<?php } elseif($gateway_setting == 'twocheckout') { ?>
															<a href="javascript:void(0);" class="pmpro_radio">Pay with 2Checkout</a> &nbsp;
														<?php } elseif($gateway_setting == 'check') { ?>
															<a href="javascript:void(0);" class="pmpro_radio">Pay by Check</a> &nbsp;
														<?php }  else { ?>
															<a href="javascript:void(0);" class="pmpro_radio">Pay by Credit Card</a> &nbsp;
														<?php } ?>
											<?php } ?>

												<input type="radio" name="gateway" value="dragonpay" <?php if($gateway == "dragonpay") { ?>checked="checked"<?php } ?> />
														<a href="javascript:void(0);" class="pmpro_radio">Pay with DragonPay</a> &nbsp;
												<?php if($gateway!='check') { ?>

												<input type="radio" name="gateway" value="check" <?php if($gateway == "check") { ?>checked="checked"<?php } ?>/>
														<a href="javascript:void(0);" class="pmpro_radio">Pay by Check</a> &nbsp;

												<?php } ?>                                           
										</div>
								</td>
						</tr>
				</tbody>
		</table>
		<div class="clear"></div>
		<script>
			var pmpro_gateway = '<?php echo pmpro_getOption('gateway');?>';		
			var code_level;
			code_level = <?php echo json_encode($pmpro_level); ?>;
			
			//function toggle billing address, payment info and checkout button
			function pmpropbdc_toggleCheckoutFields() {			
				if (typeof code_level !== 'undefined' && parseFloat(code_level.billing_amount) == 0 && parseFloat(code_level.initial_payment) == 0) 
				{
					//discount code makes the level free
					jQuery('#pmpro_billing_address_fields').hide();
					jQuery('#pmpro_payment_information_fields').hide();
					
					jQuery('.pmpro_check_instructions').hide();

						jQuery('#pmpro_submit_span').hide();

					if(pmpro_gateway == 'paypalexpress' || pmpro_gateway == 'paypalstandard')
					{
						jQuery('#pmpro_paypalexpress_checkout').show();

						jQuery('#pmpro_dragonpay_checkout').hide();
						jQuery('#pmpro_submit_span').hide();
					}
					
					pmpro_require_billing = false;
				}
				else if(jQuery('input[name=gateway]:checked').val() == 'check')
				{
					//check choosen
					jQuery('#pmpro_billing_address_fields').hide();
					jQuery('#pmpro_payment_information_fields').hide();                                                
					
					jQuery('.pmpro_check_instructions').show();


					jQuery('#pmpro_paypalexpress_checkout').hide();
					jQuery('#pmpro_dragonpay_checkout').hide();
					jQuery('#pmpro_submit_span').show();
					
					
					pmpro_require_billing = false;
				}	
				else if(jQuery('input[name=gateway]:checked').val() == 'dragonpay')
				{
					//dragonpay chosen
					jQuery('#pmpro_billing_address_fields').hide();
					jQuery('#pmpro_payment_information_fields').hide();
					
					jQuery('.pmpro_check_instructions').hide();

					jQuery('#pmpro_paypalexpress_checkout').hide();

					jQuery('#pmpro_dragonpay_checkout').show();
					jQuery('#pmpro_submit_span').hide();



					
					pmpro_require_billing = false;
				}			
				else
				{                        

					//check out with onsite gateway
					jQuery('#pmpro_billing_address_fields').show();
					jQuery('#pmpro_payment_information_fields').show();                                                
					
					jQuery('.pmpro_check_instructions').hide();

					if(pmpro_gateway == 'paypalexpress' || pmpro_gateway == 'paypalstandard')
					{
						jQuery('#pmpro_paypalexpress_checkout').show();

						jQuery('#pmpro_dragonpay_checkout').hide();
						jQuery('#pmpro_submit_span').hide();
					}
					
					pmpro_require_billing = true;
				}
			}
			
			//function to toggle the payment method box
			function pmpropbdc_togglePaymentMethodBox()
			{
				if (typeof code_level !== 'undefined' && parseFloat(code_level.billing_amount) == 0 && parseFloat(code_level.initial_payment) == 0) {
					//free
					jQuery('#pmpro_payment_method').hide();					
				}
				else {
					//not free
					jQuery('#pmpro_payment_method').show();
				}
				pmpropbdc_toggleCheckoutFields();			
			}

			//set things up on load
			jQuery(document).ready(function() {

				pmpropbdc_toggleCheckoutFields();
				//choosing payment method
				jQuery('input[name=gateway]').bind('click change keyup', function() {                
						pmpropbdc_toggleCheckoutFields();
				});			
				
				//select the radio button if the label is clicked on
				jQuery('a.pmpro_radio').click(function() {
						jQuery(this).prev().click();

				});
				
				//make sure the payment method box is shown or hidden as needed, but not on PayPal review page
				<?php if(empty($pmpro_review)) { ?>
					pmpropbdc_togglePaymentMethodBox();
				<?php } ?>			
			});
		</script>
		<?php
		
	}elseif($options['setting']==2){
	//pay by deafult , dragonpay or check
		?>
		<table id="pmpro_payment_method" class="pmpro_checkout top1em" width="100%" cellpadding="0" cellspacing="0" border="0" <?php if(!empty($pmpro_review)) { ?>style="display: none;"<?php } ?>>
				<thead>
						<tr>
								<th><?php _e('Choose Your Payment Method', 'pmpropbdc');?></th>
						</tr>
				</thead>
				<tbody>
						<tr>
								<td>
										<div>
											<?php if( !($gateway == "dragonpay") ) { ?>

												<input type="radio" name="gateway" value="<?php echo $gateway_setting;?>"  <?php if($gateway == "paypalexpress" || $gateway == "paypalstandard" || $gateway == "check"||$gateway == "twocheckout" ) { ?>checked="checked"<?php } ?>/>
														<?php if($gateway_setting == "paypalexpress" || $gateway_setting == "paypalstandard") { ?>
															<a href="javascript:void(0);" class="pmpro_radio">Pay with PayPal</a> &nbsp;
														<?php } elseif($gateway_setting == 'twocheckout') { ?>
															<a href="javascript:void(0);" class="pmpro_radio">Pay with 2Checkout</a> &nbsp;
														<?php } elseif($gateway_setting == 'check') { ?>
															<a href="javascript:void(0);" class="pmpro_radio">Pay by Check</a> &nbsp;
														<?php }  else { ?>
															<a href="javascript:void(0);" class="pmpro_radio">Pay by Credit Card</a> &nbsp;
														<?php } ?>
											<?php } ?>

												<input type="radio" name="gateway" value="dragonpay" <?php if($gateway == "dragonpay") { ?>checked="checked"<?php } ?> />
														<a href="javascript:void(0);" class="pmpro_radio">Pay with DragonPay</a> &nbsp;                                      
										</div>
								</td>
						</tr>
				</tbody>
		</table>
		<div class="clear"></div>
		<script>
			var pmpro_gateway = '<?php echo pmpro_getOption('gateway');?>';		
			var code_level;
			code_level = <?php echo json_encode($pmpro_level); ?>;
			
			//function toggle billing address, payment info and checkout button
			function pmpropbdc_toggleCheckoutFields() {			
				if (typeof code_level !== 'undefined' && parseFloat(code_level.billing_amount) == 0 && parseFloat(code_level.initial_payment) == 0) 
				{
					//discount code makes the level free
					jQuery('#pmpro_billing_address_fields').hide();
					jQuery('#pmpro_payment_information_fields').hide();
					
					jQuery('.pmpro_check_instructions').hide();

						jQuery('#pmpro_submit_span').hide();

					if(pmpro_gateway == 'paypalexpress' || pmpro_gateway == 'paypalstandard')
					{
						jQuery('#pmpro_paypalexpress_checkout').show();

						jQuery('#pmpro_dragonpay_checkout').hide();
						jQuery('#pmpro_submit_span').hide();
					}
					
					pmpro_require_billing = false;
				}
				else if(jQuery('input[name=gateway]:checked').val() == 'check')
				{
					//check choosen
					jQuery('#pmpro_billing_address_fields').hide();
					jQuery('#pmpro_payment_information_fields').hide();                                                
					
					jQuery('.pmpro_check_instructions').show();


					jQuery('#pmpro_paypalexpress_checkout').hide();
					jQuery('#pmpro_dragonpay_checkout').hide();
					jQuery('#pmpro_submit_span').show();
					
					
					pmpro_require_billing = false;
				}	
				else if(jQuery('input[name=gateway]:checked').val() == 'dragonpay')
				{
					//dragonpay chosen
					jQuery('#pmpro_billing_address_fields').hide();
					jQuery('#pmpro_payment_information_fields').hide();
					
					jQuery('.pmpro_check_instructions').hide();

					jQuery('#pmpro_paypalexpress_checkout').hide();

					jQuery('#pmpro_dragonpay_checkout').show();
					jQuery('#pmpro_submit_span').hide();



					
					pmpro_require_billing = false;
				}			
				else
				{                        

					//check out with onsite gateway
					jQuery('#pmpro_billing_address_fields').show();
					jQuery('#pmpro_payment_information_fields').show();                                                
					
					jQuery('.pmpro_check_instructions').hide();

					if(pmpro_gateway == 'paypalexpress' || pmpro_gateway == 'paypalstandard')
					{
						jQuery('#pmpro_paypalexpress_checkout').show();

						jQuery('#pmpro_dragonpay_checkout').hide();
						jQuery('#pmpro_submit_span').hide();
					}
					
					pmpro_require_billing = true;
				}
			}
			
			//function to toggle the payment method box
			function pmpropbdc_togglePaymentMethodBox()
			{
				if (typeof code_level !== 'undefined' && parseFloat(code_level.billing_amount) == 0 && parseFloat(code_level.initial_payment) == 0) {
					//free
					jQuery('#pmpro_payment_method').hide();					
				}
				else {
					//not free
					jQuery('#pmpro_payment_method').show();
				}
				pmpropbdc_toggleCheckoutFields();			
			}

			//set things up on load
			jQuery(document).ready(function() {

				pmpropbdc_toggleCheckoutFields();
				//choosing payment method
				jQuery('input[name=gateway]').bind('click change keyup', function() {                
						pmpropbdc_toggleCheckoutFields();
				});			
				
				//select the radio button if the label is clicked on
				jQuery('a.pmpro_radio').click(function() {
						jQuery(this).prev().click();

				});
				
				//make sure the payment method box is shown or hidden as needed, but not on PayPal review page
				<?php if(empty($pmpro_review)) { ?>
					pmpropbdc_togglePaymentMethodBox();
				<?php } ?>			
			});
		</script>
		<?php
		
	}elseif( $options['setting']==3)
	{ //pay by dragonpay only
		
			?>
			<table id="pmpro_payment_method" class="pmpro_checkout top1em" width="100%" cellpadding="0" cellspacing="0" border="0" <?php if(!empty($pmpro_review)) { ?>style="display: none;"<?php } ?>>
					<thead>
							<tr>
									<th><?php _e('Choose Your Payment Method', 'pmpropbdc');?></th>
							</tr>
					</thead>
					<tbody>
							<tr>
									<td>
											<div>

													<input type="radio" name="gateway" value="dragonpay" checked="checked" />
															<a href="javascript:void(0);" class="pmpro_radio">Pay by DragonPay</a> &nbsp; 


											</div>
									</td>
							</tr>
					</tbody>
			</table>
			<div class="clear"></div>
			<script>
				var pmpro_gateway = '<?php echo pmpro_getOption('gateway');?>';		
				var code_level;
				code_level = <?php echo json_encode($pmpro_level); ?>;

				//check out with dragonpay
				//set things up on load
				jQuery(document).ready(function() {

					//set for dragonpay only
					jQuery('#pmpro_billing_address_fields').hide();
					jQuery('#pmpro_payment_information_fields').hide();
					
					jQuery('.pmpro_check_instructions').hide();

					jQuery('#pmpro_paypalexpress_checkout').hide();

					jQuery('#pmpro_dragonpay_checkout').show();
					jQuery('#pmpro_submit_span').hide();
				
					pmpro_require_billing = false;
					
					//choosing payment method
					jQuery('input[name=gateway]').bind('click change keyup', function() {                
							//do nothing on click
					});			
					
					//select the radio button if the label is clicked on
					jQuery('a.pmpro_radio').click(function() {
							jQuery(this).prev().click();
					});
					
						
				});

				
			</script>
			<?php
			
	}elseif( $options['setting']==0 ){
		?>
			
		<?php
	}
	
}
add_action("pmpro_checkout_boxes", "pmpropbdc_checkout_boxes");


/*
	TODO Add note to non-member text RE waiting for check to clear
*/

/*
	TODO Send email to user when order status is changed to success
*/

/*
	Create pending orders for recurring levels.
*/
function pmpropbcheck_recurring_orders()
{
	global $wpdb;
	
	//make sure we only run once a day
	$now = current_time('timestamp');
	$today = date("Y-m-d", $now);
	
	//have to run for each level, so get levels
	$levels = pmpro_getAllLevels(true, true);

	if(empty($levels))
		return;
		
	foreach($levels as $level)
	{
		//get options
		$options = pmpropbdc_getOptions($level->id);	
		if(!empty($options['renewal_days']))
			$date = date("Y-m-d", strtotime("+ " . $options['renewal_days'] . " days", $now));
		else
			$date = $today;
	
		//need to get all combos of pay cycle and period
		$sqlQuery = "SELECT DISTINCT(CONCAT(cycle_number, ' ', cycle_period)) FROM $wpdb->pmpro_memberships_users WHERE membership_id = '" . $level->id . "' AND cycle_number > 0 AND status = 'active'";		
		$combos = $wpdb->get_col($sqlQuery);
				
		if(empty($combos))
			continue;
		
		foreach($combos as $combo)
		{			
			//check if it's been one pay period since the last payment		
			/*
				- Check should create an invoice X days before expiration based on a setting on the levels page.
				- Set invoice date based on cycle and the day of the month of the member start date.
				- Send a reminder email Y days after initial invoice is created if it's still pending.
				- Cancel membership after Z days if invoice is not paid. Send email.
			*/
			//get all check orders still pending after X days
			$sqlQuery = "
				SELECT o1.id FROM
				    (SELECT id, user_id, timestamp
				    FROM {$wpdb->pmpro_membership_orders}
				    WHERE membership_id = $level->id
				        AND gateway = 'check' 
				        AND status IN('pending', 'success')
				    ) as o1

					LEFT OUTER JOIN 
					
					(SELECT id, user_id, timestamp
				    FROM {$wpdb->pmpro_membership_orders}
				    WHERE membership_id = $level->id
				        AND gateway = 'check' 
				        AND status IN('pending', 'success')
				    ) as o2

					ON o1.user_id = o2.user_id
					AND o1.timestamp < o2.timestamp
					OR (o1.timestamp = o2.timestamp AND o1.id < o2.id)
				WHERE
					o2.id IS NULL
					AND DATE_ADD(o1.timestamp, INTERVAL $combo) <= '" . $date . "'
			";
			
			if(defined('PMPRO_CRON_LIMIT'))
				$sqlQuery .= " LIMIT " . PMPRO_CRON_LIMIT;
			
			$orders = $wpdb->get_col($sqlQuery);
		
			if(empty($orders))
				continue;
			
			foreach($orders as $order_id)
			{
				$order = new MemberOrder($order_id);
				$user = get_userdata($order->user_id);
				$user->membership_level = pmpro_getMembershipLevelForUser($order->user_id);
				
				//check that user still has same level?
				if(empty($user->membership_level) || $order->membership_id != $user->membership_level->id)
					continue;
				
				//create new pending order
				$morder = new MemberOrder();
				$morder->user_id = $order->user_id;				
				$morder->membership_id = $user->membership_level->id;
				$morder->InitialPayment = $user->membership_level->billing_amount;
				$morder->PaymentAmount = $user->membership_level->billing_amount;
				$morder->BillingPeriod = $user->membership_level->cycle_period;
				$morder->BillingFrequency = $user->membership_level->cycle_number;
				$morder->subscription_transaction_id = $order->subscription_transaction_id;
				$morder->gateway = "check";
				$morder->setGateway();
				$morder->payment_type = "Check";
				$morder->status = "pending";

				//get timestamp for new order
				$order_timestamp = strtotime("+" . $combo, $order->timestamp);
				
				//let's skip if there is already an order for this user/level/timestamp
				$sqlQuery = "SELECT id FROM $wpdb->pmpro_membership_orders WHERE user_id = '" . $order->user_id . "' AND membership_id = '" . $order->membership_id . "' AND timestamp = '" . date('d', $order_timestamp) . "' LIMIT 1";			
				$dupe = $wpdb->get_var("SELECT id FROM $wpdb->pmpro_membership_orders WHERE user_id = '" . $order->user_id . "' AND membership_id = '" . $order->membership_id . "' AND timestamp = '" . $order_timestamp . "' LIMIT 1");				
				if(!empty($dupe))
					continue;
				
				//save it
				$morder->process();
				$morder->saveOrder();

				//update the timestamp				
				$morder->updateTimestamp(date("Y", $order_timestamp), date("m", $order_timestamp), date("d", $order_timestamp));

				//send emails				
				$email = new PMProEmail();
				$email->template = "check_pending";
				$email->email = $user->user_email;
				$email->subject = sprintf(__("New Invoice for %s at %s", "pmpropbdc"), $user->membership_level->name, get_option("blogname"));
			}
		}
	}	
}
add_action('pmpropbcheck_recurring_orders', 'pmpropbcheck_recurring_orders');

/*
	Send reminder emails for pending invoices.
*/
function pmpropbcheck_reminder_emails()
{
	global $wpdb;
	
	//make sure we only run once a day
	$now = current_time('timestamp');
	$today = date("Y-m-d", $now);
	
	//have to run for each level, so get levels
	$levels = pmpro_getAllLevels(true, true);
	
	if(empty($levels))
		return;
		
	foreach($levels as $level)
	{
		//get options
		$options = pmpropbdc_getOptions($level->id);	
		if(!empty($options['reminder_days']))
			$date = date("Y-m-d", strtotime("+ " . $options['reminder_days'] . " days", $now));
		else
			$date = $today;
	
		//need to get all combos of pay cycle and period
		$sqlQuery = "SELECT DISTINCT(CONCAT(cycle_number, ' ', cycle_period)) FROM $wpdb->pmpro_memberships_users WHERE membership_id = '" . $level->id . "' AND cycle_number > 0 AND status = 'active'";		
		$combos = $wpdb->get_col($sqlQuery);
				
		if(empty($combos))
			continue;
		
		foreach($combos as $combo)
		{	
			//get all check orders still pending after X days
			$sqlQuery = "
				SELECT id 
				FROM $wpdb->pmpro_membership_orders 
				WHERE membership_id = $level->id 
					AND gateway = 'check' 
					AND status = 'pending' 
					AND DATE_ADD(timestamp, INTERVAL $combo) <= '" . $date . "'
					AND notes NOT LIKE '%Reminder Sent:%' AND notes NOT LIKE '%Reminder Skipped:%'
				ORDER BY id
			";
						
			if(defined('PMPRO_CRON_LIMIT'))
				$sqlQuery .= " LIMIT " . PMPRO_CRON_LIMIT;

			$orders = $wpdb->get_col($sqlQuery);

			if(empty($orders))
				continue;
						
			foreach($orders as $order_id)
			{
				//get some data
				$order = new MemberOrder($order_id);
				$user = get_userdata($order->user_id);
				$user->membership_level = pmpro_getMembershipLevelForUser($order->user_id);
				
				//if they are no longer a member, let's not send them an email
				if(empty($user->membership_level) || empty($user->membership_level->ID) || $user->membership_level->id != $order->membership_id)
				{
					//note when we send the reminder
					$new_notes = $order->notes . "Reminder Skipped:" . $today . "\n";
					$wpdb->query("UPDATE $wpdb->pmpro_membership_orders SET notes = '" . esc_sql($new_notes) . "' WHERE id = '" . $order_id . "' LIMIT 1");

					continue;
				}

				//note when we send the reminder
				$new_notes = $order->notes . "Reminder Sent:" . $today . "\n";
				$wpdb->query("UPDATE $wpdb->pmpro_membership_orders SET notes = '" . esc_sql($new_notes) . "' WHERE id = '" . $order_id . "' LIMIT 1");

				//setup email to send
				$email = new PMProEmail();
				$email->template = "check_pending_reminder";
				$email->email = $user->user_email;
				$email->subject = sprintf(__("Reminder: New Invoice for %s at %s", "pmpropbdc"), $user->membership_level->name, get_option("blogname"));											
				//get body from template
				$email->body = file_get_contents(PMPRO_pay_by_dragonpay_or_check_DIR . "/email/" . $email->template . ".html");
				
				//setup more data
				$email->data = array(					
					"name" => $user->display_name, 
					"user_login" => $user->user_login,
					"sitename" => get_option("blogname"),
					"siteemail" => pmpro_getOption("from_email"),
					"membership_id" => $user->membership_level->id,
					"membership_level_name" => $user->membership_level->name,
					"membership_cost" => pmpro_getLevelCost($user->membership_level),								
					"login_link" => wp_login_url(pmpro_url("account")),
					"display_name" => $user->display_name,
					"user_email" => $user->user_email,								
				);
				
				$email->data["instructions"] = pmpro_getOption('instructions');
				$email->data["invoice_id"] = $order->code;
				$email->data["invoice_total"] = pmpro_formatPrice($order->total);
				$email->data["invoice_date"] = date(get_option('date_format'), $order->timestamp);
				$email->data["billing_name"] = $order->billing->name;
				$email->data["billing_street"] = $order->billing->street;
				$email->data["billing_city"] = $order->billing->city;
				$email->data["billing_state"] = $order->billing->state;
				$email->data["billing_zip"] = $order->billing->zip;
				$email->data["billing_country"] = $order->billing->country;
				$email->data["billing_phone"] = $order->billing->phone;
				$email->data["cardtype"] = $order->cardtype;
				$email->data["accountnumber"] = hideCardNumber($order->accountnumber);
				$email->data["expirationmonth"] = $order->expirationmonth;
				$email->data["expirationyear"] = $order->expirationyear;
				$email->data["billing_address"] = pmpro_formatAddress($order->billing->name,
																	 $order->billing->street,
																	 "", //address 2
																	 $order->billing->city,
																	 $order->billing->state,
																	 $order->billing->zip,
																	 $order->billing->country,
																	 $order->billing->phone);
				
				if($order->getDiscountCode())
					$email->data["discount_code"] = "<p>" . __("Discount Code", "pmpro") . ": " . $order->discount_code->code . "</p>\n";
				else
					$email->data["discount_code"] = "";
	
				//send the email
				$email->sendEmail();
			}
		}
	}		
}
add_action('pmpropbcheck_reminder_emails', 'pmpropbcheck_reminder_emails');

/*
	Cancel overdue members.
*/
function pmpropbcheck_cancel_overdue_orders()
{
	global $wpdb;
	
	//make sure we only run once a day
	$now = current_time('timestamp');
	$today = date("Y-m-d", $now);
	
	//have to run for each level, so get levels
	$levels = pmpro_getAllLevels(true, true);
	
	if(empty($levels))
		return;
		
	foreach($levels as $level)
	{
		//get options
		$options = pmpropbdc_getOptions($level->id);	
		if(!empty($options['cancel_days']))
			$date = date("Y-m-d", strtotime("+ " . $options['cancel_days'] . " days", $now));
		else
			$date = $today;
	
		//need to get all combos of pay cycle and period
		$sqlQuery = "SELECT DISTINCT(CONCAT(cycle_number, ' ', cycle_period)) FROM $wpdb->pmpro_memberships_users WHERE membership_id = '" . $level->id . "' AND cycle_number > 0 AND status = 'active'";		
		$combos = $wpdb->get_col($sqlQuery);
				
		if(empty($combos))
			continue;
		
		foreach($combos as $combo)
		{	
			//get all check orders still pending after X days
			$sqlQuery = "
				SELECT id 
				FROM $wpdb->pmpro_membership_orders 
				WHERE membership_id = $level->id 
					AND gateway = 'check' 
					AND status = 'pending' 
					AND DATE_ADD(timestamp, INTERVAL $combo) <= '" . $date . "'
					AND notes NOT LIKE '%Cancelled:%' AND notes NOT LIKE '%Cancellation Skipped:%'
				ORDER BY id
			";
						
			if(defined('PMPRO_CRON_LIMIT'))
				$sqlQuery .= " LIMIT " . PMPRO_CRON_LIMIT;

			$orders = $wpdb->get_col($sqlQuery);

			if(empty($orders))
				continue;
						
			foreach($orders as $order_id)
			{		
				//get the order and user data
				$order = new MemberOrder($order_id);								
				$user = get_userdata($order->user_id);
				$user->membership_level = pmpro_getMembershipLevelForUser($order->user_id);
				
				//if they are no longer a member, let's not send them an email
				if(empty($user->membership_level) || empty($user->membership_level->ID) || $user->membership_level->id != $order->membership_id)
				{
					//note when we send the reminder
					$new_notes = $order->notes . "Cancellation Skipped:" . $today . "\n";
					$wpdb->query("UPDATE $wpdb->pmpro_membership_orders SET notes = '" . esc_sql($new_notes) . "' WHERE id = '" . $order_id . "' LIMIT 1");

					continue;
				}
				
				//cancel the order and subscription
				do_action("pmpro_membership_pre_membership_expiry", $order->user_id, $order->membership_id );
				
				//remove their membership
				pmpro_changeMembershipLevel(false, $order->user_id, 'expired');
				do_action("pmpro_membership_post_membership_expiry", $order->user_id, $order->membership_id );
				$send_email = apply_filters("pmpro_send_expiration_email", true, $order->user_id);
				if($send_email)
				{
					//send an email
					$pmproemail = new PMProEmail();
					$euser = get_userdata($order->user_id);
					$pmproemail->sendMembershipExpiredEmail($euser);
					if(current_user_can('manage_options'))
						printf(__("Membership expired email sent to %s. ", "pmpro"), $euser->user_email);
					else
						echo ". ";
				}
			}
		}
	}		
}
add_action('pmpropbcheck_cancel_overdue_orders', 'pmpropbcheck_cancel_overdue_orders');


/*
	TODO Add note to non-member text RE waiting for check to clear
*/

/*
	TODO Send email to user when order status is changed to success
*/

/*
	Create pending orders for recurring levels.
*/
function pmpropbdp_recurring_orders()
{
	global $wpdb;
	
	//make sure we only run once a day
	$now = current_time('timestamp');
	$today = date("Y-m-d", $now);
	
	//have to run for each level, so get levels
	$levels = pmpro_getAllLevels(true, true);

	if(empty($levels))
		return;
		
	foreach($levels as $level)
	{
		//get options
		$options = pmpropbdp_getOptions($level->id);	
		if(!empty($options['renewal_days']))
			$date = date("Y-m-d", strtotime("+ " . $options['renewal_days'] . " days", $now));
		else
			$date = $today;
	
		//need to get all combos of pay cycle and period
		$sqlQuery = "SELECT DISTINCT(CONCAT(cycle_number, ' ', cycle_period)) FROM $wpdb->pmpro_memberships_users WHERE membership_id = '" . $level->id . "' AND cycle_number > 0 AND status = 'active'";		
		$combos = $wpdb->get_col($sqlQuery);
				
		if(empty($combos))
			continue;
		
		foreach($combos as $combo)
		{			
			//check if it's been one pay period since the last payment		
			/*
				- Check should create an invoice X days before expiration based on a setting on the levels page.
				- Set invoice date based on cycle and the day of the month of the member start date.
				- Send a reminder email Y days after initial invoice is created if it's still pending.
				- Cancel membership after Z days if invoice is not paid. Send email.
			*/
			//get all check orders still pending after X days
			$sqlQuery = "
				SELECT o1.id FROM
				    (SELECT id, user_id, timestamp
				    FROM {$wpdb->pmpro_membership_orders}
				    WHERE membership_id = $level->id
				        AND gateway = 'dragonpay' 
				        AND status IN('pending', 'success')
				    ) as o1

					LEFT OUTER JOIN 
					
					(SELECT id, user_id, timestamp
				    FROM {$wpdb->pmpro_membership_orders}
				    WHERE membership_id = $level->id
				        AND gateway = 'dragonpay' 
				        AND status IN('pending', 'success')
				    ) as o2

					ON o1.user_id = o2.user_id
					AND o1.timestamp < o2.timestamp
					OR (o1.timestamp = o2.timestamp AND o1.id < o2.id)
				WHERE
					o2.id IS NULL
					AND DATE_ADD(o1.timestamp, INTERVAL $combo) <= '" . $date . "'
			";
			
			if(defined('PMPRO_CRON_LIMIT'))
				$sqlQuery .= " LIMIT " . PMPRO_CRON_LIMIT;
			
			$orders = $wpdb->get_col($sqlQuery);
		
			if(empty($orders))
				continue;
			
			foreach($orders as $order_id)
			{
				$order = new MemberOrder($order_id);
				$user = get_userdata($order->user_id);
				$user->membership_level = pmpro_getMembershipLevelForUser($order->user_id);
				
				//check that user still has same level?
				if(empty($user->membership_level) || $order->membership_id != $user->membership_level->id)
					continue;
				
				//create new pending order
				$morder = new MemberOrder();
				$morder->user_id = $order->user_id;				
				$morder->membership_id = $user->membership_level->id;
				$morder->InitialPayment = $user->membership_level->billing_amount;
				$morder->PaymentAmount = $user->membership_level->billing_amount;
				$morder->BillingPeriod = $user->membership_level->cycle_period;
				$morder->BillingFrequency = $user->membership_level->cycle_number;
				$morder->subscription_transaction_id = $order->subscription_transaction_id;
				$morder->gateway = "dragonpay";
				$morder->setGateway();
				$morder->payment_type = "dragonpay";
				$morder->status = "pending";

				//get timestamp for new order
				$order_timestamp = strtotime("+" . $combo, $order->timestamp);
				
				//let's skip if there is already an order for this user/level/timestamp
				$sqlQuery = "SELECT id FROM $wpdb->pmpro_membership_orders WHERE user_id = '" . $order->user_id . "' AND membership_id = '" . $order->membership_id . "' AND timestamp = '" . date('d', $order_timestamp) . "' LIMIT 1";			
				$dupe = $wpdb->get_var("SELECT id FROM $wpdb->pmpro_membership_orders WHERE user_id = '" . $order->user_id . "' AND membership_id = '" . $order->membership_id . "' AND timestamp = '" . $order_timestamp . "' LIMIT 1");				
				if(!empty($dupe))
					continue;
				
				//save it
				$morder->process();
				$morder->saveOrder();

				//update the timestamp				
				$morder->updateTimestamp(date("Y", $order_timestamp), date("m", $order_timestamp), date("d", $order_timestamp));

				//send emails				
				$email = new PMProEmail();
				$email->template = "dragonpay_pending";
				$email->email = $user->user_email;
				$email->subject = sprintf(__("New Invoice for %s at %s", "pmpropbdp"), $user->membership_level->name, get_option("blogname"));
			}
		}
	}	
}
add_action('pmpropbdp_recurring_orders', 'pmpropbdp_recurring_orders');

/*
	Send reminder emails for pending invoices.
*/
function pmpropbdp_reminder_emails()
{
	global $wpdb;
	
	//make sure we only run once a day
	$now = current_time('timestamp');
	$today = date("Y-m-d", $now);
	
	//have to run for each level, so get levels
	$levels = pmpro_getAllLevels(true, true);
	
	if(empty($levels))
		return;
		
	foreach($levels as $level)
	{
		//get options
		$options = pmpropbdp_getOptions($level->id);	
		if(!empty($options['reminder_days']))
			$date = date("Y-m-d", strtotime("+ " . $options['reminder_days'] . " days", $now));
		else
			$date = $today;
	
		//need to get all combos of pay cycle and period
		$sqlQuery = "SELECT DISTINCT(CONCAT(cycle_number, ' ', cycle_period)) FROM $wpdb->pmpro_memberships_users WHERE membership_id = '" . $level->id . "' AND cycle_number > 0 AND status = 'active'";		
		$combos = $wpdb->get_col($sqlQuery);
				
		if(empty($combos))
			continue;
		
		foreach($combos as $combo)
		{	
			//get all check orders still pending after X days
			$sqlQuery = "
				SELECT id 
				FROM $wpdb->pmpro_membership_orders 
				WHERE membership_id = $level->id 
					AND gateway = 'dragonpay' 
					AND status = 'pending' 
					AND DATE_ADD(timestamp, INTERVAL $combo) <= '" . $date . "'
					AND notes NOT LIKE '%Reminder Sent:%' AND notes NOT LIKE '%Reminder Skipped:%'
				ORDER BY id
			";
						
			if(defined('PMPRO_CRON_LIMIT'))
				$sqlQuery .= " LIMIT " . PMPRO_CRON_LIMIT;

			$orders = $wpdb->get_col($sqlQuery);

			if(empty($orders))
				continue;
						
			foreach($orders as $order_id)
			{
				//get some data
				$order = new MemberOrder($order_id);
				$user = get_userdata($order->user_id);
				$user->membership_level = pmpro_getMembershipLevelForUser($order->user_id);
				
				//if they are no longer a member, let's not send them an email
				if(empty($user->membership_level) || empty($user->membership_level->ID) || $user->membership_level->id != $order->membership_id)
				{
					//note when we send the reminder
					$new_notes = $order->notes . "Reminder Skipped:" . $today . "\n";
					$wpdb->query("UPDATE $wpdb->pmpro_membership_orders SET notes = '" . esc_sql($new_notes) . "' WHERE id = '" . $order_id . "' LIMIT 1");

					continue;
				}

				//note when we send the reminder
				$new_notes = $order->notes . "Reminder Sent:" . $today . "\n";
				$wpdb->query("UPDATE $wpdb->pmpro_membership_orders SET notes = '" . esc_sql($new_notes) . "' WHERE id = '" . $order_id . "' LIMIT 1");

				//setup email to send
				$email = new PMProEmail();
				$email->template = "dragonpay_pending_reminder";
				$email->email = $user->user_email;
				$email->subject = sprintf(__("Reminder: New Invoice for %s at %s", "pmpropbdp"), $user->membership_level->name, get_option("blogname"));											
				//get body from template
				$email->body = file_get_contents(PMPRO_PAY_BY_dragonpay_DIR . "/email/" . $email->template . ".html");
				
				//setup more data
				$email->data = array(					
					"name" => $user->display_name, 
					"user_login" => $user->user_login,
					"sitename" => get_option("blogname"),
					"siteemail" => pmpro_getOption("from_email"),
					"membership_id" => $user->membership_level->id,
					"membership_level_name" => $user->membership_level->name,
					"membership_cost" => pmpro_getLevelCost($user->membership_level),								
					"login_link" => wp_login_url(pmpro_url("account")),
					"display_name" => $user->display_name,
					"user_email" => $user->user_email,								
				);
				
				$email->data["instructions"] = pmpro_getOption('instructions');
				$email->data["invoice_id"] = $order->code;
				$email->data["invoice_total"] = pmpro_formatPrice($order->total);
				$email->data["invoice_date"] = date(get_option('date_format'), $order->timestamp);
				$email->data["billing_name"] = $order->billing->name;
				$email->data["billing_street"] = $order->billing->street;
				$email->data["billing_city"] = $order->billing->city;
				$email->data["billing_state"] = $order->billing->state;
				$email->data["billing_zip"] = $order->billing->zip;
				$email->data["billing_country"] = $order->billing->country;
				$email->data["billing_phone"] = $order->billing->phone;
				$email->data["cardtype"] = $order->cardtype;
				$email->data["accountnumber"] = hideCardNumber($order->accountnumber);
				$email->data["expirationmonth"] = $order->expirationmonth;
				$email->data["expirationyear"] = $order->expirationyear;
				$email->data["billing_address"] = pmpro_formatAddress($order->billing->name,
																	 $order->billing->street,
																	 "", //address 2
																	 $order->billing->city,
																	 $order->billing->state,
																	 $order->billing->zip,
																	 $order->billing->country,
																	 $order->billing->phone);
				
				if($order->getDiscountCode())
					$email->data["discount_code"] = "<p>" . __("Discount Code", "pmpro") . ": " . $order->discount_code->code . "</p>\n";
				else
					$email->data["discount_code"] = "";
	
				//send the email
				$email->sendEmail();
			}
		}
	}		
}
add_action('pmpropbdp_reminder_emails', 'pmpropbdp_reminder_emails');

/*
	Cancel overdue members.
*/
function pmpropbdp_cancel_overdue_orders()
{
	global $wpdb;
	
	//make sure we only run once a day
	$now = current_time('timestamp');
	$today = date("Y-m-d", $now);
	
	//have to run for each level, so get levels
	$levels = pmpro_getAllLevels(true, true);
	
	if(empty($levels))
		return;
		
	foreach($levels as $level)
	{
		//get options
		$options = pmpropbdp_getOptions($level->id);	
		if(!empty($options['cancel_days']))
			$date = date("Y-m-d", strtotime("+ " . $options['cancel_days'] . " days", $now));
		else
			$date = $today;
	
		//need to get all combos of pay cycle and period
		$sqlQuery = "SELECT DISTINCT(CONCAT(cycle_number, ' ', cycle_period)) FROM $wpdb->pmpro_memberships_users WHERE membership_id = '" . $level->id . "' AND cycle_number > 0 AND status = 'active'";		
		$combos = $wpdb->get_col($sqlQuery);
				
		if(empty($combos))
			continue;
		
		foreach($combos as $combo)
		{	
			//get all dragonpay orders still pending after X days
			$sqlQuery = "
				SELECT id 
				FROM $wpdb->pmpro_membership_orders 
				WHERE membership_id = $level->id 
					AND gateway = 'dragonpay' 
					AND status = 'pending' 
					AND DATE_ADD(timestamp, INTERVAL $combo) <= '" . $date . "'
					AND notes NOT LIKE '%Cancelled:%' AND notes NOT LIKE '%Cancellation Skipped:%'
				ORDER BY id
			";
						
			if(defined('PMPRO_CRON_LIMIT'))
				$sqlQuery .= " LIMIT " . PMPRO_CRON_LIMIT;

			$orders = $wpdb->get_col($sqlQuery);

			if(empty($orders))
				continue;
						
			foreach($orders as $order_id)
			{		
				//get the order and user data
				$order = new MemberOrder($order_id);								
				$user = get_userdata($order->user_id);
				$user->membership_level = pmpro_getMembershipLevelForUser($order->user_id);
				
				//if they are no longer a member, let's not send them an email
				if(empty($user->membership_level) || empty($user->membership_level->ID) || $user->membership_level->id != $order->membership_id)
				{
					//note when we send the reminder
					$new_notes = $order->notes . "Cancellation Skipped:" . $today . "\n";
					$wpdb->query("UPDATE $wpdb->pmpro_membership_orders SET notes = '" . esc_sql($new_notes) . "' WHERE id = '" . $order_id . "' LIMIT 1");

					continue;
				}
				
				//cancel the order and subscription
				do_action("pmpro_membership_pre_membership_expiry", $order->user_id, $order->membership_id );
				
				//remove their membership
				pmpro_changeMembershipLevel(false, $order->user_id, 'expired');
				do_action("pmpro_membership_post_membership_expiry", $order->user_id, $order->membership_id );
				$send_email = apply_filters("pmpro_send_expiration_email", true, $order->user_id);
				if($send_email)
				{
					//send an email
					$pmproemail = new PMProEmail();
					$euser = get_userdata($order->user_id);
					$pmproemail->sendMembershipExpiredEmail($euser);
					if(current_user_can('manage_options'))
						printf(__("Membership expired email sent to %s. ", "pmpro"), $euser->user_email);
					else
						echo ". ";
				}
			}
		}
	}		
}
add_action('pmpropbdp_cancel_overdue_orders', 'pmpropbdp_cancel_overdue_orders');



/*
	Activation/Deactivation
*/
function pmpropbdp_activation()
{
	//schedule crons dragonpay
	wp_schedule_event(current_time('timestamp'), 'daily', 'pmpropbdp_cancel_overdue_orders');
	wp_schedule_event(current_time('timestamp')+1, 'daily', 'pmpropbdp_recurring_orders');
	wp_schedule_event(current_time('timestamp')+2, 'daily', 'pmpropbdp_reminder_emails');	

	//schedule crons check
	wp_schedule_event(current_time('timestamp'), 'daily', 'pmpropbcheck_cancel_overdue_orders');
	wp_schedule_event(current_time('timestamp')+1, 'daily', 'pmpropbcheck_recurring_orders');
	wp_schedule_event(current_time('timestamp')+2, 'daily', 'pmpropbcheck_reminder_emails');	


	do_action('pmpropbdp_activation');
}
function pmpropbdp_deactivation()
{
	//remove crons dragonpay
	wp_clear_scheduled_hook('pmpropbdp_cancel_overdue_orders');
	wp_clear_scheduled_hook('pmpropbdp_recurring_orders');
	wp_clear_scheduled_hook('pmpropbdp_reminder_emails');	


	//remove crons check
	wp_clear_scheduled_hook('pmpropbcheck_cancel_overdue_orders');
	wp_clear_scheduled_hook('pmpropbcheck_recurring_orders');
	wp_clear_scheduled_hook('pmpropbcheck_reminder_emails');	

	do_action('pmpropbdp_deactivation');
}
register_activation_hook(__FILE__, 'pmpropbdp_activation');
register_deactivation_hook(__FILE__, 'pmpropbdp_deactivation');

/*
Function to add links to the plugin row meta
*/
function pmpropbdp_plugin_row_meta($links, $file) {
	if(strpos($file, 'pmpro-dragonpay-gateway.php') !== false)
	{
		$new_links = array(
			'<a href="' . esc_url('http://www.paidmembershipspro.com/add-ons/plugins-on-github/pmpro-dragonpay-gateway/')  . '" title="' . esc_attr( __( 'View Documentation', 'pmpro' ) ) . '">' . __( 'Docs', 'pmpro' ) . '</a>',
			'<a href="' . esc_url('http://paidmembershipspro.com/support/') . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro' ) ) . '">' . __( 'Support', 'pmpro' ) . '</a>',
		);
		$links = array_merge($links, $new_links);
	}
	return $links;
}
add_filter('plugin_row_meta', 'pmpropbdp_plugin_row_meta', 10, 2);


