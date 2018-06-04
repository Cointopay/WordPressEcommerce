<?php

require_once 'c2p_options.php';

function c2pCurl($url, $apiKey, $post = false) {
	global $c2pOptions;	
		
	$curl = curl_init($url);
	$length = 0;
	if ($post)
	{	
		//curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
		$length = strlen($post);
	}
	
	$uname = base64_encode($apiKey);
	$header = array(
		'Content-Type: application/json',
		"Content-Length: $length",
		"Authorization: Basic $uname",
		);

	curl_setopt($curl, CURLOPT_PORT, 443);
	curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
	curl_setopt($curl, CURLOPT_TIMEOUT, 20);
	curl_setopt($curl, CURLOPT_VERBOSE, true);
	curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC ) ;
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1); // verify certificate
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2); // check existence of CN and verify that it matches hostname
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
	curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);
		
	$responseString = curl_exec($curl);
	
	if($responseString == false) {
		$response = curl_error($curl);
	} else {
		$response = $responseString;//json_decode($responseString, true);
	}
	curl_close($curl);
	return $response;
}
// $orderId: Used to display an orderID to the buyer. In the account summary view, this value is used to 
// identify a ledger entry if present.
//
// $price: by default, $price is expressed in the currency you set in c2p_options.php.  The currency can be 
// changed in $options.
//
// $posData: this field is included in status updates or requests to get an invoice.  It is intended to be used by
// the merchant to uniquely identify an order associated with an invoice in their system.  Aside from that, Cointopay does
// not use the data in this field.  The data in this field can be anything that is meaningful to the merchant.
//
// $options keys can include any of: 
// ('itemDesc', 'itemCode', 'notificationEmail', 'notificationURL', 'redirectURL', 'apiKey'
//		'currency', 'physical', 'fullNotifications', 'defaultCoin', 'buyerName', 
//		'buyerAddress1', 'buyerAddress2', 'buyerCity', 'buyerState', 'buyerZip', 'buyerEmail', 'buyerPhone')
// If a given option is not provided here, the value of that option will default to what is found in c2p_options.php
// (see api documentation for information on these options).
function c2pCreateInvoice($orderId, $price, $posData, $options = array()) {	
	global $c2pOptions;	
	
	$options = array_merge($c2pOptions, $options);	// $options override any options found in c2p_options.php
	
	$pos = array('posData' => $posData);
	if ($c2pOptions['verifyPos'])
		$pos['hash'] = crypt(serialize($posData), $options['apiKey']);
	$options['posData'] = json_encode($pos);
	
	$options['orderID'] = $orderId;
	$options['price'] = $price;
	
	$postOptions = array('orderID', 'itemDesc', 'itemCode', 'notificationEmail', 'notificationURL', 'redirectURL', 
		'posData', 'price', 'currency', 'physical', 'fullNotifications', 'defaultCoin', 'buyerName', 
		'buyerAddress1', 'buyerAddress2', 'buyerCity', 'buyerState', 'buyerZip', 'buyerEmail', 'buyerPhone');
	foreach($postOptions as $o)
		if (array_key_exists($o, $options))
			$post[$o] = $options[$o];
	$post = json_encode($post);
	
	//$response = c2pCurl('https://cointopay.com/REAPI?key='.$options['apiKey'], $options['apiKey'], $post);
	$response = c2pCurl('https://cointopay.com/REAPI?key='.$options['apiKey'].'&price='.$options['price'].'&AltCoinID='.$options['defaultCoin'].'&OrderID='.$options['orderID'].'&inputCurrency='.$options['currency'], $options['apiKey'], $post);
	
	//if (is_string($response))
		//return array('error' => $response);	

	return $response;
}

// Call from your notification handler to convert $_POST data to an object containing invoice data
function c2pVerifyNotification($apiKey = false) {
	global $c2pOptions;
	if (!$apiKey)
		$apiKey = $c2pOptions['apiKey'];		
	
	$post = file_get_contents("php://input");
	if (!$post)
		return array('error' => 'No post data');
		
	$json = json_decode($post, true);	
	if (is_string($json))
		return array('error' => $json); // error

	if (!array_key_exists('posData', $json)) 
		return array('error' => 'no posData');
		
	// decode posData
	$posData = json_decode($json['posData'], true);
	if($c2pOptions['verifyPos'] and $posData['hash'] != crypt(serialize($posData['posData']), $apiKey)) 
		return array('error' => 'authentication failed (bad hash)');
	$json['posData'] = $posData['posData'];
		
	return $json;
}

// $options can include ('apiKey')
function c2pGetInvoice($invoiceId, $apiKey=false) {
	global $c2pOptions;
	if (!$apiKey)
		$apiKey = $c2pOptions['apiKey'];		

	$response = c2pCurl('https://cointopay.com/REAPI?key='.$apiKey,$invoiceId);
	if (is_string($response))
		return array('error' => $response); 
	//decode posData
	$response['posData'] = json_decode($response['posData'], true);
	$response['posData'] = $response['posData']['posData'];

	return $response;	
}

?>