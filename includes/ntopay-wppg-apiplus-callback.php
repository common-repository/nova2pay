<?php
/**
 * registers hook for WooCommerce payment gateway
 */

function wppg_ntopay_callback() {
	if($_GET) {
		$re_info = wc_clean( wp_unslash($_GET));
		$re_type = 'GET';
	}
	if($_POST) {
		$re_info = wc_clean( wp_unslash($_POST));
		$re_type = 'POST';
	}


	if($re_info) {
		$order_id = $re_info['merTradeId'];
		$order_id = (int) substr($order_id, strpos($order_id, 'WP') + 2);
		$merTradeId = $re_info['merTradeId'];

		if($re_info['resultCode'] == '1000' && $re_info['tradeStatus'] == 'Approved') {

			$payment_options = get_option('woocommerce_wppg-apiplus_settings');

			$sign_str = (string)wppg_ntopay_dealWdithQuery($re_info);
            $sign_result = wppg_ntopay_rsaCheck($sign_str, $payment_options['RSA'], wppg_ntopay_hexToStr($re_info['tf_sign']));
			if($sign_result) {
				wppg_ntopay_success_payment($re_type, $order_id, $merTradeId);
			} else {
				wppg_ntopay_error_payment($re_type, $order_id, $merTradeId, 'Sign Error!');
			}

		} else {
			$error_msg = $re_info['resultMessage'] . ' ' . $re_info['gatewayMessage'];
			wppg_ntopay_error_payment($re_type, $order_id, $merTradeId, $error_msg);
		}
        
	}

	die();
}
add_action('woocommerce_api_wppg_ntopay_callback', 'wppg_ntopay_callback');


/**
 * Success Payment Action
 */
function wppg_ntopay_success_payment($re_type, $order_id, $merTradeId) {
	global $woocommerce;
	$order = new WC_Order( $order_id );

	$order->payment_complete();
	$order->add_order_note('Transaction ID: '.$merTradeId);
	$woocommerce -> cart -> empty_cart();
	if($re_type == 'GET') {
		wp_redirect(sanitize_text_field( wp_unslash($_COOKIE['wppg_ntopay_success_url'])));
		exit();
	} else {
		die('success');
	}
}

/**
 * Error Payment Action
 */
function wppg_ntopay_error_payment($re_type, $order_id, $merTradeId, $error_msg = '') {
	global $woocommerce;
	$order = new WC_Order( $order_id );
	$order->update_status('failed', __('Payment has been cancelled.', 'wc-wppg-apiplus'));
	$order->add_order_note('payment failed<br/>Transaction ID: '.$merTradeId.'<br/>Error Message: '.$error_msg, 1);
	
	$woocommerce -> cart -> empty_cart();
	$redirect_url = get_permalink(woocommerce_get_page_id('myaccount'));
	if($re_type == 'GET') {
		wp_redirect($redirect_url);
		exit;
	} else {
		die('success');
	}
}

/**
 * RSA Check
 */
function wppg_ntopay_rsaCheck($data, $public_key, $sign, $sign_type='OPENSSL_ALGO_SHA1')  {
    $public_key = openssl_get_publickey("-----BEGIN PUBLIC KEY-----\r\n".$public_key."\r\n-----END PUBLIC KEY-----");
    $res = openssl_pkey_get_public($public_key);
    
    if($res) {
        $result = (bool)openssl_verify($data, base64_decode($sign), $res);
    } else {
        $result = 'Sign Error';
    }
    return $result;
}

/**
 * Query Deal Width
 */
function wppg_ntopay_dealWdithQuery($params) {
    unset($params['tf_sign']);
    ksort($params);
    reset($params);
    $pairs = array();
    foreach ($params as $k => $v) {
        if(!empty($v)){
            $pairs[] = "$k=$v";
        }
    }
    return implode('&', $pairs);
}

/**
 * Hex To String
 */
function wppg_ntopay_hexToStr($hex) {
    $string = "";
    for ($i = 0; $i < strlen($hex) - 1; $i += 2) {
        $string .= chr(hexdec($hex[$i] . $hex[$i + 1]));
    }
    return $string;
}
?>