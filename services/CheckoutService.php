<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

/**
 * Checkout utility
 * @package services
 * @author liyz 
 */
class CheckoutService {

    public function __construct() {
        $this->cart = &$_SESSION['cart'];
        $this->auth = &$_SESSION['auth'];
        $this->settings = &$_SESSION['settings'];
        $this->messages = array();

        fn_define('CHECKOUT', true);
        define('MAX_PAYPAL_PRODUCTS', 100);
    }

    /**
     * get checkout detail information
     * 
     */
    public function detail($p_id = -1) {
        if($p_id>-1)
            $this->cart['payment_id'] = $p_id;
        $detail = array();
        $detail['need_billing_address'] = defined('NEED_BILLING_ADDRESS') ? NEED_BILLING_ADDRESS : FALSE;
        $detail['need_shipping_address'] = FALSE;
        $detail['billing_address'] = $this->getBillingAddress();
        $detail['shipping_address'] = $this->getShippingAddress();
        //$this->applyPayPalAddress();
        $detail['review_orders'] = array($this->getReviewOrder());
        $detail['need_select_shipping_method'] = !$detail['review_orders'][0]['selected_shipping_method_id'];

        //$detail['payment_methods'] = $this->getPaymentMethods();
        $tmp = $this->getPaymentMethods();
        foreach($tmp['methods'] as $methodP){
            if($methodP['pm_id'] == ($p_id>-1 ? $p_id : $this->cart['payment_id'])){
                $detail['payment_method'] = $methodP;
            }
        }

        fn_calculate_cart_content($cart, $_SESSION['auth']);
        fn_calculate_payment_taxes($cart, $_SESSION['auth']);
        // finally we calculate the totals
        $detail['price_infos'] = $this->getPriceInfos();
        $detail['messages'] = $this->getMessage();
        $detail['notes'] = $this->cart['notes'];
        $detail['is_virtual'] = FALSE;
        return $detail;// fn_calculate_cart_content($this->cart, $_SESSION['auth'], 'E', true, 'F', true);
    }

    public function getReviewOrder() {
        $order = array();
        $order['cart_items'] = $this->getOrderItems();
        //$order['shipping_methods'] = $this->getOrderShippingMethods();

        $order['selected_shipping_method_id'] = $this->selectOrderShippingMethod();
        $tmp = $this->getOrderShippingMethods();
        foreach($tmp['shippings'] as $methodP){
            if($methodP['sm_id'] == $order['selected_shipping_method_id']){
                $order['shipping_method'] = $methodP;
            }
        }
        $coupons = empty($this->cart['coupons']) ? array() : array_keys($this->cart['coupons']);
        $giftCertificate = empty($this->cart['use_gift_certificates']) ? array() : array_keys($this->cart['use_gift_certificates']);
        empty($coupons) && empty($giftCertificate) || $order['coupon_code'] = join(' ', $giftCertificate) . ' ' . $coupons[0];
        return $order;
    }

    private function getMessage() {
        $messages = fn_get_notifications();
        foreach ($messages as $message) {
            if ($message['extra'] == 'promotion_no_such_coupon') {
                $this->messages [] = array('type' => 'error', 'content' => $message['message']);
            } else if ($message['extra'] == "applied_promotions") {
                $this->messages [] = array('type' => 'success', 'content' => $message['message']);
            }
        }
        return $messages;
    }

    public function checkAddressIntergrity($address) {
        if ($address) {
            $userService = ServiceFactory::factory('User');
            return $userService->checkAddressIntegrity($address);
        }
        return false;
    }

    /**
     * if there is address in the session,we extract address from session
     * @return type
     */
    public function getBillingAddress() {

        if (isset($_SESSION['auth']) && $_SESSION['auth']['user_id'] == 0) {  //apply for guest express checkout
            $_SESSION['cart']['user_data']['profile_id'] = 0;
            return translate_address($_SESSION['cart']['user_data'], 'billing');
        }

        if (!isset($_SESSION['auth']['address_id']) || $_SESSION['auth']['address_id'] == 0) {
            $_SESSION['auth']['address_id'] = get_default_address_id();
        }
        $userService = ServiceFactory::factory('User');
        return $userService->getBillingAddress($_SESSION['auth']['address_id']);
    }

    /**
     * if there is address in the session we extract address from session
     * @return type
     */
    public function getShippingAddress() {

        if (isset($_SESSION['auth']) && $_SESSION['auth']['user_id'] == 0) {  //apply for guest express checkout
            $_SESSION['cart']['user_data']['profile_id'] = 0;
            return translate_address($_SESSION['cart']['user_data'], 'shipping');
        }

        if (!isset($_SESSION['auth']['address_id']) || $_SESSION['auth']['address_id'] == 0) {
            $_SESSION['auth']['address_id'] = get_default_address_id();
        }
        $userService = ServiceFactory::factory('User');
        return $userService->getShippingAddress($_SESSION['auth']['address_id']);
    }

    /**
     * apply for palpal pay and create an order
     */
    public function applyPayPalAddress() {
        if (isset($_SESSION['auth']['address_id']) && $_SESSION['auth']['address_id'] > 0) {
            $profile_data = db_get_row("SELECT * FROM ?:user_profiles WHERE user_id = ?i AND profile_id = ?i", $_SESSION['auth']['user_id'], $_SESSION['auth']['address_id']);
            foreach ($profile_data as $key => $value) {
                $_SESSION['cart']['user_data'][$key] = $value;
            }
        }
        $_SESSION['cart']['user_data']['profile_id'] = $_SESSION['auth']['address_id'];
    }

    private function getPriceInfos() {
        $position = 0;
        $priceInfos = array();

        $total = 0;

        $priceInfos[] = array(
            'title' => fn_get_lang_var('subtotal'),
            'type' => 'subtotal',
            'price' => convert_price($this->cart['display_subtotal']),
            'currency' => CART_SECONDARY_CURRENCY,
            'position' => $position++
        );
        $total +=  $this->cart['display_subtotal'];


        if (!$this->cart['shipping_failed']) {
            if ($this->cart['shipping_required']) { //not free shipping
                if (($this->cart['display_shipping_cost'] > 0 || $this->cart['shipping_cost'] > 0) && $this->cart['shipping']) {
                    $price = $this->cart['display_shipping_cost'] > 0 ? $this->cart['display_shipping_cost'] : $this->cart['shipping_cost'];
                    $title = '';
                    foreach ($this->cart['shipping'] as $shipping) {
                        $title .=$shipping['shipping'] . ' ';
                    }
                    $priceInfos[] = array(
                        'title' => $title,
                        'type' => 'discount',
                        'price' => convert_price($price),
                        'currency' => CART_SECONDARY_CURRENCY,
                        'position' => $position++
                    );
                    $total +=  $price;
                }
            }
        }

        if ($this->cart['discount'] > 0) {
            $priceInfos[] = array(
                'title' => fn_get_lang_var('including_discount'),
                'type' => 'discount',
                'price' => convert_price($this->cart['discount']),
                'currency' => CART_SECONDARY_CURRENCY,
                'position' => $position++
            );
            $total -=  $this->cart['discount'];
        }

        if ($this->cart['subtotal_discount'] > 0) {
            $priceInfos[] = array(
                'title' => fn_get_lang_var('order_discount'),
                'type' => 'discount',
                'price' => convert_price($this->cart['subtotal_discount']),
                'currency' => CART_SECONDARY_CURRENCY,
                'position' => $position++
            );
            $total -=  $this->cart['subtotal_discount'];
        }

        $payment_surcharge = 0;
        $take_surcharge_from_vendor = (PRODUCT_TYPE == 'MULTIVENDOR') ? fn_take_payment_surcharge_from_vendor($this->cart['products']) : false;
        if ($this->cart['payment_surcharge'] > 0 && !$take_surcharge_from_vendor) {
            $priceInfos[] = array(
                'title' => $this->cart['payment_surcharge_title'] ? $this->cart['payment_surcharge_title'] : fn_get_lang_var('payment_surcharge'),
                'type' => 'payment',
                'price' => convert_price($this->cart['payment_surcharge']),
                'currency' => CART_SECONDARY_CURRENCY,
                'position' => $position++
            );
            $payment_surcharge =  $this->cart['payment_surcharge'];
        }

        if ($this->cart['coupons']) {
            $priceInfos[] = array(
                'title' => fn_get_lang_var('coupon'),
                'type' => 'info',
                'price' => join(' ', array_keys($this->cart['coupons'])),
                'currency' => CART_SECONDARY_CURRENCY,
                'position' => $position++
            );
            //$total -=  $this->cart['coupons'];
        }

        $taxes = 0;
        if ($this->cart['taxes']) {
            foreach ($this->cart['taxes'] as $tax) {
                $priceInfos[] = array(
                    'title' => get_tax_title($tax),
                    'type' => 'taxes',
                    'price' => convert_price($tax['tax_subtotal']),
                    'currency' => CART_SECONDARY_CURRENCY,
                    'position' => $position++
                );
                //$total +=  $tax['tax_subtotal'];
                $taxes += $tax['tax_subtotal'];
            }
        }

        if (isset($this->cart['use_gift_certificates']) && $this->cart['use_gift_certificates']) {
            foreach ($this->cart['use_gift_certificates'] as $gift_code => $gift) {
                $priceInfos[] = array(
                    'title' => fn_get_lang_var('gift_certificate') . ' (' . $gift_code . ')',
                    'type' => 'gift_certificate',
                    'price' => convert_price($gift['cost']),
                    'currency' => CART_SECONDARY_CURRENCY,
                    'position' => $position++
                );
                $total -=  $gift['cost'];
            }
        }


        if(($total + $taxes) == $this->cart['total']){
            $total += $taxes;
        }

        if($total + $payment_surcharge > $this->cart['total'])
            $total += $payment_surcharge;

        $priceInfos[] = array(
            'title' => fn_get_lang_var('order_total'),
            'type' => 'total',
            'price' => convert_price($total),//$this->cart['total']),
            'currency' => CART_SECONDARY_CURRENCY,
            'position' => $position++
        );

        return $priceInfos;
    }

    public function getPaymentMethods() {
        if (isset($this->cart['payment_id']) && $this->cart['payment_id']) {
            fn_update_payment_surcharge($this->cart, $this->auth);
        }
        if (defined('GET_PAYMENTS_FROM_SQL') && GET_PAYMENTS_FROM_SQL == true) {
            $payments = db_get_array('SELECT pa.payment_id, pr.processor FROM ?:payments pa LEFT JOIN ?:payment_processors pr on pa.processor_id = pr.processor_id WHERE pa.`status` = \'A\'  AND pa.processor_id>0');
        } else {
            $payments = fn_get_payment_methods($this->auth);
        }
        $paymentMethods = array();
        foreach ($payments as $payment) {
            /*if (isset($payment['processor'])) {
                if ($payment['processor'] == 'PayPal Express Checkout') {
                    $paymentMethods[] = array(
                        'pm_id' => 'paypalwpp',
                        'pm_title' => $payment['processor'],
                        'pm_code' => 'paypalwpp',
                        'pm_description' => '',
                        'img_url' => ''
                    );
                } elseif ($payment['processor'] == 'PayPal') {
                    $paymentMethods[] = array(
                        'pm_id' => 'paypal',
                        'pm_title' => $payment['processor'],
                        'pm_code' => 'paypal',
                        'pm_description' => '',
                        'img_url' => ''
                    );
                }
            }*/
            if(!strpos($payment['template'], 'cc.tpl') && !strpos($payment['template'], 'cc_outside.tpl')){
                $paymentMethods[] = array(
                    'pm_id' => $payment['payment_id'],
                    'pm_title' => $payment['payment'],
                    'pm_description' => $payment['description'],
                    'pm_instructions' => $payment['instructions']
                );
            }

        }

        $result['methods'] = $paymentMethods;
        $result['selected_method'] = $this->cart['payment_id'];

        return $result;
    }

    public function selectOrderShippingMethod() {
        if (empty($this->cart['shipping'])) {
            if (function_exists('fn_apply_cart_shipping_rates')) {
                fn_apply_cart_shipping_rates($this->cart, $this->cart['products'], $this->auth, $_SESSION['shipping_rates']);
            } else { //apply for 4.0+
                $shipping_calculation_type = (Registry::get('settings.General.estimate_shipping_cost') == 'Y') ? 'A' : 'S';
                fn_calculate_cart_content($this->cart, $this->auth, $shipping_calculation_type, true, 'F');
            }
        }
        $id = array_keys($this->cart['shipping']);

        if (sizeof($id) > 0) {
            return $id[0];
        }
        return 1;
    }

    public function getOrderShippingMethods() {
        $this->cart['calculate_shipping'] = true;
        $this->cart['shipping_required'] = true;

        list (, $_SESSION['shipping_rates']) = fn_calculate_cart_content($this->cart, $this->auth, 'A', true, 'F', true);
        $shippingRates = $_SESSION['shipping_rates'];
        $shippingMethods = array();
        $choosed = '';

        if (PRODUCT_VERSION > '4.0') {
            foreach ($shippingRates as $product_group) {
                foreach ($product_group['shippings'] as $shippingId => $method) {
                    $shipping = array();
                    $shipping['sm_id'] = $shippingId;
                    $shipping['sm_code'] = '';
                    $shipping['title'] = $method['shipping'] . (empty($method['delivery_time']) ? ' ' : ' (' . $method['delivery_time'] . ') ');
                    $shipping['description'] = '';
                    $shipping['price'] = convert_price($method['rate']);
                    $shippingMethods[] = $shipping;
                }
                $choosed = $product_group['chosen_shippings'][0]['shipping_id'];
            }
        } else {
            foreach ($shippingRates as $shippingId => $method) {
                $price = 0.0;
                $shipping = array();
                $shipping['sm_id'] = $shippingId;
                $shipping['sm_code'] = '';
                $shipping['title'] = $method['name'] . (empty($method['delivery_time']) ? ' ' : ' (' . $method['delivery_time'] . ') ');
                foreach ($method['rates'] as $p) {
                    $price += $p;
                }
                $shipping['description'] = '';
                $shipping['price'] = convert_price($price);
                $shippingMethods[] = $shipping;
            }
        }

        if (sizeof($shippingMethods) < 1) {
            $shippingMethods[] = array(
                'sm_id' => 1,
                'title' => 'Free Shipping',
                'price' => 0,
                'currency' => CART_SECONDARY_CURRENCY,
                'description' => ''
            );
        }

        $result['shippings'] = $shippingMethods;
        $result['choosed_shipping'] = $choosed;
        if(strpos($this->cart['notes'],'[from mobile app]') !== false){
            $tmp = explode('[from mobile app] ', $this->cart['notes']);
            $result['notes'] = $tmp[1];

        }
        else{
            $result['notes'] = $this->cart['notes'];
        }

        return $result;
    }

    public function getOrderItems($currency = CART_SECONDARY_CURRENCY) {
        $shoppingCartService = ServiceFactory::factory('ShoppingCart');
        $items = $shoppingCartService->getCartItems($this->cart['products']);
        $orderItems = array();
        foreach ($items as $goods) {
            $orderItem = array();
            $orderItem['order_item_id'] = $goods['item_id'];
            $orderItem['item_title'] = $goods['product'];
            $orderItem['item_id'] = $goods['product_id'];
            $orderItem['item_display_id'] = fn_get_product_code($goods['product_id']);
            $orderItem['thumbnail_pic_url'] = get_product_image_url((int) $goods['product_id']);
            $sku = '';
            if (!empty($goods['product_options'])) {
                $product_options = $goods['product_options'];
                foreach ($product_options as $product_option) {
                    $sku .= '-' . $product_option['option_name'] . ': ' . $product_option['variant_name'] . '<br>';
                }
            }
            $orderItem['display_attributes'] = $sku;
            $orderItem['qty'] = $goods['amount'];
            $orderItem['virtual_flag'] = $goods['virtual_flag'];
            $orderItem['price'] = convert_price($goods['price'], $currency);
            $orderItem['final_price'] = convert_price((string)$goods['subtotal'], $currency);
            $orderItems[] = $orderItem;
        }
        return $items;
    }

    public function updateAddress($addressBookId, $address = array()) {
        $userService = ServiceFactory::factory('User');
        if ($address) {
            if ($addressBookId) {
                $address['profile_id'] = $addressBookId;
                $userService->updateAddress($address);
                $_SESSION['auth']['address_id'] = $addressBookId;
            }
        } else if ($addressBookId) { //chang address
            $_SESSION['auth']['address_id'] = $addressBookId;
        }
    }

    public function addAddress($address) {
        $userService = ServiceFactory::factory('User');
        $profileId = $userService->addAddress($address);
        $profileId && $_SESSION['auth']['address_id'] = $profileId;
        return $profileId;
    }

    public function updateShippingMethod($shippingMethod, $comments) {
        $this->cart['notes'] = "[from mobile app] ".$comments;
        fn_checkout_update_shipping($this->cart, array($shippingMethod));
    }

    public function updateCoupon($couponCode) {
        $result = fn_load_addon('gift_certificates');
        if (empty($couponCode)) { //delete all coupon
            $this->cart['coupons'] = array();
            unset($this->cart['pending_coupon']);
            $this->cart['recalculate'] = true;

            if (isset($this->cart['use_gift_certificates']) && is_array($this->cart['use_gift_certificates'])) {
                foreach ($this->cart['use_gift_certificates'] as $key => $value) {
                    fn_delete_gift_certificate_in_use($key, $this->cart);
                }
            }
            return true;
        }

        //apply certificate
        if ($result) {
            $gift_cert_code = $couponCode;
            if (true == fn_check_gift_certificate_code($gift_cert_code, true)) {
                if (!isset($this->cart['use_gift_certificates'][$gift_cert_code])) {
                    $this->cart['use_gift_certificates'][$gift_cert_code] = 'Y';
                    $this->messages [] = array('type' => 'sucess', 'content' => fn_get_lang_var('text_gift_cert_applied'));
                } else {
                    $this->messages [] = array('type' => 'error', 'content' => fn_get_lang_var('certificate_already_used'));
                }
            } else {
                $status = db_get_field("SELECT status FROM ?:gift_certificates WHERE gift_cert_code = ?s", $gift_cert_code);
                if (!empty($status) && !strstr('A', $status)) {
                    $this->messages [] = array('type' => 'error', 'content' => fn_get_lang_var('certificate_code_not_available'));
                } else {
                    $this->messages [] = array('type' => 'error', 'content' => fn_get_lang_var('certificate_code_not_valid'));
                }
            }
        }

        //apply discount coupon
        $this->cart['pending_coupon'] = $couponCode;
        $this->cart['recalculate'] = true;
    }

    public function placeOrder() {
        $returnUrl = $_REQUEST['return_url'];
        $cancelUrl = $_REQUEST['cancel_url'];
        $cart = &$_SESSION['cart'];
        //$cart['notes'] = "from mobile";
        $auth = &$_SESSION['auth'];
        $cart['user_data'] = fn_get_user_info($_SESSION['auth']['user_id'], true, $_SESSION['auth']['address_id']);
        $processor_id = db_get_row("SELECT processor_id  FROM ?:payment_processors WHERE processor = ?s", 'PayPal');
        $paymentMethodId = db_get_row("SELECT payment_id  FROM ?:payments WHERE processor_id = ?s", $processor_id['processor_id']);
        if (!empty($paymentMethodId)) {
            $cart['payment_id'] = $paymentMethodId['payment_id'];
        }
        if (isset($cart['payment_id'])) {
            $payment_method_data = fn_get_payment_method_data($cart['payment_id']);
            if (!empty($payment_method_data['status']) && $payment_method_data['status'] != 'A') {
                return false;
            }
        }

        // Remove previous failed order
        if (!empty($cart['failed_order_id']) || !empty($cart['processed_order_id'])) {
            $_order_ids = !empty($cart['failed_order_id']) ? $cart['failed_order_id'] : $cart['processed_order_id'];

            foreach ($_order_ids as $_order_id) {
                fn_delete_order($_order_id);
            }
            $cart['rewrite_order_id'] = $_order_ids;
            unset($cart['failed_order_id'], $cart['processed_order_id']);
        }

        // Clean up saved shipping rates
        unset($_SESSION['shipping_rates']);

        unset($cart['payment_info']['secure_card_number']);

        if (!empty($cart['products'])) {
            foreach ($cart['products'] as $k => $v) {
                $_is_edp = db_get_field("SELECT is_edp FROM ?:products WHERE product_id = ?i", $v['product_id']);
                if (fn_check_amount_in_stock($v['product_id'], $v['amount'], empty($v['product_options']) ? array() : $v['product_options'], $k, $_is_edp, 0, $cart) == false) {
                    unset($cart['products'][$k]);
                    return false;
                }

                $exceptions = fn_get_product_exceptions($v['product_id'], true);
                if (!isset($v['options_type']) || !isset($v['exceptions_type'])) {
                    $v = array_merge($v, db_get_row('SELECT options_type, exceptions_type FROM ?:products WHERE product_id = ?i', $v['product_id']));
                }

                if (!fn_is_allowed_options_exceptions($exceptions, $v['product_options'], $v['options_type'], $v['exceptions_type'])) {
                    fn_set_notification('E', fn_get_lang_var('notice'), str_replace('[product]', $v['product'], fn_get_lang_var('product_options_forbidden_combination')));
                    unset($cart['products'][$k]);
                    return false;
                }
            }
        }
        fn_set_session_data('last_order_time', TIME);


        list($order_id, $process_payment) = fn_place_order($cart, $auth);

        if (!empty($order_id) && $process_payment == true) {
            $payment_info = !empty($cart['payment_info']) ? $cart['payment_info'] : array();
            $order_info = fn_get_order_info($order_id);
            if (!empty($order_info['payment_info']) && !empty($payment_info)) {
                $order_info['payment_info'] = $payment_info;
            }
            list($is_processor_script, $processor_data) = fn_check_processor_script($order_info['payment_id'], '');
            $key = isset($processor_data['params']) ? 'params' : 'processor_params';
            if ($is_processor_script) {
                set_time_limit(300);
                $idata = array(
                    'order_id' => $order_id,
                    'type' => 'S',
                    'data' => TIME,
                );
                db_query("REPLACE INTO ?:order_data ?e", $idata);
                $paypal_account = $processor_data[$key]['account'];
                $current_location = Registry::get('config.current_location');
                if ($processor_data[$key]['mode'] == 'test') {
                    $paypalRedirectUrl = "https://www.sandbox.paypal.com/cgi-bin/webscr";
                } else {
                    $paypalRedirectUrl = "https://www.paypal.com/cgi-bin/webscr";
                }
                $paypal_currency = $processor_data[$key]['currency'];
                $paypal_item_name = $processor_data[$key]['item_name'];
                //Order Total
                $paypal_shipping = fn_order_shipping_cost($order_info);
                $paypal_total = fn_format_price($order_info['total'] - $paypal_shipping, $paypal_currency);
                $paypal_shipping = fn_format_price($paypal_shipping, $paypal_currency);
                $paypal_order_id = $processor_data[$key]['order_prefix'] . (($order_info['repaid']) ? ($order_id . '_' . $order_info['repaid']) : $order_id);

                $_phone = preg_replace('/[^\d]/', '', $order_info['phone']);
                $_ph_a = $_ph_b = $_ph_c = '';

                if ($order_info['b_country'] == 'US') {
                    $_phone = substr($_phone, -10);
                    $_ph_a = substr($_phone, 0, 3);
                    $_ph_b = substr($_phone, 3, 3);
                    $_ph_c = substr($_phone, 6, 4);
                } elseif ($order_info['b_country'] == 'GB') {
                    if ((strlen($_phone) == 11) && in_array(substr($_phone, 0, 2), array('01', '02', '07', '08'))) {
                        $_ph_a = '44';
                        $_ph_b = substr($_phone, 1);
                    } elseif (substr($_phone, 0, 2) == '44') {
                        $_ph_a = '44';
                        $_ph_b = substr($_phone, 2);
                    } else {
                        $_ph_a = '44';
                        $_ph_b = $_phone;
                    }
                } elseif ($order_info['b_country'] == 'AU') {
                    if ((strlen($_phone) == 10) && $_phone[0] == '0') {
                        $_ph_a = '61';
                        $_ph_b = substr($_phone, 1);
                    } elseif (substr($_phone, 0, 2) == '61') {
                        $_ph_a = '61';
                        $_ph_b = substr($_phone, 2);
                    } else {
                        $_ph_a = '61';
                        $_ph_b = $_phone;
                    }
                } else {
                    $_ph_a = substr($_phone, 0, 3);
                    $_ph_b = substr($_phone, 3);
                }

                if ($order_info['b_country'] == 'US') {
                    $_b_state = $order_info['b_state'];
                    // all other states
                } else {
                    $_b_state = fn_get_state_name($order_info['b_state'], $order_info['b_country']);
                }

                if (defined('NOTIFY_URL')) {
                    $notify_url = sprintf(NOTIFY_URL, $order_id);
                } else {
                    $index_script = fn_get_index_script(AREA);
                    $notify_url = "$current_location/$index_script?dispatch=payment_notification.notify&payment=paypal&order_id=$order_id";
                }

                $params = array(
                    'charset' => 'utf-8',
                    'cmd' => '_cart',
                    'custom' => $order_id,
                    'invoice' => $paypal_order_id,
                    'redirect_cmd' => '_xclick',
                    'rm' => 2,
                    'email' => $order_info['email'],
                    'first_name' => $order_info['b_firstname'],
                    'last_name' => $order_info['b_lastname'],
                    'address1' => $order_info['b_address'],
                    'address2' => $order_info['b_address_2'],
                    'country' => $order_info['b_country'],
                    'city' => $order_info['b_city'],
                    'state' => $_b_state,
                    'zip' => $order_info['b_zipcode'],
                    'day_phone_a' => $_ph_a,
                    'day_phone_b' => $_ph_b,
                    'day_phone_c' => $_ph_c,
                    'night_phone_a' => $_ph_a,
                    'night_phone_b' => $_ph_b,
                    'night_phone_c' => $_ph_c,
                    'business' => $paypal_account,
                    'item_name' => $paypal_item_name,
                    'amount' => $paypal_total,
                    'upload' => '1',
                    'handling_cart' => $paypal_shipping,
                    'currency_code' => $paypal_currency,
                    'return' => $returnUrl . "?order_id=$order_id",
                    'cancel_return' => $cancelUrl . "?order_id=$order_id",
                    'notify_url' => $notify_url,
                    'bn' => 'ST_ShoppingCart_Upload_US',
                );

                $i = 1;
                $key = isset($order_info['items']) ? 'items' : 'products';
                if (empty($order_info['use_gift_certificates'])
                        && !floatval($order_info['subtotal_discount'])
                        && empty($order_info['points_info']['in_use'])
                        && count($order_info[$key]) < MAX_PAYPAL_PRODUCTS) {

                    if (!empty($order_info[$key])) {
                        foreach ($order_info[$key] as $k => $v) {
                            $suffix = '_' . ($i++);
                            $v['product'] = htmlspecialchars(strip_tags($v['product']));
                            $v['price'] = fn_format_price(($v['subtotal'] - fn_external_discounts($v)) / $v['amount'], $paypal_currency);
                            $params["item_name{$suffix}"] = $v['product'];
                            $params["amount{$suffix}"] = $v['price'];
                            $params["quantity{$suffix}"] = $v['amount'];
                            if (!empty($v['product_options'])) {
                                foreach ($v['product_options'] as $_k => $_v) {
                                    $_v['option_name'] = htmlspecialchars(strip_tags($_v['option_name']));
                                    $_v['variant_name'] = htmlspecialchars(strip_tags($_v['variant_name']));
                                    $params["on{$_k}{$suffix}"] = $_v['option_name'];
                                    $params["os{$_k}{$suffix}"] = $_v['variant_name'];
                                }
                            }
                        }
                    }

                    if (!empty($order_info['taxes']) && Registry::get('settings.General.tax_calculation') == 'subtotal') {
                        foreach ($order_info['taxes'] as $tax_id => $tax) {
                            if ($tax['price_includes_tax'] == 'Y') {
                                continue;
                            }
                            $suffix = '_' . ($i++);
                            $item_name = htmlspecialchars(strip_tags($tax['description']));
                            $item_price = fn_format_price($tax['tax_subtotal'], $paypal_currency);
                            $params["item_name{$suffix}"] = $item_name;
                            $params["amount{$suffix}"] = $item_price;
                            $params["quantity{$suffix}"] = '1';
                        }
                    }

                    // Gift Certificates
                    if (!empty($order_info['gift_certificates'])) {
                        foreach ($order_info['gift_certificates'] as $k => $v) {
                            $suffix = '_' . ($i++);
                            $v['gift_cert_code'] = htmlspecialchars($v['gift_cert_code']);
                            $v['amount'] = (!empty($v['extra']['exclude_from_calculate'])) ? 0 : fn_format_price($v['amount'], $paypal_currency);
                            $params["item_name{$suffix}"] = $v['gift_cert_code'];
                            $params["amount{$suffix}"] = $v['amount'];
                            $params["quantity{$suffix}"] = '1';
                        }
                    }

                    // Payment surcharge
                    if (floatval($order_info['payment_surcharge'])) {
                        $suffix = '_' . ($i++);
                        $name = fn_get_lang_var('surcharge');
                        $payment_surcharge_amount = fn_format_price($order_info['payment_surcharge'], $paypal_currency);
                        $params["item_name{$suffix}"] = $name;
                        $params["amount{$suffix}"] = $payment_surcharge_amount;
                        $params["quantity{$suffix}"] = '1';
                    }
                } else {
                    $total_description = fn_get_lang_var('total_product_cost');
                    $params["item_name_1"] = $total_description;
                    $params["amount_1"] = $paypal_total;
                    $params["quantity_1"] = '1';
                }
                return array('paypal_redirect_url' => $paypalRedirectUrl, 'paypal_params' => $params);
            }
        }
        return false;
    }

}

?>
