<?php
/*
  $Id$

  DIBS module for osCommerce

  DIBS Payment Systems
  http://www.dibs.dk

  Copyright (c) 2011 DIBS A/S

  Released under the GNU General Public License
 
*/

  define('MODULE_PAYMENT_DIBS_TEXT_TITLE_MODULES',      'DIBS (PW) | Secured Payment Services');
  define('MODULE_PAYMENT_DIBS_TEXT_PUBLIC TITLE',       'Credit Card (DIBS)');
  define('MODULE_PAYMENT_DIBS_TEXT_ADMIN_TITLE',        'DIBS Payment Services.');
  define('MODULE_PAYMENT_DIBS_TEXT_DESCRIPTION',        'Credit Card (Secure payment through DIBS Payment Services)');
  define('MODULE_PAYMENT_DIBS_TEXT_PUBLIC_DESCRIPTION', 'Secure payment.');
  define('MODULE_PAYMENT_DIBS_ERROR_MID',               'Please specify DIBS Merchant Id in module configuration.');
  define('MODULE_PAYMENT_DIBS_TEXT_EMAIL_FOOTER',       'DIBS Transaction Reference: ');
  define('MODULE_PAYMENT_DIBS_SETUP_ERROR_TITLE',       'Setup Error');
  define('MODULE_PAYMENT_DIBS_ERROR_TITLE',             'Payment Cancelled');
  define('MODULE_PAYMENT_DIBS_ERROR_DEFAULT',           'Your payment did not complete successfully. 
                                                         Please try again, or chose another payment option. 
                                                         If any problem persists, please contact the store owner.');
  define('MODULE_PAYMENT_DIBS_TEXT_PROCESSING_PAYMENT', 'Processing your payment...');
  define('MODULE_PAYMENT_DIBS_HEADING_TITLE',           'Online payment');
  define('MODULE_PAYMENT_DIBS_STATUS_PAYMENT',          'Transaction');
  define('MODULE_PAYMENT_DIBS_TEXT_ERR_FATAL',          'A fatal error has occured!');
  define('MODULE_PAYMENT_DIBS_TEXT_RETURN_TOSHOP',      'Return to shop');
  define('MODULE_PAYMENT_DIBS_TEXT_ERR_11',             "Unknown orderid was returned from DIBS payment gateway!");
  define('MODULE_PAYMENT_DIBS_TEXT_ERR_12',             "No orderid was returned from DIBS payment gateway!");
  define('MODULE_PAYMENT_DIBS_TEXT_ERR_21',             "The amount received from DIBS payment gateway 
                                                        differs from original order amount!");
  define('MODULE_PAYMENT_DIBS_TEXT_ERR_22',             "No amount was returned from DIBS payment gateway!");
  define('MODULE_PAYMENT_DIBS_TEXT_ERR_31',             "The currency type received from DIBS payment gateway 
                                                        differs from original order currency type!");
  define('MODULE_PAYMENT_DIBS_TEXT_ERR_32',             "No currency type was returned from DIBS payment 
                                                        gateway!");
  define('MODULE_PAYMENT_DIBS_TEXT_ERR_41',             "The fingerprint key does not match!");
  define('MODULE_PAYMENT_DIBS_TEXT_ERR_DEF',            "Unknown error appeared. Please contact to shop 
                                                        administration to check transaction.");
  define('TITLE_CONTINUE_CHECKOUT_PROCEDURE',           'Process online payment transaction');
  define('TEXT_CONTINUE_CHECKOUT_PROCEDURE',            'and finish the order process');
  define('NAVBAR_TITLE_1',                              'Checkout');
  define('NAVBAR_TITLE_2',                              'Transaction');
  define('CHECKOUT_BAR_ONLINE_PAYMENT',                 'Transaction');
?>