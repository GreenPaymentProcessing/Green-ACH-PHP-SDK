<?php
require_once '../Green.php';

use Green\ACHGateway as Gateway;

$ClientID = "your_client_id"; //Your numeric Client_ID
$ApiPassword = "your_api_password"; //Your system generated ApiPassword

$gateway = new Gateway($ClientID, $ApiPassword); //Create the gatway using the Client_ID and Password combination
$gateway->testMode(); //Put the Gateway into testing mode so calls go to the Sandbox and you won't get charged!

//Create a single transaction and get results back after acceptance
$nameFirst = "Testing";
$nameMiddleInit = "";
$nameLast = "Smith";
$email = 'test@test.test';
$phone = '323-232-3232';
$dob = "";
$last4SSN = "";
$address = "123 Testing Lane";
$city = "Testville";
$state = "GA";
$zip = "12345-1234";
$country = "US";
$routing = "000000000";
$account = "12345601";
$accountType = "PC"; /* Personal Checking */
$bankName = "Test Bank";
$bankCity = "";
$bankState = "";
$bankPhone = "";
$product = "Internal description of transaction";
$descriptor = "For Services Rendered";
$currency = "USD";
$amount = "123.45";
$date = date("m/d/Y");
$result = $gateway->singleCredit($nameFirst, $nameMiddleInit, $nameLast, $email, $phone, $dob, $last4SSN, $address, $city, $state, $zip, $country, $routing, $account, $accountType,
                                 $bankName, $bankCity, $bankState, $bankPhone, $product, $descriptor, $currency, $amount, $date);

/*
//Using the same code as above but calling "singleDebit" instead (like below) will insert the ACH transaction as a debit instead. Easy!
$result = $gateway->singleDebit($nameFirst, $nameMiddleInit, $nameLast, $email, $phone, $dob, $last4SSN, $address, $city, $state, $zip, $country, $routing, $account, $accountType,
                                $bankName, $bankCity, $bankState, $bankPhone, $product, $descriptor, $currency, $amount, $date);
*/

if($result) {
  //The call succeeded, let's parse it out
  if($result['Result'] == '0'){
    //A "Result" of 0 typically means success
    echo "Credit created with ID: " . $result['ACHTransaction_ID'] . "<br/>";
  } else {
    //Anything other than 0 specifies some kind of error.
    echo "Credit not created.<br/>Error Code: {$result['Result']}<br/>Error: {$result['ResultDescription']}<br/>";
  }

  echo "Full Return Details<br/><pre>".print_r($result, TRUE)."</pre>";
} else {
  //The call failed!
  echo "GATEWAY ERROR: " . $gateway->getLastError();
}

?>
