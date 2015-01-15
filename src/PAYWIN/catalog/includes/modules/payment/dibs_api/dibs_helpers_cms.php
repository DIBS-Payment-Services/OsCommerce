<?php
class dibs_helpers_cms {
    
    function dbquery_read_fetch($mQuery) {
        while ($aFetch = tep_db_fetch_array($mQuery)) {
            $aResult[] = $aFetch;
        }
        return $aResult;
    }
    
    function osc_detectPost() {
        foreach($_SESSION['navigation']->path as $sKey => &$aPath) {
            if($aPath['page'] == 'success.php' && isset($aPath['post']['orderid'])) {
                return $aPath['post'];
            }
        }
        return false;
    }
    
    /*
     * Get order data, previously saved befor customer was redirect to DIBS Payment Window
     */
    function osc_getOrderData($mPostOrderId) {
        $get_order_sql = "SELECT orders.currency, orders_total.value AS total from orders "
                       . " JOIN orders_total ON orders.orders_id = orders_total.orders_id "
                       . " WHERE orders_total.class = 'ot_total' "
                       . " AND orders.orders_id = ".addslashes($mPostOrderId)." LIMIT 1;";
        
        $mOrderData = $this->dibs_helper_dbquery_read($get_order_sql);	
        
        $aResult = $this->dbquery_read_fetch($mOrderData);
        if(count($aResult[0]) > 0) {
            return (object)$aResult[0];
        }
        else return (object)array();
    }
    
    function osc_completeCart($iOrderId, $mCartId) {
        /*
        $this->dibs_helper_dbquery_write("UPDATE `dibs_order_to_session` SET `order_id`='" .
                                   $iOrderId . "' WHERE `session_cart_id`='" .
                                   addslashes($mCartId) . "' LIMIT 1;"); */
    }   
    function osc_processHelperTable($oOrderInfo) {
        $this->osc_helperTable();
        
        $mOrderExists = $this->dibs_helper_dbquery_read("SELECT COUNT(`session_cart_id`) 
                                                    AS session_cart_exists 
                                                    FROM `dibs_order_to_session` 
                                                    WHERE `session_cart_id` = '" . 
                                                    $oOrderInfo->order->order_id . 
                                                    "' LIMIT 1;");	

        $aResult = $this->dbquery_read_fetch($mOrderExists);
        
        if($aResult[0]['session_cart_exists'] > 0) {
            $this->dibs_helper_dbquery_write("DELETE FROM `dibs_order_to_session` 
                                        WHERE `session_cart_id` = '" . 
                                        $oOrderInfo->order->order_id . 
                                       "' LIMIT 1;");
        }
        
        $aInsertData = array(
            'session_cart_id' => $oOrderInfo->order->order_id,
            'order_id'        => '0',
            'amount'          => $oOrderInfo->order->total,
            'currency'        => $oOrderInfo->order->currency
        );
        tep_db_perform('dibs_order_to_session', $aInsertData);
    }
    
    function osc_helperTable() {
        $this->dibs_helper_dbquery_write("CREATE TABLE IF NOT EXISTS `dibs_order_to_session` (
                                       `session_cart_id` VARCHAR(45) NOT NULL DEFAULT '',
                                       `order_id` VARCHAR(45) NOT NULL DEFAULT '',
                                       `amount` INTEGER UNSIGNED NOT NULL DEFAULT 0,
                                       `currency` VARCHAR(45) NOT NULL DEFAULT '');"
                                  );
    }
    
    function osc_getShippingRate() {
        $aShippingId = explode("_", $_SESSION['shipping']['id']);
        $mShippingQuery = $this->dibs_helper_dbquery_read("SELECT `configuration_value` FROM " . 
                                    TABLE_CONFIGURATION . " WHERE `configuration_key`='MODULE_SHIPPING_".$aShippingId[0]."_TAX_CLASS'");
        unset($aShippingId);
        $aResult = $this->dbquery_read_fetch($mShippingQuery);
        if($aResult[0]['configuration_value'] != '0') {
            return tep_get_tax_rate($aResult[0]['configuration_value'],
                                    $aOrderInfo->delivery['country']['id'],
                                    $aOrderInfo->delivery['zone_id']) / 100;
        }
        else return '0';
    }

    /**
     * Creating settings pulldown list (<select>) for PW language
     */
    public static function osc_selectGetLang($sValue, $sKey = '') {
        $sName = (($sKey) ? 'configuration[' . $sKey . ']' : 'configuration_value');

        $aLangArray = array();
        $aLangArray = array(
                             array('id' => 'da_DK',
                                   'text' => 'Danish'),
                             array('id' => 'en_GB',
                                   'text' => 'English (GB)'),
                             array('id' => 'nb_NO',
                                   'text' => 'Norwegian'),
                             array('id' => 'sv_SE',
                                   'text' => 'Swedish'),
                             array('id' => 'de_DE',
                                   'text' => 'German'),
                             array('id' => 'en_US',
                                   'text' => 'English (US)'),
                             array('id' => 'es_ES',
                                   'text' => 'Spanish'),
                             array('id' => 'fi_FI',
                                   'text' => 'Finnish'),
                             array('id' => 'fr_FR',
                                   'text' => 'French'),
                             array('id' => 'it_IT',
                                   'text' => 'Italian'),
                             array('id' => 'es_ES',
                                   'text' => 'Spanish'),
                             array('id' => 'nl_NL',
                                   'text' => 'Dutch'),
                             array('id' => 'pl_PL',
                                   'text' => 'Polish'),
                             array('id' => 'pt_PT',
                                   'text' => 'Portuguese'),
                         );

        return tep_draw_pull_down_menu($sName, $aLangArray, $sValue);
    }
    
    /*
     * HMAC key is too long and crashes 
     * desigh of module settings box, this function just
     * reduce its lengh 
     */    
    public static function osc_splitMac($mac) {
        $return = '';
        if($mac) {
            $return =  substr($mac,0, 25) . '...';
        }
        return $return;
        
     }
   
    
    function getInvoiceReturnFields() {
        $dibsInvoiceFields = array("acquirerLastName",          "acquirerFirstName",
                                       "acquirerDeliveryAddress",   "acquirerDeliveryPostalCode",
                                       "acquirerDeliveryPostalPlace", "transaction" );
        $dibsInvoiceFieldsString = "";
       
        foreach($_POST as $key=>$value) {
              if(in_array($key, $dibsInvoiceFields)) {
                   $dibsInvoiceFieldsString .= "{$key}={$value}\n";              
              }
         }
         
        return $dibsInvoiceFieldsString;
    }
    
    /**
     * Creating settings pulldown list (<select>) for payment methods
     */
    public static function osc_selectGetMethods($sValue, $sKey = '') {
        $sName = (($sKey) ? 'configuration[' . $sKey . ']' : 'configuration_value');

        $aMethodsArray = array();
        $aMethodsArray = array(
                             array('id' => '1',
                                   'text' => 'Auto'),
                             array('id' => '2',
                                   'text' => 'Standart Payment Window'),
                             array('id' => '3',
                                   'text' => 'Mobile Payment Window')
                         );

        return tep_draw_pull_down_menu($sName, $aMethodsArray, $sValue);
    }
    
    /**
     * Creating settings pulldown list (<select>) for distribution method
     */
    public static function osc_selectGetDistr($sValue, $sKey = '') {
        $sName = (($sKey) ? 'configuration[' . $sKey . ']' : 'configuration_value');

        $aDistrArray = array();
        $aDistrArray = array(
                             array('id' => 'empty',
                                   'text' => '-'),
                             array('id' => 'email',
                                   'text' => 'Email'),
                             array('id' => 'paper',
                                   'text' => 'Paper')
                         );

        return tep_draw_pull_down_menu($sName, $aDistrArray, $sValue);
    }
    
    /** END OF osCommerce SPECIFIC METHODS **/
}
?>