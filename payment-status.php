[insert_php]

error_reporting(E_ALL); ini_set('display_errors', 1);

if(!(isset($_GET['status']) and isset($_GET['txnid']) and isset($_GET['refno']) and isset($_GET['message']) and isset($_GET['digest'])) ){

echo "Error - Missing Parameters.";

}

else

{

$txnid=$_GET['txnid']; $refno=$_GET['refno']; $status=$_GET['status']; $message=$_GET['message']; $password=pmpro_getOption("dragonpay_secret_key"); $digest = $_GET['digest'];

$merchantid  = pmpro_getOption("dragonpay_merchant_id");

if(sha1("$txnid:$refno:$status:$message:$password")!=$digest){
echo "Error - Digest Mismatch";
return;
}
if(false){
//TODO: Replace with your own enrollment confirmation page
header('Location: https://www.YourSite.com/members/enrollment-confirmation/?level=1&txnid='.$txnid.'&refno='.$refno.'&status='.$status.'&message='.$message.'&digest='.$digest);
}

	// post back to Dragonpay system to validate
	$url = 'https://gw.dragonpay.ph/MerchantRequest.aspx?op=GETSTATUS&merchantid='.$merchantid.'&merchantpwd='.$password.'&txnid='.$txnid;

	$environment = pmpro_getOption("gateway_environment");
	if("sandbox" === $environment || "beta-sandbox" === $environment)
	{
		$url = 'http://test.dragonpay.ph/MerchantRequest.aspx?op=GETSTATUS&merchantid='.$merchantid.'&merchantpwd='.$password.'&txnid='.$txnid;
	}

	// Get Request to DragonPay
	$status = file_get_contents($url);

$error_messages = array(
"S" => "Success",
"F" => "Failure",
"P" => "Pending",
"U" => "Unknown",
"R" => "Refund",
"K" => "Chargeback",
"V" => "Void",
"A" => "Authorized"
);

if(isset($error_messages[$status]))
$payment_status = $error_messages[$status];
else
$payment_status="Error";
			
echo "<ul>";
 	echo "<li><strong>Status:</strong> $payment_status</li>";
 	echo "<li><strong>Transaction ID:</strong> $txnid</li>";
 	echo "<li><strong>Refererence No.:</strong> $refno</li>";
echo "</ul>";

switch($status){

case 'S':

//TODO: Replace with your own invoice page

echo "Your payment has been accepted and successfully processed.<br>If your account is not activated within a few minutes, please contact the site owner.<br><a href='https://www.YourSite.com/members/invoice/?invoice=".$txnid."'>View Your Membership Invoice</a>";

break;

case 'P':

echo "Your Payment has been accepted  and successfully processed.<br> Your Membership will not activated until payment has been made. Please check your email for instructions.<br>";
echo '<a href="https://secure.dragonpay.ph/Bank/GetEmailInstruction.aspx?refno='.$refno.'">View DragonPay Payment instruction</a>';


break;

default:

echo "An error occurred during transaction. Your Payment was not accepted.";

}

}

[/insert_php]

&nbsp;