<?php
/*
  $Id$

  DIBS module for osCommerce

  DIBS Payment Systems
  http://www.dibs.dk

  Copyright (c) 2011 DIBS A/S

  Released under the GNU General Public License
 
*/

chdir('../../../../');
require('includes/application_top.php');
require('includes/classes/order.php');
require(DIR_WS_LANGUAGES . $language . '/modules/payment/dibs.php');
require(DIR_WS_MODULES . 'payment/dibs.php');

$oDIBS = new dibs();
$oOrderClass = new order();

$oDIBS->success();
  
?>
