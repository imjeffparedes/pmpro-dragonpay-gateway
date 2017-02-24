<?php
/*******************************************
This is the postback for DragonPay response.
*******************************************/
error_reporting(E_ALL); ini_set('display_errors', 1);

$logs = '========= Log Start ['.date("l jS \of F Y h:i:s A").'] ==========\n';

echo 'result=';

$logs.='Look for parameters\n';

if(  !(isset($_POST['status']) and isset($_POST['txnid']) and isset($_POST['refno']) and isset($_POST['message']) and isset($_POST['digest'])) ){

	$logs.='Failed. One or more parameters is missing.\n';
	echo "MISSING_PARAMETERS";
}
else
{
	$txnid=$_POST['txnid']; 
	$refno=$_POST['refno']; 
	$status=$_POST['status']; 
	$message=$_POST['message'];
	$digest=$_POST['digest'];

	$logs.='txnid='.$txnid.':';
	$logs.='refno='.$refno.':';
	$logs.='message='.$message.':';
	$logs.='status='.$status.':';
	$logs.='digest='.$digest.'\n';

	$merchantid  = pmpro_getOption("dragonpay_merchant_id");
	$merchantpwd = pmpro_getOption("dragonpay_secret_key");


	$logs.='merchantid='.$merchantid.':';
	$logs.='merchantpwd='.$merchantpwd.'\n';

	// Check digest authentication here
	$digest_str = $txnid.':'.$refno.':'.$status.':'.$message.':'.$merchantpwd;
	$digest_test = sha1($digest_str);

	$logs.='Checking digest: sha1('.$digest_str.') = '.$digest_test.'\n';

	if( $digest_test != $digest){
		$logs.='Failed digest authentication.\n';
		echo "ACTIVATE_FAILED_DIGEST_MISMATCH";
		return;
	}

	// post back to Dragonpay system to validate
	$url = 'https://gw.dragonpay.ph/MerchantRequest.aspx?op=GETSTATUS&merchantid='.$merchantid.'&merchantpwd='.$merchantpwd.'&txnid='.$txnid;

	$environment = pmpro_getOption("gateway_environment");
	if("sandbox" === $environment || "beta-sandbox" === $environment)
	{
		$url = 'http://test.dragonpay.ph/MerchantRequest.aspx?op=GETSTATUS&merchantid='.$merchantid.'&merchantpwd='.$merchantpwd.'&txnid='.$txnid;
	}

	$logs.='Check status of transaction.';
	$logs.='url = '.$url.'\n';

	// Get Request to DragonPay
	$status = file_get_contents($url);

	// Print status of request
	$status_messages = array(
		"S" => "Success",
		"F" => "Failure",
		"P" => "Pending",
		"U" => "Unknown",
		"R" => "Refund",
		"K" => "Chargeback",
		"V" => "Void",
		"A" => "Authorized"
		);
	
	if(isset($status_messages[$status])){
		$payment_status = $status_messages[$status];
		$logs.=$payment_status.'\n';
	}else{
		$logs.='ERROR_UNKNOWN_STATUS\n';
		echo "ERROR_UNKNOWN_STATUS";
		return;
	}

	$logs.='Updating order.\n';

	switch($status){

		case 'S':

			$logs.='Activate order.\n';

			//initial payment, get the order
			$morder = new MemberOrder( $txnid );

			//No order?
			if ( empty( $morder ) || empty( $morder->id ) ) {
				$logs.='Failed. Order not found.\n';
				echo 'ERROR_UNKNOWN_ORDER';
				return;
			}
			//get some more order info
			$morder->getMembershipLevel();
			$morder->getUser();




			//update membership
			global $wpdb;


			//set the start date to current_time('timestamp') but allow filters  (documented in preheaders/checkout.php)
			$startdate = apply_filters( "pmpro_checkout_start_date", "'" . current_time( 'mysql' ) . "'", $morder->user_id, $morder->membership_level );

			//If checking out for the same level, keep your old startdate.
			$startdate = pmpro_dragonpay_checkout_start_date_keep_startdate($startdate, $morder->user_id , $morder->membership_level);

			$logs.='Set startdate to '.$startdate.'\n';

			//check if last order is active
			$old_morder = new MemberOrder();
			$old_morder->getLastMemberOrder($morder->user_id, 'success');

			$level = $morder->membership_level;

			$logs.='Get membership_level.\n';
			$logs.=json_encode($morder->membership_level).'\n';
			$morder->membership_level = pmpro_dragonpaypostback_level_extend_memberships($morder->membership_level,$morder->user_id );

			
			//fix expiration date
			if ( ! empty( $morder->membership_level->expiration_number ) ) {
				$enddate = "'" . date_i18n( "Y-m-d", strtotime( "+ " . $morder->membership_level->expiration_number . " " . $morder->membership_level->expiration_period, current_time( "timestamp" ) ) ) . "'";
			} else {
				$enddate = "NULL";
			}

			$logs.="Membership expiry date: ".$morder->membership_level->expiration_number.' '.$morder->membership_level->expiration_period.'\n';

			//get discount code
			$morder->getDiscountCode();
			if ( ! empty( $morder->discount_code ) ) {
				//update membership level
				$morder->getMembershipLevel( true );
				$discount_code_id = $morder->discount_code->id;
			} else {
				$discount_code_id = "";
			}
			$logs.='Used discount_code_id to '.$discount_code_id.'\n';


			//custom level to change user to
			$custom_level = array(
				'user_id'         => $morder->user_id,
				'membership_id'   => $morder->membership_level->id,
				'code_id'         => $discount_code_id,
				'initial_payment' => $morder->membership_level->initial_payment,
				'billing_amount'  => $morder->membership_level->billing_amount,
				'cycle_number'    => $morder->membership_level->cycle_number,
				'cycle_period'    => $morder->membership_level->cycle_period,
				'billing_limit'   => $morder->membership_level->billing_limit,
				'trial_amount'    => $morder->membership_level->trial_amount,
				'trial_limit'     => $morder->membership_level->trial_limit,
				'startdate'       => $startdate,
				'enddate'         => $enddate
			);

			global $pmpro_error;
			if ( ! empty( $pmpro_error ) ) {
				$logs.='Error on pmpro. $pmpro_error.\n';
				echo $pmpro_error;
				return;
			}

			//change level and continue "checkout"
			if ( pmpro_changeMembershipLevel( $custom_level, $morder->user_id ) !== false ) {
				//update order status and transaction ids
				$morder->status                 = "success";
				$morder->payment_transaction_id = 'DRAGONPAY'.$txnid;

				$morder->subscription_transaction_id = "";
				
				$morder->saveOrder();

				$logs.='Order saved with payment transaction id '.$morder->payment_transaction_id.'\n';

				//add discount code use
				if ( ! empty( $discount_code ) && ! empty( $use_discount_code ) ) {

					$wpdb->query(
						$wpdb->prepare(
							"INSERT INTO {$wpdb->pmpro_discount_codes_uses} 
								( code_id, user_id, order_id, timestamp ) 
								VALUES( %d, %d, %s, %s )",
							$discount_code_id),
							$morder->user_id,
							$morder->id,
							current_time( 'mysql' )
						);
				}


				//hook
				do_action( "pmpro_after_checkout", $morder->user_id );

				//setup some values for the emails
				if ( ! empty( $morder ) ) {
					$invoice = new MemberOrder( $morder->id );
				} else {
					$invoice = null;
				}

				$user                   = get_userdata( $morder->user_id );
				$user->membership_level = $morder->membership_level;        //make sure they have the right level info

				//send email to member
				$pmproemail = new PMProEmail();
				$pmproemail->sendCheckoutEmail( $user, $invoice );

				//send email to admin
				$pmproemail = new PMProEmail();
				$pmproemail->sendCheckoutAdminEmail( $user, $invoice );

				$logs.='Activation OK.\n';
				echo "OK";

			} else {	
				echo 'ERROR_FAILED_UPDATE_LEVEL';
			}
	
		break;
		case 'P':
			$logs.='Update order.\n';

			//initial payment, get the order
			$morder = new MemberOrder( $txnid );

			//No order?
			if ( empty( $morder ) || empty( $morder->id ) ) {
				$logs.='Failed. Order not found.\n';
				echo 'ERROR_UNKNOWN_ORDER';
				return;
			}
			//get some more order info
			$morder->getMembershipLevel();
			$morder->getUser();

			//update order status and transaction ids
			$morder->status                 = "pending";

			$morder->subscription_transaction_id = "";
			
			$morder->saveOrder();

				$logs.='Update to pending OK.\n';
		break;

		default:

			$logs.='Failed. UPDATE_FAILED_'.strtoupper($payment_status).'_STATUS'.'\n';
			echo "UPDATE_FAILED_".strtoupper($payment_status).'_STATUS';

		break;
	}


}



	$logs.='=========LOG EXIT===========\n';


	$myfile = fopen(dirname(__FILE__) . "/log.txt", "a") or die("Unable to open file!");
	fwrite($myfile, $logs);
	fclose($myfile);

