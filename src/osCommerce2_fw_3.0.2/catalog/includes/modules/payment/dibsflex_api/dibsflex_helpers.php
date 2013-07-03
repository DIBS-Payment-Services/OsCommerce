<?php
interface dibsflex_helpers_iface {
    function dibsflex_helper_dbquery_write($sQuery);
    function dibsflex_helper_dbquery_read($sQuery);
    function dibsflex_helper_dbquery_read_single($mResult, $sName);
    function dibsflex_helper_cmsurl($sLink);
    function dibsflex_helper_getconfig($sVar, $sPrefix);
    function dibsflex_helper_getdbprefix();
    function dibsflex_helper_getReturnURLs($sURL);
    function dibsflex_helper_getOrderObj($mOrderInfo, $bResponse = FALSE);
    function dibsflex_helper_getAddressObj($mOrderInfo);
    function dibsflex_helper_getShippingObj($mOrderInfo);
    function dibsflex_helper_getItemsObj($mOrderInfo);
    function dibsflex_helper_redirect($sURL);
    function dibsflex_helper_afterCallback($oOrder);
    function dibsflex_helper_getlang($sKey);
    function dibsflex_helper_cgiButtonsClass();
    function dibsflex_helper_modVersion();
}

class dibsflex_helpers extends dibsflex_helpers_cms implements dibsflex_helpers_iface {
    /** START OF DIBS HELPERS AREA **/
    /** |---MODEL **/
    
    function dibsflex_helper_dbquery_write($sQuery) {
        return tep_db_query($sQuery);
    }
    
    function dibsflex_helper_dbquery_read($sQuery) {
        return tep_db_query($sQuery);
    }
    
    function dibsflex_helper_dbquery_read_single($mResult, $sName) {
        $mSingle = $this->dbquery_read_fetch($mResult);
        if(isset($mSingle[0][$sName])) return $mSingle[0][$sName];
        else return null;
    }
    
    function dibsflex_helper_cmsurl($sLink) {
        return tep_href_link($sLink,'','NONSSL');
    }
    
    function dibsflex_helper_getconfig($sVar, $sPrefix = 'DIBSFLEX_') {
        return constant(strtoupper("MODULE_PAYMENT_" . $sPrefix . $sVar));
    }
    
    function dibsflex_helper_getdbprefix() {
        return "";
    }
    
    function dibsflex_helper_getReturnURLs($sURL) {
        switch ($sURL) {
            case 'success':
                return $this->dibsflex_helper_cmsurl("ext/modules/payment/dibsflex/success.php");
            break;
            case 'callback':
                return $this->dibsflex_helper_cmsurl("ext/modules/payment/dibsflex/callback.php");
            break;
            case 'callbackfix':
                return $this->dibsflex_helper_cmsurl("ext/modules/payment/dibsflex/callback.php");
            break;
            case 'cgi':
                return $this->dibsflex_helper_cmsurl("ext/modules/payment/dibsflex/cgiapi.php");
            break;
            case 'cancel':
                return $this->dibsflex_helper_cmsurl("ext/modules/payment/dibsflex/cancel.php");
            break;
            default:
                return $this->dibsflex_helper_cmsurl(FILENAME_SHOPPING_CART);
            break;
        }
    }
    
    function dibsflex_helper_getOrderObj($mOrderInfo, $bResponse = FALSE) {
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
                'currency'  => $this->dibsflex_api_getCurrencyValue(
                                  $mOrderInfo->info['currency']
                               )
            );
        }
        else {
            return $this->osc_getOrderData((string)$_POST['orderid']);
        }
    }
    
    function dibsflex_helper_getItemsObj($mOrderInfo) {
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
    
    function dibsflex_helper_getAddressObj($mOrderInfo) {
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

    function dibsflex_helper_getShippingObj($mOrderInfo) {
        if(isset($mOrderInfo->info['currency_value']) && 
                 $mOrderInfo->info['currency_value'] > 0) {
            $sTmpPrice = $mOrderInfo->info['shipping_cost'] * 
                         $mOrderInfo->info['currency_value'];
        }
        else $sTmpPrice = $mOrderInfo->info['shipping_cost'];
        
        return (object)array(
                'method' => $mOrderInfo->info['shipping_method'],
                'rate'  => round($sTmpPrice, 2) * 100,
                'tax'   => round($sTmpPrice * 
                           $this->osc_getShippingRate(), 2) * 100
            );
    }

    /** |---CONTROLLER **/
    function dibsflex_helper_getlang($sKey) {
        return constant(strtoupper("MODULE_PAYMENT_DIBSFLEX_" . $sKey));
    }
    
    function dibsflex_helper_redirect($sURL) {
        tep_redirect($sURL);
    }
    
    function dibsflex_helper_afterCallback($oOrder) {
        return true;
    }
    
    function dibsflex_helper_cgiButtonsClass() {
        return "";
    }
    
    function dibsflex_helper_modVersion() {
        return "osc2_3.0.2";
    }
    /** END OF DIBS HELPERS AREA **/
}
?>
