<?php

/**
 * An external standard for Auropay.
 *
 * @package AuroPay_Gateway_For_WooCommerce
 * @link    https://auropay.net/
 */
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Handle all admin plugin settings
 */

if ( 'yes' == $this->testmode ) {
	$paymentmode = 'Test';
} else {
	$paymentmode = 'Live';
}
return array(
	'enabled' => array(
		'title' => 'Enable/Disable',
		'label' => 'Enable AuroPay Gateway',
		'type' => 'checkbox',
		'description' => 'This controls the Payment Gateway the user sees during checkout.',
		'default' => 'no',
		'desc_tip' => false,
	),

	'title' => array(
		'title' => 'Title',
		'type' => 'text',
		'description' => 'This controls the title which the user sees during checkout.',
		'default' => 'AuroPay Gateway',
		'desc_tip' => false,
	),

	'description' => array(
		'title' => 'Description',
		'type' => 'textarea',
		'description' => 'This controls the description which the user sees during checkout.',
		'default' => 'Pay with your credit card via AuroPay gateway.',
		'desc_tip' => false,
	),

	'testmode' => array(
		'title' => $paymentmode . ' mode',
		'label' => 'Enable Test Mode',
		'type' => 'checkbox',
		'description' => 'Place the payment gateway in test mode using test credentials.',
		'default' => 'yes',
		'desc_tip' => false,
	),

	'api_url' => array(
		'title' => $paymentmode . ' API URL',
		'type' => 'text',
	),
	'access_key' => array(
		'title' => $paymentmode . ' Access Key',
		'type' => 'password',
	),
	'secret_key' => array(
		'title' => $paymentmode . ' Secret Key',
		'type' => 'password',
	),
	'usd_access_key' => array(
		'title' => $paymentmode . ' Usd Access Key',
		'type' => 'password',
	),
	'usd_secret_key' => array(
		'title' => $paymentmode . ' Usd Secret Key',
		'type' => 'password',
	),
	'sub_plan_access_key' => array(
		'title' => $paymentmode . ' Subscription plan Access Key',
		'type' => 'password',
	),
	'sub_plan_secret_key' => array(
		'title' => $paymentmode . ' Subscription plan Secret Key',
		'type' => 'password',
	),
	'monthly' => array(
		'title' => 'Monthly',
		'type' => 'text',
		'desc_tip' => false,
	),
	'quarterly' => array(
		'title' => 'Quarterly',
		'type' => 'text',
		'desc_tip' => false,
	),
	'half_yearly' => array(
		'title' => 'Half Yearly',
		'type' => 'text',
		'desc_tip' => false,
	),
	'yearly' => array(
		'title' => 'Yearly',
		'type' => 'text',
		'desc_tip' => false,
	),
	'logging' => array(
		'title' => 'Logging',
		'type' => 'checkbox',
		'label' => 'Log debug messages',
		'default' => 'no',
		'description' => 'Save debug messages to the WooCommerce System Status log.',
		'desc_tip' => false,
	),
	'payments' => array(
		'title' => 'Payment Summary',
		'type' => 'checkbox',
		'label' => 'Add payments overview page',
		'default' => 'no',
		'description' => 'View your Payment Transactions done via Auropay Gateway.',
		'desc_tip' => false,
	),
	'expiry' => array(
		'title' => 'Checkout Timeout (Min)',
		'type' => 'select',
		'label' => 'Define Expiry Time for Payment Form. Checkout form will be reloaded for customer if expiry time is reached.',
		'default' => '5',
		'description' => 'Define Expiry Time for Payment Form. Checkout form will be reloaded for customer if expiry time is reached.',
		'desc_tip' => false,
		'options' => array(
			'3' => '3',
			'4' => '4',
			'5' => '5',
			'6' => '6',
			'7' => '7',
			'8' => '8',
			'9' => '9',
			'10' => '10',
		),
	),
);
