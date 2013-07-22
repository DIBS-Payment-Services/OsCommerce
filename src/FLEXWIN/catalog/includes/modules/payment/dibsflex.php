<?php
/*
  $Id$

  DIBS module for osCommerce

  DIBS Payment Systems
  http://www.dibs.dk

  Copyright (c) 2011 DIBS A/S

  Released under the GNU General Public License
 
*/

require_once dirname(__FILE__) . '/dibsflex_api/dibsflex_helpers_cms.php';
require_once dirname(__FILE__) . '/dibsflex_api/dibsflex_helpers.php';
require_once dirname(__FILE__) . '/dibsflex_api/dibsflex_api.php';

class dibsflex extends dibsflex_api {
    
    /** START OF osCommerce SPECIFIC METHODS **/
    
    var $code, $title, $description, $enabled, $p_text;
    /**
     * osCommerce constructor
     * 
     * @global array $order 
     */
    function dibsflex() {
        global $order;

        $this->signature = 'dibsflex|dibsflex|3.0.0|2.2';
        $this->api_version = '3.1';

        $this->code = 'dibsflex';
        $this->title = MODULE_PAYMENT_DIBSFLEX_TEXT_TITLE_MODULES;
        $this->public_customer_title = MODULE_PAYMENT_DIBSFLEX_TEXT_TITLE;
        $this->public_title = "dibsflex";
        $this->description = MODULE_PAYMENT_DIBSFLEX_TEXT_DESCRIPTION;
        $this->sort_order = MODULE_PAYMENT_DIBSFLEX_SORT_ORDER;
        $this->enabled = ((MODULE_PAYMENT_DIBSFLEX_STATUS == 'True') ? true : false);

        if ((int)MODULE_PAYMENT_DIBSFLEX_ORDER_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_DIBSFLEX_ORDER_STATUS_ID;
        }

        if (is_object($order)) $this->update_status();

        $this->form_action_url = $this->dibsflex_api_getFormAction();
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
        global $HTTP_POST_VARS, $order, $currencies, $currency,  $languages_id, $shipping;
        
        /** DIBS integration */
        $aData = $this->dibsflex_api_requestModel($order);
        /* DIBS integration **/
        unset($_SESSION['dibsflex_data']);
        
        $this->osc_processHelperTable($this->dibsflex_api_orderObject($order));
        
        $sProcess_button_string = "";
        foreach($aData as $sName => $sValue) {
            $sProcess_button_string .= tep_draw_hidden_field($sName, $sValue);
        }
        
        return $sProcess_button_string;
    }
    
    function update_status() {
	global $order;
		
	if (($this->enabled == true) && ((int)MODULE_PAYMENT_DIBSFLEX_ZONE > 0)) {
            $check_flag = false;
            $check_query = tep_db_query("SELECT `zone_id` FROM " . TABLE_ZONES_TO_GEO_ZONES . 
                                        " WHERE `geo_zone_id` = '" . MODULE_PAYMENT_DIBSFLEX_ZONE . 
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
    
    function before_process() {
        return false;
    }
    
    function after_process() {
	global $insert_id, $order;

        $aPost = $this->osc_detectPost();
        if($aPost !== FALSE) $this->osc_completeCart($insert_id, $aPost['orderid']);
        unset($aPost);
        return false;
    }

    function output_error() {
      return false;
    }
    
    function get_error() {
      $error = array('title' => '',
                     'error' => MODULE_PAYMENT_DIBSFLEX_ERROR_MID);

      return $error;
    }
    
    function selection() {
      return array('id' => $this->code,
                   'module' => '<img src="images/DIBSFLEX/dibsflex.gif" alt="DIBS Payment Services" style="vertical-align: middle; margin-right: 10px;" /> ' .
                               $this->public_customer_title . 
                               (strlen(MODULE_PAYMENT_DIBSFLEX_TEXT_PUBLIC_DESCRIPTION) > 0 ? ' (' . 
                               MODULE_PAYMENT_DIBSFLEX_TEXT_PUBLIC_DESCRIPTION . ')' : ''));
    }
    
    function pre_confirmation_check() {
        if (MODULE_PAYMENT_DIBSFLEX_MID == "") {
            $this->dibsflex_helper_redirect($this->dibsflex_helper_cmsurl(FILENAME_CHECKOUT_PAYMENT, 
                                        'payment_error=dibsflex&error=dibsflex_empty_mid', 'SSL', true));
	}
        else {
            return true;
	}
    }
        
    function confirmation() {
        return array ('title' => '<img src="images/DIBSFLEX/dibsflex.gif" alt="DIBS Payment Services" style="vertical-align: middle; margin-right: 10px;" />' . 
                               $this->public_customer_title . 
                               (strlen(MODULE_PAYMENT_DIBSFLEX_TEXT_PUBLIC_DESCRIPTION) > 0 ? ' (' . 
                               MODULE_PAYMENT_DIBSFLEX_TEXT_PUBLIC_DESCRIPTION . ')' : '')
                     );
    }
    
    function javascript_validation() {
        return false;
    }
    
    function check() {
	if(!isset($this->_check )) {
            $sCheck_query = $this->dibsflex_helper_dbquery_read("SELECT configuration_value FROM " . 
                                                       TABLE_CONFIGURATION . 
                                                      " WHERE configuration_key = 
                                                      'MODULE_PAYMENT_DIBSFLEX_STATUS'" );
            $this->_check = tep_db_num_rows($sCheck_query);
	}
        return $this->_check;
    }
    
    /**
     * Succes page handler
     */
    function success() {
        if (isset($_POST['orderid'])) {
            $oOrder = $this->osc_getOrderData((string)$_POST['orderid']);
        }
        else exit($this->dibsflex_api_errCodeToMessage(11));

        $mErr = $this->dibsflex_api_checkMainFields($oOrder);
        if($mErr === FALSE) {
            $this->dibsflex_helper_dbquery_write("UPDATE `" . $this->dibsflex_helper_getdbprefix() . 
                                       "dibs_orderdata` SET `ordercancellation` = 0,
                                       `successaction` = 1 WHERE `orderid` = '" . 
                                       $oOrder->order_id . "' LIMIT 1;");
            
            $this->dibsflex_helper_redirect($this->dibsflex_helper_cmsurl(FILENAME_CHECKOUT_PROCESS));
        }
        else {
            echo $this->dibsflex_api_errCodeToMessage($mErr);
            exit();
        }
    }
    
    /**
     * Callback handler
     */
    function callback(){
        $oOrder = $this->osc_getOrderData((string)$_POST['orderid']);
        $this->dibsflex_api_callback($oOrder);
    }
    
    /**
     * Cancel page handler
     */
    function cancel() {
        $aFields = array();

        if (isset($_POST['orderid'])) {
            $oOrder = $this->osc_getOrderData((string)$_POST['orderid']);
            if(isset($oOrder->order_id) && $oOrder->order_id > 0) {
                $this->dibsflex_helper_dbquery_write("UPDATE `" . $this->dibsflex_helper_getdbprefix() . 
                                           "dibs_orderdata` SET `ordercancellation` = 1 
                                            WHERE `orderid` = '".$oOrder->order_id . 
                                           "' LIMIT 1;");
            }
	}
        
	$this->dibsflex_helper_redirect($this->dibsflex_helper_cmsurl(FILENAME_SHOPPING_CART));
    }
    
    function installApply($sName, $sConst, $sVal, $sDescr, $iSort, $sFunc, $sUseFunc = "NULL") {
        $this->dibsflex_helper_dbquery_write("INSERT INTO " . 
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
        $this->dibsflex_helper_dbquery_write("DELETE FROM " . TABLE_CONFIGURATION . 
                                   " WHERE configuration_key in ('" . 
                                   implode("', '", $this->keys()) . "')");
    }
    
    /**
     * osCommerce module installer
     */
    function install() {
        $this->installApply('Enable DIBS module:', 'MODULE_PAYMENT_DIBSFLEX_STATUS', 
                            'False', 'Turn on DIBS module', 
                            '0', "'tep_cfg_select_option(array(\'True\', \'False\'),'");
        $this->installApply('Test mode:', 'MODULE_PAYMENT_DIBSFLEX_TESTMODE', 
                            'yes', 'Use test mode', 
                            5, "'tep_cfg_select_option(array(\'yes\', \'no\'),'");
        $this->installApply('Unique order Id:', 'MODULE_PAYMENT_DIBSFLEX_UNIQ', 
                            'no', 'Only unique order IDs', 
                            6, "'tep_cfg_select_option(array(\'yes\', \'no\'),'");
        $this->installApply('Add fee:', 'MODULE_PAYMENT_DIBSFLEX_FEE', 
                            'no', 'Add fee to payment (Standart Payment Window and FlexWin)', 
                            7, "'tep_cfg_select_option(array(\'yes\', \'no\'),'");
        $this->installApply('Enable vouchers:', 'MODULE_PAYMENT_DIBSFLEX_VOUCHER', 
                            'no', 'Enable to use vouchers (Standart Payment Window and FlexWin)', 
                             8, "'tep_cfg_select_option(array(\'yes\', \'no\'),'");
        $this->installApply('Capture now:', 'MODULE_PAYMENT_DIBSFLEX_CAPT', 
                            'no', '(Only FlexWin) If this field exists, an "instant capture" is carried out, 
                            i.e. the amount is immediately transferred from the customer’s 
                            account to the shop’s account.', 
                            15, "'tep_cfg_select_option(array(\'yes\', \'no\'),'");
        $this->installApply('Skip last page:', 'MODULE_PAYMENT_DIBSFLEX_SKIPLAST', 
                            'no', '(Only FlexWin) Skip last page after payment.', 
                            16, "'tep_cfg_select_option(array(\'yes\', \'no\'),'");
        
        $this->installApply('Title:', 'MODULE_PAYMENT_DIBSFLEX_TEXT_TITLE',
                            'DIBS (FlexWin) | Secure Payment Services', 
                            'Title of payment system that customer see on checkout.', 
                            1, 'NULL');
        $this->installApply('Merchant Id:', 'MODULE_PAYMENT_DIBSFLEX_MID', 
                            '', 'Your merchant id in DIBS service.', 
                            2, 'NULL');
        $this->installApply('API login:', 'MODULE_PAYMENT_DIBSFLEX_APIUSER', 
                            '', 'Your login of DIBS API user.', 
                            3, 'NULL');
        $this->installApply('API password:', 'MODULE_PAYMENT_DIBSFLEX_APIPASS', 
                            '', 'Your password of DIBS API user.', 
                            4, 'NULL');
        $this->installApply('Account:', 'MODULE_PAYMENT_DIBSFLEX_ACCOUNT', 
                            '', 'You can use different accounts with one merchantid', 
                            14, "NULL");
        $this->installApply('Paytype:', 'MODULE_PAYMENT_DIBSFLEX_PAYTYPE', 
                            'VISA,MC', 'This list must be comma separated with 
                            no spaces in between. E.g. VISA,MC', 
                            9, "NULL");
        $this->installApply('MD5 Key 1:', 'MODULE_PAYMENT_DIBSFLEX_MD51', 
                            '', 'Your MD5 Key 1 security code for FlexWin', 
                            10, "NULL");
        $this->installApply('MD5 Key 2:', 'MODULE_PAYMENT_DIBSFLEX_MD52', 
                            '', 'Your MD5 Key 2 security code for FlexWin', 
                            11, "NULL");
        $this->installApply('Sort order:', 'MODULE_PAYMENT_DIBSFLEX_SORT_ORDER',
                            '0', 'Sort order in list of availiable payment methods.', 
                            22, "NULL");
        $this->installApply('Language FlexWin:', 'MODULE_PAYMENT_DIBSFLEX_LANG', 
                            'en', 'Language used in FlexWin.', 
                            13, "'dibsflex::osc_selectGetLangFlex('");
        $this->installApply('FlexWin decorator:', 'MODULE_PAYMENT_DIBSFLEX_DECOR', 
                            '1', 'Decorator for FlexWin method.', 17, "'dibsflex::osc_selectGetDecor('");
        $this->installApply('FlexWin color:', 'MODULE_PAYMENT_DIBSFLEX_COLOR', 
                            '1', 'Color for FlexWin method.', 18, "'dibsflex::osc_selectGetColor('");
        $this->installApply('Distribution method:', 'MODULE_PAYMENT_DIBSFLEX_DISTR', 
                            '1', 'Invoice distribution.', 19, "'dibsflex::osc_selectGetDistr('");
        $this->installApply('Payment zone:', 'MODULE_PAYMENT_DIBSFLEX_ZONE', 
                            '0', 'If a zone is selected, only enable this payment method for that zone.', 
                            21, "'tep_cfg_pull_down_zone_classes('", "'tep_get_zone_class_title'");
	$this->installApply('Set order status', 'MODULE_PAYMENT_DIBSFLEX_ORDER_STATUS_ID',
                            '2', 'Set the status of orders made with this payment module to this value', 
                            20, "'tep_cfg_pull_down_order_statuses('", "'tep_get_order_status_name'");
	
    }

    /**
     * osCommerce config keys helper
     * 
     * @return array 
     */
    function keys() {
        return array('MODULE_PAYMENT_DIBSFLEX_STATUS', 'MODULE_PAYMENT_DIBSFLEX_TEXT_TITLE',
                     'MODULE_PAYMENT_DIBSFLEX_MID','MODULE_PAYMENT_DIBSFLEX_APIUSER',
                     'MODULE_PAYMENT_DIBSFLEX_APIPASS',
                     'MODULE_PAYMENT_DIBSFLEX_TESTMODE', 'MODULE_PAYMENT_DIBSFLEX_UNIQ', 
                     'MODULE_PAYMENT_DIBSFLEX_FEE', 'MODULE_PAYMENT_DIBSFLEX_VOUCHER', 
                     'MODULE_PAYMENT_DIBSFLEX_PAYTYPE', 
                     'MODULE_PAYMENT_DIBSFLEX_MD51', 'MODULE_PAYMENT_DIBSFLEX_MD52',
                     'MODULE_PAYMENT_DIBSFLEX_LANG', 
                     'MODULE_PAYMENT_DIBSFLEX_ACCOUNT', 'MODULE_PAYMENT_DIBSFLEX_CAPT', 
                     'MODULE_PAYMENT_DIBSFLEX_SKIPLAST','MODULE_PAYMENT_DIBSFLEX_DECOR', 
                     'MODULE_PAYMENT_DIBSFLEX_COLOR', 'MODULE_PAYMENT_DIBSFLEX_DISTR',
                     'MODULE_PAYMENT_DIBSFLEX_ORDER_STATUS_ID', 'MODULE_PAYMENT_DIBSFLEX_ZONE',
                     'MODULE_PAYMENT_DIBSFLEX_SORT_ORDER'
                     );
    }
}
?>