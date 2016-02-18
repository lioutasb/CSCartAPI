<?php

kc_include_once(KANCART_ROOT . '/version/function' . substr(PRODUCT_VERSION, 0, 1) . '.php');

if (!function_exists('kc_order_placement_routines')) {

    function kc_order_placement_routines($order_id, $force_notification = array(), $clear_cart = true, $action = 'repay', $area = AREA) {
        try {
            $method = new ReflectionFunction('fn_order_placement_routines');
            $filename = $method->getFileName();
            $start_line = $method->getStartLine() + 1;
            $end_line = $method->getEndLine() - 1;
            $length = $end_line - $start_line;
            $source = @file($filename, FILE_USE_INCLUDE_PATH);
            $body = implode('', array_slice($source, $start_line, $length));
            $code = str_replace('fn_redirect', 'kc_redirect', $body);
            return eval($code);
        } catch (Exception $e) {
            return;
        }
    }

    function kc_redirect() {
        $args = func_get_args();
        throw new Exception(join(PHP_EOL, $args));
    }

}

/**
 * convert price by currency
 * @staticvar null $currencies
 * @param type $price
 * @return type
 */
function convert_price($price, $currency_code = CART_SECONDARY_CURRENCY) {
    $currencies = Registry::get('currencies');
    $currency = $currencies[$currency_code];
    $price = fn_format_rate_value($price, 'F', $currency['decimals'], '.', ',', $currency['coefficient']);
    return $price;
}

function is_addon_enabled($addon) {
    $addonInfo = db_get_row("SELECT *  FROM ?:addons WHERE addon = ?s", $addon);
    if ($addonInfo) {
        return $addonInfo['status'] == 'A';
    }
    return false;
}

function get_product_image_url($product) {
    if (is_numeric($product)) {
        $product = fn_get_image_pairs($product, 'product', 'M', true, true, CART_LANGUAGE);
    } else if (isset($product['main_pair'])) {
        $product = $product['main_pair'];
    }

    if (isset($product['detailed'])) {
        $image = $product['detailed']['http_image_path'];
    } else {
        $image = $product['icon']['http_image_path'];
    }

    if (!$image) {
        $image = Registry::get('config.no_image_path');
    }

    if (strpos($image, 'http') !== FALSE) { //apply for 4.0+
        return $image;
    } else {
        if($image)
	    return Registry::get('config.current_location') . $image;
	else
	    return '';
    }
}

function get_default_address_id() {
    if (isset($_SESSION['auth']['user_id']) && $_SESSION['auth']['user_id'] > 0) {
        $address = fn_get_user_info($_SESSION['auth']['user_id']);
        if (isset($address['profile_id'])) {
            return (int) $address['profile_id'];
        }
    }
    return false;
}

/**
 * Translate cscart's address to kancart's address
 * @staticvar array $addressFields
 * @param type $cscartAddress
 * @param type $address_type
 * @return type
 */
function translate_address($cscartAddress = array(), $address_type = 'billing') {
    static $addressFields =
    array(
        'profile_id' => 'address_book_id',
        'firstname' => 'firstname',
        'lastname' => 'lastname',
        'address' => 'address1',
        'address_2' => 'address2',
        'city' => 'city',
        'country' => 'country_id',
        'country_descr' => 'country_name',
        'zipcode' => 'postcode',
        'phone' => 'telephone',
        'title' => 'gender'
    );
    $translateTo = array();
    if ($cscartAddress) {
        foreach ($addressFields as $key => $val) {
            if ($key == 'profile_id') {
                $translateTo[$val] = $cscartAddress[$key];
            } else if ($key == 'title') {
                $translateTo['gender'] = $cscartAddress['title'] == 'mr' ? 'm' : 'f';
            } else {
                if ($address_type == 'billing') {
                    $translateTo[$val] = $cscartAddress['b_' . $key];
                } else {
                    $translateTo[$val] = $cscartAddress['s_' . $key];
                }
            }
        }
        //special treat for zone
        if ($address_type == 'billing') {
            if (is_country_has_zones($cscartAddress['b_country'])) {
                $translateTo['zone_id'] = get_state_id($cscartAddress['b_state'], $cscartAddress['b_country']);
            } else {
                $translateTo['state'] = $cscartAddress['b_state'];
            }
        } else {
            if (is_country_has_zones($cscartAddress['s_country'])) {
                $translateTo['zone_id'] = get_state_id($cscartAddress['s_state'], $cscartAddress['s_country']);
            } else {
                $translateTo['state'] = $cscartAddress['s_state'];
            }
        }
    }
    return $translateTo;
}

function translate_address_for_checkout($cscartAddress = array(), $address_type = 'billing') {
    if($address_type == 'billing'){
        $address = array();
        $address['address_book_id'] = '';
        $address['address_type'] = 'bill';
        $address['lastname'] = $orderDetail['b_lastname'];
        $address['firstname'] = $orderDetail['b_firstname'];
        $address['gender'] = !empty($orderDetail['b_title'])?$orderDetail['b_title']:'';
        $address['telephone'] = $orderDetail['b_phone'];
        $address['tax_code'] = '';
        $address['postcode'] = $orderDetail['b_zipcode'];
        $address['address1'] = $orderDetail['b_address'];
        $address['address2'] = $orderDetail['b_address_2'];
        $address['city'] = $orderDetail['b_city'];
        $address['zone_id'] = $orderDetail['b_state'];
        $address['zone_code'] = $orderDetail['b_state'];
        $address['zone_name'] = $orderDetail['b_state_descr'];
        $address['country_id'] = $orderDetail['b_country'];
        $address['country_code'] = $orderDetail['b_country'];
        $address['country_name'] = $orderDetail['b_country_descr'];
        $address['state'] = $orderDetail['b_state'];
        return $address;
    }
    else{
        $address = array();
        $address['address_book_id'] = '';
        $address['address_type'] = 'ship';
        $address['lastname'] = $orderDetail['s_lastname'];
        $address['firstname'] = $orderDetail['s_firstname'];
        $address['gender'] = !empty($orderDetail['b_title'])?$orderDetail['b_title']:'';
        $address['telephone'] = $orderDetail['s_phone'];
        $address['tax_code'] = '';
        $address['postcode'] = $orderDetail['s_zipcode'];
        $address['address1'] = $orderDetail['s_address'];
        $address['address2'] = $orderDetail['s_address_2'];
        $address['city'] = $orderDetail['s_city'];
        $address['zone_id'] = $orderDetail['s_state'];
        $address['zone_code'] = $orderDetail['s_state'];
        $address['zone_name'] = $orderDetail['s_state_descr'];
        $address['country_id'] = $orderDetail['s_country'];
        $address['country_code'] = $orderDetail['s_country'];
        $address['country_name'] = $orderDetail['s_country_descr'];
        $address['state'] = $orderDetail['s_state'];
        return $address;
    }
}

function is_country_has_zones($country_code) {
    //special treat for zone
    $total = db_get_row('SELECT count(*) as total FROM ?:states WHERE country_code = ?s', $country_code);
    return $total && $total['total'] > 0;
}

function get_state_id($stateCode, $country_code) {
    $state = db_get_row('SELECT state_id FROM ?:states WHERE code = ?s AND country_code = ?s', $stateCode, $country_code);
    if ($state) {
        return $state['state_id'];
    }
    return '';
}

function get_tax_title($tax) {
    if (!$tax || !is_array($tax)) {
        return '';
    }

    $title = $tax['description'] . '(';
    $modValue = round($tax['rate_value']);
    $modType = $tax['rate_type'];
    if ($modType == 'A' || $modType == 'F') {
        $title .= fn_format_price_by_currency(abs($modValue));
    } else {
        $title .= abs($modValue) . '%';
    }
    if ($tax['price_includes_tax'] == 'Y'
            && ((Registry::get('settings.Appearance.cart_prices_w_taxes') != 'Y') || Registry::get('settings.General.tax_calculation') == 'subtotal')) {
        $title .= ' ' . fn_get_lang_var('included');
    }
    $title .= ')';

    return $title;
}

function get_error_message($default = '') {
    $error = array();
    $messages = fn_get_notifications();
    foreach ($messages as $message) {
        $error[] = $message['message'];
    }

    return sizeof($error) < 1 ? array($default) : $error;
}

/**
 * prepare address from kancart for cs-cart
 * @param type $address
 * @param type $user_id
 * @return type
 */
function prepare_address($address, $address_type = 'billing') {
    $user_data = array();
    $user_data['profile_id'] = intval($address['address_book_id']);
    if ($address_type == 'billing') {
        $user_data['b_title'] = $address['gender'] == 'm' ? 'mr' : 'ms';
        $user_data['b_lastname'] = isset($address['lastname']) ? trim($address['lastname']) : '';
        $user_data['b_firstname'] = isset($address['firstname']) ? trim($address['firstname']) : '';
        $user_data['b_phone'] = isset($address['telephone']) ? trim($address['telephone']) : '';
        $user_data['b_zipcode'] = isset($address['postcode']) ? trim($address['postcode']) : '';
        $user_data['b_city'] = isset($address['city']) ? trim($address['city']) : '';
        $user_data['b_address'] = isset($address['address1']) ? trim($address['address1']) : '';
        $user_data['b_address_2'] = isset($address['address2']) ? trim($address['address2']) : '';
        $user_data['b_country'] = isset($address['country_code']) ? trim($address['country_code']) : '';
        $user_data['b_state'] = isset($address['zone_code']) ? trim($address['zone_code']) : '';
        if (!is_country_has_zones($user_data['b_country'])) {
            $user_data['b_state'] = $address['state'];
        }
    } else {
        $user_data['s_title'] = $address['gender'] == 'm' ? 'mr' : 'ms';
        $user_data['s_lastname'] = isset($address['lastname']) ? trim($address['lastname']) : '';
        $user_data['s_firstname'] = isset($address['firstname']) ? trim($address['firstname']) : '';
        $user_data['s_phone'] = isset($address['telephone']) ? trim($address['telephone']) : '';
        $user_data['s_zipcode'] = isset($address['postcode']) ? trim($address['postcode']) : '';
        $user_data['s_city'] = isset($address['city']) ? trim($address['city']) : '';
        $user_data['s_address'] = isset($address['address1']) ? trim($address['address1']) : '';
        $user_data['s_address_2'] = isset($address['address2']) ? trim($address['address2']) : '';
        $user_data['s_country'] = isset($address['country_code']) ? trim($address['country_code']) : '';
        $user_data['s_state'] = isset($address['zone_code']) ? trim($address['zone_code']) : '';
        if (!is_country_has_zones($user_data['s_country'])) {
            $user_data['s_state'] = $address['state'];
        }
    }

    return $user_data;
}

?>