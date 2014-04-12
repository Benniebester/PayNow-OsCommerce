<?php
/**
 * Callback module for Sage Pay Now
 * /oscommerce/ext/modules/paynow/paynow.php 
 *
 */
chdir( '../../../../' );
require( 'includes/application_top.php' );

//// Check if module is enabled before processing
if( !defined( 'MODULE_PAYMENT_PAYNOW_STATUS' ) || ( MODULE_PAYMENT_PAYNOW_STATUS  != 'True' ) )
    exit;

// Include Pay Now common file
define( 'PAYNOW_DEBUG', ( strcasecmp( MODULE_PAYMENT_PAYNOW_DEBUG, 'True' ) == 0 ? true : false ) );
include_once( 'includes/modules/payment/sagepaynow_common.inc' );

// Variable Initialization
$pnError = false;
$pnNotes = array();
$pnData = array();
// TODO 
$pnHost = "https://paynow.sagepay.co.za/site/paynow.aspx";
$orderId = '';
$pnParamString = '';

$pnErrors = array();

pnlog( 'Sage Pay Now IPN call received' );

//// Set debug email address
$pnDebugEmail = ( strlen( MODULE_PAYMENT_PAYNOW_DEBUG_EMAIL ) > 0 ) ?
    MODULE_PAYMENT_PAYNOW_DEBUG_EMAIL : STORE_OWNER_EMAIL_ADDRESS;

pnlog( 'Debug email address = '. $pnDebugEmail );

//// Notify Pay Now that information has been received
if( !$pnError )
{
    header( 'HTTP/1.0 200 OK' );
    flush();
}

//// Get data sent by Pay Now
if( !$pnError )
{
    pnlog( 'Get posted data' );

    // Posted variables from ITN
    $pnData = pnGetData();

    pnlog( 'Sage Pay Now Data: '. print_r( $pnData, true ) );

    if( $pnData === false )
    {
        $pnError = true;
        $pnNotes[] = PN_ERR_BAD_ACCESS;
    }
}

//// Retrieve order from eCommerce System
if( !$pnError )
{
    pnlog( 'Get order' );
}

//// Verify data
if( !$pnError )
{
    pnlog( 'Verify data received' );

//     if( $config['proxy'] == 1 )
//         $pnValid = pnValidData( $pnHost, $pnParamString, $config['proxyHost'] .":". $config['proxyPort'] );
//     else
//         $pnValid = pnValidData( $pnHost, $pnParamString );

//     if( !$pnValid )
//     {
//         $pnError = true;
//         $pnNotes[] = PN_ERR_BAD_ACCESS;
//     }
}

//// Check status and update order & transaction table
if( !$pnError )
{
    pnlog( 'Check status and update order - START...' );
    
    // Get order
    $orderId = (int)$pnData['Reference'];
    $order_query = tep_db_query(
        "SELECT `orders_status`, `currency`, `currency_value`
        FROM `". TABLE_ORDERS ."`
        WHERE `orders_id` = '" . $orderId . "'
          AND `customers_id` = '" . $pnData['custom_int1'] . "'" );
    
    // If order found
    if( tep_db_num_rows( $order_query ) > 0 )
    {
        // Get order details
        $order = tep_db_fetch_array( $order_query );

        // If order in "Preparing" state, update to "Pending"
        if( $order['orders_status'] == MODULE_PAYMENT_PAYNOW_PREPARE_ORDER_STATUS_ID )
        {
            $sql_data_array = array(
                'orders_id' => $orderId,
                'orders_status_id' => MODULE_PAYMENT_PAYNOW_PREPARE_ORDER_STATUS_ID,
                'date_added' => 'now()',
                'customer_notified' => '0',
                'comments' => '' );

            tep_db_perform( TABLE_ORDERS_STATUS_HISTORY, $sql_data_array );

            // Update order status
            tep_db_query(
                "UPDATE ". TABLE_ORDERS ."
                SET `orders_status` = '". ( MODULE_PAYMENT_PAYNOW_ORDER_STATUS_ID > 0 ?
                  (int)MODULE_PAYMENT_PAYNOW_ORDER_STATUS_ID : (int)DEFAULT_ORDERS_STATUS_ID) . "',
                  `last_modified` = NOW()
                WHERE `orders_id` = '". $orderId ."'" );
        }

        // Get order total
        $total_query = tep_db_query(
            "SELECT `value`
            FROM `". TABLE_ORDERS_TOTAL ."`
            WHERE `orders_id` = '" . $orderId . "'
              AND `class` = 'ot_total'
            LIMIT 1" );
        $total = tep_db_fetch_array( $total_query );

        // Add comment to order history
        $comment_status = "Transaction ID ". $pnData['pn_payment_id'];
        $comment_status = $pnData['payment_status'] .' ('. $currencies->format( $pnData['amount_gross'], false, 'ZAR' ) .')';

        $orderValue = number_format( $total['value'] * $order['currency_value'], $currencies->get_decimal_places( $order['currency'] ), '.', '' );
        if( $pnData['amount_gross'] != $orderValue )
        {
            $comment_status .= '; Pay Now transaction value ('. tep_output_string_protected( $pnData['amount_gross'] ) .') does not match order value ('. $orderValue .')';
            $pnError = true;
            $pnErrMsg = PN_ERR_AMOUNT_MISMATCH;
        }

        $sql_data_array = array(
            'orders_id' => $orderId,
            'orders_status_id' => ( MODULE_PAYMENT_PAYNOW_ORDER_STATUS_ID > 0 ?
                (int)MODULE_PAYMENT_PAYNOW_ORDER_STATUS_ID : (int)DEFAULT_ORDERS_STATUS_ID ),
            'date_added' => 'now()',
            'customer_notified' => '0',
            'comments' => 'Pay Now ITN received ['. $comment_status .']'
            );

        tep_db_perform( TABLE_ORDERS_STATUS_HISTORY, $sql_data_array );
    }
    
    pnlog( 'Check status and update order - DONE!' );
    // Redirect    
    tep_redirect(tep_href_link( FILENAME_CHECKOUT_PROCESS, 'order_id='. $orderId, 'SSL' ));
}

// If an error occurred
if( $pnError )
{
    pnlog( 'Error occurred: '. $pnErrMsg );
    pnlog( 'Sending email notification' );

    // Compose email to send
    $subject = "Pay Now ITN error: ". $pnErrMsg;
    $body =
        "Hi,\n\n".
        "An invalid Pay Now transaction on your website requires attention\n".
        "-----------------------------------------------------------------\n".
        "Site: ". $vendor_name ." (". $vendor_url .")\n".
        "Remote IP Address: ".$_SERVER['REMOTE_ADDR']."\n".
        "Remote host name: ". gethostbyaddr( $_SERVER['REMOTE_ADDR'] ) ."\n".
        "Order ID: ". $pnData['m_payment_id'] ."\n";
    if( isset( $pnData['pn_payment_id'] ) )
        $body .= "Pay Now Transaction ID: ". $pnData['pn_payment_id'] ."\n";
    if( isset( $pnData['payment_status'] ) )
        $body .= "Pay Now Payment Status: ". $pnData['payment_status'] ."\n";
    $body .=
        "\nError: ". $pnErrMsg ."\n";

    switch( $pnErrMsg )
    {
        case PN_ERR_AMOUNT_MISMATCH:
            $body .=
                "Value received : ". $pnData['amount_gross'] ."\n".
                "Value should be: ". $orderValue;
            break;

        // For all other errors there is no need to add additional information
        default:
            break;
    }

    // Send email
    tep_mail( '', $pnDebugEmail, $subject, $body, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS );
}

// Close log
pnlog( '', '', true );

require('includes/application_bottom.php');
?>