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
    
    var $code, $title, $description, $enabled, $p_text;
    /**
     * osCommerce constructor
     * 
     * @global array $order 
     */
    function dibs() {
        global $order;

        $this->signature = 'dibs|dibs|4.0.3|2.2';
        $this->api_version = '3.1';

        $this->code = 'dibs';
        $this->title = MODULE_PAYMENT_DIBS_TEXT_TITLE_MODULES;
        //$this->public_customer_title = MODULE_PAYMENT_DIBS_TEXT_TITLE;
        $this->public_title = MODULE_PAYMENT_DIBS_TEXT_TITLE; //"dibs";
        $this->description = MODULE_PAYMENT_DIBS_TEXT_DESCRIPTION;
        $this->sort_order = MODULE_PAYMENT_DIBS_SORT_ORDER;
        $this->enabled = ((MODULE_PAYMENT_DIBS_STATUS == 'True') ? true : false);

        if ((int)MODULE_PAYMENT_DIBS_ORDER_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_DIBS_ORDER_STATUS_ID;
        }

        if (is_object($order)) $this->update_status();

        $iPayMethod = $this->dibs_api_getMethod();
        $this->form_action_url = $this->dibs_api_getFormAction($iPayMethod);
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
        $aData = $this->dibs_api_requestModel($order);
        /* DIBS integration **/
        unset($_SESSION['dibs_data']);
        
        $this->osc_processHelperTable($this->dibs_api_orderObject($order));
        
        $sProcess_button_string = "";
        foreach($aData as $sName => $sValue) {
            $sProcess_button_string .= tep_draw_hidden_field($sName, $sValue);
        }
        
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
                     'error' => MODULE_PAYMENT_DIBS_ERROR_MID);

      return $error;
    }
    
    function selection() {
      return array('id' => $this->code,
                   'module' => '<img src="images/DIBS/dibs.gif" alt="DIBS Payment Services" style="vertical-align: middle; margin-right: 10px;" /> ' .
                               $this->public_title . 
                               (strlen(MODULE_PAYMENT_DIBS_TEXT_PUBLIC_DESCRIPTION) > 0 ? ' (' . 
                               MODULE_PAYMENT_DIBS_TEXT_PUBLIC_DESCRIPTION . ')' : ''));
    }
    
    function pre_confirmation_check() {
        if (MODULE_PAYMENT_DIBS_MID == "") {
            $this->dibs_helper_redirect($this->dibs_helper_cmsurl(FILENAME_CHECKOUT_PAYMENT, 
                                        'payment_error=dibs&error=dibs_empty_mid', 'SSL', true));
	}
        else {
            return true;
	}
    }
        
    function confirmation() {
        return array ('title' => '<img src="images/DIBS/dibs.gif" alt="DIBS Payment Services" style="vertical-align: middle; margin-right: 10px;" />' . 
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
        if (isset($_POST['orderid'])) {
            $oOrder = $this->osc_getOrderData($_POST['orderid']);
        }
        else exit();

        $mErr = $this->dibs_api_checkMainFields($oOrder);
        if($mErr === FALSE) {
            $this->dibs_helper_dbquery_write("UPDATE `" . $this->dibs_helper_getdbprefix() . 
                                       "dibs_orderdata` SET `ordercancellation` = 0,
                                       `successaction` = 1 WHERE `orderid` = '" . 
                                       $oOrder->order_id . "' LIMIT 1;");
            
            $this->dibs_helper_redirect($this->dibs_helper_cmsurl(FILENAME_CHECKOUT_PROCESS));
        }
        else {
            echo $this->dibs_api_errCodeToMessage($mErr);
            exit();
        }
    }
    
    /**
     * Callback handler
     */
    function callback(){
        $oOrder = $this->osc_getOrderData((string)$_POST['orderid']);
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
                            'False', 'Turn on DIBS module', 
                            '0', "'tep_cfg_select_option(array(\'True\', \'False\'),'");
        $this->installApply('Test mode:', 'MODULE_PAYMENT_DIBS_TESTMODE', 
                            'yes', 'Use test mode', 
                            4, "'tep_cfg_select_option(array(\'yes\', \'no\'),'");
        $this->installApply('Add fee:', 'MODULE_PAYMENT_DIBS_FEE', 
                            'no', 'Add fee to payment', 
                            6, "'tep_cfg_select_option(array(\'yes\', \'no\'),'");
        $this->installApply('Enable vouchers:', 'MODULE_PAYMENT_DIBS_VOUCHER', 
                            'no', 'Enable to use vouchers', 
                             7, "'tep_cfg_select_option(array(\'yes\', \'no\'),'");
        
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
                            9, "NULL");
        $this->installApply('Sort order:', 'MODULE_PAYMENT_DIBS_SORT_ORDER',
                            '0', 'Sort order in list of availiable payment methods.', 
                            22, "NULL");
        $this->installApply('Checkout type:', 'MODULE_PAYMENT_DIBS_METHOD', 
                            '2', 'Standart or Mobile Payment Window or Auto.', 
                            3, "'dibs::osc_selectGetMethods('");
        $this->installApply('Language Payment Windows:', 'MODULE_PAYMENT_DIBS_LANG', 
                            'en_UK', 'Language used in Payment Windows.', 
                            12, "'dibs::osc_selectGetLang('");
        $this->installApply('Distribution method:', 'MODULE_PAYMENT_DIBS_DISTR', 
                            '1', 'Invoice distribution.', 19, "'dibs::osc_selectGetDistr('");
        $this->installApply('Payment zone:', 'MODULE_PAYMENT_DIBS_ZONE', 
                            '0', 'If a zone is selected, only enable this payment method for that zone.', 
                            21, "'tep_cfg_pull_down_zone_classes('", "'tep_get_zone_class_title'");
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
                     'MODULE_PAYMENT_DIBS_MID', 'MODULE_PAYMENT_DIBS_PID' ,'MODULE_PAYMENT_DIBS_METHOD',
                     'MODULE_PAYMENT_DIBS_FEE', 'MODULE_PAYMENT_DIBS_VOUCHER', 
                     'MODULE_PAYMENT_DIBS_PAYTYPE', 'MODULE_PAYMENT_DIBS_HMAC', 
                     'MODULE_PAYMENT_DIBS_LANG',
                     'MODULE_PAYMENT_DIBS_ACCOUNT','MODULE_PAYMENT_DIBS_DISTR',
                     'MODULE_PAYMENT_DIBS_ORDER_STATUS_ID', 'MODULE_PAYMENT_DIBS_ZONE',
                     'MODULE_PAYMENT_DIBS_SORT_ORDER'
                     );
    }
}
?>