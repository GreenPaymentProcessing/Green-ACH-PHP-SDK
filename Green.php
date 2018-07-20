<?php
namespace Green;

/**
 * A basic class to create calls to the Green Payment Processing API
 *
 * Used to easily generate API calls by instead calling pre-made PHP functions of this class with your check data.
 * The class then handles generating the API call automatically
 * TERMS:
 *  EndPoint - the mode in which calls are made. You can make calls to our "test" sandbox or directly to our "live" system.
 */
class ACHGateway
{
  private $client_id = "";
  private $api_pass = "";
  private $endpoint = "";
  private $error = "";

  /** @var bool $live Specifies whether this Gateway should make calls to the live API or to the Sandbox */
  private $live = false;

  const ENDPOINT = array(
    "test" => "https://cpsandbox.com/ACHService.asmx",
    "live" => "https://greenbyphone.com/ACHService.asmx"
  );

  /***
  Standard constructor
  ***/
  function __construct($client_id, $api_pass, $live = true){
    $this->client_id = $client_id;
    $this->api_pass = $api_pass;
    $this->live = $live;
    $this->setEndpoint();
  }

  public function setClientID($id) {
    $this->$client_id = $id;
  }

  public function getClientID(){
    return $this->client_id;
  }

  public function setApiPassword($pass){
    $this->api_pass = $pass;
  }

  public function getApiPassword() {
    return $this->api_pass;
  }

  public function setEndpoint() {
    if($this->live){
      $this->endpoint = self::ENDPOINT['live'];
    } else {
      $this->endpoint = self::ENDPOINT['test'];
    }
  }

  public function getEndpoint(){
    return $this->endpoint;
  }

  public function liveMode(){
    $this->live = true;
    $this->setEndpoint();
  }

  public function testMode(){
    $this->live = false;
    $this->setEndpoint();
  }

  function __toString(){
    $str  = "Gateway Type: POST\n";
    $str .= "Endpoint: ".$this->getEndpoint()."\n";
    $str .= "Client ID: ".$this->getClientID()."\n";
    $str .= "ApiPassword: ".$this->getApiPassword()."\n";

    return $str;
  }

  function toString($html = TRUE){
    if($html){
      return nl2br($this->__toString());
    }

    return $this->__toString();
  }

  private function setLastError($error){
    $this->error = $error;
  }

  public function getLastError(){
    return $this->error;
  }



  /**
   * A default method used to generate API Calls
   *
   * This method is used internally by all other methods to generate API calls easily. This method can be used externally to create a request to any API method available if we haven't created a simple method for it in the class
   *
   * @param string  $method   The name of the API method to call at the endpoint (ex. OneTimeDraftRTV, CheckStatus, etc.)
   * @param array   $options  An array of "APIFieldName" => "Value" pairs. Must include the Client_ID and ApiPassword variables
   *
   * @return mixed            Returns associative array or delimited string on success OR cURL error string on failure
   */
  function request($method, $options, $resultArray = array()) {
    if(!isset($options['Client_ID'])){
      $options["Client_ID"] = $this->getClientID();
    }

    if(!isset($options['ApiPassword'])){
      $options['ApiPassword'] = $this->getApiPassword();
    }

    //Test whether they want the delimited return or not to start with
    $returnDelim = ($options['x_delim_data'] === "TRUE");
    //Now let's actually set delim to TRUE because we always want to get a delimited string back from the API so we can parse it
    $options["x_delim_data"] = "TRUE";

    try {
      $ch = curl_init();

      if($ch === FALSE){
        throw new \Exception('Failed to initialize cURL');
      }

      curl_setopt($ch, CURLOPT_URL, $this->getEndpoint() . '/' . $method);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($options));

      $response = curl_exec($ch);

      if($response === FALSE){
        throw new \Exception(curl_error($ch), curl_errno($ch));
      }

      curl_close($ch);
    } catch(\Exception $e) {
      $this->setLastError(sprintf('Curl failed with error #%d: %s', $e->getCode(), $e->getMessage()));
      return false;
    }

    try {
      if($returnDelim){
        return $response;
      } else {
        return $this->resultToArray($response, $options['x_delim_char'], $resultArray);
      }
    } catch(\Exception $e){
      $this->setLastError("An error occurred while attempting to parse the API result: ". $e->getMessage());
      return false;
    }
  }

  function requestSOAP($method, $options){
    if(!isset($options['Client_ID'])){
      $options["Client_ID"] = $this->getClientID();
    }

    if(!isset($options['ApiPassword'])){
      $options['ApiPassword'] = $this->getApiPassword();
    }

    //Test whether they want the delimited return or not to start with
    $returnDelim = ($options['x_delim_data'] === "TRUE");
    //Now let's actually set delim to FALSE because calling by SOAP requires we get a response in XML
    $options["x_delim_data"] = "";

    $client = new \SoapClient($this->getEndpoint() . "?wsdl", array("trace" => 1));
    try {
      $result = $client->__soapCall($method, array($options));

      $resultArray = (array) $result;
      $resultInnerArray = (array) reset($resultArray); //cheat to return the first element in the array without needing the key for it

      if($returnDelim){
        //We need to take it's arguments and turn them into a delimited string
        return implode($options['x_delim_char'], array_values($resultInnerArray));
      } else {
        //Return it as an array
        return $resultInnerArray;
      }
    } catch(\Exception $e){
      $this->setLastError(sprintf('SOAP Request failed with error #%d: %s <br/> %s <br/> %s', $e->getCode(), $e->getMessage(), $client->__getLastRequest(), $client->__getLastResponse()));
      return false;
    }
  }

  /**
   * Function takes result string from API and parses into PHP associative Array
   *
   * If a return is specified to be returned as delimited, it will return the string. Otherwise, this function will be called to
   * return the result as an associative array in the format specified by the API documentation.
   *
   * @param string  $result       The result string as returned by cURL
   * @param string  $delim_char   The character used to delimit the string in cURL
   * @param array   $keys         An array containing the key names for the result variable as specified by the API docs
   *
   * @return array                Associative array of key=>values pair described by the API docs as the return for the called method
   */
  private function resultToArray($result, $delim_char, $keys){
    $split = explode($delim_char, $result);
    $resultArray = array();
    foreach ($keys as $key => $keyName) {
      $resultArray[$keyName] = $split[$key];
    }

    return $resultArray;
  }

  /**
   * Inserts a single ACH Credit
   *
   * Inserts a single ACH Credit from your merchant account to a customer's account for the specified amount/date.
   *
   * @param string  $nameFirst      Customer's first name on account or, if a business, the full business name.
   * @param string  $nameMiddleInit Customer's middle initial
   * @param string  $nameLast       Customer's last name
   * @param string  $email          Customer's email address. If provided, will be notified with receipt of payment. If not provided, customer will be notified via US Mail at additional cost to your Green Account
   * @param string  $phone          Customer's 10-digit US phone number in the format ###-###-####
   * @param string  $dob            Customer's date of birth in the format MM/DD/YYYY
   * @param string  $last4SSN       Customer's last 4 digits of their Social Security Number
   * @param string  $address        Customer's street number and street name
   * @param string  $city           Customer's city name
   * @param string  $state          Customer's 2-character state abbreviation (ex. NY, CA, GA, etc.)
   * @param string  $zip            Customer's 5-digit or 9-digit zip code in the format ##### or #####-####
   * @param string  $country        Customer's 2-character country code, ex. "US"
   * @param string  $routing        Customer's 9-digit bank routing number
   * @param string  $account        Customer's bank account number
   * @param string  $accountType    A two-character account type descriptor: PC - personal checking, PS - personal savings, CC - commercial checking
   * @param string  $bankName       The customer's Bank Name (ex. Wachovia, BB&T, etc.)
   * @param string  $bankCity       The customer's bank's city
   * @param string  $bankState      The customer's bank's state abbreviation
   * @param string  $bankPhone      The customer's bank's phone
   * @param string  $product        Memo to appear on the transaction in the System Portal
   * @param string  $descriptor     Line that appears on the bank transaction
   * @param string  $currency       The currency descriptor for the transaction = 'USD'
   * @param string  $amount         Check amount in the format ##.##. Do not include monetary symbols
   * @param string  $date           The transaction date
   * @param bool    $delim          True if you want the result returned character delimited. Defaults to false, which returns results in an associative array format
   * @param string  $delim_char     Defaults to "," but can be set to any character you wish to delimit. Examples being "|" or "."
   *
   * @return mixed                  Returns associative array or delimited string on success OR cURL error string on failure
   */
  public function singleCredit($nameFirst, $nameMiddleInit, $nameLast, $email, $phone, $dob, $last4SSN, $address, $city, $state, $zip, $country, $routing, $account, $accountType,
  $bankName, $bankCity, $bankState, $bankPhone, $product, $descriptor, $currency, $amount, $date, $delim = FALSE, $delim_char = ",")
  {
    return $this->request("SingleCreditTransaction", array(
      "Currency" => $currency,
      "Amount" => $amount,
      "RoutingNumber" => $routing,
      "AccountNumber" => $account,
      "AccountType" => $accountType,
      "TransactionDate" => $date,
      "NameFirst" => $nameFirst,
      "NameMiddleInitial" => $nameMiddleInit,
      "NameLast" => $nameLast,
      "EmailAddress" => $email,
      "Phone" => $phone,
      "DateOfBirth" => $dob,
      "Last4SSN" => $last4SSN,
      "Address" => $address,
      "City" => $city,
      "State" => $state,
      "Zip" => $zip,
      "Country" => $country,
      "BankName" => $bankName,
      "BankCity" => $bankCity,
      "BankState" => $bankState,
      "BankPhone" => $bankPhone,
      "Product" => $product,
      "Descriptor" => $descriptor,
      "x_delim_data" => ($delim) ? "True" : "",
      "x_delim_char" => $delim_char,
    ), array(
      "Result",
      "ResultDescription",
      "ACHTransaction_ID"
    ));
  }

  /**
   * Inserts a single ACH Debit
   *
   * Inserts a single ACH Debit from a customer's account to your merchant account for the specified amount/date.
   *
   * @param string  $nameFirst      Customer's first name on account or, if a business, the full business name.
   * @param string  $nameMiddleInit Customer's middle initial
   * @param string  $nameLast       Customer's last name
   * @param string  $email          Customer's email address. If provided, will be notified with receipt of payment. If not provided, customer will be notified via US Mail at additional cost to your Green Account
   * @param string  $phone          Customer's 10-digit US phone number in the format ###-###-####
   * @param string  $dob            Customer's date of birth in the format MM/DD/YYYY
   * @param string  $last4SSN       Customer's last 4 digits of their Social Security Number
   * @param string  $address        Customer's street number and street name
   * @param string  $city           Customer's city name
   * @param string  $state          Customer's 2-character state abbreviation (ex. NY, CA, GA, etc.)
   * @param string  $zip            Customer's 5-digit or 9-digit zip code in the format ##### or #####-####
   * @param string  $country        Customer's 2-character country code, ex. "US"
   * @param string  $routing        Customer's 9-digit bank routing number
   * @param string  $account        Customer's bank account number
   * @param string  $accountType    A two-character account type descriptor: PC - personal checking, PS - personal savings, CC - commercial checking
   * @param string  $bankName       The customer's Bank Name (ex. Wachovia, BB&T, etc.)
   * @param string  $bankCity       The customer's bank's city
   * @param string  $bankState      The customer's bank's state abbreviation
   * @param string  $bankPhone      The customer's bank's phone
   * @param string  $product        Memo to appear on the transaction in the System Portal
   * @param string  $descriptor     Line that appears on the bank transaction
   * @param string  $currency       The currency descriptor for the transaction = 'USD'
   * @param string  $amount         Check amount in the format ##.##. Do not include monetary symbols
   * @param string  $date           The transaction date
   * @param bool    $delim          True if you want the result returned character delimited. Defaults to false, which returns results in an associative array format
   * @param string  $delim_char     Defaults to "," but can be set to any character you wish to delimit. Examples being "|" or "."
   *
   * @return mixed                  Returns associative array or delimited string on success OR cURL error string on failure
   */
  public function singleDebit($nameFirst, $nameMiddleInit, $nameLast, $email, $phone, $dob, $last4SSN, $address, $city, $state, $zip, $country, $routing, $account, $accountType,
  $bankName, $bankCity, $bankState, $bankPhone, $product, $descriptor, $currency, $amount, $date, $delim = FALSE, $delim_char = ",")
  {
    return $this->request("SingleDebitTransaction", array(
      "Currency" => $currency,
      "Amount" => $amount,
      "RoutingNumber" => $routing,
      "AccountNumber" => $account,
      "AccountType" => $accountType,
      "TransactionDate" => $date,
      "NameFirst" => $nameFirst,
      "NameMiddleInitial" => $nameMiddleInit,
      "NameLast" => $nameLast,
      "EmailAddress" => $email,
      "Phone" => $phone,
      "DateOfBirth" => $dob,
      "Last4SSN" => $last4SSN,
      "Address" => $address,
      "City" => $city,
      "State" => $state,
      "Zip" => $zip,
      "Country" => $country,
      "BankName" => $bankName,
      "BankCity" => $bankCity,
      "BankState" => $bankState,
      "BankPhone" => $bankPhone,
      "Product" => $product,
      "Descriptor" => $descriptor,
      "x_delim_data" => ($delim) ? "True" : "",
      "x_delim_char" => $delim_char,
    ), array(
      "Result",
      "ResultDescription",
      "ACHTransaction_ID"
    ));
  }


  /**
   * Return the status results for a transaction that was previously input
   *
   * Will return a status string that contains the results of processing, return status and dates, and other relevant information
   *
   * @param string  $txn_id         The numeric Transaction_ID of the previously entered transaction you want the status for
   * @param bool    $delim          True if you want the result returned character delimited. Defaults to false, which returns results in an associative array format
   * @param string  $delim_char     Defaults to "," but can be set to any character you wish to delimit. Examples being "|" or "."
   *
   * @return mixed                  Returns associative array or delimited string on success OR cURL error string on failure
   */
  public function transactionStatus($txn_id, $delim = FALSE, $delim_char = ","){
    return $this->request("TransactionStatus", array(
      "Transaction_ID" => $txn_id,
      "x_delim_data" => ($delim) ? "TRUE" : "",
      "x_delim_char" => $delim_char
    ), array(
      "Result",
      "ResultDescription",
      "ACHTransaction_ID",
      "ACHTransactionType",
      "Currency",
      "Amount",
      "Routing",
      "Account",
      "AccountType",
      "TransactionDate",
      "Name",
      "SameDay",
      "Processed",
      "ProcessedTime",
      "Returned",
      "ReturnedTime",
    ));
  }

  /**
   * Voids a previously entered transaction
   *
   * This function allows you to cancel any previously entered transaction as long as it has NOT already been processed.
   * NOTE: Transactions can only be voided before they are processed.
   *
   * @param string  $txn_id         The numeric Transaction_ID of the previously entered transaction you want to void
   * @param bool    $delim          True if you want the result returned character delimited. Defaults to false, which returns results in an associative array format
   * @param string  $delim_char     Defaults to "," but can be set to any character you wish to delimit. Examples being "|" or "."
   *
   * @return mixed                  Returns associative array or delimited string on success OR cURL error string on failure
   */
  public function voidTransaction($txn_id, $delim = FALSE, $delim_char = ","){
    return $this->request("VoidTransaction", array(
      "Transaction_ID" => $txn_id,
      "x_delim_data" => ($delim) ? "TRUE" : "",
      "x_delim_char" => $delim_char
    ), array(
      "Result",
      "ResultDescription",
      "ACHTransaction_ID"
    ));
  }

  /**
   * Issue a refund for a single transaction previously entered
   *
   * Allows you to reverse a previous transaction as a refund
   *
   * @param string  $txn_id		      The numeric Transaction_ID of the previously entered transaction you want to refund
   * @param bool    $delim          True if you want the result returned character delimited. Defaults to false, which returns results in an associative array format
   * @param string  $delim_char     Defaults to "," but can be set to any character you wish to delimit. Examples being "|" or "."
   *
   * @return mixed                  Returns associative array or delimited string on success OR cURL error string on failure
   */
  public function refundTransaction($txn_id, $delim = FALSE, $delim_char = ","){
    return $this->request("RefundTransaction", array(
      "Transaction_ID" => $txn_id,
      "x_delim_data" => ($delim) ? "TRUE" : "",
      "x_delim_char" => $delim_char
    ), array(
      "Result",
      "ResultDescription",
      "ACHTransaction_ID",
      "RefundACHTransaction_ID"
	 ));
  }


  /**
   * Run multiple transactions at the same time
   *
   * Allows you to take a Comma Delimited string from a CSV file or other method and insert multiple transactions
   *
   * @param string  $description    A description for the upload to be used to identify it in your portal in the future.
   * @param string  $csv            The textual, comma delimited data for the transactions
   * @param bool    $hasHeader      True if the $csv data contains a header row. If this is True, the first row of data will be ignored by the API.
   * @param bool    $delim          True if you want the result returned character delimited. Defaults to false, which returns results in an associative array format
   * @param string  $delim_char     Defaults to "," but can be set to any character you wish to delimit. Examples being "|" or "."
   *
   * @return mixed                  Returns associative array or delimited string on success OR cURL error string on failure
   */
   public function inboundBatch($description, $csv, $hasHeader, $delim = FALSE, $delim_char = ","){
     return $this->request("inboundBatch", array(
       "Description" => $description,
       "FileText" => $csv,
       "HasHeader" => ($hasHeader) ? "TRUE" : ""
       "x_delim_data" => ($delim) ? "TRUE" : "",
       "x_delim_char" => $delim_char
     ), array(
       "Result",
       "ResultDescription",
       "ACHInboundBatch_ID"
     ));
   }

}
