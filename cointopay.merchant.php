<?php
/**
  * Plugin Name: Cointopay Payment Gateway for WP eCommerce
  * Plugin URI: http://wpecommerce.org/
  * Description: A plugin that provides a Cointopay Payment Gateway.
  * Version: 1.0
  * Author: WP eCommerce
  * Author URI: http://wpecommerce.org/
  * Text Domain: wp-e-commerce
  **/

/**
 * WP_eCommerce
 *
 * Main WPEC Plugin Class
 *
 * @package wp-e-commerce
 */
require_once(dirname(__FILE__) . "/includes/cointopay/c2p_lib.php");
$num = 10;
$nzshpcrt_gateways[$num] = array('name' => 'Cointopay International B.V.',
'internalname' => 'wpsc_merchant_cointopay',
'function' => 'gateway_cointopay',
'form' => 'form_cointopay',
'submit_function' => "submit_cointopay",
);
function debuglog($contents)
{
	error_log($contents);
}
function wpsc_merchant_cointopay_activate() {

    $default_payment_gateways_names = array(
		'wpsc_merchant_cointopay' => '',
	);

	$existing_payment_gateways_names = get_option( 'payment_gateway_names' );

	$new_payment_gateways_name = array_merge( $default_payment_gateways_names, (array) $existing_payment_gateways_names);
	update_option( 'payment_gateway_names', $new_payment_gateways_name );
}
register_activation_hook( __FILE__, 'wpsc_merchant_cointopay_activate' );
function form_cointopay()
{
	global $wpdb, $wpsc_gateways;
	$rows = array();
	$output = '';
	
	// API key
	
	$rows[] = array('API key', '<input name="cointopay_apikey" type="text" value="'.get_option('cointopay_apikey').'" />', 'Get this at cointopay.com under Account.');
	
	// Merchant ID:
	
	$rows[] = array('Security Code', '<input name="cointopay_SecurityCode" type="text" value="'.get_option('cointopay_SecurityCode').'" />', 'Get this at cointopay.com under Account.');
	
	// Security Code:
	
	$rows[] = array('Merchant ID', '<input name="cointopay_MerchantID" type="text" value="'.get_option('cointopay_MerchantID').'" />', 'Get this at cointopay.com under Account.');

	// transaction speed
	$sBitCoin = $sLiteCoin = $sDarkCoin = '';
	switch(get_option('cointopay_default_coin')){
		case '1': $sBitCoin = 'selected="selected"'; break;
		case '2': $sLiteCoin = 'selected="selected"'; break;
		case '8': $sDarkCoin = 'selected="selected"'; break;
		}
	$rows[] = array('Default Crypto Coin', 
		'<select name="cointopay_default_coin">'
		.'<option value="1" '.$sBitCoin.'>Bitcoin</option>'
		.'<option value="2" '.$sLiteCoin.'>Litecoin</option>'
		.'<option value="8" '.$sDarkCoin.'>Dashcoin</option>'
		.'</select>', 'Default crypto coin selected at checkout.');

	//Allows the merchant to specify a URL to redirect to upon the customer completing payment on the cointopay.com
	//invoice page. This is typcially the "Transaction Results" page.
	//$rows[] = array('Redirect URL', '<input name="cointopay_redirect" type="text" value="'.get_option('cointopay_redirect').'" />', 'The Redirect URL may be specified in your Cointopay.com Account.');
	$rows[] = array('Redirect URL', 'The Redirect URL may be specified in your Cointopay.com Account.', '');

	foreach($rows as $r)
	{
		$output.= '<tr> <td>'.$r[0].'</td> <td>'.$r[1];
		if (isset($r[2]))
			$output .= '<BR/><small>'.$r[2].'</small></td> ';
		$output.= '</tr>';
	}
	
	return $output;
}

function submit_cointopay()
{
	$params = array('cointopay_apikey', 'cointopay_default_coin', 'cointopay_SecurityCode', 'cointopay_MerchantID');
	foreach($params as $p)
		if ($_POST[$p] != null)
			update_option($p, $_POST[$p]);
	return true;
}

function gateway_cointopay($seperator, $sessionid)
{
	//$wpdb is the database handle,
	//$wpsc_cart is the shopping cart object
	global $wpdb, $wpsc_cart;
	
	//This grabs the purchase log id from the database
	//that refers to the $sessionid
	$purchase_log = $wpdb->get_row(
		"SELECT * FROM `".WPSC_TABLE_PURCHASE_LOGS.
		"` WHERE `sessionid`= ".$sessionid." LIMIT 1"
		,ARRAY_A) ;

	//This grabs the users info using the $purchase_log
	// from the previous SQL query
	$usersql = "SELECT `".WPSC_TABLE_SUBMITED_FORM_DATA."`.value,
		`".WPSC_TABLE_CHECKOUT_FORMS."`.`name`,
		`".WPSC_TABLE_CHECKOUT_FORMS."`.`unique_name` FROM
		`".WPSC_TABLE_CHECKOUT_FORMS."` LEFT JOIN
		`".WPSC_TABLE_SUBMITED_FORM_DATA."` ON
		`".WPSC_TABLE_CHECKOUT_FORMS."`.id =
		`".WPSC_TABLE_SUBMITED_FORM_DATA."`.`form_id` WHERE
		`".WPSC_TABLE_SUBMITED_FORM_DATA."`.`log_id`=".$purchase_log['id'];
	$userinfo = $wpdb->get_results($usersql, ARRAY_A);
	// convert from awkward format 
	foreach((array)$userinfo as $value) 
		if (strlen($value['value']))
			$ui[$value['unique_name']] = $value['value'];
	$userinfo = $ui;
		
	
	// name
	if (isset($userinfo['billingfirstname']))
	{
		$options['buyerName'] = $userinfo['billingfirstname'];
		if (isset($userinfo['billinglastname']))
			$options['buyerName'] .= ' '.$userinfo['billinglastname'];
	}
	
	//address -- remove newlines
	if (isset($userinfo['billingaddress']))
	{
		$newline = strpos($userinfo['billingaddress'],"\n");
		if ($newline !== FALSE)
		{
			$options['buyerAddress1'] = substr($userinfo['billingaddress'], 0, $newline);
			$options['buyerAddress2'] = substr($userinfo['billingaddress'], $newline+1);
			$options['buyerAddress2'] = preg_replace('/\r\n/', ' ', $options['buyerAddress2'], -1, $count);
		}
		else
			$options['buyerAddress1'] = $userinfo['billingaddress'];
	}
	// state
	if (isset($userinfo['billingstate']))
		$options['buyerState'] = wpsc_get_state_by_id($userinfo['billingstate'], 'code');

	// more user info
	foreach(array('billingphone' => 'buyerPhone', 'billingemail' => 'buyerEmail', 'billingcity' => 'buyerCity',  'billingcountry' => 'buyerCountry', 'billingpostcode' => 'buyerZip') as $f => $t)
		if ($userinfo[$f])
			$options[$t] = $userinfo[$f];

	// itemDesc
	if (count($wpsc_cart->cart_items) == 1)
	{
		$item = $wpsc_cart->cart_items[0];
		$options['itemDesc'] = $item->product_name;
		if ( $item->quantity > 1 )
			$options['itemDesc'] = $item->quantity.'x '.$options['itemDesc'];
	}
	else
	{
		foreach($wpsc_cart->cart_items as $item) 
			$quantity += $item->quantity;
		$options['itemDesc'] = $quantity.' items';
	}	

	if( get_option( 'permalink_structure' ) != '' ) {
		$separator = "?";
	} else {
		$separator = "&";
	}
	
	//currency
	$currencyId = get_option( 'currency_type' );
	$options['currency'] = $wpdb->get_var( $wpdb->prepare( "SELECT `code` FROM `".WPSC_TABLE_CURRENCY_LIST."` WHERE `id` = %d LIMIT 1", $currencyId ) );
	$options['notificationURL'] = get_option('siteurl')."/?cointopay_callback=true";
	//pass sessionid along so that it can be used to populate the transaction results page
	$options['redirectURL'] = get_option('cointopay_redirect').$separator."sessionid=".$sessionid;  
	$options['defaultCoin'] = get_option('cointopay_default_coin');	
	$options['apiKey'] = get_option('cointopay_apikey');
	$options['cointopay_MerchantID'] = get_option('cointopay_MerchantID');
	$options['cointopay_SecurityCode'] = get_option('cointopay_SecurityCode');
	$options['posData'] = $sessionid;
	$options['fullNotifications'] = true;
	
	// truncate if longer than 100 chars
	foreach(array("buyerName", "buyerAddress1", "buyerCity", "buyerZip", "buyerCountry", "buyerEmail", "buyerPhone") as $k)
		$options[$k] = substr($options[$k], 0, 100);
		
	//$price = number_format($wpsc_cart->total_price,2);	
   $price = $wpsc_cart->total_price;

	$invoice = c2pCreateInvoice($sessionid, $price, $sessionid, $options);

	if (!isset($invoice)) {
		debuglog($invoice);
		// close order
		$sql = "UPDATE `".WPSC_TABLE_PURCHASE_LOGS."` SET `processed`= '5' WHERE `sessionid`=".$sessionid;
		$wpdb->query($sql);
		//redirect back to checkout page with errors		
		$_SESSION['WpscGatewayErrorMessage'] = __('Sorry your transaction did not go through successfully, please try again.');
		//header("Location: ".get_option('checkout_url').'/payment');
		//echo $_SESSION['WpscGatewayErrorMessage'];
		if($_SESSION['WpscGatewayErrorMessage'] && $_SESSION['WpscGatewayErrorMessage']!=''){
			add_action('wp_footer', 'cointopay_footer');
		}
	}else{
		$wpsc_cart->empty_cart();
		unset($_SESSION['WpscGatewayErrorMessage']);
		//echo 'bla'.$invoice;
		header("Location: ".$invoice);
		exit();
	}

}

function cointopay_callback()
{
	
	if(isset($_REQUEST['CustomerReferenceNr']))
	{
		global $wpdb;

		//$response = c2pVerifyNotification(get_option('cointopay_apikey'));
		
		//if (isset($response['error']))
		//	debuglog($response);
		//else
		//{

			$data = [ 
                           'mid' => get_option('cointopay_MerchantID') , 
                           'TransactionID' => $_REQUEST['TransactionID'] ,
                           'ConfirmCode' => $_REQUEST['ConfirmCode']
                      ];
              $response = validateOrder($data);
			  //$response->Status = 'paid';
			  if($response->Status !== $_REQUEST['status'])
              {
				  get_header();
                  echo '<div class="container" style="text-align: center;"><div><div>
					<br><br>
					<h2 style="color:#ff0000">Failure!</h2>
					<img src="'.plugins_url( 'images/fail.png', __FILE__ ).'">
					<p style="font-size:20px;color:#5C5C5C;">We have detected different order status. Your order has been halted.</p>
					<a href="'.site_url().'" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >    Back     </a>
					<br><br>
					</div>
					</div>
					</div>';
					get_footer();
                  exit;
              }
			  else if($response->CustomerReferenceNr == $_REQUEST['CustomerReferenceNr'])
              {
				global $wpdb, $current_user, $pmpro_invoice, $pmpro_msg, $pmpro_msgt;
				$table_name = $wpdb->pmpro_membership_orders;
				$table_name_u = $wpdb->pmpro_memberships_users;
				
				if($_REQUEST['status']=='paid' && $_REQUEST['notenough']==0){
					if (!preg_match("/^[0-9]+$/D", $_REQUEST['CustomerReferenceNr'])) {
					debuglog('Input not type integer');
					}
		
					$sessionid = $_REQUEST['CustomerReferenceNr'];
		
					//get buyer email
					$sql = "SELECT * FROM `" . WPSC_TABLE_PURCHASE_LOGS . "` WHERE `sessionid`=".$sessionid;
					$purchase_log = $wpdb->get_results( $sql, ARRAY_A );
					
					$email_form_field = $wpdb->get_var( "SELECT `id` FROM `" . WPSC_TABLE_CHECKOUT_FORMS . "` WHERE `type` IN ('email') AND `active` = '1' ORDER BY `checkout_order` ASC LIMIT 1" );
					$email = $wpdb->get_var( $wpdb->prepare( "SELECT `value` FROM `" . WPSC_TABLE_SUBMITTED_FORM_DATA . "` WHERE `log_id` = %d AND `form_id` = %d LIMIT 1", $purchase_log[0]['id'], $email_form_field ) );
		
					//get cart contents
					$sql = "SELECT * FROM `" . WPSC_TABLE_CART_CONTENTS . "` WHERE `purchaseid`=".$purchase_log[0]['id'];
					$cart_contents = $wpdb->get_results($sql, ARRAY_A);
					
					//get currency symbol
					$currency_id = get_option('currency_type');
					$sql = "SELECT * FROM `" . WPSC_TABLE_CURRENCY_LIST . "` WHERE `id`=".$currency_id;
					$currency_data = $wpdb->get_results($sql, ARRAY_A);
					$currency_symbol = $currency_data[0]['symbol'];
		
					//list products and individual prices in the email
					$message_product = "\r\n\r\nTransaction Details: \r\n\r\n";
					$pnp = 0.0;
					$subtotal = 0.0;
					foreach($cart_contents as $product) {
						$pnp += $product['pnp']; //shipping for each item
						$message_product .= "x" . $product['quantity'] . " " . $product['name'] . " - " . $currency_symbol . $product['price']*$product['quantity'] . "\r\n";
						$subtotal += $product['price']*$product['quantity'];
					}
		
					//list subtotal
					$subtotal = number_format($subtotal , 2 , '.', ',');
					$message_product .= "\r\n" . "Subtotal: " . $currency_symbol . $subtotal . "\r\n";
		
					//list total taxes and total shipping costs in the email
					$message_product .= "Taxes: " . $currency_symbol . $purchase_log[0]['wpec_taxes_total'] . "\r\n";
					$message_product .= "Shipping: " . $currency_symbol . ($purchase_log[0]['base_shipping'] + $pnp) . "\r\n\r\n";
		
					//display total price in the email
					$message_product .= "Total Price: " . $currency_symbol . $purchase_log[0]['totalprice'];
		
					//The purchase receipt email is sent upon the invoice status changing to "complete", and the order
					//status is changed to Accepted Payment
					//case 'complete':
		
						$sql = "UPDATE `".WPSC_TABLE_PURCHASE_LOGS."` SET `processed`= '3' WHERE `sessionid`=".$sessionid;
						if (is_numeric($sessionid)) {
							$wpdb->query($sql);
						}
		
						$message = "Your transaction is now complete! Thank you for using Cointopay International B.V.!" ;
						wp_mail($email, "Transaction Complete", $message.$message_product);
		
						$sql = "UPDATE `".WPSC_TABLE_PURCHASE_LOGS."` SET `email_sent`= '1' WHERE `sessionid`=".$sessionid;
						//transaction_results($sessionid, false); //false because this is just for email notification	
						get_header();
							echo '<div class="container" style="text-align: center;"><div><div>
							<br><br>
							<h2 style="color:#0fad00">Success!</h2>
							<img src="'.plugins_url( 'images/check.png', __FILE__ ).'">
							<p style="font-size:20px;color:#5C5C5C;">The payment has been received and confirmed successfully.</p>
							<a href="'.site_url().'" style="background-color: #0fad00;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >    Back     </a>
							<br><br>
							<p>Redirecting in 10 Seconds.</p>
							<br><br>
							</div>
							</div>
							</div>';
							get_footer();
							exit;
				}
				
				else if($_REQUEST['status']=='paid' && $_REQUEST['notenough']==1){
					get_header();
			   			echo '<div class="container" style="text-align: center;"><div><div>
					<br><br>
					<h2 style="color:#ff0000">Failure!</h2>
					<img src="'.plugins_url( 'images/fail.png', __FILE__ ).'">
					<p style="font-size:20px;color:#5C5C5C;">IPN: Payment failed from Cointopay because notenough.</p>
					<a href="'.site_url().'" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >    Back     </a>
					<br><br>

					</div>
					</div>
					</div>';
					get_footer();
					 exit;
				}
				
				else if($_REQUEST['status']=='failed' && $_REQUEST['notenough']==1){ 
			        get_header();
					echo '<div class="container" style="text-align: center;"><div><div>
					<br><br>
					<h2 style="color:#ff0000">Failure!</h2>
					<img src="'.plugins_url( 'images/fail.png', __FILE__ ).'">
					<p style="font-size:20px;color:#5C5C5C;">IPN: Payment failed from Cointopay because notenough.</p>
					<a href="'.site_url().'" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >    Back     </a>
					<br><br>

					</div>
					</div>
					</div>';
					get_footer();
					 exit;
				
				}
				else if($_REQUEST['status']=='failed' && $_REQUEST['notenough']==0){ 
		             get_header();
					 echo '<div class="container" style="text-align: center;"><div><div>
					<br><br>
					<h2 style="color:#ff0000">Failure!</h2>
					<img src="'.plugins_url( 'images/fail.png', __FILE__ ).'">
					<p style="font-size:20px;color:#5C5C5C;">Payment failed from Cointopay.</p>
					<a href="'.site_url().'" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >    Back     </a>
					<br><br>

					</div>
					</div>
					</div>';
					get_footer();
					 exit;
				
				}
		   }
		  else if($response == 'not found')
              {
				  get_header();
				  echo '<div class="container" style="text-align: center;"><div><div>
					<br><br>
					<h2 style="color:#ff0000">Failure!</h2>
					<img src="'.plugins_url( 'images/fail.png', __FILE__ ).'">
					<p style="font-size:20px;color:#5C5C5C;">We have detected different order status. Your order has not been found.</p>
					<a href="'.site_url().'" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >    Back     </a>
					<br><br>

					</div>
					</div>
					</div>';
				get_footer();
                  exit;
              }
		   else{
			   get_header();
				   echo '<div class="container" style="text-align: center;"><div><div>
					<br><br>
					<h2 style="color:#ff0000">Failure!</h2>
					<img src="'.plugins_url( 'images/fail.png', __FILE__ ).'">
					<p style="font-size:20px;color:#5C5C5C;">We have detected different order status. Your order has been halted.</p>
					<a href="'.site_url().'" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >    Back     </a>
					<br><br>
					</div>
					</div>
					</div>';
					get_footer();
                  exit;
			  }
				//break;
		//}
	}
}

add_action('init', 'cointopay_callback');
/**
 * Validate Order
 */
function  validateOrder($data){
   $params = array(
   "authentication:1",
   'cache-control: no-cache',
   );
	$ch = curl_init();
	curl_setopt_array($ch, array(
	CURLOPT_URL => 'https://app.cointopay.com/v2REAPI?',
	//CURLOPT_USERPWD => $this->apikey,
	CURLOPT_POSTFIELDS => 'MerchantID='.$data['mid'].'&Call=QA&APIKey=_&output=json&TransactionID='.$data['TransactionID'].'&ConfirmCode='.$data['ConfirmCode'],
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_SSL_VERIFYPEER => false,
	CURLOPT_HTTPHEADER => $params,
	CURLOPT_USERAGENT => 1,
	CURLOPT_HTTPAUTH => CURLAUTH_BASIC
	)
	);
	$response = curl_exec($ch);
	$results = json_decode($response);
	if($results->CustomerReferenceNr)
	{
		return $results;
	}
	else if($response == '"not found"')
	  {
		  get_header();
		   echo '<div class="container" style="text-align: center;"><div><div>
					<br><br>
					<h2 style="color:#ff0000">Failure!</h2>
					<img src="'.plugins_url( 'images/fail.png', __FILE__ ).'">
					<p style="font-size:20px;color:#5C5C5C;">Your order not found.</p>
					<a href="'.site_url().'" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >    Back     </a>
					<br><br>

					</div>
					</div>
					</div>';
					get_footer();
                  exit;
	  }
   
	   echo $response;
	  
}
/**
 * transaction_results function main function for creating the purchase reports, transaction results page, and email receipts
 * @access public
 *
 * @since 3.7
 * @param $sessionid (string) unique session id
 * @param echo_to_screen (boolean) whether to output the results or return them (potentially redundant)
 * @param $transaction_id (int) the transaction id
 */
function cointopay_transaction_results( $sessionid, $display_to_screen = true, $transaction_id = null ) {
	global $message_html, $echo_to_screen, $wpsc_cart, $purchase_log;

	// pre-3.8.9 variable
	$echo_to_screen = $display_to_screen;

	$purchase_log_object = new WPSC_Purchase_Log( $sessionid, 'sessionid' );

	// compatibility with pre-3.8.9 templates where they use a global
	// $purchase_log object which is simply just a database row
	$purchase_log = $purchase_log_object->get_data();

	// pre-3.8.9 templates also use this global variable
	$message_html = wpsc_get_transaction_html_output( $purchase_log_object );

	$wpsc_cart->empty_cart();

	do_action( 'wpsc_transaction_results_shutdown', $purchase_log_object, $sessionid, $display_to_screen );

	return $message_html;
}
function cointopay_footer(){
?>
<script>
jQuery('.wpsc-wizard-step-results').removeClass('pending');
jQuery('.wpsc-wizard-step-results').addClass('active');
</script>
<?php }?>