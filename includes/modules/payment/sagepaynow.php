<?php
/**
 * 
 * The Sage Pay Now class file sagepaynow.php 
 * 
 * TODO Map correct fields in method process_button()
 * 
 * @location	osCommerce/includes/modules/payment/paynow.php
 *
 * Main module file which is responsible for installing, editing and deleting
 * module details from DB and sending data to Sage Pay Now.
 * 
 * @references
 * http://www.sourcexref.com/xref/oscommerce/nav.html?includes/modules/payment/paypal_express.php.source.html
 *
 * @license    http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link       http://www.sagepay.co.za/sagepay/pay_now_gateway.asp
 * 
 * @notes		$notify_url points to ext/modules/payment/sagepaynow/sagepaynow.php
 * Function install and function keys return configuration data
 * 
 */

// Include Pay Now common file
define( 'PAYNOW_DEBUG', ( strcasecmp( MODULE_PAYMENT_PAYNOW_DEBUG, 'True' ) == 0 ? true : false ) );
include_once( 'sagepaynow_common.inc' );

/**
 * paynow
 *
 * Class for payment module
 */
class sagepaynow
{
    var $code;
	var $title;
	var $public_title;
	var $description;
	var $sort_order;
	var $enabled;

	var $status_text;
	var $form_action_url;

    /**
     * paynow
     *
     * Class constructor
     */
    function sagepaynow()
    {
        pnlog( __METHOD__ .': bof' );
        
    	// Variable Initialization
        global $order;

        $this->signature = 'sagepay|paynow|1.0|2.3';

		$this->code = 'sagepaynow';
        $this->title = MODULE_PAYMENT_PAYNOW_TEXT_TITLE;
        $this->public_title = MODULE_PAYMENT_PAYNOW_TEXT_PUBLIC_TITLE;
        $this->description = MODULE_PAYMENT_PAYNOW_TEXT_DESCRIPTION;
		$this->sort_order = MODULE_PAYMENT_PAYNOW_SORT_ORDER;
		$this->enabled = ( (MODULE_PAYMENT_PAYNOW_STATUS == 'True') ? true : false );

		$this->status_text = 'Preparing [Pay Now]';

        if( (int)MODULE_PAYMENT_PAYNOW_ORDER_STATUS_ID > 0 )
        {
            $this->order_status = MODULE_PAYMENT_PAYNOW_ORDER_STATUS_ID;
        }

        if( is_object($order) )
            $this->update_status();

        // Server selection
		$pnHost = "https://paynow.sagepay.co.za/site/paynow.aspx";
        $this->form_action_url = $pnHost;
    }

	/**
     * update_status
     *
     * Called by module's class constructor, checkout_confirmation.php,
	 * checkout_process.php
     */
    function update_status()
    {
        pnlog( __METHOD__ .': bof' );
        
    	// Variable initialization
        global $order;

		// If payment by Pay Now enabled
        if( ( $this->enabled == true ) && ( (int) MODULE_PAYMENT_PAYNOW_ZONE > 0 ) )
        {
            $check_flag = false;
            $check_query = tep_db_query(
				"SELECT `zone_id`
				FROM " . TABLE_ZONES_TO_GEO_ZONES ."
                WHERE `geo_zone_id` = '" . MODULE_PAYMENT_PAYNOW_ZONE ."'
					AND `zone_country_id` = '" . $order->billing['country']['id'] ."'
				ORDER BY `zone_id`" );

			while( $check = tep_db_fetch_array( $check_query ) )
            {
                if( $check['zone_id'] < 1 )
                {
                    $check_flag = true;
                    break;
                }
				elseif( $check['zone_id'] == $order->billing['zone_id'] )
                {
                    $check_flag = true;
                    break;
                }
            }

            if( $check_flag == false )
            {
                $this->enabled = false;
            }
        }
    }


    /**
     * javascript_validation
     *
     * Returns javascript code for validating what has entered by the user.
	 *
	 * >> Called by checkout_payment.php
     */
    function javascript_validation()
    {
        pnlog( __METHOD__ .': bof' );
        
        return false;
    }

    /**
     * selection
     *
     * Removes records from order history table
     *
     * >> Called by checkout_payment.php
     */
    function selection()
    {
        pnlog( __METHOD__ .': bof' );
        
        global $cart_PayNow_ID;

        if( tep_session_is_registered('cart_PayNow_ID') )
        {
            $order_id = substr( $cart_PayNow_ID, strpos( $cart_PayNow_ID, '-' ) + 1 );

            $check_query = tep_db_query(
				"SELECT `orders_id`
				FROM `". TABLE_ORDERS_STATUS_HISTORY ."`
				WHERE `orders_id` = '". (int)$order_id ."' LIMIT 1" );

            if( tep_db_num_rows($check_query) < 1 )
            {
                tep_db_query(
					"DELETE FROM `". TABLE_ORDERS ."` WHERE `orders_id` = '". (int)$order_id ."'" );
                tep_db_query(
					"DELETE FROM `". TABLE_ORDERS_TOTAL ."` WHERE `orders_id` = '". (int) $order_id ."'" );
				tep_db_query(
					"DELETE FROM `". TABLE_ORDERS_STATUS_HISTORY ."` WHERE `orders_id` = '" . (int)$order_id ."'" );
                tep_db_query(
					"DELETE FROM `". TABLE_ORDERS_PRODUCTS ."` WHERE `orders_id` = '" . (int)$order_id ."'" );
                tep_db_query(
					"DELETE FROM `". TABLE_ORDERS_PRODUCTS_ATTRIBUTES ."` WHERE `orders_id` = '" . (int)$order_id ."'" );
                tep_db_query(
					"DELETE FROM `". TABLE_ORDERS_PRODUCTS_DOWNLOAD ."` WHERE `orders_id` = '" . (int)$order_id ."'" );

                tep_session_unregister( 'cart_PayNow_ID' );
            }
        }

        return array( 'id' => $this->code, 'module' => $this->public_title );
    }

	/**
     * pre_confirmation_check
     *
     * Used to deeply test what the user has entered in the checkout_payment
	 * form (eg. Credit card number validation may be here).
	 *
	 * >> Called by checkout_confirmation.php before any page output.
     */
    function pre_confirmation_check()
    {
        pnlog( __METHOD__ .': bof' );
        
        global $cartID, $cart;

        if( empty($cart->cartID) )
        {
            $cartID = $cart->cartID = $cart->generate_cart_id();
        }

        if( !tep_session_is_registered( 'cartID' ) )
        {
            tep_session_register( 'cartID' );
        }
    }

	/**
     * confirmation
     *
     * Returns the fields of data to show from checkout_payment in
	 * checkout_confirmation, in a multi-level array (see ipayment.php or cc.php
	 * for example). Update order with pending status.
	 *
	 * >> Called by checkout_confirmation.php
     */
    function confirmation()
    {
        pnlog( __METHOD__ .': bof' );
        
    	// Variable initialization
        global $cartID, $cart_PayNow_ID, $customer_id,
			$languages_id, $order, $order_total_modules;

        if( tep_session_is_registered('cartID') )
        {
            $insert_order = false;

            if( tep_session_is_registered('cart_PayNow_ID') )
            {
                $order_id = substr( $cart_PayNow_ID,
					strpos($cart_PayNow_ID, '-') + 1 );

                $curr_check = tep_db_query(
					"SELECT `currency` FROM ". TABLE_ORDERS ."
                    WHERE `orders_id` = '". (int)$order_id . "'" );
                $curr = tep_db_fetch_array( $curr_check );

                if( ($curr['currency'] != $order->info['currency']) ||
					($cartID != substr($cart_PayNow_ID, 0, strlen($cartID))) )
                {
                    $check_query = tep_db_query(
						"SELECT `orders_id` FROM ". TABLE_ORDERS_STATUS_HISTORY ."
						WHERE `orders_id` = '". (int)$order_id ."' limit 1" );

                    if( tep_db_num_rows($check_query) < 1 )
		            {
		                tep_db_query(
							"DELETE FROM `". TABLE_ORDERS ."` WHERE `orders_id` = '". (int)$order_id ."'" );
		                tep_db_query(
							"DELETE FROM `". TABLE_ORDERS_TOTAL ."` WHERE `orders_id` = '". (int)$order_id ."'" );
						tep_db_query(
							"DELETE FROM `". TABLE_ORDERS_STATUS_HISTORY ."` WHERE `orders_id` = '" . (int)$order_id ."'" );
		                tep_db_query(
							"DELETE FROM `". TABLE_ORDERS_PRODUCTS ."` WHERE `orders_id` = '" . (int)$order_id ."'" );
		                tep_db_query(
							"DELETE FROM `". TABLE_ORDERS_PRODUCTS_ATTRIBUTES ."` WHERE `orders_id` = '" . (int)$order_id ."'" );
		                tep_db_query(
							"DELETE FROM `". TABLE_ORDERS_PRODUCTS_DOWNLOAD ."` WHERE `orders_id` = '" . (int)$order_id ."'" );
                    }

                    $insert_order = true;
                }
            }
            else
            {
                $insert_order = true;
            }

            if( $insert_order == true )
            {
                $order_totals = array();
                if( is_array($order_total_modules->modules) )
                {
                    reset( $order_total_modules->modules );
                    while( list(, $value) = each($order_total_modules->modules) )
                    {
                        $class = substr( $value, 0, strrpos($value, '.') );
                        if( $GLOBALS[$class]->enabled )
                        {
                            for ( $i = 0, $n = sizeof($GLOBALS[$class]->output); $i < $n; $i++ )
                            {
                                if( tep_not_null($GLOBALS[$class]->output[$i]['title']) && tep_not_null($GLOBALS[$class]->
                                    output[$i]['text']) )
                                {
                                    $order_totals[] = array(
										'code' => $GLOBALS[$class]->code,
										'title' => $GLOBALS[$class]->output[$i]['title'],
										'text' => $GLOBALS[$class]->output[$i]['text'],
										'value' => $GLOBALS[$class]->output[$i]['value'],
										'sort_order' => $GLOBALS[$class]->sort_order
										);
                                }
                            }
                        }
                    }
                }

                global $paynow_ord_status_id;
                $ord_status_check = tep_db_query(
					"SELECT * FROM `orders_status`
					WHERE `orders_status_name` = '". $this->status_text ."'" );
                $ord_row = tep_db_fetch_array( $ord_status_check );
                $paynow_ord_status_id = $ord_row['orders_status_id'];

                // Update order with pending status.
                $sql_data_array = array(
					'customers_id' => $customer_id,
					'customers_name' => $order->customer['firstname'] . ' ' . $order->customer['lastname'],
					'customers_company' => $order->customer['company'],
					'customers_street_address' => $order->customer['street_address'],
                    'customers_suburb' => $order->customer['suburb'],
					'customers_city' => $order->customer['city'],
					'customers_postcode' => $order->customer['postcode'],
                    'customers_state' => $order->customer['state'],
					'customers_country' => $order->customer['country']['title'],
					'customers_telephone' => $order->customer['telephone'],
                    'customers_email_address' => $order->customer['email_address'],
                    'customers_address_format_id' => $order->customer['format_id'],
					'delivery_name' => $order->delivery['firstname'] . ' ' . $order->delivery['lastname'],
                    'delivery_company' => $order->delivery['company'],
					'delivery_street_address' => $order->delivery['street_address'],
					'delivery_suburb' => $order->delivery['suburb'],
                    'delivery_city' => $order->delivery['city'],
					'delivery_postcode' => $order->delivery['postcode'],
					'delivery_state' => $order->delivery['state'],
                    'delivery_country' => $order->delivery['country']['title'],
                    'delivery_address_format_id' => $order->delivery['format_id'],
					'billing_name' => $order->billing['firstname'] . ' ' . $order->billing['lastname'],
                    'billing_company' => $order->billing['company'],
					'billing_street_address' => $order->billing['street_address'],
					'billing_suburb' => $order->billing['suburb'],
                    'billing_city' => $order->billing['city'],
					'billing_postcode' => $order->billing['postcode'],
					'billing_state' => $order->billing['state'],
                    'billing_country' => $order->billing['country']['title'],
                    'billing_address_format_id' => $order->billing['format_id'],
					'payment_method' => $order->info['payment_method'],
					'cc_type' => $order->info['cc_type'],
					'cc_owner' => $order->info['cc_owner'],
					'cc_number' => $order->info['cc_number'],
					'cc_expires' => $order->info['cc_expires'],
					'date_purchased' => 'now()',
					'orders_status' => $paynow_ord_status_id,
                    'currency' => $order->info['currency'],
					'currency_value' => $order->info['currency_value']
					);

                tep_db_perform( TABLE_ORDERS, $sql_data_array );
				$insert_id = tep_db_insert_id();

                for ( $i = 0, $n = sizeof($order_totals); $i < $n; $i++ )
                {
                    $sql_data_array = array(
						'orders_id' => $insert_id,
						'title' => $order_totals[$i]['title'],
                        'text' => $order_totals[$i]['text'],
						'value' => $order_totals[$i]['value'],
                        'class' => $order_totals[$i]['code'],
						'sort_order' => $order_totals[$i]['sort_order']
						);

                    tep_db_perform( TABLE_ORDERS_TOTAL, $sql_data_array );
                }

                for ( $i = 0, $n = sizeof($order->products); $i < $n; $i++ )
                {
                    $sql_data_array = array(
						'orders_id' => $insert_id,
						'products_id' => tep_get_prid( $order->products[$i]['id'] ),
						'products_model' => $order->products[$i]['model'],
                        'products_name' => $order->products[$i]['name'],
						'products_price' => $order->products[$i]['price'],
						'final_price' => $order->products[$i]['final_price'],
                        'products_tax' => $order->products[$i]['tax'],
						'products_quantity' => $order->products[$i]['qty']
						);

                    tep_db_perform( TABLE_ORDERS_PRODUCTS, $sql_data_array );

                    $order_products_id = tep_db_insert_id();

                    $attributes_exist = '0';
                    if( isset($order->products[$i]['attributes']) )
                    {
                        $attributes_exist = '1';
                        for ( $j = 0, $n2 = sizeof($order->products[$i]['attributes']); $j < $n2; $j++ )
                        {
                            if( DOWNLOAD_ENABLED == 'true' )
                            {
                                $attributes_query =
									"SELECT popt.products_options_name, poval.products_options_values_name,
										pa.options_values_price, pa.price_prefix, pad.products_attributes_maxdays,
										pad.products_attributes_maxcount , pad.products_attributes_filename
                                    FROM " . TABLE_PRODUCTS_OPTIONS ." popt,
										". TABLE_PRODUCTS_OPTIONS_VALUES ." poval,
										". TABLE_PRODUCTS_ATTRIBUTES ." pa
                                       LEFT JOIN ". TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD ." pad ON pa.products_attributes_id = pad.products_attributes_id
                                    WHERE pa.products_id = '". $order->products[$i]['id'] ."'
                                       AND pa.options_id = '". $order->products[$i]['attributes'][$j]['option_id'] ."'
                                       AND pa.options_id = popt.products_options_id
                                       AND pa.options_values_id = '". $order->products[$i]['attributes'][$j]['value_id'] ."'
                                       AND pa.options_values_id = poval.products_options_values_id
                                       AND popt.language_id = '". $languages_id ."'
                                       AND poval.language_id = '". $languages_id ."'";
                                $attributes = tep_db_query( $attributes_query );
                            }
                            else
                            {
                                $attributes = tep_db_query(
									"SELECT popt.products_options_name, poval.products_options_values_name,
										pa.options_values_price, pa.price_prefix
									FROM ". TABLE_PRODUCTS_OPTIONS ." popt,
										". TABLE_PRODUCTS_OPTIONS_VALUES ." poval,
										". TABLE_PRODUCTS_ATTRIBUTES . " pa
									WHERE pa.products_id = '" . $order->products[$i]['id'] ."'
										AND pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] ."'
										AND pa.options_id = popt.products_options_id
										AND pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] ."'
										AND pa.options_values_id = poval.products_options_values_id
										AND popt.language_id = '". $languages_id . "'
										AND poval.language_id = '" . $languages_id . "'" );
                            }
                            $attributes_values = tep_db_fetch_array( $attributes );

                            $sql_data_array = array(
								'orders_id' => $insert_id,
								'orders_products_id' => $order_products_id,
                                'products_options' => $attributes_values['products_options_name'],
                                'products_options_values' => $attributes_values['products_options_values_name'],
                                'options_values_price' => $attributes_values['options_values_price'],
                                'price_prefix' => $attributes_values['price_prefix']
								);

                            tep_db_perform( TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $sql_data_array );

                            if( (DOWNLOAD_ENABLED == 'true') &&
								isset($attributes_values['products_attributes_filename']) &&
                                tep_not_null($attributes_values['products_attributes_filename']) )
                            {
                                $sql_data_array = array(
									'orders_id' => $insert_id,
									'orders_products_id' => $order_products_id,
                                    'orders_products_filename' => $attributes_values['products_attributes_filename'],
                                    'download_maxdays' => $attributes_values['products_attributes_maxdays'],
                                    'download_count' => $attributes_values['products_attributes_maxcount']
									);

                                tep_db_perform( TABLE_ORDERS_PRODUCTS_DOWNLOAD, $sql_data_array );
                            }
                        }
                    }
                }

                $cart_PayNow_ID = $cartID . '-' . $insert_id;
                tep_session_register( 'cart_PayNow_ID' );
            }
        }

        return false;
    }

	/**
     * process_button
     *
     * Returns the extra form data in checkout_confirmation. Used to pass data
	 * to Pay Now as hidden fields in a POST-type form string $form_action_url.
	 * The target of the form for the confirmation button in
	 * checkout_confirmation.php is used to select the address to post to.
	 *
	 * >> Called by checkout_confirmation.php
	 * 
	 * TODO Change references of merchant_id to (Pay Now) Service Key
	 * TODO Change references of service_key to (Pay Now) Software Vendor Key
	 * TODO Remove sandbox code
	 * 
     */
    function process_button()
    {
        pnlog( __METHOD__ .': bof' );
        
    	// Variable declaration
        global $customer_id, $order, $sendto, $currency;
        global $cart_PayNow_ID, $paynow_ord_status_id;
        $process_button_string = '';

		// Get Order ID
        $order_id = substr( $cart_PayNow_ID, strpos( $cart_PayNow_ID, '-' ) + 1 );

		// Generate description
        foreach ( $order->products as $p_details )
        {
            $item_description .= $p_details['name'] .' x '. $p_details['qty'] .' @ '.
				$this->format_raw( $p_details['price'] ) .',';
        }

		// Remove the last ","
        $item_description = substr( $item_description, 0, -1 );

        // Use appropriate merchant identifiers
        $serviceKey = trim( MODULE_PAYMENT_PAYNOW_SERVICE_KEY );
         
        // Clean amount
        $amount = str_replace( ',', '', $order->info['total'] );
        $amount = number_format( $amount, 2, '.', '' );

		// Passing hidden fields to process payment
        $process_button_string =
        	// Merchant fields			
			tep_draw_hidden_field( 'm1', $serviceKey ) .
			tep_draw_hidden_field( 'm2', "24ade73c-98cf-47b3-99be-cc7b867b3080" ) .
			
			// Customer details			
            tep_draw_hidden_field( 'm9', substr( trim( $order->customer['email_address'] ), 0, 100 ) ) .
			tep_draw_hidden_field( 'm4', $customer_id ).

			// Item details updated 18 Aug 2014 to be more descriptive
            tep_draw_hidden_field ('p3', $item_description . ' (' . $order->billing['firstname'] . ' ' . $order->billing['lastname'] . ' - #' . $order_id . ')' ).
            // m6 added 18 Aug 2014, same as p3
            tep_draw_hidden_field ('m6', $item_description . ' (' . $order->billing['firstname'] . ' ' . $order->billing['lastname'] . ' - #' . $order_id . ')' ).

			//tep_draw_hidden_field( 'p3', substr( trim( $item_description ), 0, 255 ) ) .
            tep_draw_hidden_field( 'p4', $amount ) .
            
            tep_draw_hidden_field( 'm10', 'osCsid=' . tep_session_id()) .
            
			tep_draw_hidden_field( 'p2', $order_id );
            
			pnlog (__METHOD__ . 'Sending these fields to Sage Pay Now', $process_button_string);
		
        return $process_button_string;
    }

    /**
     * before_process
     *
	 * PDT Verification is done here before the order is processed. This function
	 * would normally end and pass control back to "checkout_process.php", but in
	 * our case that can't be done and most of the code after where
	 * "before_process" was originally called has been copied and pasted here.
	 *
	 * >> Called by checkout_process.php before order is finalised
     */
    function before_process()
    {
        pnlog( __METHOD__ .': bof' );

    	//-----------------------------------------------------------------------
    	// bof: CODE FROM THE STANDARD "checkout_process.php" FILE
    	//-----------------------------------------------------------------------
		global $customer_id, $order, $order_totals, $sendto, $billto,
			$languages_id, $payment, $currencies, $cart, $cart_PayNow_ID;

		//-- PAYNOW MOD: bof --//
		$order_id = substr( $cart_PayNow_ID, strpos( $cart_PayNow_ID, '-' ) + 1 );

		// Check if order has the initial Pay Now "Preparing" status
		$check_query = tep_db_query(
			"SELECT `orders_status`
			FROM ". TABLE_ORDERS. "
			WHERE `orders_id` = '" . (int)$order_id . "'" );

		if( tep_db_num_rows( $check_query ) )
		{
			$check = tep_db_fetch_array( $check_query );

			if( $check['orders_status'] == MODULE_PAYMENT_PAYNOW_PREPARE_ORDER_STATUS_ID )
			{
				$sql_data_array = array(
					'orders_id' => $order_id,
					'orders_status_id' => MODULE_PAYMENT_PAYNOW_PREPARE_ORDER_STATUS_ID,
					'date_added' => 'now()',
					'customer_notified' => '0',
				'comments' => '' );

				tep_db_perform( TABLE_ORDERS_STATUS_HISTORY, $sql_data_array );
			}
		}

		// Update order status for payment received status
		tep_db_query(
			"UPDATE ". TABLE_ORDERS ."
			SET `orders_status` = '" . (MODULE_PAYMENT_PAYNOW_ORDER_STATUS_ID > 0 ? (int)MODULE_PAYMENT_PAYNOW_ORDER_STATUS_ID : (int)DEFAULT_ORDERS_STATUS_ID) . "',
				`last_modified` = now()
			WHERE orders_id = '" . (int)$order_id . "'" );

		// Update order history
		$sql_data_array = array(
			'orders_id' => $order_id,
			'orders_status_id' => (MODULE_PAYMENT_PAYNOW_ORDER_STATUS_ID > 0 ? (int)MODULE_PAYMENT_PAYNOW_ORDER_STATUS_ID : (int)DEFAULT_ORDERS_STATUS_ID),
			'date_added' => 'now()',
			'customer_notified' => (SEND_EMAILS == 'true') ? '1' : '0',
			'comments' => $order->info['comments']);
		tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
		//-- PAYNOW MOD: eof --//

		// initialized for the email confirmation
		$products_ordered = '';
		$subtotal = 0;
		$total_tax = 0;

		for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
		// Stock Update - Joao Correia
		if (STOCK_LIMITED == 'true') {
		  if (DOWNLOAD_ENABLED == 'true') {
		    $stock_query_raw = "SELECT products_quantity, pad.products_attributes_filename
		                        FROM " . TABLE_PRODUCTS . " p
		                        LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES . " pa
		                        ON p.products_id=pa.products_id
		                        LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
		                        ON pa.products_attributes_id=pad.products_attributes_id
		                        WHERE p.products_id = '" . tep_get_prid($order->products[$i]['id']) . "'";
		// Will work with only one option for downloadable products
		// otherwise, we have to build the query dynamically with a loop
		    $products_attributes = $order->products[$i]['attributes'];
		    if (is_array($products_attributes)) {
		      $stock_query_raw .= " AND pa.options_id = '" . $products_attributes[0]['option_id'] . "' AND pa.options_values_id = '" . $products_attributes[0]['value_id'] . "'";
		    }
		    $stock_query = tep_db_query($stock_query_raw);
		  } else {
		    $stock_query = tep_db_query("select products_quantity from " . TABLE_PRODUCTS . " where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
		  }
		  if (tep_db_num_rows($stock_query) > 0) {
		    $stock_values = tep_db_fetch_array($stock_query);
		// do not decrement quantities if products_attributes_filename exists
		    if ((DOWNLOAD_ENABLED != 'true') || (!$stock_values['products_attributes_filename'])) {
		      $stock_left = $stock_values['products_quantity'] - $order->products[$i]['qty'];
		    } else {
		      $stock_left = $stock_values['products_quantity'];
		    }
		    tep_db_query("update " . TABLE_PRODUCTS . " set products_quantity = '" . $stock_left . "' where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
		    if ( ($stock_left < 1) && (STOCK_ALLOW_CHECKOUT == 'false') ) {
		      tep_db_query("update " . TABLE_PRODUCTS . " set products_status = '0' where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
		    }
		  }
		}

		// Update products_ordered (for bestsellers list)
		tep_db_query("update " . TABLE_PRODUCTS . " set products_ordered = products_ordered + " . sprintf('%d', $order->products[$i]['qty']) . " where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");

		//------insert customer choosen option to order--------
		$attributes_exist = '0';
		$products_ordered_attributes = '';
		if (isset($order->products[$i]['attributes'])) {
		  $attributes_exist = '1';
		  for ($j=0, $n2=sizeof($order->products[$i]['attributes']); $j<$n2; $j++) {
		    if (DOWNLOAD_ENABLED == 'true') {
		      $attributes_query = "select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pad.products_attributes_maxdays, pad.products_attributes_maxcount , pad.products_attributes_filename
		                           from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa
		                           left join " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
		                           on pa.products_attributes_id=pad.products_attributes_id
		                           where pa.products_id = '" . $order->products[$i]['id'] . "'
		                           and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "'
		                           and pa.options_id = popt.products_options_id
		                           and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "'
		                           and pa.options_values_id = poval.products_options_values_id
		                           and popt.language_id = '" . $languages_id . "'
		                           and poval.language_id = '" . $languages_id . "'";
		      $attributes = tep_db_query($attributes_query);
		    } else {
		      $attributes = tep_db_query("select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa where pa.products_id = '" . $order->products[$i]['id'] . "' and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "' and pa.options_id = popt.products_options_id and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "' and pa.options_values_id = poval.products_options_values_id and popt.language_id = '" . $languages_id . "' and poval.language_id = '" . $languages_id . "'");
		    }
		    $attributes_values = tep_db_fetch_array($attributes);

		    $products_ordered_attributes .= "\n\t" . $attributes_values['products_options_name'] . ' ' . $attributes_values['products_options_values_name'];
		  }
		}
		//------insert customer choosen option eof ----
		$total_weight += ($order->products[$i]['qty'] * $order->products[$i]['weight']);
		$total_tax += tep_calculate_tax($total_products_price, $products_tax) * $order->products[$i]['qty'];
		$total_cost += $total_products_price;

		$products_ordered .= $order->products[$i]['qty'] . ' x ' . $order->products[$i]['name'] . ' (' . $order->products[$i]['model'] . ') = ' . $currencies->display_price($order->products[$i]['final_price'], $order->products[$i]['tax'], $order->products[$i]['qty']) . $products_ordered_attributes . "\n";
		}

		// lets start with the email confirmation
		$email_order = STORE_NAME . "\n" .
		             EMAIL_SEPARATOR . "\n" .
		             EMAIL_TEXT_ORDER_NUMBER . ' ' . $order_id . "\n" .
		             EMAIL_TEXT_INVOICE_URL . ' ' . tep_href_link(FILENAME_ACCOUNT_HISTORY_INFO, 'order_id=' . $order_id, 'SSL', false) . "\n" .
		             EMAIL_TEXT_DATE_ORDERED . ' ' . strftime(DATE_FORMAT_LONG) . "\n\n";
		if ($order->info['comments']) {
		$email_order .= tep_db_output($order->info['comments']) . "\n\n";
		}
		$email_order .= EMAIL_TEXT_PRODUCTS . "\n" .
		              EMAIL_SEPARATOR . "\n" .
		              $products_ordered .
		              EMAIL_SEPARATOR . "\n";

		for ($i=0, $n=sizeof($order_totals); $i<$n; $i++) {
		$email_order .= strip_tags($order_totals[$i]['title']) . ' ' . strip_tags($order_totals[$i]['text']) . "\n";
		}

		if ($order->content_type != 'virtual') {
		$email_order .= "\n" . EMAIL_TEXT_DELIVERY_ADDRESS . "\n" .
		                EMAIL_SEPARATOR . "\n" .
		                tep_address_label($customer_id, $sendto, 0, '', "\n") . "\n";
		}

		$email_order .= "\n" . EMAIL_TEXT_BILLING_ADDRESS . "\n" .
		              EMAIL_SEPARATOR . "\n" .
		              tep_address_label($customer_id, $billto, 0, '', "\n") . "\n\n";

		if (is_object($$payment)) {
		$email_order .= EMAIL_TEXT_PAYMENT_METHOD . "\n" .
		                EMAIL_SEPARATOR . "\n";
		$payment_class = $$payment;
		$email_order .= $payment_class->title . "\n\n";
		if ($payment_class->email_footer) {
		  $email_order .= $payment_class->email_footer . "\n\n";
		}
		}

		tep_mail($order->customer['firstname'] . ' ' . $order->customer['lastname'], $order->customer['email_address'], EMAIL_TEXT_SUBJECT, $email_order, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);

		// send emails to other people
		if (SEND_EXTRA_ORDER_EMAILS_TO != '') {
		tep_mail('', SEND_EXTRA_ORDER_EMAILS_TO, EMAIL_TEXT_SUBJECT, $email_order, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
		}

		// load the after_process function from the payment modules
		$this->after_process();

		$cart->reset(true);

		// unregister session variables used during checkout
		tep_session_unregister('sendto');
		tep_session_unregister('billto');
		tep_session_unregister('shipping');
		tep_session_unregister('payment');
		tep_session_unregister('comments');

		//-- PAYNOW MOD: bof --//
		tep_session_unregister( 'cart_PayNow_ID' );
		//-- PAYNOW MOD: eof --//

		tep_redirect( tep_href_link( FILENAME_CHECKOUT_SUCCESS, '', 'SSL' ) );
		//-----------------------------------------------------------------------
    	// eof: CODE FROM THE STANDARD "checkout_process.php" FILE
        //-----------------------------------------------------------------------
    }

	/**
     * after_process
     *
     * Not used in our case as there is nothing which needs to be done.
     *
     * >> Called by checkout_process.php after order is finalised
     */
    function after_process()
    {
        pnlog( __METHOD__ .': bof' );
        
        return false;
    }

	/**
     * output_error
     */
	function output_error()
	{
        pnlog( __METHOD__ .': bof' );
        
      return false;
    }

	/**
     * check
     *
     * Check that Sage Pay Now module is enabled
     *
     * >> Standard osCommerce
     */
    function check()
    {
        pnlog( __METHOD__ .': bof' );
        
        if( !isset($this->_check) )
        {
            $check_query = tep_db_query(
				"SELECT `configuration_value`
				FROM ". TABLE_CONFIGURATION ."
                WHERE `configuration_key` = 'MODULE_PAYMENT_PAYNOW_STATUS'" );
            $this->_check = tep_db_num_rows( $check_query );
        }

        return $this->_check;
    }

    /**
     * install
     *
     * Installs Sage Pay Now payment module in osCommerce and creates necessary
     * configuration fields which need to be supplied by store owner.
     *
     * >> Standard osCommerce
     */
    function install()
    {
        pnlog( __METHOD__ .': bof' );
        
    	//// Insert Pay Now order status entry if not present
        // Get Pay Now order status id
        $check_query = tep_db_query(
			"SELECT `orders_status_id` AS `status_id`
			FROM ". TABLE_ORDERS_STATUS ."
            WHERE `orders_status_name` = '". $this->status_text ."' LIMIT 1" );

		// If Sage Pay Now order status entry not present, add it
        if( tep_db_num_rows( $check_query ) < 1 )
        {
            $status_query = tep_db_query(
				"SELECT max( orders_status_id ) AS status_id
				FROM ". TABLE_ORDERS_STATUS );
            $status = tep_db_fetch_array( $status_query );

            $statusId = $status['status_id'] + 1;

            $languages = tep_get_languages();

            foreach ( $languages as $lang )
            {
                tep_db_query(
					"INSERT INTO ". TABLE_ORDERS_STATUS ." (orders_status_id, language_id, orders_status_name)
					VALUES ('". $statusId ."', '" . $lang['id'] ."', '". $this->status_text ."')" );
            }

            $flags_query = tep_db_query(
				"DESCRIBE ". TABLE_ORDERS_STATUS ." public_flag" );

			if( tep_db_num_rows( $flags_query ) == 1 )
            {
                tep_db_query(
					"UPDATE " . TABLE_ORDERS_STATUS ."
                    SET `public_flag` = 0 AND `downloads_flag` = 0
					WHERE `orders_status_id` = '" . $statusId ."'" );
            }
        }
        // Else, if Sage Pay Now order status present, get the ID
        else
        {
			$data = tep_db_fetch_array( $check_query );

            $statusId = $data['status_id'];
		}

		//// Insert configuration values
		// MODULE_PAYMENT_PAYNOW_STATUS (Default = False)
        tep_db_query(
			"INSERT INTO ". TABLE_CONFIGURATION ."( configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added )
			VALUES( 'Enable Pay Now?', 'MODULE_PAYMENT_PAYNOW_STATUS', 'False', 'Do you want to enable Sage Pay Now?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now() )" );
		// MODULE_PAYMENT_PAYNOW_SERVICE_KEY (Default = <blank>)
        tep_db_query(
			"INSERT INTO ". TABLE_CONFIGURATION ."( configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added )
			VALUES( 'Sage Pay Now Service Key', 'MODULE_PAYMENT_PAYNOW_SERVICE_KEY', '', 'Your Sage Pay Now Service Key', '6', '0', now() )" );				
        // MODULE_PAYMENT_PAYNOW_DEBUG (Default = false)
		tep_db_query(
			"INSERT INTO ". TABLE_CONFIGURATION ."( configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added )
			VALUES( 'Enable debugging?', 'MODULE_PAYMENT_PAYNOW_DEBUG', 'False', 'Do you want to enable debugging?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now() )" );
		// MODULE_PAYMENT_PAYNOW_DEBUG_EMAIL (Default = <blank>)
		tep_db_query(
			"INSERT INTO ". TABLE_CONFIGURATION ."( configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added )
			VALUES( 'Debug email address', 'MODULE_PAYMENT_PAYNOW_DEBUG_EMAIL', '', 'Email address to receive debugging emails', '6', '0', now() )" );
		// MODULE_PAYMENT_PAYNOW_SORT_ORDER (Default = 0)
		tep_db_query(
			"INSERT INTO " . TABLE_CONFIGURATION . "( configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added )
			VALUES( 'Sort Display Order', 'MODULE_PAYMENT_PAYNOW_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())" );
		// MODULE_PAYMENT_PAYNOW_PREPARE_ORDER_STATUS_ID
		tep_db_query(
			"INSERT INTO " . TABLE_CONFIGURATION . "( configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added )
			VALUES( 'Set Preparing Order Status', 'MODULE_PAYMENT_PAYNOW_PREPARE_ORDER_STATUS_ID', '" . $statusId . "', 'Set the status of prepared orders made with Pay Now to this value', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
		// MODULE_PAYMENT_PAYNOW_ORDER_STATUS_ID
		tep_db_query(
			"INSERT INTO " . TABLE_CONFIGURATION . "( configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added )
			VALUES( 'Set Acknowledged Order Status', 'MODULE_PAYMENT_PAYNOW_ORDER_STATUS_ID', '0', 'Set the status of orders made with Pay Now to this value', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
    }

    /**
     * remove
     *
     * Removes Pay Now module from store.
     *
     * This is done by removing all the configuration values. The order status
     * entry is NOT removed, as there may be pre-existing orders which are
     * using that status (either currently or in the order_status_history
	 * table) which should be retained for historical reasons.
	 *
	 * >> Standard osCommerce
     */
    function remove()
    {
        pnlog( __METHOD__ .': bof' );
        
    	// Remove all configuration values
        tep_db_query(
			"DELETE FROM ". TABLE_CONFIGURATION ."
            WHERE `configuration_key` LIKE 'MODULE_PAYMENT_PAYNOW%'" );
    }

	/**
     * keys
     *
     * Returns an array of the configuration keys for the module
     *
     * >> Standard osCommerce
     */
    function keys()
    {
        pnlog( __METHOD__ .': bof' );
        
        return array(
			'MODULE_PAYMENT_PAYNOW_STATUS',
			'MODULE_PAYMENT_PAYNOW_SERVICE_KEY',                       
            'MODULE_PAYMENT_PAYNOW_DEBUG',
            'MODULE_PAYMENT_PAYNOW_DEBUG_EMAIL',
            'MODULE_PAYMENT_PAYNOW_SORT_ORDER',            
            'MODULE_PAYMENT_PAYNOW_PREPARE_ORDER_STATUS_ID',
            'MODULE_PAYMENT_PAYNOW_ORDER_STATUS_ID',
			);
    }

    /**
     * format_raw
     *
     * Format prices without currency formatting
     */
    function format_raw( $number, $currency_code = '', $currency_value = '' )
    {
        pnlog( __METHOD__ .': bof' );
        
        global $currencies, $currency;

        if( empty($currency_code) || !$this->is_set($currency_code) )
            $currency_code = $currency;

        if( empty($currency_value) || !is_numeric($currency_value) )
            $currency_value = $currencies->currencies[$currency_code]['value'];

        return number_format(
			tep_round( $number * $currency_value, $currencies->currencies[$currency_code]['decimal_places'] ),
				$currencies->currencies[$currency_code]['decimal_places'], '.', '' );
    }
}
?>