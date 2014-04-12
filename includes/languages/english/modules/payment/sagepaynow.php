<?php
/**
 * paynow.php language file /includes/language/english/modules/payment/paynow.php 
 *
 * Stores definable information for Sage Pay Now payment module
 * 
 */
// General
define( 'MODULE_PAYMENT_PAYNOW_TEXT_TITLE', 'Sage Pay Now');
define( 'MODULE_PAYMENT_PAYNOW_TEXT_PUBLIC_TITLE', 'Sage Pay Now');
define( 'MODULE_PAYMENT_PAYNOW_TEXT_DESCRIPTION', '<img src="images/icon_popup.gif" border="0">&nbsp;<a href="https://www.sagepay.co.za/sagepay/pay_now_gateway.asp" target="_blank" style="text-decoration: underline; font-weight: bold;">Visit Pay Now Website</a>');

// Errors
define( 'MODULE_PAYMENT_PAYNOW_ERR_PDT_TOKEN_MISSING', 'PDT token not present in URL' );
define( 'MODULE_PAYMENT_PAYNOW_ERR_ORDER_ID_MISSING_URL', 'Order ID not present in URL' );
define( 'MODULE_PAYMENT_PAYNOW_ERR_ORDER_PROCESSED', 'This order has already been processed' );
define( 'MODULE_PAYMENT_PAYNOW_ERR_ORDER_INVALID', 'This order ID is invalid' );
define( 'MODULE_PAYMENT_PAYNOW_ERR_CONNECT_FAILED', 'Failed to connect to Pay Now' );
define( 'MODULE_PAYMENT_PAYNOW_ERR_ID_MISMATCH', 'Order ID mismatch' );
define( 'MODULE_PAYMENT_PAYNOW_ERR_PAYMENT_NOT_COMPLETE', 'Payment is not yet COMPLETE' );
?>