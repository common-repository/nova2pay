<?php 
/**
 * Checked Woocommerce activation
 */
if( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ){

    /**
     * registers hook for WooCommerce payment gateway
     */
    function whpg_wppg_ntopay_apiplus_class( $methods ) {
        $methods[] = 'WC_wppg_ntopay_apiplus_class';
        return $methods;
    };
    add_filter('woocommerce_payment_gateways', 'whpg_wppg_ntopay_apiplus_class');


    /**
     * Add custom action links.
     */
    function whpg_wppg_ntopay_apiplus_action_links( $links ) {
        $plugin_links = array(
            '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'wc-wppg-apiplus' ) . '</a>',
        );
        return array_merge( $plugin_links, $links );
    }
    add_filter('plugin_action_links_' . plugin_basename( __FILE__ ), 'whpg_wppg_ntopay_apiplus_action_links');

    /**
     * Initialize the gateway.
     */
    function whpg_wppg_ntopay_apiplus_init() {
        class WC_wppg_ntopay_apiplus_class extends WC_Payment_Gateway_CC {
    
            function __construct() {
                $this->id = 'wppg-apiplus';
                $this->method_title       = __( 'Nova2pay', 'wc-wppg-apiplus' );
                $this->title              = __( "Payment Gateway Api+", 'wc-wppg-apiplus' );	// vertical tab title
                $this->method_description = __( "WooCommerce payment gateway integration for API+", 'wc-wppg-apiplus' );	// Show Description
                $this->has_fields = true;
                $this->supports = array( 'default_credit_card_form' );	// support default form with credit card
    
                $this->init_form_fields();	// setting defines
                $this->init_settings();	// load the setting
    
                // Turn these settings into variables we can use
                foreach ( $this->settings as $setting_key => $value ) {
                    $this->$setting_key = $value;
                }
    
    
                // further check of SSL if you want
                add_action( 'admin_notices', array( $this,	'do_ssl_check' ) );
                // Save settings
                if ( is_admin() ) {
                    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
                }		
            }
    
            public function init_form_fields() {
                $this->form_fields = array(
                    'enabled' => array(
                        'title'       => __( 'Enable/Disable', 'wc-wppg-apiplus' ),
                        'label'       => __( 'Enable Payment', 'wc-wppg-apiplus' ),
                        'type'        => 'checkbox',
                        'description' => __( 'This controls whether or not this gateway is enabled within WooCommerce.', 'wc-wppg-apiplus' ),
                        'default'     => 'no',
                        'desc_tip'    => true,
                    ),
                    'title' => array(
                        'title'       => __( 'Title', 'wc-wppg-apiplus' ),
                        'type'        => 'text',
                        'description' => __( 'This controls the title which the user sees during checkout.', 'wc-wppg-apiplus' ),
                        'default'     => __( 'Credite Card', 'wc-wppg-apiplus' ),
                        'desc_tip'    => true,
                    ),
                    'description' => array(
                        'title'       => __( 'Description', 'wc-wppg-apiplus' ),
                        'type'        => 'text',
                        'description' => __( 'This controls the description which the user sees during checkout.', 'wc-wppg-apiplus' ),
                        'default'     => '',
                        'desc_tip'    => true,
                    ),
                    'testmode' => array(
                        'title'       => __( 'Sandbox', 'wc-wppg-apiplus' ),
                        'type'        => 'checkbox',
                        'description' => __( 'Place the payment gateway in development mode.', 'wc-wppg-apiplus' ),
                        'default'     => 'yes',
                    ),
                    'account_id' => array(
                        'title'       => __( 'Account ID', 'wc-wppg-apiplus' ),
                        'type'        => 'text',
                        'description' => __( 'This is the Account ID, received from Payment Gateway.', 'wc-wppg-apiplus' ),
                        'default'     => '',
                    ),
                    'MD5' => array(
                        'title'       => __( 'MD5 Cert', 'wc-wppg-apiplus' ),
                        'type'        => 'text',
                        'description' => __( 'This is the MD5 Cert, received from Payment Gateway.', 'wc-wppg-apiplus' ),
                        'default'     => '',
                    ),
                    'RSA' => array(
                        'title'       => __( 'RSA PublicKey', 'wc-wppg-apiplus' ),
                        'type'        => 'text',
                        'description' => __( 'This is the RSA PublicKey, received from Payment Gateway.', 'wc-wppg-apiplus' ),
                        'default'     => '',
                    ),
        
                    // ACCOUNT_ID md5 rsa
                );
            }
    
            // Response handled for payment gateway
            public function process_payment($order_id) {
                // var_dump(get_woocommerce_currency());
                // die();
                // Order Info
                $order_info = new WC_Order($order_id);
                // Payment Options
                $payment_options = get_option('woocommerce_wppg-apiplus_settings');
                $environment = ( $payment_options['testmode'] == "yes" ) ? TRUE : FALSE;
                
                // 校验卡信息
                $check_card = wppg_ntopay_check_card(wc_clean( wp_unslash($_POST)), $order_info);
    
                if($check_card['status'] == false) {
                    throw new Exception( __( $check_card['error'], 'wc-wppg-apiplus' ) );
                }
    
                $order_number = $order_info->get_order_number();
    
                $order_length_left = 10 - strlen(floor($order_number));
                if($order_length_left > 0) {
                    $min_number = pow(10, $order_length_left - 1);
                    $max_number = pow(10, $order_length_left) - 1;
                    $new_order_number = rand($min_number, $max_number). 'WP'. $order_number;
                } else {
                    $new_order_number = 'WP'. $order_number;
                }
    
                $billingAddress = array(
                    'firstName' => $order_info->get_billing_first_name(),
                    'lastName' => $order_info->get_billing_last_name(),
                    'street' => $order_info->get_billing_address_1(),
                    'houseNumberOrName' => $order_info->get_billing_address_2() ? $order_info->get_billing_address_2() : $order_info->get_billing_address_1(),
                    'city' => $order_info->get_billing_city(),
                    'postalCode' => $order_info->get_billing_postcode(),
                    'stateOrProvince' => $order_info->get_billing_state(),
                    'country' => $order_info->get_billing_country(),
                    'phone' => $order_info->get_billing_phone(),
                    'email' => $order_info->get_billing_email(),
                );
    
                $shippingAddress = array(
                    'firstName' => $order_info->get_shipping_first_name() ? $order_info->get_shipping_first_name() : $billingAddress['firstName'],
                    'lastName' => $order_info->get_shipping_last_name() ? $order_info->get_shipping_last_name() : $billingAddress['lastName'],
                    'street' => $order_info->get_shipping_address_1() ? $order_info->get_shipping_address_1() : $billingAddress['street'],
                    'houseNumberOrName' => $order_info->get_shipping_address_2() ? $order_info->get_shipping_address_2() : (
                        $order_info->get_shipping_address_1() ? $order_info->get_shipping_address_1() : $billingAddress['street']
                    ),
                    'city' => $order_info->get_shipping_city() ? $order_info->get_shipping_city() : $billingAddress['city'],
                    'postalCode' => $order_info->get_shipping_postcode() ? $order_info->get_shipping_postcode() : $billingAddress['postalCode'],
                    'stateOrProvince' => $order_info->get_shipping_state() ? $order_info->get_shipping_state() : $billingAddress['stateOrProvince'],
                    'country' => $order_info->get_shipping_country() ? $order_info->get_shipping_country() : $billingAddress['country'],
                    'phone' => $order_info->get_shipping_phone() ? $order_info->get_shipping_phone() : $billingAddress['phone'],
                    'email' => $order_info->get_billing_email(),
                );
    
                $callback_url = WC()->api_request_url('wppg_ntopay_callback');
    
                $myorder = array(
                    'accountId' => $payment_options['account_id'],
                    'merOrderId' => $new_order_number,
                    'merTradeId' => $new_order_number,
                    'amount' => array(
                        'currency' => get_woocommerce_currency(),
                        'value' => sprintf("%.2f", substr(sprintf("%.3f", $order_info->order_total), 0, -1))
                    ),
                    'version' => '2.1',
                    'card' => $check_card['info'],
                    'billingAddress' => $billingAddress,
                    'deliveryAddress' => $shippingAddress,
                    'shopperUrl' => $callback_url,
                    'notifyUrl' => $callback_url,
                    'md5Key' => $payment_options['MD5'],
                );
    
                $result = wppg_ntopay_sendRequest($myorder, $environment);
        
                if($result['resultCode'] == '10000') {
                    wc_reduce_stock_levels( $order_id );
    
                    $wppgSuccess = $this->get_return_url( $order_info );
                    $wppgCancel = urlencode($order_info->get_cancel_order_url_raw());
                    setcookie("wppg_ntopay_success_url", $wppgSuccess, time() + 3600, '/');
                    setcookie("wppg_ntopay_cancel_url", $wppgCancel, time() + 3600, '/');
    
                    $paymentRedirectURL = $result['checkoutUrl'];
                    if($paymentRedirectURL) {
                        setcookie("wppg_ntopay_url", $paymentRedirectURL, time() + 3600, '/');
    
                        return array (
                            'result' => 'success',
                            'redirect'  => $paymentRedirectURL
                        );
                    
                    }
                } else {
                    throw new Exception( __( $result['resultMessage'], 'wc-wppg-apiplus' ) );
                }
            }
    
            // Validate fields
            public function validate_fields() {
                return true;
            }
    
            public function do_ssl_check() {
                if( $this->enabled == "yes" ) {
                    if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
                        echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";	
                    }
                }		
            }
        }
    };
    add_action('plugins_loaded', 'whpg_wppg_ntopay_apiplus_init', 0);

    /**
     * Check Card
     */
    function wppg_ntopay_check_card($info, $order_info) {

        $number = preg_replace('/[^0-9]/', '', $info['wppg-apiplus-card-number']);
        $expiry = explode('/', $info['wppg-apiplus-card-expiry']);
        $expiry_month = preg_replace('/[^0-9]/', '', $expiry[0]);
        $expiry_year = preg_replace('/[^0-9]/', '', $expiry[1]);
        $firstname = $order_info->get_billing_first_name();
        $lastname = $order_info->get_billing_last_name();
        $cvc = $info['wppg-apiplus-card-cvc'];
        $error = '';

        // Card Number
        if(empty($number)) {
            $error = 'Card Error';
        }

        // First Name
        if(empty($firstname)) {
            $error = 'Name Error';
        }

        // CVC
        if(empty($cvc) || strlen($cvc) < 3) {
            $error = 'CVC ERROR';
        }
        
        // Month
        if (!(is_numeric($expiry_month) && ($expiry_month > 0) && ($expiry_month < 13))) {
            $error = 'Expiry Month Error';
        }

        // Year
        $current_year = date('Y');

        if(empty($expiry_year)) { 
            $error = 'Expiry Year Error';
        } else {
            if (strlen($expiry_year) == 2) {
                $expiry_year = intval(substr($current_year, 0, 2) . $expiry_year);
            };
        }

        if($error) {
            return array(
                'status' => false,
                'error' => $error
            );
        } else {
            return array(
                'status' => true,
                'info' => array(
                    'number' => $number,
                    'expiryMonth' => $expiry_month,
                    'expiryYear' => $expiry_year,
                    'cvc' => $cvc,
                    'firstName' => $firstname,
                    'lastName' => $lastname
                )
            );
        }
        
    }

    /**
     * Send Request
     */
    function wppg_ntopay_sendRequest($order, $environment) {
        $order['tf_sign'] = wppg_ntopay_sign($order);

        $url = $environment == FALSE ? 'https://api.silverexpress.asia/payment-order/api/transaction/apiplus/pay' : 'https://api.test.silverexpress.asia/payment-order/api/transaction/apiplus/pay';

        $response = wp_remote_retrieve_body(wp_remote_post($url, array(
            'headers'   => array('Content-Type' => 'application/json; charset=utf-8'),
            'body'      => json_encode($order),
            'method'    => 'POST'
        )));
        
        return json_decode($response, true);
    }

    /**
     * Sign Action
     */
    function wppg_ntopay_sign($params) {
        if(!empty($params)){
            $p = ksort($params);
            if($p) {
                $str = '';
                foreach ($params as $k => $val) {
                    if(!empty($val)) {
                        if(is_array($val)) {
                            if(count($val) == count($val, 1)) {
                                ksort($val);
                                $val = json_encode($val, 320);
                            } else {
                                foreach($val as $key => $value) {
                                    ksort($val[$key]);
                                };
                                $val = json_encode($val);
                            };
                        }
                        $str .= $k .'=' . $val . '&';						
                    }

                };
                $strs = rtrim($str, '&');
                return strtoupper(md5($strs));
            }
            return false;
        }
        return false;
    }

} else {
    /**
     * Admin Notice
     */
    add_action( 'admin_notices', 'wppg_ntopay_admin_notice__error' );
    function wppg_ntopay_admin_notice__error() {
        ?>
        <div class="notice notice-error">
            <p><a href="http://wordpress.org/extend/plugins/woocommerce/"><?php esc_html_e( 'Woocommerce', 'themebing' ); ?></a> <?php esc_html_e( 'plugin required to actived if you want to install this plugin.', 'themebing' ); ?></p>
        </div>
        <?php
    }

    /**
     * Deactivate Plugin
     */
    function wppg_ntopay_deactivate() {
        deactivate_plugins( plugin_basename( __DIR__ ) );
        unset( $_GET['activate'] );
    }
    add_action( 'admin_init', 'wppg_ntopay_deactivate' );
}
?>