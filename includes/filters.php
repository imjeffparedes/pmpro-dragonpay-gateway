<?php

/*
	Append remaining days to membership when processed by the postback for DragonPay.
*/
function pmpro_dragonpaypostback_level_extend_memberships($level, $user_id)
{		
	global $pmpro_msg, $pmpro_msgt;

	//does this level expire? are they an existing user of this level?
	if(!empty($level) && !empty($level->expiration_number) && pmpro_hasMembershipLevel($level->id, $user_id))
	{
		//get the current enddate of their membership
		$user_level = pmpro_getMembershipLevelForUser($user_id);		
		$expiration_date = $user_level->enddate;

		//calculate days left
		$todays_date = current_time('timestamp');
		$time_left = $expiration_date - $todays_date;

		//time left?
		if($time_left > 0)
		{
			//convert to days and add to the expiration date (assumes expiration was 1 year)
			$days_left = floor($time_left/(60*60*24));

			//figure out days based on period
			if($level->expiration_period == "Day")
				$total_days = $days_left + $level->expiration_number;
			elseif($level->expiration_period == "Week")
				$total_days = $days_left + $level->expiration_number * 7;
			elseif($level->expiration_period == "Month")
				$total_days = $days_left + $level->expiration_number * 30;
			elseif($level->expiration_period == "Year")
				$total_days = $days_left + $level->expiration_number * 365;

			//update number and period
			$level->expiration_number = $total_days;
			$level->expiration_period = "Day";
		}
	}

	return $level;
}
add_filter("pmpro_dragonpaypostback_level", "pmpro_dragonpaypostback_level_extend_memberships", 10, 2);

/*
	If checking out for the same level, keep your old startdate.
	Added with 1.5.5
*/
function pmpro_dragonpay_checkout_start_date_keep_startdate($startdate, $user_id, $level)
{			
	if(pmpro_hasMembershipLevel($level->id, $user_id))
	{
		global $wpdb;
		$sqlQuery = "SELECT startdate FROM $wpdb->pmpro_memberships_users WHERE user_id = '" . esc_sql($user_id) . "' AND membership_id = '" . esc_sql($level->id) . "' AND status = 'active' ORDER BY id DESC LIMIT 1";		
		$old_startdate = $wpdb->get_var($sqlQuery);
		
		if(!empty($old_startdate))
			$startdate = "'" . $old_startdate . "'";
	}
	
	return $startdate;
}
add_filter("pmpro_dragonpay_checkout_start_date", "pmpro_dragonpay_checkout_start_date_keep_startdate", 10, 3);