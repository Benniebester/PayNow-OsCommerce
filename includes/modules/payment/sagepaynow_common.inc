<?php
/**
 * oscommerce/includes/modules/payment/sagepaynow_common.inc 
 *
 */
// // Create user agent string
// User agent constituents (for cURL)
global $ini;
define ( 'PN_SOFTWARE_NAME', 'osCommerce' );
define ( 'PN_SOFTWARE_VER', tep_get_version () );
define ( 'PN_MODULE_NAME', 'PayNow-osCommerce' );
define ( 'PN_MODULE_VER', '1.0' );

// Features
// - PHP
$pnFeatures = 'PHP ' . phpversion () . ';';

// - cURL
if (in_array ( 'curl', get_loaded_extensions () )) {
	define ( 'PN_CURL', '' );
	$pnVersion = curl_version ();
	$pnFeatures .= ' curl ' . $pnVersion ['version'] . ';';
} else
	$pnFeatures .= ' nocurl;';
	
	// Create user agrent
define ( 'PN_USER_AGENT', PN_SOFTWARE_NAME . '/' . PN_SOFTWARE_VER . ' (' . trim ( $pnFeatures ) . ') ' . PN_MODULE_NAME . '/' . PN_MODULE_VER );

// General Defines
define ( 'PN_TIMEOUT', 15 );
define ( 'PN_EPSILON', 0.01 );

// Messages
// Error
define ( 'PN_ERR_AMOUNT_MISMATCH', 'Amount mismatch' );
define ( 'PN_ERR_BAD_ACCESS', 'Bad access of page' );
define ( 'PN_ERR_BAD_SOURCE_IP', 'Bad source IP address' );
define ( 'PN_ERR_CONNECT_FAILED', 'Failed to connect to Pay Now' );
define ( 'PN_ERR_INVALID_SIGNATURE', 'Security signature mismatch' );
define ( 'PN_ERR_MERCHANT_ID_MISMATCH', 'Merchant ID mismatch' );
define ( 'PN_ERR_NO_SESSION', 'No saved session found for ITN transaction' );
define ( 'PN_ERR_ORDER_ID_MISSING_URL', 'Order ID not present in URL' );
define ( 'PN_ERR_ORDER_ID_MISMATCH', 'Order ID mismatch' );
define ( 'PN_ERR_ORDER_INVALID', 'This order ID is invalid' );
define ( 'PN_ERR_ORDER_PROCESSED', 'This order has already been processed' );
define ( 'PN_ERR_PDT_FAIL', 'PDT query failed' );
define ( 'PN_ERR_PDT_TOKEN_MISSING', 'PDT token not present in URL' );
define ( 'PN_ERR_SESSIONID_MISMATCH', 'Session ID mismatch' );
define ( 'PN_ERR_UNKNOWN', 'Unkown error occurred' );

// General
define ( 'PN_MSG_OK', 'Payment was successful' );
define ( 'PN_MSG_FAILED', 'Payment has failed' );
define ( 'PN_MSG_PENDING', 'The payment is pending. Please note, you will receive another Instant' . ' Transaction Notification when the payment status changes to' . ' "Completed", or "Failed"' );

/**
 * pnlog
 *
 * Log function for logging output.
 *
 * Merged from /whmcs/autoload/debug
 * 
 * Log close called once in ext/modules/payment/paynow/paynow.php
 *
 * @author Eugene van der Merwe, Gateway Modules
 * @param $msg String
 *        	Message to log
 * @param $close Boolean
 *        	Whether to close the log file or not
 */
function pnlog( $msg = '', $variable = '', $close = false ) {
//function pnlog($msg = '', $close = false) {
	static $fh = 0;
	global $module;
	
	// Only log if debugging is enabled
	if (PN_DEBUG) {
		if ($close) {
			fclose ( $fh );
		} else {
			// If file doesn't exist, create it
			if (! $fh) {
				$pathinfo = pathinfo ( __FILE__ );
				$fh = fopen ( $pathinfo ['dirname'] . '/paynow.log', 'a+' );
			}
			
			// If file was successfully created
			if ($fh) {
				
 				if ($variable) {
 					if (is_array ( $variable )) {
 						$variable = print_r ( $variable, true );
 					}
 					$line = date ( "Y-m-d H:i:s" ). " : " . $msg . ":" . print_r ( $variable, true ) . "\n";
 				} else {
 					$line = date ( "Y-m-d H:i:s" ). " : " . $msg . "\n";
 				}
				//$line = date ( 'Y-m-d H:i:s' ) . ' : ' . $msg . "\n";
				fwrite ( $fh, $line );
			}
		}
	}
}
// }}}
// {{{ pnGetData
/**
 * pnGetData
 */
function pnGetData() {
	// Posted variables from ITN
	$pnData = $_POST;
	
	// Strip any slashes in data
	foreach ( $pnData as $key => $val )
		$pnData [$key] = stripslashes ( $val );
		
		// Return "false" if no data was received
	if (sizeof ( $pnData ) == 0)
		return (false);
	else
		return ($pnData);
}

/**
 * pnValidSignature
 */
function pnValidSignature($pnData = null, &$pnParamString = null) {
	// Dump the submitted variables and calculate security signature
	foreach ( $pnData as $key => $val ) {
		if ($key != 'signature') {
			$pnParamString .= $key . '=' . urlencode ( $val ) . '&';
		} else {
			break;
		}
	}
	
	// Remove the last '&' from the parameter string
	$pnParamString = substr ( $pnParamString, 0, - 1 );
	$signature = md5 ( $pnParamString );
	
	$result = ($pnData ['signature'] == $signature);
	
	pnlog ( 'Signature = ' . ($result ? 'valid' : 'invalid') );
	
	return ($result);
}

/**
 * pnValidData
 *
 * @param $pnHost String
 *        	Hostname to use
 * @param $pnParamString String
 *        	Parameter string to send
 * @param $proxy String
 *        	Address of proxy to use or NULL if no proxy
 */
function pnValidData($pnHost = 'paynow.sagepay.co.za', $pnParamString = '', $pnProxy = null) {
	pnlog ( 'Host = ' . $pnHost );
	pnlog ( 'Params = ' . $pnParamString );
	
	// Use cURL (if available)
	if (defined ( 'PN_CURL' )) {
		// Variable initialization
		$url = 'https://' . $pnHost;
		
		// Create default cURL object
		$ch = curl_init ();
		
		// Set cURL options - Use curl_setopt for freater PHP compatibility
		// Base settings
		curl_setopt ( $ch, CURLOPT_USERAGENT, PN_USER_AGENT ); // Set user agent
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true ); // Return output as string rather than outputting it
		curl_setopt ( $ch, CURLOPT_HEADER, false ); // Don't include header in output
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, false );
		
		// Standard settings
		curl_setopt ( $ch, CURLOPT_URL, $url );
		curl_setopt ( $ch, CURLOPT_POST, true );
		curl_setopt ( $ch, CURLOPT_POSTFIELDS, $pnParamString );
		curl_setopt ( $ch, CURLOPT_TIMEOUT, PN_TIMEOUT );
		if (! empty ( $pnProxy ))
			curl_setopt ( $ch, CURLOPT_PROXY, $proxy );
			
			// Execute CURL
		$response = curl_exec ( $ch );
		curl_close ( $ch );
	} 	// Use fsockopen
	else {
		// Variable initialization
		$header = '';
		$res = '';
		$headerDone = false;
		
		// Construct Header
		$header = "POST  HTTP/1.0\r\n";
		$header .= "Host: " . $pnHost . "\r\n";
		$header .= "User-Agent: " . PN_USER_AGENT . "\r\n";
		$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$header .= "Content-Length: " . strlen ( $pnParamString ) . "\r\n\r\n";
		
		// Connect to server
		$socket = fsockopen ( 'ssl://' . $pnHost, 443, $errno, $errstr, PN_TIMEOUT );
		
		// Send command to server
		fputs ( $socket, $header . $pnParamString );
		
		// Read the response from the server
		while ( ! feof ( $socket ) ) {
			$line = fgets ( $socket, 1024 );
			
			// Check if we are finished reading the header yet
			if (strcmp ( $line, "\r\n" ) == 0) {
				// read the header
				$headerDone = true;
			} 			// If header has been processed
			else if ($headerDone) {
				// Read the main response
				$response .= $line;
			}
		}
	}
	
	pnlog ( "Response:\n" . print_r ( $response, true ) );
	
	// Interpret Response
	$lines = explode ( "\r\n", $response );
	$verifyResult = trim ( $lines [0] );
	
	if (strcasecmp ( $verifyResult, 'VALID' ) == 0)
		return (true);
	else
		return (false);
}

/**
 * pnValidIP
 *
 * @param $sourceIP String
 *        	Source IP address
 */
function pnValidIP($sourceIP) {
	// Variable initialization
	$validHosts = array (
			'paynow.sagepay.co.za' 
	);
	
	$validIps = array ();
	
	foreach ( $validHosts as $pnHostname ) {
		$ips = gethostbynamel ( $pnHostname );
		
		if ($ips !== false)
			$validIps = array_merge ( $validIps, $ips );
	}
	
	// Remove duplicates
	$validIps = array_unique ( $validIps );
	
	pnlog ( "Valid IPs:\n" . print_r ( $validIps, true ) );
	
	if (in_array ( $sourceIP, $validIps ))
		return (true);
	else
		return (false);
}

/**
 * pnAmountsEqual
 *
 * Checks to see whether the given amounts are equal using a proper floating
 * point comparison with an Epsilon which ensures that insignificant decimal
 * places are ignored in the comparison.
 *
 * eg. 100.00 is equal to 100.0001
 *
 * @param $amount1 Float
 *        	1st amount for comparison
 * @param $amount2 Float
 *        	2nd amount for comparison
 */
function pnAmountsEqual($amount1, $amount2) {
	if (abs ( floatval ( $amount1 ) - floatval ( $amount2 ) ) > PN_EPSILON)
		return (false);
	else
		return (true);
}

?>