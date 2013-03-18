<?php
class dibs_api extends dibs_helpers {
    /** START OF DIBS API AREA **/
    
    /**
     * Collects API parameters to send in dependence of checkout type
     * 
     * @param array $iPayMethod
     * @param object $oOrderInfo
     * @return array 
     */
    function dibs_api_requestModel($mOrderInfo) {
        $oOrderInfo = $this->dibs_api_orderObject($mOrderInfo);
        $this->dibs_api_processDB($oOrderInfo->order->order_id);

        $aData = array();
        
        $this->dibs_api_applyCommon($aData, $oOrderInfo);
        
        $iPayMethod = $this->dibs_api_getMethod();
        $aData['pw'] = $iPayMethod;
        
        $this->dibs_api_applyCommonWin($aData);
        if($iPayMethod == 2) $this->dibs_api_applyInvoice($aData, $oOrderInfo);
        $sMAC = $this->dibs_api_calcMAC($aData);
        if($sMAC != "") $aData['MAC'] = $sMAC;
        
        return $aData;
    }
    
    function dibs_api_getMethod() {
        $iPayMethod = $this->dibs_helper_getconfig("method");
        if($iPayMethod != 2 && $iPayMethod != 3) {
            $bIsMobile = $this->dibs_api_detectMobile();
            if($bIsMobile === TRUE) return 3;
            else return 2;
        }
        else return $iPayMethod;
    }
    
    /**
     *  Calls dibs_api_checkTable() method and 
     *  adds orderID to dibs_orderdata table if needed
     */
    function dibs_api_processDB($iOrderId) {
        $this->dibs_api_checkTable();
        $mOrderExists = $this->dibs_helper_dbquery_read("SELECT COUNT(`orderid`) 
                                                    AS order_exists FROM `" . 
                                                    $this->dibs_helper_getdbprefix() . 
                                                    "dibs_orderdata` where `orderid` = '" . 
                                                    $iOrderId . 
                                                    "' LIMIT 1;");
        
        if($this->dibs_helper_dbquery_read_single($mOrderExists, 'order_exists') <= 0) {
            $this->dibs_helper_dbquery_write("INSERT INTO `" . 
                                             $this->dibs_helper_getdbprefix() . 
                                             "dibs_orderdata`(`orderid`) VALUES('" . 
                                             $iOrderId."')");
        }
    }
    
    /**
     *  Create dibs_orderdata table if not exists
     */
    function dibs_api_checkTable() {
        $this->dibs_helper_dbquery_write("CREATE TABLE IF NOT EXISTS `" . 
                            $this->dibs_helper_getdbprefix() . "dibs_orderdata` (
                            `orderid` VARCHAR(45) NOT NULL DEFAULT '',
                            `transact` VARCHAR(50) NOT NULL DEFAULT '',
                            `status` INTEGER UNSIGNED NOT NULL DEFAULT 0 
                                                    COMMENT '0 = unpaid, 1 = paid',
                            `amount` VARCHAR(45) NOT NULL DEFAULT '',
                            `currency` VARCHAR(45) NOT NULL DEFAULT '',
                            `paytype` VARCHAR(45) NOT NULL DEFAULT '',
                            `PBB_customerId` VARCHAR(45) NOT NULL DEFAULT '',
                            `PBB_deliveryAddress` VARCHAR(45) NOT NULL DEFAULT '',
                            `PBB_deliveryCountryCode` VARCHAR(45) NOT NULL DEFAULT '',
                            `PBB_deliveryPostalCode` VARCHAR(45) NOT NULL DEFAULT '',
                            `PBB_deliveryPostalPlace` VARCHAR(45) NOT NULL DEFAULT '',
                            `PBB_firstName` VARCHAR(45) NOT NULL DEFAULT '',
                            `PBB_lastName` VARCHAR(45) NOT NULL DEFAULT '',
                            `cardnomask` VARCHAR(45) NOT NULL DEFAULT '',
                            `cardprefix` VARCHAR(45) NOT NULL DEFAULT '',
                            `cardexpdate` VARCHAR(45) NOT NULL DEFAULT '',
                            `cardcountry` VARCHAR(45) NOT NULL DEFAULT '',
                            `acquirer` VARCHAR(45) NOT NULL DEFAULT '',
                            `enrolled` VARCHAR(45) NOT NULL DEFAULT '',
                            `fee` VARCHAR(45) NOT NULL DEFAULT '',
                            `test` VARCHAR(45) NOT NULL DEFAULT '',
                            `uniqueoid` VARCHAR(45) NOT NULL DEFAULT '',
                            `approvalcode` VARCHAR(45) NOT NULL DEFAULT '',
                            `voucher` VARCHAR(45) NOT NULL DEFAULT '',
                            `amountoriginal` VARCHAR(45) NOT NULL DEFAULT '',
                            `voucheramount` VARCHAR(45) NOT NULL DEFAULT '',
                            `voucherpaymentid` VARCHAR(45) NOT NULL DEFAULT '',
                            `voucherentry` VARCHAR(45) NOT NULL DEFAULT '',
                            `voucherrest` VARCHAR(45) NOT NULL DEFAULT '',
                            `ordercancellation` INTEGER UNSIGNED NOT NULL DEFAULT 0 
                                        COMMENT '0 = NotPerformed, 1 = Performed',
                            `successaction` INTEGER UNSIGNED NOT NULL DEFAULT 0 
                                        COMMENT '0 = NotPerformed, 1 = Performed',
                            `callback` INTEGER UNSIGNED NOT NULL DEFAULT 0 
                                        COMMENT '0 = NotPerformed, 1 = Performed'
                        );"
        );
    }
    
    /**
     * Collects common API parameters to send
     * 
     * @param array $aData
     * @param object $oOrderInfo 
     */
    function dibs_api_applyCommon(&$aData, $oOrderInfo) {
        $aData['orderid'] = $oOrderInfo->order->order_id;
        $aData['merchant'] = $this->dibs_helper_getconfig('mid');
        $aData['amount'] = $oOrderInfo->order->total;
        $aData['currency'] = $oOrderInfo->order->currency;
        $aData['callbackurl'] = $this->dibs_helper_getReturnURLs("callback");
        $aData['callbackfix'] = $this->dibs_helper_getReturnURLs("callbackfix");
        
        
        $sAccount = $this->dibs_helper_getconfig('account');
        if((string)$sAccount != "") {
            $aData['account'] = $sAccount;
        }
        
	$sPaytype = $this->dibs_helper_getconfig('paytype');
        if((string)$sPaytype != '') {
            $aData['paytype'] = $this->dibs_api_getPaytype($sPaytype);
        }
        
        $sDistributionType = $this->dibs_helper_getconfig('distr');
        if((string)$sDistributionType != 'empty') {
            $aData['distributionType'] = $sDistributionType;
            if ($sDistributionType == 'email'){
            	$aData['email'] = $oOrderInfo->customer->billing->email;
            }
	}
    }
    
    /**
     * Collects PW Invoice API parameters to send
     * 
     * @param array $aData
     * @param object $oOrderInfo 
     */
    function dibs_api_applyInvoice(&$aData, $oOrderInfo) {
        $aData ['billingFirstName']    = $oOrderInfo->customer->billing->firstname;
        $aData ['billingLastName']     = $oOrderInfo->customer->billing->lastname;
        $aData ['billingAddress2']     = $oOrderInfo->customer->billing->street;
        $aData ['billingPostalCode']   = $oOrderInfo->customer->billing->postcode;
        $aData ['billingPostalPlace']  = $oOrderInfo->customer->billing->city;
        $aData ['billingAddress']      = $oOrderInfo->customer->billing->country . " " .
                                        $oOrderInfo->customer->billing->region;
        $aData ['billingMobile']       = $oOrderInfo->customer->billing->phone;
        $aData ['billingEmail']        = $oOrderInfo->customer->billing->email;
	
        $aData ['shippingFirstName']   = $oOrderInfo->customer->delivery->firstname;
        $aData ['shippingLastName']    = $oOrderInfo->customer->delivery->lastname;
        $aData ['shippingAddress2']    = $oOrderInfo->customer->delivery->street;
        $aData ['shippingPostalCode']  = $oOrderInfo->customer->delivery->postcode;
        $aData ['shippingPostalPlace'] = $oOrderInfo->customer->delivery->city;
        $aData ['shippingAddress']     = $oOrderInfo->customer->delivery->country . " " .
                                         $oOrderInfo->customer->delivery->region;
        $aData ['shippingMobile']      = $oOrderInfo->customer->delivery->phone;
        $aData ['shippingEmail']       = $oOrderInfo->customer->delivery->email;

        if ($oOrderInfo->items) {
            $aData ['oitypes'] = 'QUANTITY;UNITCODE;DESCRIPTION;AMOUNT;ITEMID;VATAMOUNT';
            $aData ['oinames'] = 'Qty;UnitCode;Description;Amount;ItemId;VatAmount';
           
            $i = 1;
            $sZeros = "";
            if(count($oOrderInfo->items) > 9) $sZeros = "00";
            foreach($oOrderInfo->items as $oItem) {
                $aData ['oiRow' . $sZeros . $i] = round($oItem->qty) . ";" . 
                                      "pcs" . ";" . 
                                      $oItem->name . ";" .
                                      round($oItem->price / 100, 2) . ";" .
                                      $oItem->item_id . ";" .
                                      round($oItem->tax_rate / 100, 2);
                $i++;
            }
	}
        
        $aData ['yourRef'] = $oOrderInfo->order->order_id;
    }
    
    /**
     * Collects common PWs API parameters to send
     * 
     * @param array $aData 
     */
    function dibs_api_applyCommonWin(&$aData) {
        $aData['acceptreturnurl'] = $this->dibs_helper_getReturnURLs('success');
	$aData['cancelreturnurl'] = $this->dibs_helper_getReturnURLs('cancel');
        $aData['language'] = $this->dibs_helper_getconfig('lang');
        $aData['sysmod']    = $this->dibs_helper_modVersion();
        
        $sFee = $this->dibs_helper_getconfig('fee');
        if((string)$sFee == 'yes') {
            $aData['addfee'] = 1;
        }

        $sVoucher = $this->dibs_helper_getconfig('voucher');
        if((string)$sVoucher == 'yes') {
            $aData['voucher'] = 1;
        }
        
        $sTest = $this->dibs_helper_getconfig('testmode');
        if((string)$sTest == 'yes') {
            $aData['test'] = 1;
        }
        
        $sUid = $this->dibs_helper_getconfig('uniq');
        if((string)$sUid == 'yes') {
            $aData['uniqueid'] = $aData['orderid'];
        }
    }
    
    /**
     * Gets gateway URL depending to checkout method
     * 
     * @param int $iPayMethod
     * @return string 
     */
    function dibs_api_getFormAction($iPayMethod) {
        if ($iPayMethod == '2') {
            return 'https://pay.dibspayment.com/';
	}
        elseif ($iPayMethod == '3') {
            return 'https://mopay.dibspayment.com/';
	}
    }
    
    /**
     * Calculates MAC for given array of data
     * Used in Standart and Mobile PW
     * 
     * @param array $aData
     * @param bool $bURLDecode
     * @return string 
     */
    function dibs_api_calcMAC($aData, $bURLDecode = FALSE) {
        $sMAC = "";
        $sHMAC = $this->dibs_helper_getconfig('hmac');
        if($sHMAC != "") {
            $sHMAC = $this->dibs_api_hextostr($sHMAC);

            $sDataString = "";
            ksort($aData);
            foreach($aData as $key => $value) {
                if($bURLDecode === TRUE) {
                    $sDataString .= "&" . $key . "=" .urldecode($value);
                }
                else {
                    $sDataString .= "&" . $key . "=" .$value;
                }
            }
            $sDataString = ltrim($sDataString, "&");
            $sMAC = hash_hmac("sha256", $sDataString, $sHMAC);
        }
        return $sMAC;
    }
    
    /**
     * Convert hex HMAC to string.
     * 
     * @param string $hex
     * @return string 
     */
    function dibs_api_hextostr($hex) {
       $string = "";
        foreach(explode("\n", trim(chunk_split($hex,2))) as $h) {
            $string .= chr(hexdec($h));
        }
        return $string;
    }
    
    /**
     * Returns formated paytype parameter
     * 
     * @param string $sPaytype
     * @return string 
     */
    function dibs_api_getPaytype($sPaytype) {
        $sNPaytype = "";
        $iTest = $this->dibs_helper_getconfig('testmode');
        $selectedpaytypes = explode(',',$sPaytype);
        foreach ($selectedpaytypes as $selectedpaytype){
            if (($iTest == 'yes') && (strtolower($selectedpaytype) == 'pbb')){
                $sNPaytype .= ',pbbtest';
            }
            else $sNPaytype .= ",".$selectedpaytype;
        }
        $sNPaytype = trim($sNPaytype, ",");
        
        return $sNPaytype;
    }
    
    function dibs_api_getCurrencyArray() {
        $aCurrency = array ('ADP' => '020','AED' => 784,'AFA' => '004','ALL' => '008',
                            'AMD' => '051','ANG' => 532,'AOA' => 973,'ARS' => '032',
                            'AUD' => '036','AWG' => 533,'AZM' => '031','BAM' => 977,
                            'BBD' => '052','BDT' => '050','BGL' => 100,'BGN' => 975,
                            'BHD' => '048','BIF' => 108,'BMD' => '060','BND' => '096',
                            'BOB' => '068','BOV' => 984,'BRL' => 986,'BSD' => '044',
                            'BTN' => '064','BWP' => '072','BYR' => 974,'BZD' => '084',
                            'CAD' => 124,'CDF' => 976,'CHF' => 756,'CLF' => 990,
                            'CLP' => 152,'CNY' => 156,'COP' => 170,'CRC' => 188,
                            'CUP' => 192,'CVE' => 132,'CYP' => 196,'CZK' => 203,
                            'DJF' => 262,'DKK' => 208,'DOP' => 214,'DZD' => '012',
                            'ECS' => 218,'ECV' => 983,'EEK' => 233,'EGP' => 818,
                            'ERN' => 232,'ETB' => 230,'EUR' => 978,'FJD' => 242,
                            'FKP' => 238,'GBP' => 826,'GEL' => 981,'GHC' => 288,
                            'GIP' => 292,'GMD' => 270,'GNF' => 324,'GTQ' => 320,
                            'GWP' => 624,'GYD' => 328,'HKD' => 344,'HNL' => 340,
                            'HRK' => 191,'HTG' => 332,'HUF' => 348,'IDR' => 360,
                            'ILS' => 376,'INR' => 356,'IQD' => 368,'IRR' => 364,
                            'ISK' => 352,'JMD' => 388,'JOD' => 400,'JPY' => 392,
                            'KES' => 404,'KGS' => 417,'KHR' => 116,'KMF' => 174,
                            'KPW' => 408,'KRW' => 410,'KWD' => 414,'KYD' => 136,
                            'KZT' => 398,'LAK' => 418,'LBP' => 422,'LKR' => 144,
                            'LRD' => 430,'LSL' => 426,'LTL' => 440,'LVL' => 428,
                            'LYD' => 434,'MAD' => 504,'MDL' => 498,'MGF' => 450,
                            'MKD' => 807,'MMK' => 104,'MNT' => 496,'MOP' => 446,
                            'MRO' => 478,'MTL' => 470,'MUR' => 480,'MVR' => 462,
                            'MWK' => 454,'MXN' => 484,'MXV' => 979,'MYR' => 458,
                            'MZM' => 508,'NAD' => 516,'NGN' => 566,'NIO' => 558,
                            'NOK' => 578,'NPR' => 524,'NZD' => 554,'OMR' => 512,
                            'PAB' => 590,'PEN' => 604,'PGK' => 598,'PHP' => 608,
                            'PKR' => 586,'PLN' => 985,'PYG' => 600,'QAR' => 634,
                            'ROL' => 642,'RUB' => 643,'RUR' => 810,'RWF' => 646,
                            'SAR' => 682,'SBD' => '090','SCR' => 690,'SDD' => 736,
                            'SEK' => 752,'SGD' => 702,'SHP' => 654,'SIT' => 705,
                            'SKK' => 703,'SLL' => 694,'SOS' => 706,'SRG' => 740,
                            'STD' => 678,'SVC' => 222,'SYP' => 760,'SZL' => 748,
                            'THB' => 764,'TJS' => 972,'TMM' => 795,'TND' => 788,
                            'TOP' => 776,'TPE' => 626,'TRL' => 792,'TRY' => 949,
                            'TTD' => 780,'TWD' => 901,'TZS' => 834,'UAH' => 980,
                            'UGX' => 800,'USD' => 840,'UYU' => 858,'UZS' => 860,
                            'VEB' => 862,'VND' => 704,'VUV' => 548,'XAF' => 950,
                            'XCD' => 951,'XOF' => 952,'XPF' => 953,'YER' => 886,
                            'YUM' => 891,'ZAR' => 710,'ZMK' => 894,'ZWD' => 716,
        );
        
        return $aCurrency;
    }
    
    function dibs_api_getCurrencyValue($sCode, $bFlip = FALSE) {
        $aCurrency = $this->dibs_api_getCurrencyArray();
        if($bFlip === TRUE) $aCurrency = array_flip($aCurrency);
        return (string)$aCurrency[$sCode];
    }
    
    function dibs_api_detectMobile() { 
        $sUserAgent = strtolower(getenv('HTTP_USER_AGENT')); 
        $sAccept    = strtolower(getenv('HTTP_ACCEPT')); 
  
        if ((strpos($sAccept,'text/vnd.wap.wml')!==false) || 
            (strpos($sAccept,'application/vnd.wap.xhtml+xml')!==false)) { 
            return TRUE;
        }
  
        if (isset($_SERVER['HTTP_X_WAP_PROFILE']) || 
            isset($_SERVER['HTTP_PROFILE'])) { 
            return TRUE;
        }
  
        if (preg_match('/(mini 9.5|vx1000|lge |m800|e860|u940|ux840|compal|'. 
            'wireless| mobi|ahong|lg380|lgku|lgu900|lg210|lg47|lg920|lg840|'. 
            'lg370|sam-r|mg50|s55|g83|t66|vx400|mk99|d615|d763|el370|sl900|'. 
            'mp500|samu3|samu4|vx10|xda_|samu5|samu6|samu7|samu9|a615|b832|'. 
            'm881|s920|n210|s700|c-810|_h797|mob-x|sk16d|848b|mowser|s580|'. 
            'r800|471x|v120|rim8|c500foma:|160x|x160|480x|x640|t503|w839|'. 
            'i250|sprint|w398samr810|m5252|c7100|mt126|x225|s5330|s820|'. 
            'htil-g1|fly v71|s302|-x113|novarra|k610i|-three|8325rc|8352rc|'. 
            'sanyo|vx54|c888|nx250|n120|mtk |c5588|s710|t880|c5005|i;458x|'. 
            'p404i|s210|c5100|teleca|s940|c500|s590|foma|samsu|vx8|vx9|a1000|'. 
            '_mms|myx|a700|gu1100|bc831|e300|ems100|me701|me702m-three|sd588|'. 
            's800|8325rc|ac831|mw200|brew |d88|htc\/|htc_touch|355x|m50|km100|'. 
            'd736|p-9521|telco|sl74|ktouch|m4u\/|me702|8325rc|kddi|phone|lg |'. 
            'sonyericsson|samsung|240x|x320vx10|nokia|sony cmd|motorola|'. 
            'up.browser|up.link|mmp|symbian|smartphone|midp|wap|vodafone|o2|'. 
            'pocket|kindle|mobile|psp|treo)/', $sUserAgent)) { 
            return TRUE;
        }
  
        if (in_array(substr($sUserAgent, 0, 4), 
            array("1207", "3gso", "4thp", "501i", "502i", "503i", "504i", "505i", 
                  "506i", "6310", "6590", "770s", "802s", "a wa", "abac", "acer", 
                  "acoo", "acs-", "aiko", "airn", "alav", "alca", "alco", "amoi", 
                  "anex", "anny", "anyw", "aptu", "arch", "argo", "aste", "asus", 
                  "attw", "au-m", "audi", "aur ", "aus ", "avan", "beck", "bell", 
                  "benq", "bilb", "bird", "blac", "blaz", "brew", "brvw", "bumb", 
                  "bw-n", "bw-u", "c55/", "capi", "ccwa", "cdm-", "cell", "chtm", 
                  "cldc", "cmd-", "cond", "craw", "dait", "dall", "dang", "dbte", 
                  "dc-s", "devi", "dica", "dmob", "doco", "dopo", "ds-d", "ds12", 
                  "el49", "elai", "eml2", "emul", "eric", "erk0", "esl8", "ez40", 
                  "ez60", "ez70", "ezos", "ezwa", "ezze", "fake", "fetc", "fly-", 
                  "fly_", "g-mo", "g1 u", "g560", "gene", "gf-5", "go.w", "good", 
                  "grad", "grun", "haie", "hcit", "hd-m", "hd-p", "hd-t", "hei-", 
                  "hiba", "hipt", "hita", "hp i", "hpip", "hs-c", "htc ", "htc-", 
                  "htc_", "htca", "htcg", "htcp", "htcs", "htct", "http", "huaw", 
                  "hutc", "i-20", "i-go", "i-ma", "i230", "iac",  "iac-", "iac/", 
                  "ibro", "idea", "ig01", "ikom", "im1k", "inno", "ipaq", "iris", 
                  "jata", "java", "jbro", "jemu", "jigs", "kddi", "keji", "kgt", 
                  "kgt/", "klon", "kpt ", "kwc-", "kyoc", "kyok", "leno", "lexi", 
                  "lg g", "lg-a", "lg-b", "lg-c", "lg-d", "lg-f", "lg-g", "lg-k", 
                  "lg-l", "lg-m", "lg-o", "lg-p", "lg-s", "lg-t", "lg-u", "lg-w", 
                  "lg/k", "lg/l", "lg/u", "lg50", "lg54", "lge-", "lge/", "libw", 
                  "lynx", "m-cr", "m1-w", "m3ga", "m50/", "mate", "maui", "maxo", 
                  "mc01", "mc21", "mcca", "medi", "merc", "meri", "midp", "mio8", 
                  "mioa", "mits", "mmef", "mo01", "mo02", "mobi", "mode", "modo", 
                  "mot ", "mot-", "moto", "motv", "mozz", "mt50", "mtp1", "mtv ", 
                  "mwbp", "mywa", "n100", "n101", "n102", "n202", "n203", "n300", 
                  "n302", "n500", "n502", "n505", "n700", "n701", "n710", "nec-", 
                  "nem-", "neon", "netf", "newg", "newt", "nok6", "noki", "nzph", 
                  "o2 x", "o2-x", "o2im", "opti", "opwv", "oran", "owg1", "p800", 
                  "palm", "pana", "pand", "pant", "pdxg", "pg-1", "pg-2", "pg-3", 
                  "pg-6", "pg-8", "pg-c", "pg13", "phil", "pire", "play", "pluc", 
                  "pn-2", "pock", "port", "pose", "prox", "psio", "pt-g", "qa-a", 
                  "qc-2", "qc-3", "qc-5", "qc-7", "qc07", "qc12", "qc21", "qc32", 
                  "qc60", "qci-", "qtek", "qwap", "r380", "r600", "raks", "rim9", 
                  "rove", "rozo", "s55/", "sage", "sama", "samm", "sams", "sany", 
                  "sava", "sc01", "sch-", "scoo", "scp-", "sdk/", "se47", "sec-", 
                  "sec0", "sec1", "semc", "send", "seri", "sgh-", "shar", "sie-", 
                  "siem", "sk-0", "sl45", "slid", "smal", "smar", "smb3", "smit", 
                  "smt5", "soft", "sony", "sp01", "sph-", "spv ", "spv-", "sy01", 
                  "symb", "t-mo", "t218", "t250", "t600", "t610", "t618", "tagt", 
                  "talk", "tcl-", "tdg-", "teli", "telm", "tim-", "topl", "tosh", 
                  "treo", "ts70", "tsm-", "tsm3", "tsm5", "tx-9", "up.b", "upg1", 
                  "upsi", "utst", "v400", "v750", "veri", "virg", "vite", "vk-v", 
                  "vk40", "vk50", "vk52", "vk53", "vm40", "voda", "vulc", "vx52", 
                  "vx53", "vx60", "vx61", "vx70", "vx80", "vx81", "vx83", "vx85", 
                  "vx98", "w3c ", "w3c-", "wap-", "wapa", "wapi", "wapj", "wapm", 
                  "wapp", "wapr", "waps", "wapt", "wapu", "wapv", "wapy", "webc", 
                  "whit", "wig ", "winc", "winw", "wmlb", "wonu", "x700", "xda-", 
                  "xda2", "xdag", "yas-", "your", "zeto", "zte-"))) { 
            return TRUE;
        }
  
        return FALSE;
    }
    
    function dibs_api_orderObject($mOrderInfo) {
        return (object)array(
            'order'    => $this->dibs_helper_getOrderObj($mOrderInfo),
            'items'    => $this->dibs_helper_getItemsObj($mOrderInfo),
            'shipping' => $this->dibs_helper_getShippingObj($mOrderInfo),
            'customer' => $this->dibs_helper_getAddressObj($mOrderInfo)
        );
    }
    
    function dibs_api_DBarray(){
        $aDBFieldsList = array('orderid','amount','currency','test','acquirer',
                         'transact','uniqueoid','paytype','cardnomask','cardcountry',
                         'approvalcode','fee','voucher','amountoriginal','voucheramount',
                         'voucherentry','voucherpaymentid','voucherrest','enrolled',
                         'cardprefix','cardexpdate');

        $aRetFieldsList = $this->dibs_api_DBarray_PayWin();

        return array_combine($aDBFieldsList, $aRetFieldsList);
    }
    
    function dibs_api_DBarray_PayWin() {
        return array('orderid','amount','currency','test','acquirer','transaction',
                     'uniqueid','payTypeName','cardNumberMasked','issuerCountry',
                     'approvalCode','fee','voucher','amountOriginal','voucherAmount',
                     'voucherEntry','voucherPaymentId','voucherRest','enrolled','cardprefix',
                     'cardexpdate');
    }
    
    function dibs_api_detectMethod() {
        if(isset($_POST['pw']) && $_POST['pw'] > 0){
            return $_POST['pw'];
        }
        else return $this->dibs_api_getMethod();
    }
    
    function dibs_api_checkMainFields($oOrder, $bURLDecode = TRUE) {
        
        if (isset($_POST['orderid'])) {
            $oOrder = $this->dibs_helper_getOrderObj($oOrder, TRUE);
            if(!$oOrder->order_id) return 11;
        }
        else return 12;

        $iPayMethod = $this->dibs_api_detectMethod();
        if($iPayMethod == "2" && isset($_POST['voucherAmount']) && $_POST['voucherAmount'] > 0) {
            $iAmount = $_POST['amountOriginal'];
        }
        else $iAmount = $_POST['amount'];

        if(isset($_POST['fee']) && $iPayMethod != 3) {
            $iFeeAmount = $iAmount - $_POST['fee'];
        }
        
        if (isset($_POST['amount'])) {
		if ((abs((int)$iAmount - $oOrder->total) >= 0.01) && 
                   (abs((int)$iFeeAmount - $oOrder->total) >= 0.01)) return 21;
	}
        else return 22;

	if (isset($_POST['currency'])) {
            if ((int)$oOrder->currency != (int)$_POST['currency']) return 31;
        }
        else return 32;
                
        if ($this->dibs_helper_getconfig('hmac') != "") {
            if($this->dibs_api_checkMAC($_POST, $bURLDecode) !== TRUE) return 42;
        }
        
        return FALSE;
    }
    
    /**
     * Compare calculated MAC with MAC from response
     * urldecode response if second parameter is TRUE
     * 
     * @param array $aReq
     * @param bool $bURLDecode
     * @return bool 
     */
    function dibs_api_checkMAC($aReq, $bURLDecode = FALSE) {
        $sReqMAC = $aReq['MAC'];
        unset($aReq['MAC']);
        $sMAC = $this->dibs_api_calcMAC($aReq, $bURLDecode);
        if($sReqMAC == $sMAC) return TRUE;
        else return FALSE;
    }
    
    function dibs_api_callbackPBB(&$aFields) {
        $aFields['PBB_customerId'] = isset($_POST['customerId']) ? $_POST['customerId'] : "-";
	$aFields['PBB_deliveryAddress'] = isset($_POST['deliveryAddress']) ? iconv("ISO-8859-1","UTF-8",$_POST['deliveryAddress']) : "-";		
        $aFields['PBB_deliveryCountryCode'] = isset($_POST['deliveryCountryCode']) ? iconv("ISO-8859-1","UTF-8",$_POST['deliveryCountryCode']) : "-";
        $aFields['PBB_deliveryPostalCode'] = isset($_POST['deliveryPostalCode']) ? iconv("ISO-8859-1","UTF-8",$_POST['deliveryPostalCode']) : "-";
        $aFields['PBB_deliveryPostalPlace'] = isset($_POST['deliveryPostalPlace']) ? iconv("ISO-8859-1","UTF-8",$_POST['deliveryPostalPlace']) : "-";
        $aFields['PBB_firstName'] = isset($_POST['firstName']) ? iconv("ISO-8859-1","UTF-8",$_POST['firstName']) : "-";
        $aFields['PBB_lastName'] = isset($_POST['lastName']) ? iconv("ISO-8859-1","UTF-8",$_POST['lastName']) : "";
    }
    
    function dibs_api_errCodeToMessage($iErrCode) {
        $sToShopLink = $this->dibs_helper_getReturnURLs('cart');
        $sErrBegin = "<h1>" . $this->dibs_helper_getlang('text_err_fatal') . "</h1>";
        $sErrEnd =   "<br><br> <button type=\"button\" onclick=window.location.replace('" . 
                     $sToShopLink . "')>" . $this->dibs_helper_getlang('text_return_toshop') . 
                     "</button>";
        
        $sErrMessage = $this->dibs_helper_getlang('text_err_' . $iErrCode);
        if($sErrMessage == "") {
            $sErrMessage = $this->dibs_helper_getlang('text_err_def');
        }
        
        return $sErrBegin.$sErrMessage.$sErrEnd;
    }
    
    function dibs_api_sqlEncode($sValue) {
        return addslashes(str_replace("`","'",$sValue));
    }
    
    function dibs_api_callback($oOrder) {
        $mErr = $this->dibs_api_checkMainFields($oOrder, FALSE);
        if($mErr !== FALSE) exit($mErr);
        
   	$mStatus = $this->dibs_helper_dbquery_read("SELECT `status` FROM `" . 
                                               $this->dibs_helper_getdbprefix() . 
                                              "dibs_orderdata` WHERE `orderid` = '" . 
                                               $this->dibs_api_sqlEncode($_POST['orderid']) . 
                                              "' LIMIT 1;");
       
        if ($this->dibs_helper_dbquery_read_single($mStatus, 'status') == 0) {
            $iPayMethod = $this->dibs_api_detectMethod();
            
            $aFieldsList = $this->dibs_api_DBarray();
            $aFields = array();
            foreach($aFieldsList as $key => $val) {
                if(($iPayMethod == 2 || $iPayMethod == 3)) {
                    switch ($val) {
                        case 'cardexpdate':
                            if(isset($_POST['expYear']) && isset($_POST['expMonth'])) {
                                $aFields[$key] = $_POST['expYear'] . 
                                                 $_POST['expMonth'];
                            }
                        break;
                        case 'cardprefix':
                            if(isset($_POST['cardNumberMasked'])) {
                                $aFields[$key] = substr($_POST['cardNumberMasked'], 0, 6);
                            }
                        break;
                        default:
                            if(isset($_POST[$val])) {
                                $aFields[$key] = $_POST[$val];
                            }
                            else $_POST[$key] = 0;
                        break;
                    }
                }
                else {
                    if(isset($_POST[$val])) {
                        $aFields[$key] = $_POST[$val];
                    }
                    else $_POST[$key] = 0;
                }
            }
                
            $this->dibs_api_callbackPBB($aFields);
           
            $aFields['callback'] = '1';
            $aFields['status'] = '1';
            
            $this->dibs_helper_afterCallback($oOrder);
            
            $sUpdate = '';
            foreach ($aFields as $sCell => $sValue) {
                $sUpdate .= '`' . $sCell.'`=' . "'" . $this->dibs_api_sqlEncode($sValue) . "',";
            }
            $sUpdate = rtrim($sUpdate, ",");
            $this->dibs_helper_dbquery_write("UPDATE `" . 
                                       $this->dibs_helper_getdbprefix() . 
                                      "dibs_orderdata` SET " . $sUpdate . 
                                      " WHERE `orderid`=" .
                                      $aFields['orderid']." LIMIT 1;");
        }
        else exit();
    }
    
    /** END OF DIBS API AREA **/
}
?>