<?php
class dibsflex_helpers_cms {
    
    function osc_cgiButtonsRealID($iRealId) {
        $mPostId = $this->osc_getPostId($iRealId);
        if($mPostId !== FALSE) {
            return $this->dibsflex_api_cgiButtons($mPostId);
        }
        else return '';
    }
    
    function osc_getPostId($iRealId) {
        $mOrderData = $this->dibsflex_helper_dbquery_read("SELECT 
                                                   `session_cart_id` AS oid 
                                                    FROM `dibs_order_to_session` 
                                                    WHERE `order_id` = '" . 
                                                    $iRealId . 
                                                    "' LIMIT 1;");
        
        $mSingle = $this->dbquery_read_fetch($mOrderData);
        if(isset($mSingle[0]['oid'])) return $mSingle[0]['oid'];
        else return FALSE;
    }
    
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
    
    function osc_getOrderData($mPostOrderId) {
        $mOrderData = $this->dibsflex_helper_dbquery_read("SELECT 
                                                   `session_cart_id` AS order_id, 
                                                   `amount` AS total,
                                                   `currency`
                                                    FROM `dibs_order_to_session` 
                                                    WHERE `session_cart_id` = '" . 
                                                    addslashes($mPostOrderId) . 
                                                    "' LIMIT 1;");	

        $aResult = $this->dbquery_read_fetch($mOrderData);
        if(count($aResult[0]) > 0) return (object)$aResult[0];
        else return (object)array();
    }
    
    function osc_completeCart($iOrderId, $mCartId) {
        $this->dibsflex_helper_dbquery_write("UPDATE `dibs_order_to_session` SET `order_id`='" .
                                   $iOrderId . "' WHERE `session_cart_id`='" .
                                   addslashes($mCartId) . "' LIMIT 1;");
        $this->dibsflex_helper_dbquery_write("UPDATE `orders_status_history` 
                                   SET `comments`=CONCAT('[DIBS Order ID: " . $mCartId . "] \n', `comments`) 
                                   WHERE `orders_id`='" .
                                   $iOrderId . "' AND `orders_status_id`='" . 
                                   $this->order_status . "' LIMIT 1;");
    }
    
    function osc_processHelperTable($oOrderInfo) {
        $this->osc_helperTable();
        
        $mOrderExists = $this->dibsflex_helper_dbquery_read("SELECT COUNT(`session_cart_id`) 
                                                    AS session_cart_exists 
                                                    FROM `dibs_order_to_session` 
                                                    WHERE `session_cart_id` = '" . 
                                                    $oOrderInfo->order->order_id . 
                                                    "' LIMIT 1;");	

        $aResult = $this->dbquery_read_fetch($mOrderExists);
        
        if($aResult[0]['session_cart_exists'] > 0) {
            $this->dibsflex_helper_dbquery_write("DELETE FROM `dibs_order_to_session` 
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
        $this->dibsflex_helper_dbquery_write("CREATE TABLE IF NOT EXISTS `dibs_order_to_session` (
                                       `session_cart_id` VARCHAR(45) NOT NULL DEFAULT '',
                                       `order_id` VARCHAR(45) NOT NULL DEFAULT '',
                                       `amount` INTEGER UNSIGNED NOT NULL DEFAULT 0,
                                       `currency` VARCHAR(45) NOT NULL DEFAULT '');"
                                  );
    }
    
    function osc_getShippingRate() {
        $aShippingId = explode("_", $_SESSION['shipping']['id']);
        $mShippingQuery = $this->dibsflex_helper_dbquery_read("SELECT `configuration_value` FROM " . 
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
     * Creating settings pulldown list (<select>) for FlexWin language
     */
    function osc_selectGetLangFlex($sValue, $sKey = '') {
        $sName = (($sKey) ? 'configuration[' . $sKey . ']' : 'configuration_value');

        $aLangFlexArray = array();
        $aLangFlexArray = array(
                             array('id' => 'da',
                                   'text' => 'Danish'),
                             array('id' => 'nl',
                                   'text' => 'Dutch'),
                             array('id' => 'en',
                                   'text' => 'English'),
                             array('id' => 'fo',
                                   'text' => 'Faroese'),
                             array('id' => 'fi',
                                   'text' => 'Finnish'),
                             array('id' => 'fr',
                                   'text' => 'French'),
                             array('id' => 'de',
                                   'text' => 'German'),
                             array('id' => 'it',
                                   'text' => 'Italian'),
                             array('id' => 'no',
                                   'text' => 'Norwegian'),
                             array('id' => 'pl',
                                   'text' => 'Polish'),
                             array('id' => 'es',
                                   'text' => 'Spanish'),
                             array('id' => 'sv',
                                   'text' => 'Swedish'),
                         );

        return tep_draw_pull_down_menu($sName, $aLangFlexArray, $sValue);
    }
    
    /**
     * Creating settings pulldown list (<select>) for FlexWin decorators
     */
    function osc_selectGetDecor($sValue, $sKey = '') {
        $sName = (($sKey) ? 'configuration[' . $sKey . ']' : 'configuration_value');

        $aDecorArray = array();
        $aDecorArray = array(
                             array('id' => 'default',
                                   'text' => 'Default'),
                             array('id' => 'basal',
                                   'text' => 'Basal'),
                             array('id' => 'rich',
                                   'text' => 'Rich'),
                             array('id' => 'own',
                                   'text' => 'Own decorator')
                         );

        return tep_draw_pull_down_menu($sName, $aDecorArray, $sValue);
    }
    
    /**
     * Creating settings pulldown list (<select>) for FlexWin color
     */
    function osc_selectGetColor($sValue, $sKey = '') {
        $sName = (($sKey) ? 'configuration[' . $sKey . ']' : 'configuration_value');

        $aColorArray = array();
        $aColorArray = array(
                             array('id' => 'blank',
                                   'text' => 'None'),
                             array('id' => 'sand',
                                   'text' => 'Sand'),
                             array('id' => 'grey',
                                   'text' => 'Grey'),
                             array('id' => 'blue',
                                   'text' => 'Blue')
                         );

        return tep_draw_pull_down_menu($sName, $aColorArray, $sValue);
    }
    
    /**
     * Creating settings pulldown list (<select>) for distribution method
     */
    function osc_selectGetDistr($sValue, $sKey = '') {
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