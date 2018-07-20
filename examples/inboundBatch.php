<?php

require_once '../Green.php';

use Green\ACHGateway as Gateway;

$ClientID = "your_client_id"; //Your numeric Client_ID
$ApiPassword = "your_api_password"; //Your system generated ApiPassword

$gateway = new Gateway($ClientID, $ApiPassword); //Create the gatway using the Client_ID and Password combination
$gateway->testMode(); //Put the Gateway into testing mode so calls go to the Sandbox and you won't get charged!

/*
 * The InboundBatch method of the ACHService API assumes the incoming text is in a Comma Delimited string format.
 * This format can be provided in many different ways but the information contained must match the minimum requirements
 * for your account as determined by Green Underwriting.
 *
 * The code below will make assumptions about minimum data just as an example of the different ways you might be able to provide data.
 */

/*
 * EXAMPLE 1: Provide CSV from file
 */
$dir = "/path/to/your/folder/";
$filename = "file.csv";
$path = $dir . $filename;
$data = file_get_contents($path, FALSE, null, 0, filesize($path));

$descripption = ""
$hasHeader = TRUE;
$delim = FALSE;
$result = $gateway->inboundBatch($description, $data, $hasHeader, $delim);
echo "<pre>" . print_r($result, TRUE) . "</pre>";


/*
 * Example 2: Provide CSV from PHP Array
 */
$data = array(
 array("TransactionType" "Currency",	"Amount",	"RoutingNumber", "AccountNumber",	"AccountType", "TransactionDate", "NameFirst", "NameMiddleInitial", "NameLast",	"EmailAddress",	"Phone", "Address", "City", "State",	"Zip", "Country", "Descriptor"),
 array("C", "USD",	"123.45",	"000000000", "10000001",	"PC", "7/19/2018", "John", "", "Doe",	"test@test.test",	"123-456-7891", "123 Testing Lane", "Testington", "CA",	"90210", "US", "Widgets Regional"),
 array("D", "USD",	"234.56",	"000000000", "10000002",	"PS", "7/19/2018", "Jane", "", "Smith",	"test@test.com",	"234-567-8910", "234 Main Street", "Testville", "GA",	"30040", "US", "Widgets Domestic"),
 array("D", "USD",	"345.67",	"000000000", "1234501",	"CC", "7/19/2018", "Joe", "A", "Jackson",	"test@test.net",	"345-678-9101", "345 Test Avenue", "Tester", "MN",	"12765", "US", "Widgets International")
);
$stringData = "";
foreach($data as $line){
  $stringData .= implode(",", $line);
}

$hasHeader = TRUE;
$delim = FALSE;
$result = $gateway->inboundBatch($description, $stringData, $hasHeader, $delim);
echo "<pre>" . print_r($result, TRUE) . "</pre>";
