<?php
/**
 * Settings for Bitazza Payment Gateway.
 *
 */

defined( 'ABSPATH' ) || exit;

return  array(
    'enabled' => array(
        'title' => __('Enable/Disable', 'bitazza'),
        'type' => 'checkbox',
        'label' => __('Enable Bitazza Checkout', 'bitazza'),
        'default' => 'no'
    ),
    'title' => array(
        'title' => __('Title', 'bitazza'),
        'type' => 'text',
        'css' => 'width:300px',
        'description' => __('This controls the title which the user sees during checkout.', 'scb'),
        'default' => __('Paywith Bitazza Wallet', 'bitazza'),
        'desc_tip' => true,
    ),
    // 'userid' => array(
    //     'title' => __('User Id', 'bitazza'),
    //     'type' => 'text',
    //     'css' => 'width:300px',
    //     'description' => __('Bitazza User Id', 'bitazza'),
    //     'default' => '',
    //     'desc_tip' => true,
    // ),
    'apiusername' => array(
        'title' => __('API Username', 'bitazza'),
        'type' => 'text',
        'css' => 'width:300px',
        'description' => __('Username for Authentication via API', 'bitazza'),
        'default' => '',
        'desc_tip' => true,
    ),
    'apipassword' => array(
        'title' => __('API Password', 'bitazza'),
        'type' => 'password',
        'css' => 'width:300px',
        'description' => __('Password for Authentication via API', 'bitazza'),
        'default' => '',
        'desc_tip' => true,
    ),
    'merchantid' => array(
        'title' => __('Merchant Id', 'bitazza'),
        'type' => 'text',
        'css' => 'width:300px',
        'description' => __('Bitazza Merchant Id', 'bitazza'),
        'default' => '178542',
        'desc_tip' => true,
    ),
    // 'accountid' => array(
    //     'title' => __('Account Id', 'bitazza'),
    //     'type' => 'text',
    //     'css' => 'width:300px',
    //     'description' => __('Bitazza Account Id', 'bitazza'),
    //     'default' => '362203',
    //     'desc_tip' => true,
    // ),
);
