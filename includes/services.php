<?php
/**
* Added Adjax call for dragonpay
*
**/

function pmpro_wp_ajax_dragonpaypostback()
{
	require_once(dirname(__FILE__) . "/../services/dragonpaypostback.php");	
	exit;	
}
add_action('wp_ajax_nopriv_dragonpaypostback', 'pmpro_wp_ajax_dragonpaypostback');
add_action('wp_ajax_dragonpaypostback', 'pmpro_wp_ajax_dragonpaypostback');