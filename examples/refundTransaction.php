<?php
require_once '../Green.php';


use Green\ACHGateway as Gateway;


$ClientID = "your_client_id"; //Your numeric Client_ID
$ApiPassword = "your_api_password"; //Your system generated ApiPassword


$gateway = new Gateway($ClientID, $ApiPassword); //Create the gatway using the Client_ID and Password combination
$gateway->testMode(); //Put the Gateway into testing mode so calls go to the Sandbox and you won't get charged!



//Refund check and get results back after verification in array format

$txn_id = '12322';
$delim  = FALSE;
$delim_char = ',';
$result = $gateway->refundTransaction($txn_id, $delim, $delim_char);



if($result) {
  //The call succeeded, let's parse it out
  if($result['Result'] == '0'){
    //A "Result" of 0 typically means success
    echo "Refund created with ID: " . $result['RefundACHTransaction_ID'] . "<br/>";
  } else {
    //Anything other than 0 specifies some kind of error.
    echo "Refund not created.<br/>Error Code: {$result['Result']}<br/>Error: {$result['ResultDescription']}<br/>";
  }

  echo "Full Return Details<br/><pre>".print_r($result, TRUE)."</pre>";
} else {
  //The call failed!
  echo "GATEWAY ERROR: " . $gateway->getLastError();

}

?>
