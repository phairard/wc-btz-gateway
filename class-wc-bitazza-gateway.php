<?php
/*
  Plugin Name: 	WC Bitazza Gateway
  Plugin URI: 	https://www.nsquared.asia/
  Description: 	Provides WooCommerce with Bitazza Payment Gateway.Support woo commerce 1.6.6 and above.Suppert bitazza api version 3.3
  Version: 	    1.0
  Author: 	    Sukhum Butrkam
  Author URI: 	https://www.nsquared.asia/
  License: 	    commercial
 */

load_plugin_textdomain( 'bitazza', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );


/**
 * Custom currency and currency symbol
 */
add_filter( 'woocommerce_currencies', 'add_bitazza_currencies' );

function add_bitazza_currencies( $currencies ) {
    $bitzaza_gateway = new WC_Gateway_Bitazza;
    $bitzaza_settings = $bitzaza_gateway->get_api_settings();

    foreach($bitzaza_settings['bitzaza_support_currencies'] as $currency => $currency_info){
        $currencies[$currency] = $currency_info['name'];
    }

     return $currencies;
}

// add_filter('woocommerce_currency_symbol', 'add_bitazza_currency_symbol', 10, 2);

// function add_bitazza_currency_symbol( $currency_symbol, $currency ) {
//      switch( $currency ) {
//           case 'ABC': $currency_symbol = '$'; break;
//      }
//      return $currency_symbol;
// }


// Fire when activate plugin
// Add realtime-bitazza-callback page for bitazza callback
register_activation_hook( __FILE__, 'bitazza_gateway_activation' );

function bitazza_gateway_activation() {
    global $wpdb;

    // Create scb log table
    $table_name = $wpdb->prefix . 'bitazza_transactions';

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        transaction_id VARCHAR(50)  NOT NULL,
        order_id VARCHAR(50)  NOT NULL,
        status VARCHAR(20)  NOT NULL,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        UNIQUE KEY id (id),
        UNIQUE KEY transaction_id (transaction_id)
    );";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    // Add page for Bitazza Order Payment Status checking
    $page = get_page_by_path( 'bitazza-order' );

    if(!is_object($page)){
        // Add new page
        global $user_ID;

        $page = array(
            'post_type' => 'page',
            'post_name' => 'bitazza-order',
            'post_content' => '[bitazza_order_status]',
            'post_parent' => 0,
            'post_author' => $user_ID,
            'post_status' => 'publish',
            'post_title' => 'Bitazza Order',
            'ping_status' => 'closed',
            'comment_status' => 'closed',
        );

        wp_insert_post ($page);
    }
}

// Fire when deactivate plugin
// Remove realtime-bitazza-callback page
register_deactivation_hook(__FILE__, 'bitazza_gateway_deactivation');

function bitazza_gateway_deactivation(){
    global $wpdb;

    // Delete scblogs table
    $table_name = $wpdb->prefix . 'bitazza_transactions';

    $sql = "DROP TABLE $table_name;";
    $wpdb->query($sql);
    
    // Delete Bitazza Order Payment Status checking page
    $page = get_page_by_path( 'bitazza-order' );

    if(is_object($page)){
        wp_delete_post( $page->ID, true);
    }
}


require_once( __DIR__ . '/includes/bitazza-order-shortcode.php' );

/*
 * Add settings link under plugin name on plugins page.
 */
add_filter('plugin_action_links', 'wc_bitazza_gateway_plugin_action_links', 10, 2);

function wc_bitazza_gateway_plugin_action_links( $links, $file ) {

    if ( $file != plugin_basename( __FILE__ ) ) {
        return $links;
    }

    $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=bitazza">' . __( 'Settings', 'bitazza' ) . '</a>';

    array_unshift( $links, $settings_link );

    return $links;
}



/*
 * Begin class
 */
add_action('plugins_loaded', 'init_bitazza_gateway_class');

function init_bitazza_gateway_class() {

    //Check class WC_Payment_Gateway is exists
    if (class_exists("WC_Payment_Gateway")) {

        class WC_Gateway_Bitazza extends WC_Payment_Gateway {
            private $PAYMENT_STATUS_URL = "bitazza_payment_status";


            /**
             * Constructor for the gateway.
             *
             * @access public
             * @return void
             */
            public function __construct() {
                global $woocommerce;

                $this->id = 'bitazza';
                $this->method_title = __('Bitazza', 'bitazza');
                $this->method_description = __('After you completely test payment. Please contact to Bitazza for allow production', 'bitazza');
                $this->icon = apply_filters('woocommerce_bitazza_checkout_icon', plugin_dir_url(__FILE__) . 'bitzazalogo.png');
                $this->has_fields = true;

                // Bitazza
                $this->bitazza_auth_url = 'https://apexapi.bitazza.com:8443/AP/Authenticate';
                $this->bitazza_create_invoice_url = 'https://gateway.bitazza.com/api/partner/invoice';
                $this->bitazza_get_invoice_url = 'https://gateway.bitazza.com/api/partner/invoices/';
                $this->bitazza_quote_invoice_url = 'https://gateway.bitazza.com/api/partner/quote';
                $this->bitazza_price_current_endpoint_url = 'https://apexapi.bitazza.com:8443/AP/GetLevel1Summary?OMSId=1';

                // Support Currency
                $this->bitzaza_support_currencies = array( 
                    'BTC' => array('currency_id' => 1, 'instrument_id' => 1, 'name' => __( 'Bitcoin' ) , 'image' => __('BUSD_512.png')), 
                    'ETH' => array('currency_id' => 2, 'instrument_id' => 2, 'name' => __( 'Etherium' ), 'image' => __('ETH_512.png') ), 
                    'XLM' => array('currency_id' => 5, 'instrument_id' => 4, 'name' => __( 'XLM Stellar' ), 'image' => __('XLM_512.png') ),
                    'XRP' => array('currency_id' => 6, 'instrument_id' => 5, 'name' => __( 'XRP Ripple' ), 'image' => __('XRP_512.png') ), 
                    'USDT' => array('currency_id' => 7, 'instrument_id' => 5, 'name' => __( 'USD Tether' ), 'image' => __('USDT_512.png') ),
                    'BTZ' => array('currency_id' => 8, 'instrument_id' => 8, 'name' => __( 'BTZ Bitazza' ), 'image' => __('BTZ_512.png') ), 
                    'USDC' => array('currency_id' => 20, 'instrument_id' => 38, 'name' => __( 'USD Circle' ), 'image' => __('USDC_512.png') ), 
                    'BUSD' => array('currency_id' => 21, 'instrument_id' => 40, 'name' => __( 'BUSD' ), 'image' => __('BUSD_512.png') ), 
                    'DOGE' => array('currency_id' => 34, 'instrument_id' => 54, 'name' => __( 'DOGE Dogecoin' ), 'image' => __('DOGE_512.png') ), 
                    'SHIB' => array('currency_id' => 39, 'instrument_id' => 59, 'name' => __( 'SHIB Shiba Inu' ), 'image' => __('SHIB_512.png') ), 
                    'SLP' => array('currency_id' => 45, 'instrument_id' => 66, 'name' => __( 'SLP Smooth Love Potion' ), 'image' => __('SLP_512.png') ), 
                    'SAND' => array('currency_id' => 50, 'instrument_id' => 73, 'name' => __( 'SAND The Sandbox' ), 'image' => __('SAND_512.png') ),     
                );

                // Load the form fields.
                $this->init_form_fields();

                // Load the settings.
                $this->init_settings();

                $this->siteUrl = get_site_url();

                // Define user set variables
                // Check compatibility
                if ($woocommerce->version <= 1.6) {
                    // $this->userid = $this->settings['userid']; 
                    $this->title = $this->settings['title']; 
                    $this->apiusername = $this->settings['apiusername']; 
                    $this->apipassword = $this->settings['apipassword']; 
                    $this->merchantid = $this->settings['merchantid'];
                    // $this->accountid = $this->settings['accountid'];

                    // Save options
                    add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
                } else {
                    // $this->userid = $this->get_option('userid'); 
                    $this->title = $this->get_option('title'); 
                    $this->apiusername = $this->get_option('apiusername'); 
                    $this->apipassword = $this->get_option('apipassword'); 
                    $this->merchantid = $this->get_option('merchantid');
                    // $this->accountid = $this->get_option('accountid');

                    // Save options
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                }

                // Actions
                add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
                add_action('woocommerce_api_'. $this->PAYMENT_STATUS_URL, array( $this, 'bitazza_payment_status'));
            }

            public function bitazza_payment_status() {
                
                header('Content-Type: application/json; charset=utf-8');

                $ref_no = trim($_REQUEST["ref_no"]);
                $order_id = absint($ref_no);
                $order = new WC_Order($order_id);


                $order_status  = $order->get_status();
                if($order_status == "pending"){
                    $order_meta = get_post_meta($order_id);

                    if(isset($order_meta['bitazza_trasaction_id'])){
                        $bitazza_trasaction_id = $order_meta['bitazza_trasaction_id'][0];
                        $invoice_status_url = $this->bitazza_get_invoice_url.$bitazza_trasaction_id;

                        $ch = curl_init($invoice_status_url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HEADER, 0);
                        $output = curl_exec($ch);
                        // print_r($invoice_data);
                        curl_close($ch);
                        $invoice_data = json_decode($output, true);

                        if($invoice_data['status'] == 'expired') {
                            $order_status = 'cancel';
                        }
                        else {
                            $order_status = $invoice_data['status'];
                        }
                    }
                }
                // print_r(wc_get_order_statuses());
                // ( [wc-pending] => Pending payment [wc-processing] => Processing [wc-on-hold] => On hold [wc-completed] => Completed [wc-cancelled] => Cancelled [wc-refunded] => Refunded [wc-failed] => Failed )

                $payment_status = array(
                    "status" => $order_status
                );

                $payment_status_json = json_encode($payment_status);
                echo $payment_status_json;
                die();
            }                

            /**
             * Logging method.
             *
             * @param string $message Log message.
             * @param string $level Optional. Default 'info'. Possible values:
             *                      emergency|alert|critical|error|warning|notice|info|debug.
             */
            public static function log( $message, $level = 'info' ) {
                if ( self::$log_enabled ) {
                    if ( empty( self::$log ) ) {
                        self::$log = wc_get_logger();
                    }
                    self::$log->log( $level, $message, array( 'source' => 'bitazza' ) );
                }
            }

            /**
             * Initialise Gateway Settings Form Fields
             *
             * @access public
             * @return void
             */
            public function init_form_fields() {
                $this->form_fields = include __DIR__ . '/includes/settings-bitazza.php';
            }

            /**
             * Process the payment and return the result
             *
             * @access public
             * @param int $order_id
             * @return array
             */
            public function process_payment( $order_id ) {
 
                global $woocommerce;
             
                // we need it to get any order detailes
                $order = wc_get_order( $order_id );
                $order_meta = get_post_meta($order_id);


                $authentication_data = array(
                    'username' => $this->apiusername,
                    'password' => $this->apipassword,
                );

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $this->bitazza_auth_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_USERPWD, $this->apiusername . ":" . $this->apipassword);  
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $authentication_data);
                                    
                $output = curl_exec($ch);
                curl_close($ch);
                $result_auth = json_decode($output, true);
                $transaction_id = 'NSQTEST-'.$order_id;

                if($result_auth['Authenticated']){
                          
                    // Calculate Amount
                    $currency_symbol = 'BTC';
                    if(isset($order_meta['Currency'])){
                        $currency_symbol = $order_meta['Currency'][0];
                    }
                    
                    $currency_info = $this->bitzaza_support_currencies[$currency_symbol];

                    /*$ch = curl_init($this->bitazza_price_current_endpoint_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HEADER, 0);
                    $output = curl_exec($ch);
                    
                    curl_close($ch);
                    $price_data_list = json_decode($output, true);
                    
                    $amount_baht = floatval($order->get_total());
                    $amount = 10000;
                    foreach($price_data_list as $price_data){
                        $price_data = json_decode($price_data, true);
                        if($price_data['InstrumentId'] == $currency_info['instrument_id']){
                            $amount = floatval($amount_baht / (0.985 * $price_data['BestBid']));
                            break;
                        }
                    }*/

                    $amount_usdt = floatval($order->get_total());
                    $amount = 0;

                    $qouat_data = array(
                        "targetProductId"=> 7,
                        "productId"=> $currency_info['currency_id'],
                        "targetAmount"=> $amount_usdt,
                        "side"=> "external",
                        "address"=> "N-Squared",
                    );

                    $quote_invoice = curl_init($this->bitazza_quote_invoice_url);  
                    $quote_data_payload = json_encode( $qouat_data );
                    curl_setopt($quote_invoice, CURLOPT_HTTPHEADER, array(
                        "x-btz-token: ".$result_auth['Token'], 
                        'Content-Type: application/json',
                    ));
                    curl_setopt($quote_invoice, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($quote_invoice, CURLOPT_POST, true);
                    curl_setopt($quote_invoice, CURLOPT_POSTFIELDS, $quote_data_payload);
                                        
                    $output_amount = curl_exec($quote_invoice);
                    curl_close($quote_invoice);
                    $result_amount = json_decode($output_amount, true);

                    if($result_amount['status'] == 'draft'){
                    
                        $amount = $result_amount['amount'];
                    }

                    /*$invoice_data = array(
                        'transactionId' => $transaction_id,
                        'instrumentId' => $currency_info['instrument_id'], // BTC=1, ETH=2, USDT=5
                        'merchantId' => intval($this->merchantid),
                        'accountId' => intval($result_auth['AccountId']),
                        'userId' => intval($result_auth['UserId']),
                        'productId' => $currency_info['currency_id'], // BTC=1, ETH=2, USDT=7
                        'amount' => $amount,  // Mustbe calculated
                        'baht' => $amount_usdt, 
                        'price' => $amount_usdt, 
                        'side' => 'internal', 
                        'username' => $this->apiusername,
                        'displayName' => 'N-Squared', 
                        'address' => 'N-Squared', 
                    );*/

                    $invoice_data = array(
                        'invoiceId' => $transaction_id,
                        'productId' => $currency_info['currency_id'], // BTC=1, ETH=2, USDT=7
                        'targetAmount' => $amount_usdt, 
                        'side' => 'external', 
                        'address' => 'N-Squared', 
                    );


                    $ch_invoice = curl_init($this->bitazza_create_invoice_url);  
                    $invoice_data_payload = json_encode( $invoice_data );
                    curl_setopt($ch_invoice, CURLOPT_HTTPHEADER, array(
                        "x-btz-token: ".$result_auth['Token'], 
                        'Content-Type: application/json',
                    ));
                    curl_setopt($ch_invoice, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch_invoice, CURLOPT_POST, true);
                    curl_setopt($ch_invoice, CURLOPT_POSTFIELDS, $invoice_data_payload);
                                        
                    $output = curl_exec($ch_invoice);
                    curl_close($ch_invoice);
                    $result_invoice = json_decode($output, true);


                    // return $result_invoice;

                    if($result_invoice['status'] == 'success'){

                        $order->update_meta_data( 'bitazza_trasaction_id', $result_invoice['transactionId'] );
                        $order->update_meta_data( 'bitazza_amount', $result_invoice['amount'] );
                        $order->save();

                        $woocommerce->cart->empty_cart();

                        $redirect_link = get_permalink( get_page_by_path( 'bitazza-order' ) );
                        $findme   = '?';
                        $pos = strpos($redirect_link, $findme);

                        if ($pos === false) {
                            $redirect_link = $redirect_link . "?ref_no=".$order_id;
                        } else {
                            $redirect_link = $redirect_link . "&ref_no=".$order_id;
                        }

                        return array(
                            'result' => 'success',
                            'redirect' => $redirect_link
                        );
                    }

                    return array(
                        'result' => 'success',
                        // 'a' => $invoice_data,
                        // 'token' => $result_auth['Token'],
                        // 'auth' => $result_auth
                        // 'redirect' => 'https://transfer.bitazza.com/merchant/invoice?id='.$transaction_id
                    );
                }
                return array(
                    'result' => 'success',
                    // 'redirect' => $this->get_return_url( $order )
                );
             
             
                /*
                  * Array with parameters for API interaction
                 */
                // $args = array(
             
                //     ...
             
                // );
             
                /*
                 * Your API interaction could be built with wp_remote_post()
                  */
                //  $response = wp_remote_post( '{payment processor endpoint}', $args );
             
             
                //  if( !is_wp_error( $response ) ) {
             
                //      $body = json_decode( $response['body'], true );
             
                //      // it could be different depending on your payment processor
                //      if ( $body['response']['responseCode'] == 'APPROVED' ) {
             
                //         // we received the payment
                //         $order->payment_complete();
                //         $order->reduce_order_stock();
             
                //         // some notes to customer (replace true with false to make it private)
                //         $order->add_order_note( 'Hey, your order is paid! Thank you!', true );
             
                //         // Empty cart
                //         $woocommerce->cart->empty_cart();
             
                //         // Redirect to the thank you page
                //         return array(
                //             'result' => 'success',
                //             'redirect' => $this->get_return_url( $order )
                //         );
             
                //      } else {
                //         wc_add_notice(  'Please try again.', 'error' );
                //         return;
                //     }
             
                // } else {
                //     wc_add_notice(  'Connection error.', 'error' );
                //     return;
                // }
             
            }

            /**
             * Output for the order received page.
             *
             * @access public
             * @return void
             */
            function receipt_page($order_id) {
                
            }

            /**
             * Output for custom fields.
             *
             * @access public
             * @return array
             */
            public function get_api_settings() {
                return array(
                    'bitazza_price_current_endpoint_url' => $this->bitazza_price_current_endpoint_url,
                    'bitazza_quote_invoice_url' => $this->bitazza_quote_invoice_url,
                    'bitzaza_support_currencies' => $this->bitzaza_support_currencies,
                    'token-x' => 'dsdsdsd-dsdsds-dsdsdsd',
                );
            }
        }
    }
}

/**
 * Add scb payment gateway into woocommerce payment gateway
 */
add_filter('woocommerce_payment_gateways', 'add_bitazza_gateway');

function add_bitazza_gateway($methods) {
    $methods[] = 'WC_Gateway_Bitazza';
    return $methods;
}

/**
 * Add custom field at checkout. (only added not display)
 */
function bitzaza_custom_checkout_fields($fields){

    $bitzaza_gateway = new WC_Gateway_Bitazza;
    $bitzaza_settings = $bitzaza_gateway->get_api_settings();
    
    $currency_choices = array();
    foreach($bitzaza_settings['bitzaza_support_currencies'] as $currency => $currency_info){
        $currency_choices[$currency] = $currency_info['name'];
    }

    $fields['extra_fields'] = array(
            'currency' => array(
                'type' => 'select',
                'options' => $currency_choices,
                'required' => true,
                'label' => __( 'Please select currency' ),
                )
            );

    return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'bitzaza_custom_checkout_fields' );

/**
 * Display custom field at checkout
 */
function bitzaza_extra_checkout_fields(){ 


    $bitzaza_gateway = new WC_Gateway_Bitazza;
    $bitzaza_settings = $bitzaza_gateway->get_api_settings();

    $checkout = WC()->checkout(); ?>
    <div class="extra-fields">
        <h3><?php _e( 'Payment Info ' ); ?></h3>
        <?php 
        // because of this foreach, everything added to the array in the previous function will display automagically
        foreach ( $checkout->checkout_fields['extra_fields'] as $key => $field ) : ?>
        <?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>
        <?php endforeach; ?>
        <div class="currency-container">
            <dl id="custom-select-currencies" class="dropdown">
                <dt>
                    <div class="select">
                        <span>--- Please select the Currency ---</span>
                    </div>
                </dt> 
                <dd>
                    <ul>
                    <?php foreach ( $bitzaza_settings['bitzaza_support_currencies'] as $key => $field ) : ?>
                            <li>
                                <div class="item">
                                    <img class="flag" src="<?php _e(plugin_dir_url(__FILE__) . $field['image']); ?>" alt="" />
                                    <span class="currency"><?php _e($key); ?></span>
                                    <span class="value"><?php _e($field['currency_id']); ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </dd>
            </dl>
            <!-- <span id="result"></span> -->
            <div id="calculate-price"></div>
        </div>
    </div>


    <style>
        #currency{
            display:none;
        }
        #custom-select-currencies{
            margin:0;
            min-width:200px;
            width:100%;
            z-index: 2;
        }
        .currency-container{
            display:flex;
            align-items:center;
            justify-content: space-between;
            margin-bottom:20px;
        }
        .desc { color:#6b6b6b;}
        .desc a {color:#0092dd;}
        .dropdown dd, .dropdown dt, .dropdown ul { margin:0px; padding:0px; }
        .dropdown dd { position:relative; }
        .dropdown div, .dropdown a:visited { color:#816c5b; text-decoration:none; outline:none;}
        .dropdown dt a:hover { color:#5d4617; border: 1px solid #d0c9af;}
        .dropdown dt div {
            background:white url('http://www.jankoatwarpspeed.com/wp-content/uploads/examples/reinventing-drop-down/arrow.png') no-repeat scroll right center; 
            background-position-x:95%;
            display:block; 
            padding-right:20px;
            border:1px solid #eee; 
            width:100%;
        }
        .dropdown dt a span {
            cursor:pointer; 
            display:block; 
            padding:5px;
        }
        .dropdown dd ul { 
            background:#fff none repeat scroll 0 0;
            border:1px solid #eee; 
            color:black; 
            display:none;
            left:0px; 
            /* padding:5px 0px;  */
            position:absolute; 
            top:2px; 
            width:100%; 
            min-width:170px; 
            list-style:none;
            z-index: 9999;
        }
        .dropdown span.value { display:none;}
        .dropdown dd ul li a { padding:5px; display:block;}
        .dropdown dd ul li a:hover { background-color:#d0c9af;}
        
        .dropdown img.flag { border:none; vertical-align:middle; margin-left:10px; }
        .dropdown .item{ display: flex;  align-items: center;cursor: pointer; padding:5px 0px;}
        .dropdown ul .item:hover{ background-color:#f2f2f2;}
        .dropdown .item .flag{ margin-right:10px;max-width:20px;}
        .dropdown .item span{ color:black; }
        .dropdown .select { padding: 5px 5px; min-height: 44px; color:black; cursor: pointer; align-items:center; display:flex;}
        .dropdown .select .item { border:0; display: flex;  align-items: center;cursor: pointer; }
        .flagvisibility { display:none;}
    </style>

<?php }
add_action( 'woocommerce_checkout_order_review' ,'bitzaza_extra_checkout_fields',20 );

/**
 * Validate custom field at checkout
 */
function bitzaza_custom_checkout_field_process() {
    // Check if set, if its not set add an error.
    if ( ! $_POST['currency'] )
        wc_add_notice( __( 'Please select value of currency field.' ), 'error' );
}
add_action('woocommerce_checkout_process', 'bitzaza_custom_checkout_field_process');

/**
 * Update the order meta with field value
 */
function bitzaza_checkout_field_update_order_meta( $order_id ) {
    if ( ! empty( $_POST['currency'] ) ) {
        update_post_meta( $order_id, 'Currency', sanitize_text_field( $_POST['currency'] ) );
    }
}
add_action( 'woocommerce_checkout_update_order_meta', 'bitzaza_checkout_field_update_order_meta' );

/**
 * Update price to checkout page when currency selectbox has changed
 */
function bitzaza_currency_change( $order_id) {
    global $woocommerce;
    
    $bitzaza_gateway = new WC_Gateway_Bitazza;
    $bitzaza_settings = $bitzaza_gateway->get_api_settings();

    $cart_total = $woocommerce->cart->get_cart_contents_total();
    ?>
    <div id="cart-total" style="display:none;"><?php _e($cart_total); ?></div>
    <div id="token-bx" style="display:none;"><?php _e($bitzaza_settings['token-x']); ?></div>
    <?
    ?>
    <script type="text/javascript">

        jQuery(document).ready(function($){

            const BITZAZA_PRICE_ENDPOINT = `<?php _e($bitzaza_settings['bitazza_price_current_endpoint_url']); ?>`

            function getCurrencyId(currency){
                let currencyId = 0
                switch(currency)
                {
                    <?php foreach($bitzaza_settings['bitzaza_support_currencies'] as $currency => $currency_info){ ?>
                        case '<?php _e($currency) ?>':
                            currencyId = <?php _e($currency_info['currency_id']) ?>;
                        break;
                    <?php } ?>
                    default: break;
                }
                return currencyId
            }

            /*async function getPrice(currencyId)
            {
                const response =  await $.get(BITZAZA_PRICE_ENDPOINT)
                const results = response.filter((item)=> {
                    let jsonData = JSON.parse(item)
                    return jsonData['InstrumentId'] === currencyId
                })
                return JSON.parse(results)
            }*/

            async function getPrice(currencyId, amount)
            {
                let productId = parseInt(currencyId)
                let targetAmount = parseInt(amount)

                const response =  await $.ajax({
                    type: 'post',
                    contentType: 'application/json',
                    headers: {
                        'x-btz-token':'6e4a172b-0a40-47b9-a13a-9dd0dd0a6614'
                    },
                    url: 'https://gateway.bitazza.com/api/partner/quote',
                    data: {
                        'productId': productId,
                        'targetAmount': targetAmount
                    },  
                    dataType: 'json',
                    
                    success: function (result) {
                    // CallBack(result);
                        console.log(result)
                    },
                    error: function (error) {
                        console.log(error)
                    }
                });

            }

            async function changeCalculatePrice()
            {
                // ETC,BTC,USDT
                let currency = $("#currency").find(":selected").val();

                //Get Instument Id
                let currencyId = getCurrencyId(currency)

                if(!currencyId){
                    $("#refresh-btn").hide()
                    $("#calculate-price").hide()
                }else{
                    $("#refresh-btn").show()
                    $("#calculate-price").show()
                }

                //Get Cart Total
                let cartTotal = $("#cart-total").text();

                //Get Price from bitzaza api
                let price = await getPrice(currencyId,cartTotal) || 0
                //let price = await getPrice(currencyId,cartTotal) || 0

                let html = ''
                if(price)
                {
                    html = `${ price }`//(cartTotal / price['BestBid']).toFixed(8)
                }else {
                    html = 'Service not available , try again.'
                }

                $("#calculate-price").html(`
                    <div style="display:flex;align-items:center;font-size:24px;">
                        <img style="height:10px;margin-right:15px;" src="<?php _e(plugin_dir_url(__FILE__) . __('equalIcon.png'));?>" />
                        ${html}
                    </div>`)
                $("#calculate-price").css('margin-left',20)
            }

            $("#currency").change(async function(){
                await changeCalculatePrice()
            });

            $("#refresh-btn").click(async function(){
                await changeCalculatePrice()
            })

            // Add Class for flag
            $(".dropdown img.flag").addClass("flagvisibility");
            $(".dropdown img.flag").toggleClass("flagvisibility");

            // When clicked at div toggle ui
            $(".dropdown dt div").click(function() {
                $(".dropdown dd ul").toggle();
            });

            // When Selected Flag
            $(".dropdown dd ul li div").click(function() {

                //change whole select with selted flag
                const text = $(this).html();
                $(".dropdown dt div").html(`<div class="item selected">${text}</div>`);
                $(".dropdown dd ul").hide();
                
                const getCurrencyInstrumentId = $(".select .item.selected .value").text().trim();
                const getCurrencyName =  $(".select .item.selected .currency").text().trim();
                //
                console.log(getCurrencyName,getCurrencyInstrumentId);
                // $("#result").html("Selected value is: " + getSelectedValue("custom-select-currencies"));
                $("#currency").val(getCurrencyName).change();

            });

        
            $(document).bind('click', function(e) {
                var $clicked = $(e.target);
                if (! $clicked.parents().hasClass("dropdown"))
                    $(".dropdown dd ul").hide();
            });
        });
    </script>
<?php
}
add_action('woocommerce_checkout_order_review', 'bitzaza_currency_change', 10);

function extract_order_oject($order_id)
{
    $order = wc_get_order( $order_id );

    $order_data = $order->get_data(); // The Order data

    $order_id = $order_data['id'];
    $order_parent_id = $order_data['parent_id'];
    $order_status = $order_data['status'];
    $order_currency = $order_data['currency'];
    $order_version = $order_data['version'];
    $order_payment_method = $order_data['payment_method'];
    $order_payment_method_title = $order_data['payment_method_title'];
    $order_payment_method = $order_data['payment_method'];
    $order_payment_method = $order_data['payment_method'];

    ## Creation and modified WC_DateTime Object date string ##

    // Using a formated date ( with php date() function as method)
    $order_date_created = $order_data['date_created']->date('Y-m-d H:i:s');
    $order_date_modified = $order_data['date_modified']->date('Y-m-d H:i:s');

    // Using a timestamp ( with php getTimestamp() function as method)
    $order_timestamp_created = $order_data['date_created']->getTimestamp();
    $order_timestamp_modified = $order_data['date_modified']->getTimestamp();

    $order_discount_total = $order_data['discount_total'];
    $order_discount_tax = $order_data['discount_tax'];
    $order_shipping_total = $order_data['shipping_total'];
    $order_shipping_tax = $order_data['shipping_tax'];
    $order_total = $order_data['total'];
    $order_total_tax = $order_data['total_tax'];
    $order_customer_id = $order_data['customer_id']; // ... and so on

    ## BILLING INFORMATION:

    $order_billing_first_name = $order_data['billing']['first_name'];
    $order_billing_last_name = $order_data['billing']['last_name'];
    $order_billing_company = $order_data['billing']['company'];
    $order_billing_address_1 = $order_data['billing']['address_1'];
    $order_billing_address_2 = $order_data['billing']['address_2'];
    $order_billing_city = $order_data['billing']['city'];
    $order_billing_state = $order_data['billing']['state'];
    $order_billing_postcode = $order_data['billing']['postcode'];
    $order_billing_country = $order_data['billing']['country'];
    $order_billing_email = $order_data['billing']['email'];
    $order_billing_phone = $order_data['billing']['phone'];

    ## SHIPPING INFORMATION:

    $order_shipping_first_name = $order_data['shipping']['first_name'];
    $order_shipping_last_name = $order_data['shipping']['last_name'];
    $order_shipping_company = $order_data['shipping']['company'];
    $order_shipping_address_1 = $order_data['shipping']['address_1'];
    $order_shipping_address_2 = $order_data['shipping']['address_2'];
    $order_shipping_city = $order_data['shipping']['city'];
    $order_shipping_state = $order_data['shipping']['state'];
    $order_shipping_postcode = $order_data['shipping']['postcode'];
    $order_shipping_country = $order_data['shipping']['country'];

    return $order_data;
}


// define woocommerce_new_order callback function
function call_woocommerce_new_order($order_id) { 
    
    $order_data = extract_order_oject($order_id);

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL,"https://webhook.site/5f3b2c34-ec52-4366-895d-f768e76321aa");
    curl_setopt($ch, CURLOPT_POST, 1);
    //curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($order_id));

    // Receive server response ...
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $server_output = curl_exec($ch);
    curl_close ($ch);

    //email here with information , with back to payment link.

};

add_action( 'woocommerce_new_order', 'call_woocommerce_new_order', 10, 1);


// define the woocommerce_order_status_changed callback 
function action_woocommerce_order_status_changed( $order_id,$old_status,$new_status ) { 
    $order_data = extract_order_oject($order_id);

    if ( $order->has_status('completed') ) {
        // email here with information , with back to thank you page.
    }
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL,"https://webhook.site/5f3b2c34-ec52-4366-895d-f768e76321aa");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,"${order_id} ${old_status} ${new_status}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $server_output = curl_exec($ch);
    curl_close ($ch);

    send_email();
}; 
         
// add the action 
add_action( 'woocommerce_order_status_changed', 'action_woocommerce_order_status_changed', 10, 3 ); 

function send_email(){
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, 'https://api.postmarkapp.com/email');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "
        {\n  \"From\": \"admin@catalog.in.th\",\n  
        \"To\": \"gunkimsong8@gmail.com\",\n  
        \"Subject\": \"Postmark test\",\n  
        \"TextBody\": \"Hello dear Postmark user.\",\n  
        \"HtmlBody\": \"<html><body><strong>Hello</strong> dear Postmark user.</body></html>\",\n  
        \"MessageStream\": \"outbound\"\n}"
    );
    
    $headers = array();
    $headers[] = 'Accept: application/json';
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'X-Postmark-Server-Token: 5e82ca68-b28e-46bf-ba96-f98c983e3260';

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }
    curl_close($ch);
}
?>
