<?php
/*
  $Id$

  DIBS module for osCommerce

  DIBS Payment Systems
  http://www.dibs.dk

  Copyright (c) 2011 DIBS A/S

  Released under the GNU General Public License
 
*/

require_once dirname(__FILE__) . '/dibs_api/dibs_helpers_cms.php';
require_once dirname(__FILE__) . '/dibs_api/dibs_helpers.php';
require_once dirname(__FILE__) . '/dibs_api/dibs_api.php';

class dibs extends dibs_api {
    
    /** START OF osCommerce SPECIFIC METHODS **/
    const DIBS_LOGO_URL = 'http://tech.dibspayment.com/sites/tech/files/pictures/LOGO/DIBS/PNG/DIBS_logo_blue_RGB.png';
    
    var $code, $title, $description, $enabled, $p_text;
    /**
     * osCommerce constructor
     * 
     * @global array $order 
     */
    function dibs() {
        global $order;

        $this->signature = 'dibs|dibs|4.0.8.1|2.2';
        //$this->api_version = '3.1';

        $this->code = 'dibs';
        $this->title = MODULE_PAYMENT_DIBS_TEXT_TITLE_MODULES;
        //$this->public_customer_title = MODULE_PAYMENT_DIBS_TEXT_TITLE;
        $this->public_title = MODULE_PAYMENT_DIBS_TEXT_TITLE; //"dibs";
        $this->description = MODULE_PAYMENT_DIBS_TEXT_DESCRIPTION;
        $this->sort_order = MODULE_PAYMENT_DIBS_SORT_ORDER;
        $this->enabled = ((MODULE_PAYMENT_DIBS_STATUS == 'True') ? true : false);

        if ((int)MODULE_PAYMENT_DIBS_ORDER_INITIAL_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_DIBS_ORDER_INITIAL_STATUS_ID;
        }
        if (is_object($order)) $this->update_status();
        
        // Two different entrypoint urls for D@ ad DX platforms
        // For more details about this platforms look here:
        // http://tech.dibspayment.com/platformfinder 
        if(MODULE_PAYMENT_DIBS_PLATFORM == 'D2') {
            $this->form_action_url = "https://sat1.dibspayment.com/dibspaymentwindow/entrypoint";
        } else if(MODULE_PAYMENT_DIBS_PLATFORM == 'DX') {
            $this->form_action_url = "https://payment.dibspayment.com/dpw/entrypoint";
        }
    }
    
    /**
     * osCommerce form handler
     * 
     * @global type $HTTP_POST_VARS
     * @global array $order
     * @global type $currencies
     * @global type $currency
     * @global type $languages_id
     * @global type $shipping
     * @return string 
     */
    function process_button() {
        global $HTTP_POST_VARS, $order, $currencies, $currency,  $languages_id, $shipping, $dibs_order_id;
        
        /** DIBS integration */
        $aData = $this->dibs_api_requestModel($order);
        $aData['orderid'] = $dibs_order_id;
        $sMAC = $this->dibs_api_calcMAC($aData);
        if($sMAC != "") $aData['MAC'] = $sMAC;
        /* DIBS integration **/
        unset($_SESSION['dibs_data']);
        
        $this->osc_processHelperTable($this->dibs_api_orderObject($order));
        
        $sProcess_button_string = "";
        foreach($aData as $sName => $sValue) {
            $sProcess_button_string .= tep_draw_hidden_field($sName, $sValue);
        }
        
        /*  This is a hack, we need to remove 'comment' field from form, 
         *  because it cause error if we use MAC code
         *  OsCommerce always add hidden field 'comment' to final form see: 
         *  \osc\ver_2.3.4\checkout_confirmation.php line 275
         */
        $sProcess_button_string .= "<script type=\"text/javascript\"> var form = document.forms[0];"
                                .  "form.elements[\"comments\"].remove(); </script>";
        return $sProcess_button_string;
    }
    
    function update_status() {
	global $order;
		
	if (($this->enabled == true) && ((int)MODULE_PAYMENT_DIBS_ZONE > 0)) {
            $check_flag = false;
            $check_query = tep_db_query("SELECT `zone_id` FROM " . TABLE_ZONES_TO_GEO_ZONES . 
                                        " WHERE `geo_zone_id` = '" . MODULE_PAYMENT_DIBS_ZONE . 
                                        "' AND `zone_country_id` = '" . $order->billing['country']['id'] . 
                                        "' ORDER BY `zone_id`");
		
            while ($check = tep_db_fetch_array($check_query)) {
		if ($check['zone_id'] < 1) {
                    $check_flag = true;
                    break;
		}
                elseif ($check['zone_id'] == $order->billing['zone_id']) {
                    $check_flag = true;
                    break;
                }
            }
			
            if ($check_flag == false) {
                $this->enabled = false;
            }
        }
        else {
            $aPost = $this->osc_detectPost();
            if($aPost !== FALSE) $this->enabled = true;
            unset($aPost);
        }
    }
    
    /*
     * After customer redirect to shop we handle stock,
     * add comments to order and set order status,
     * send email confiramtion to customer. Email 
     * confirmation contains also DIBS transaction details
     */
    
    function before_process() {
      global $order, $dibs_order_id , $customer_id, $order, $order_totals;
      global $sendto, $billto, $languages_id, $payment, $currencies, $cart, $$payment, $DIBS_post_return;
     
      $order_id = $dibs_order_id;
      
      // We want catch  customer's comment if it it
      $customers_comment = $order->info['comments'];
     
      $order_query = tep_db_query("select orders_status from " . TABLE_ORDERS . " where orders_id = '" . (int)$order_id . "' and customers_id = '" . (int)$customer_id . "'");

      if (!tep_db_num_rows($order_query)) {
        tep_redirect(tep_href_link(FILENAME_SHOPPING_CART));
      }

      $order = tep_db_fetch_array($order_query);

      $order_status_id = (MODULE_PAYMENT_DIBS_ORDER_STATUS_ID > 0 ? (int)MODULE_PAYMENT_DIBS_ORDER_STATUS_ID : (int)DEFAULT_ORDERS_STATUS_ID);
      
      $dibsInvoiceFields       = array("acquirerLastName",          "acquirerFirstName",
                                 "acquirerDeliveryAddress",   "acquirerDeliveryPostalCode",
                                 "acquirerDeliveryPostalPlace", "transaction" );
      $dibsInvoiceFieldsString = "";
       
      foreach($DIBS_post_return as $key=>$value) {
              if(in_array($key, $dibsInvoiceFields)) {
                   $dibsInvoiceFieldsString .= "{$key}={$value}\n";              
              }
      }
      
      
      $comments = "\n" . "Sucessfully returned from DIBS with status: {$DIBS_post_return['status']} \n" . $order->info['comments'] . $dibsInvoiceFieldsString;
     
      if ($order['orders_status'] == MODULE_PAYMENT_DIBS_ORDER_INITIAL_STATUS_ID) {
        tep_db_query("update " . TABLE_ORDERS . " set orders_status = '" . $order_status_id . "', last_modified = now() where orders_id = '" . (int)$order_id . "'");
        
        
        // We save customer's comments and DIBS transaction 
        // details like comments in 2 separate inserts.
        $sql_data_array = array('orders_id' => $order_id,
                                'orders_status_id' => $order_status_id,
                                'date_added' => 'now()',
                                'customer_notified' => (SEND_EMAILS == 'true') ? '1' : '0',
                                'comments' => $comments);
        if( $customers_comment ) {
             $sql_data_array['comments'] = $customers_comment;
             tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
             $sql_data_array['comments'] = $comments;
        }
        tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array); 
       
      } else {
        $order_status_query = tep_db_query("select orders_status_history_id from " . TABLE_ORDERS_STATUS_HISTORY . " where orders_id = '" . (int)$order_id . "' and orders_status_id = '" . (int)$order_status_id . "' and comments = '' order by date_added desc limit 1");

        if (tep_db_num_rows($order_status_query)) {
          $order_status = tep_db_fetch_array($order_status_query);

          $sql_data_array = array('customer_notified' => (SEND_EMAILS == 'true') ? '1' : '0',
                                  'comments' => $order->info['comments']);

          tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array, 'update', "orders_status_history_id = '" . (int)$order_status['orders_status_history_id'] . "'");
        }
      }

 
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
      if ($comments =  $customers_comment  . $comments ) {
        $email_order .= tep_db_output($comments) . "\n\n";
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

      tep_session_unregister('dibs_order_id');

      tep_redirect(tep_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
        
        return false;
    }
    
    function after_process() {
       return false;
    }

    function output_error() {
      return false;
    }
    
    function get_error() {
      $error = array('title' => '',
                     'error' => MODULE_PAYMENT_DIBS_ERROR_MID);

      return $error;
    }
    
    function selection() {
      return array('id' => $this->code,
                   'module' => '<img width=159; height=53; src="'.self::DIBS_LOGO_URL.'" alt="DIBS Payment Services" style="vertical-align: middle; margin-right: 10px;" /> ' .
                               $this->public_title . 
                               (strlen(MODULE_PAYMENT_DIBS_TEXT_PUBLIC_DESCRIPTION) > 0 ? ' (' . 
                               MODULE_PAYMENT_DIBS_TEXT_PUBLIC_DESCRIPTION . ')' : ''));
    }
    
    /*
     * If merchantid is empty, just redirect to current page with error.
     */
    function pre_confirmation_check() {
       $error = '';
        if (MODULE_PAYMENT_DIBS_MID == "") {
            $error = 'Merchant ID cannot be null, please fill Merchant Id field on module settings page';
	}
        if( $error ) {
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode($error), 'SSL'));
        }
        
        return true;
	
    }
    /*
     * Create order before customer redirect to DIBS Pay Win, 
     * we can get real order id that will be send to DIBS. 
     * Save all order details. 
     */
    function confirmation() {
      global $cartID, $dibs_order_id, $customer_id, $languages_id, $order, $order_total_modules;

      $insert_order = false;

      if (tep_session_is_registered('dibs_order_id')) {
        $order_id = $dibs_order_id;

        $curr_check = tep_db_query("select currency from " . TABLE_ORDERS . " where orders_id = '" . (int)$order_id . "'");
        $curr = tep_db_fetch_array($curr_check);
        
        if ( ($curr['currency'] != $order->info['currency']) || ($cartID != substr($cart_RBS_Worldpay_Hosted_ID, 0, strlen($cartID))) ) {
          $check_query = tep_db_query('select orders_id from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '" limit 1');

          if (tep_db_num_rows($check_query) < 1) {
            tep_db_query('delete from ' . TABLE_ORDERS . ' where orders_id = "' . (int)$order_id . '"');
            tep_db_query('delete from ' . TABLE_ORDERS_TOTAL . ' where orders_id = "' . (int)$order_id . '"');
            tep_db_query('delete from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '"');
            tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS . ' where orders_id = "' . (int)$order_id . '"');
            tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . ' where orders_id = "' . (int)$order_id . '"');
            tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_DOWNLOAD . ' where orders_id = "' . (int)$order_id . '"');
          }

          $insert_order = true;
        }
      } else {
        $insert_order = true;
      }

      if ($insert_order == true) {
        $order_totals = array();
        if (is_array($order_total_modules->modules)) {
          reset($order_total_modules->modules);
          while (list(, $value) = each($order_total_modules->modules)) {
            $class = substr($value, 0, strrpos($value, '.'));
            if ($GLOBALS[$class]->enabled) {
              for ($i=0, $n=sizeof($GLOBALS[$class]->output); $i<$n; $i++) {
                if (tep_not_null($GLOBALS[$class]->output[$i]['title']) && tep_not_null($GLOBALS[$class]->output[$i]['text'])) {
                  $order_totals[] = array('code' => $GLOBALS[$class]->code,
                                          'title' => $GLOBALS[$class]->output[$i]['title'],
                                          'text' => $GLOBALS[$class]->output[$i]['text'],
                                          'value' => $GLOBALS[$class]->output[$i]['value'],
                                          'sort_order' => $GLOBALS[$class]->sort_order);
                }
              }
            }
          }
        }

        $sql_data_array = array('customers_id' => $customer_id,
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
                                'orders_status' => $order->info['order_status'],
                                'currency' => $order->info['currency'],
                                'currency_value' => $order->info['currency_value']);

        tep_db_perform(TABLE_ORDERS, $sql_data_array);

        $insert_id = tep_db_insert_id();

        for ($i=0, $n=sizeof($order_totals); $i<$n; $i++) {
          $sql_data_array = array('orders_id' => $insert_id,
                                  'title' => $order_totals[$i]['title'],
                                  'text' => $order_totals[$i]['text'],
                                  'value' => $order_totals[$i]['value'],
                                  'class' => $order_totals[$i]['code'],
                                  'sort_order' => $order_totals[$i]['sort_order']);

          tep_db_perform(TABLE_ORDERS_TOTAL, $sql_data_array);
        }

        for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
          $sql_data_array = array('orders_id' => $insert_id,
                                  'products_id' => tep_get_prid($order->products[$i]['id']),
                                  'products_model' => $order->products[$i]['model'],
                                  'products_name' => $order->products[$i]['name'],
                                  'products_price' => $order->products[$i]['price'],
                                  'final_price' => $order->products[$i]['final_price'],
                                  'products_tax' => $order->products[$i]['tax'],
                                  'products_quantity' => $order->products[$i]['qty']);

          tep_db_perform(TABLE_ORDERS_PRODUCTS, $sql_data_array);

          $order_products_id = tep_db_insert_id();

          $attributes_exist = '0';
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

              $sql_data_array = array('orders_id' => $insert_id,
                                      'orders_products_id' => $order_products_id,
                                      'products_options' => $attributes_values['products_options_name'],
                                      'products_options_values' => $attributes_values['products_options_values_name'],
                                      'options_values_price' => $attributes_values['options_values_price'],
                                      'price_prefix' => $attributes_values['price_prefix']);

              tep_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $sql_data_array);

              if ((DOWNLOAD_ENABLED == 'true') && isset($attributes_values['products_attributes_filename']) && tep_not_null($attributes_values['products_attributes_filename'])) {
                $sql_data_array = array('orders_id' => $insert_id,
                                        'orders_products_id' => $order_products_id,
                                        'orders_products_filename' => $attributes_values['products_attributes_filename'],
                                        'download_maxdays' => $attributes_values['products_attributes_maxdays'],
                                        'download_count' => $attributes_values['products_attributes_maxcount']);

                tep_db_perform(TABLE_ORDERS_PRODUCTS_DOWNLOAD, $sql_data_array);
              }
            }
          }
        }
        $dibs_order_id = $insert_id;
        tep_session_register('dibs_order_id');
      }
     
      return array ('title' => '<img width=159; height=53; src="'.self::DIBS_LOGO_URL.'" alt="DIBS Payment Services" style="vertical-align: middle; margin-right: 10px;" />' . 
                               $this->public_title . 
                               (strlen(MODULE_PAYMENT_DIBS_TEXT_PUBLIC_DESCRIPTION) > 0 ? ' (' . 
                               MODULE_PAYMENT_DIBS_TEXT_PUBLIC_DESCRIPTION . ')' : '')
                     );
    }
    
    function javascript_validation() {
        return false;
    }
    
    function check() {
	if(!isset($this->_check )) {
            $sCheck_query = $this->dibs_helper_dbquery_read("SELECT configuration_value FROM " . 
                                                       TABLE_CONFIGURATION . 
                                                      " WHERE configuration_key = 
                                                      'MODULE_PAYMENT_DIBS_STATUS'" );
            $this->_check = tep_db_num_rows($sCheck_query);
	}
        return $this->_check;
    }
  
    /**
     * Succes page handler
     */
    function success() {
        global $order, $comments, $DIBS_post_return;
        if (isset($_POST['orderid'])) {
            $oOrder = $this->osc_getOrderData($_POST['orderid']);
            
            $oOrder->total = round( $oOrder->total, 2) * 100;
            $oOrder->currency =  $this->dibs_api_getCurrencyValue($oOrder->currency);  
        }
        else exit();

        $mErr = $this->dibs_api_checkMainFields($oOrder);
       
        if($mErr === FALSE) {
            $this->dibs_helper_dbquery_write("UPDATE `" . $this->dibs_helper_getdbprefix() . 
                                       "dibs_orderdata` SET `ordercancellation` = 0,
                                       `successaction` = 1 WHERE `orderid` = '" . 
                                       $oOrder->order_id . "' LIMIT 1;");
            if( $_POST['status'] == "ACCEPTED" || 
                    $_POST['status'] == "PENDING") {
                $DIBS_post_return = $_POST;
                tep_session_register('DIBS_post_return');
                $this->dibs_helper_redirect($this->dibs_helper_cmsurl(FILENAME_CHECKOUT_PROCESS));
            } else {
                $redirect_url = tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . "DIBS_returned_DECLINED_status&error=DIBSERROR", 'SSL');
                tep_redirect($redirect_url, '', 'SSL');
            }
        }
        else {
            echo $mErr;
            echo $this->dibs_api_errCodeToMessage($mErr);
            exit();
        }
    }
    
    /**
     * Callback handler
     */
    function callback(){
        $oOrder = $this->osc_getOrderData((string)$_POST['orderid']);
        $oOrder->total = round( $oOrder->total, 2) * 100;
        $oOrder->currency =  $this->dibs_api_getCurrencyValue($oOrder->currency);  
        $this->dibs_api_callback($oOrder);
    }
    
    /**
     * Cancel page handler
     */
    function cancel() {
        $aFields = array();
       if (isset($_POST['orderid'])) {
            $oOrder = $this->osc_getOrderData($_POST['orderid']);
            if(isset($oOrder->order_id) && $oOrder->order_id > 0) {
                $this->dibs_helper_dbquery_write("UPDATE `" . $this->dibs_helper_getdbprefix() . 
                                           "dibs_orderdata` SET `ordercancellation` = 1 
                                            WHERE `orderid` = '".$oOrder->order_id . 
                                           "' LIMIT 1;");
            }
	}
        
	$this->dibs_helper_redirect($this->dibs_helper_cmsurl(FILENAME_SHOPPING_CART));
    }
    
    function installApply($sName, $sConst, $sVal, $sDescr, $iSort, $sFunc, $sUseFunc = "NULL") {
        $this->dibs_helper_dbquery_write("INSERT INTO " . 
                                    TABLE_CONFIGURATION . "(
                                        configuration_title, 
                                        configuration_key, 
                                        configuration_value, 
                                        configuration_description, 
                                        configuration_group_id, 
                                        sort_order, 
                                        set_function,
                                        use_function,
                                        date_added
                                    ) 
                                    VALUES(
                                        '".$sName."',
                                        '".$sConst."',
                                        '".$sVal."', 
                                        '".$sDescr."', 
                                        '6', 
                                        '".$iSort."', 
                                        ".$sFunc.",
                                        ".$sUseFunc.",
                                        NOW()
                                    )"
                                  );
    }
    
    /**
     * osCommerce module uninstaller
     */
    function remove() {
        $this->dibs_helper_dbquery_write("DELETE FROM " . TABLE_CONFIGURATION . 
                                   " WHERE configuration_key in ('" . 
                                   implode("', '", $this->keys()) . "')");
    }
    
    /**
     * osCommerce module installer
     */
    function install() {
        $this->installApply('Enable DIBS module:', 'MODULE_PAYMENT_DIBS_STATUS', 
                            'True', 'Turn on DIBS module', 
                            '0', "'tep_cfg_select_option(array(\'True\', \'False\'),'");
        $this->installApply('Dibs platform D2/DX:', 'MODULE_PAYMENT_DIBS_PLATFORM', 
                            'D2', 'Which platform to use ?. You can detect your platform here: <a style="color:#2E6E9E" '
                                  .'href="http://tech.dibspayment.com/platformfinder">http://tech.dibspayment.com/platformfinder </a>' , 
                            '0', "'tep_cfg_select_option(array(\'D2\', \'DX\'),'");
        $this->installApply('Test mode:', 'MODULE_PAYMENT_DIBS_TESTMODE', 
                            'yes', 'Use test mode', 
                            4, "'tep_cfg_select_option(array(\'yes\', \'no\'),'");
        $this->installApply('Add fee:', 'MODULE_PAYMENT_DIBS_FEE', 
                            'no', 'Add fee to payment', 
                            6, "'tep_cfg_select_option(array(\'yes\', \'no\'),'");
        $this->installApply('Title:', 'MODULE_PAYMENT_DIBS_TEXT_TITLE',
                            'DIBS (PW) | Secure Payment Services', 
                            'Title of payment system that customer see on checkout.', 
                            1, 'NULL');
        $this->installApply('Merchant Id:', 'MODULE_PAYMENT_DIBS_MID', 
                            '', 'Your merchant id in DIBS service.', 
                            2, 'NULL');
        $this->installApply('Pertner Id:', 'MODULE_PAYMENT_DIBS_PID', 
                            '', 'Partner Id.', 
                            0, 'NULL');	
        $this->installApply('Account:', 'MODULE_PAYMENT_DIBS_ACCOUNT', 
                            '', 'An "account number" may be inserted in this field, so as to separate transactions at DIBS.', 
                            14, "NULL");
        $this->installApply('Paytype:', 'MODULE_PAYMENT_DIBS_PAYTYPE', 
                            'VISA,MC', 'This list must be comma separated with 
                            no spaces in between. E.g. VISA,MC', 
                            8, "NULL");
        $this->installApply('HMAC:', 'MODULE_PAYMENT_DIBS_HMAC', 
                            '', 'Your security code for Standart and Mobile Payment Windows', 
                            9, "NULL", "'dibs::osc_splitMac'");
        $this->installApply('Sort order:', 'MODULE_PAYMENT_DIBS_SORT_ORDER',
                            '0', 'Sort order in list of availiable payment methods.', 
                            22, "NULL");
        $this->installApply('Language Payment Windows:', 'MODULE_PAYMENT_DIBS_LANG', 
                            'en_UK', 'Language used in Payment Windows.', 
                            12, "'dibs::osc_selectGetLang('");
        $this->installApply('Distribution method:', 'MODULE_PAYMENT_DIBS_DISTR', 
                            '1', 'Invoice distribution.', 19, "'dibs::osc_selectGetDistr('");
        $this->installApply('Payment zone:', 'MODULE_PAYMENT_DIBS_ZONE', 
                            '0', 'If a zone is selected, only enable this payment method for that zone.', 
                            21, "'tep_cfg_pull_down_zone_classes('", "'tep_get_zone_class_title'");
        $this->installApply('Set order initial status', 'MODULE_PAYMENT_DIBS_ORDER_INITIAL_STATUS_ID',
                            '1', 'Set the status of initial orders made with this payment module to this value', 
                            20, "'tep_cfg_pull_down_order_statuses('", "'tep_get_order_status_name'");
        $this->installApply('Set order status', 'MODULE_PAYMENT_DIBS_ORDER_STATUS_ID',
                            '2', 'Set the status of orders made with this payment module to this value', 
                            20, "'tep_cfg_pull_down_order_statuses('", "'tep_get_order_status_name'");
	
    }

    /**
     * osCommerce config keys helper
     * 
     * @return array 
     */
    function keys() {
        return array('MODULE_PAYMENT_DIBS_STATUS', 'MODULE_PAYMENT_DIBS_TEXT_TITLE',
                     'MODULE_PAYMENT_DIBS_MID', 'MODULE_PAYMENT_DIBS_PLATFORM', 'MODULE_PAYMENT_DIBS_PID',
                     'MODULE_PAYMENT_DIBS_TESTMODE', 'MODULE_PAYMENT_DIBS_FEE', 
                     'MODULE_PAYMENT_DIBS_PAYTYPE', 'MODULE_PAYMENT_DIBS_HMAC', 
                     'MODULE_PAYMENT_DIBS_LANG', 'MODULE_PAYMENT_DIBS_ACCOUNT','MODULE_PAYMENT_DIBS_DISTR',
                     'MODULE_PAYMENT_DIBS_ORDER_INITIAL_STATUS_ID','MODULE_PAYMENT_DIBS_ORDER_STATUS_ID', 
                     'MODULE_PAYMENT_DIBS_ZONE', 'MODULE_PAYMENT_DIBS_SORT_ORDER',
                     );
    }
}
?>