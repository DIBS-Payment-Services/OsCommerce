<?php
interface dibs_helpers_iface {
    function dibs_helper_dbquery_write($sQuery);
    function dibs_helper_dbquery_read($sQuery);
    function dibs_helper_dbquery_read_single($mResult, $sName);
    function dibs_helper_cmsurl($sLink);
    function dibs_helper_getconfig($sVar, $sPrefix = 'DIBS_');
    function dibs_helper_getdbprefix();
    function dibs_helper_getReturnURLs($sURL);
    function dibs_helper_getOrderObj($mOrderInfo, $bResponse = FALSE);
    function dibs_helper_getAddressObj($mOrderInfo);
    function dibs_helper_getShippingObj($mOrderInfo);
    function dibs_helper_getItemsObj($mOrderInfo);
    function dibs_helper_redirect($sURL);
    function dibs_helper_afterCallback($oOrder);
    function dibs_helper_getlang($sKey);
    function dibs_helper_modVersion();
}

class dibs_helpers extends dibs_helpers_cms implements dibs_helpers_iface {
    /** START OF DIBS HELPERS AREA **/
    /** |---MODEL **/
    
    function dibs_helper_dbquery_write($sQuery) {
        return tep_db_query($sQuery);
    }
    
    function dibs_helper_dbquery_read($sQuery) {
        return tep_db_query($sQuery);
    }
    
    function dibs_helper_dbquery_read_single($mResult, $sName) {
        $mSingle = $this->dbquery_read_fetch($mResult);
        if(isset($mSingle[0][$sName])) return $mSingle[0][$sName];
        else return null;
    }
    
    function dibs_helper_cmsurl($sLink) {
        return tep_href_link($sLink,'','NONSSL');
    }
    
    function dibs_helper_getconfig($sVar, $sPrefix = 'DIBS_') {
        return constant(strtoupper("MODULE_PAYMENT_" . $sPrefix . $sVar));
    }
    
    function dibs_helper_getdbprefix() {
        return "";
    }
    
    function dibs_helper_getReturnURLs($sURL) {
        switch ($sURL) {
            case 'success':
                return $this->dibs_helper_cmsurl("ext/modules/payment/dibs/success.php");
            break;
            case 'callback':
                return $this->dibs_helper_cmsurl("ext/modules/payment/dibs/callback.php");
            break;
            case 'callbackfix':
                return $this->dibs_helper_cmsurl("ext/modules/payment/dibs/callback.php");
            break;
            case 'cancel':
                return $this->dibs_helper_cmsurl("ext/modules/payment/dibs/cancel.php");
            break;
            default:
                return $this->dibs_helper_cmsurl(FILENAME_SHOPPING_CART);
            break;
        }
    }
    
    function dibs_helper_getOrderObj($mOrderInfo, $bResponse = FALSE) {
        if($bResponse === FALSE) {
            if(isset($mOrderInfo->info['currency_value']) && 
                     $mOrderInfo->info['currency_value'] > 0) {
                $sTotal = $mOrderInfo->info['total'] * 
                          $mOrderInfo->info['currency_value'];
            }
            else $sTotal = $mOrderInfo->info['total'];
            
            return (object)array(
                'order_id'  => $_SESSION['cartID'] . $_SESSION['customer_id'] .
                               date("dmyHi"),
                'total'     => round($sTotal, 2) * 100,
                'currency'  => $this->dibs_api_getCurrencyValue(
                                  $mOrderInfo->info['currency']
                               )
            );
        }
        else {
            return $this->osc_getOrderData((string)$_POST['orderid']);
        }
    }
    
    function dibs_helper_getItemsObj($mOrderInfo) {
        foreach($mOrderInfo->products as $aItem) {
            
            if(isset($mOrderInfo->info['currency_value']) && 
                     $mOrderInfo->info['currency_value'] > 0) {
                $sTmpPrice = $aItem['price'] * 
                             $mOrderInfo->info['currency_value'];
            }
            else $sTmpPrice = $aItem['price'];
            
            $oItems[] = (object)array(
                'item_id'   => $aItem['id'],
                'name'      => $aItem['name'],
                'sku'       => $aItem['model'],
                'price'     => round($sTmpPrice, 2) * 100,
                'qty'       => $aItem['qty'],
                'tax_name'  => $aItem['tax_description'],
                'tax_rate'  => round(($aItem['tax'] / 100) * 
                                      $sTmpPrice, 2) * 100
            );
        }
        return $oItems;
    }
    
    function dibs_helper_getAddressObj($mOrderInfo) {
        return (object)array(
                'billing'   => (object)array(
                    'firstname' => $mOrderInfo->billing['firstname'],
                    'lastname'  => $mOrderInfo->billing['lastname'],
                    'street'    => $mOrderInfo->billing['street_address'],
                    'postcode'  => $mOrderInfo->billing['postcode'],
                    'city'      => $mOrderInfo->billing['city'],
                    'region'    => $mOrderInfo->billing['state'],
                    'country'   => $mOrderInfo->billing['country']['iso_code_3'],
                    'phone'     => $mOrderInfo->customer['telephone'],
                    'email'     => $mOrderInfo->customer['email_address']
                ),
                'delivery'  => (object)array(
                    'firstname' => $mOrderInfo->delivery['firstname'],
                    'lastname'  => $mOrderInfo->delivery['lastname'],
                    'street'    => $mOrderInfo->delivery['street_address'],
                    'postcode'  => $mOrderInfo->delivery['postcode'],
                    'city'      => $mOrderInfo->delivery['city'],
                    'region'    => $mOrderInfo->delivery['state'],
                    'country'   => $mOrderInfo->delivery['country']['iso_code_3'],
                    'phone'     => $mOrderInfo->customer['telephone'],
                    'email'     => $mOrderInfo->customer['email_address']
                )
            );
    }

    function dibs_helper_getShippingObj($mOrderInfo) {
        if(isset($mOrderInfo->info['currency_value']) && 
                 $mOrderInfo->info['currency_value'] > 0) {
            $sTmpPrice = $mOrderInfo->info['shipping_cost'] * 
                         $mOrderInfo->info['currency_value'];
        }
        else $sTmpPrice = $mOrderInfo->info['shipping_cost'];
        return (object)array(
                'method' => $mOrderInfo->info['shipping_method'],
                'rate'  => round($sTmpPrice, 2) * 100,
                'tax'   => round($mOrderInfo->info['tax'], 2) * 100
            );
    }

    /** |---CONTROLLER **/
    function dibs_helper_getlang($sKey) {
        return constant(strtoupper("MODULE_PAYMENT_DIBS_" . $sKey));
    }
    
    function dibs_helper_redirect($sURL) {
        tep_redirect($sURL);
    }
    
    function dibs_helper_afterCallback($oOrder) {
      $orderId = $oOrder->order_id;
      $dibsInvoiceFields = array("acquirerLastName",          "acquirerFirstName",
                                       "acquirerDeliveryAddress",   "acquirerDeliveryPostalCode",
                                       "acquirerDeliveryPostalPlace" );
      $dibsInvoiceFieldsString = "";
      foreach($_POST as $key=>$value) {
              if(in_array($key, $dibsInvoiceFields)) {
                   $dibsInvoiceFieldsString .= "{$key}={$value}\n";              
              }
         }
         
      if($dibsInvoiceFieldsString) {
        $result =  $this->dibs_helper_dbquery_read("SELECT `order_id` FROM `dibs_order_to_session` WHERE `session_cart_id` = '{$orderId}'");	
        $aResult = $this->dbquery_read_fetch($result);
        $orderId = $aResult[0]['order_id']; 
        $result =  $this->dibs_helper_dbquery_read("SELECT `comments` FROM `orders_status_history` WHERE `orders_id` = '{$orderId}'");	
        $aResult = $this->dbquery_read_fetch($result);
        $comment = $aResult[0]['comments']." $dibsInvoiceFieldsString";
        $this->dibs_helper_dbquery_write("UPDATE ". TABLE_ORDERS_STATUS_HISTORY ." 
                                   SET comments = '" . $comment . "' WHERE `orders_id`='" .
                                   $orderId . "'");
      }
      return true;
    }
    
    function dibs_helper_modVersion() {
        return "osc2_4.0.8";
    }
    /** END OF DIBS HELPERS AREA **/
}
?>