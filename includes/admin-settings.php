<?php

/**
 * An external standard for Auropay.
 *
 * @category Payment
 * @package  AuroPay_Gateway_For_WooCommerce
 * @author   Akshita Minocha <akshita.minocha@aurionpro.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://auropay.net/
 */
if (!defined('ABSPATH')) {
    exit;
}

/*
 * Handle all admin plugin settings
*/

if ($this->testmode == 'yes') {
    $mode = "Test";
} else {
    $mode = "Live";
}
return array(
    'enabled' => array(
        'title'       => 'Enable/Disable',
        'label'       => 'Enable AuroPay Gateway',
        'type'        => 'checkbox',
        'description' => 'This controls the Payment Gateway the user sees during checkout.',
        'default'     => 'no',
        'desc_tip'    => false,
    ),

    'title' => array(
        'title'       => 'Title',
        'type'        => 'text',
        'description' => 'This controls the title which the user sees during checkout.',
        'default'     => 'AuroPay Gateway',
        'desc_tip'    => false,
    ),

    'description' => array(
        'title'       => 'Description',
        'type'        => 'textarea',
        'description' => 'This controls the description which the user sees during checkout.',
        'default'     => 'Pay with your credit card via AuroPay gateway.',
        'desc_tip'    => false,
    ),

    'testmode' => array(
        'title'       => $mode . ' mode',
        'label'       => 'Enable Test Mode',
        'type'        => 'checkbox',
        'description' => 'Place the payment gateway in test mode using test credentials.',
        'default'     => 'yes',
        'desc_tip'    => false,
    ),

    'api_url' => array(
        'title'       => $mode . ' API URL',
        'type'        => 'text'
    ),
    'access_key' => array(
        'title'       => $mode . ' Access Key',
        'type'        => 'password'
    ),
    'secret_key' => array(
        'title'       => $mode . ' Secret Key',
        'type'        => 'password'
    ),
    'logging' => array(
        'title'       => 'Logging',
        'type'        => 'checkbox',
        'label'       => 'Log debug messages',
        'default'     => 'no',
        'description' => 'Save debug messages to the WooCommerce System Status log.',
        'desc_tip'    => false
    ),
    'payments' => array(
        'title'       => 'Payment Summary',
        'type'        => 'checkbox',
        'label'       => 'Add payments overview page',
        'default'     => 'no',
        'description' => 'View your Payment Transactions done via Auropay Gateway.',
        'desc_tip'    => false
    ),
    'expiry' => array(
        'title'       => 'Checkout Timeout (Min)',
        'type'        => 'select',
        'label'       => 'Define Expiry Time for Payment Form. Checkout form will be reloaded for customer if expiry time is reached.',
        'default'     => '5',
        'description' => 'Define Expiry Time for Payment Form. Checkout form will be reloaded for customer if expiry time is reached.',
        'desc_tip'    => false,
        'options'     => array(
            '3'  => '3',
            '4'  => '4',
            '5'  => '5',
            '6'  => '6',
            '7'  => '7',
            '8'  => '8',
            '9'  => '9',
            '10' => '10',
        ),
    ),
);
