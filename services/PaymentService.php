<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class PaymentService {

    public function placeOrder($clear_cart = TRUE) {
        $_SESSION['notifications'] = array(); //clear all messages.
        $cart = &$_SESSION['cart'];
        //$cart['notes'] = 'From mobile payment ' . $paymentMethod;
        $auth = &$_SESSION['auth'];
        $cart['user_data'] = fn_get_user_info($_SESSION['auth']['user_id'], true, $_SESSION['auth']['address_id']);
        /*$paymentMethodId = db_get_row("SELECT payment_id  FROM ?:payment_descriptions WHERE payment = ?s", 'Money Order');
        if (!empty($paymentMethodId)) {
            $cart['payment_id'] = $paymentMethodId['payment_id'];
        }*/

        // Clean up saved shipping rates
        unset($_SESSION['shipping_rates']);
        unset($cart['payment_info']['secure_card_number']);

        if (!empty($cart['products'])) {
            foreach ($cart['products'] as $k => $v) {
                $_is_edp = db_get_field("SELECT is_edp FROM ?:products WHERE product_id = ?i", $v['product_id']);
                if (fn_check_amount_in_stock($v['product_id'], $v['amount'], empty($v['product_options']) ? array() : $v['product_options'], $k, $_is_edp, 0, $cart) == false) {
                    unset($cart['products'][$k]);
                    $message = get_error_message($v['product'] . ' qty excess inventory.');
                    return array(false, null, $message);
                }

                $exceptions = fn_get_product_exceptions($v['product_id'], true);
                if (!isset($v['options_type']) || !isset($v['exceptions_type'])) {
                    $v = array_merge($v, db_get_row('SELECT options_type, exceptions_type FROM ?:products WHERE product_id = ?i', $v['product_id']));
                }

                if (!fn_is_allowed_options_exceptions($exceptions, $v['product_options'], $v['options_type'], $v['exceptions_type'])) {
                    fn_set_notification('E', fn_get_lang_var('notice'), str_replace('[product]', $v['product'], fn_get_lang_var('product_options_forbidden_combination')));
                    unset($cart['products'][$k]);
                    $message = get_error_message();
                    return array(false, null, $message);
                }
            }
        }
        fn_set_session_data('last_order_time', TIME);
        list($order_id, $process_payment) = fn_place_order($cart, $auth);

        if (!empty($order_id) && $process_payment == true) {
            kc_order_placement_routines($order_id, false);
            $order = fn_get_order_info($order_id);

            // Empty cart
            if ($clear_cart) {
                $_SESSION['cart'] = array(
                    'user_data' => !empty($_SESSION['cart']['user_data']) ? $_SESSION['cart']['user_data'] : array(),
                    'profile_id' => !empty($_SESSION['cart']['profile_id']) ? $_SESSION['cart']['profile_id'] : 0,
                    'user_id' => !empty($_SESSION['cart']['user_id']) ? $_SESSION['cart']['user_id'] : 0,
                );
                $_SESSION['shipping_rates'] = array();
                unset($_SESSION['shipping_hash']);

                db_query('DELETE FROM ?:user_session_products WHERE session_id = ?s AND type = ?s', Session::get_id(), 'C');
            }

            return array(true, $order, array());
        }

        $message = get_error_message('Place order failed.');
        return array(false, null, $message);
    }

    public function kancartPaymentDone($order_id, $custom_kc_comments, $payment_status) {
        $status = (strtolower($payment_status) == 'succeed') ? 'P' : 'O';
        $force_notification = array('C' => true, 'A' => false, 'S' => false);
        if ($custom_kc_comments) {
            $update_order = array('notes' => $custom_kc_comments);
            db_query('UPDATE ?:orders SET ?u WHERE order_id = ?i', $update_order, $order_id);
        }
        fn_change_order_status($order_id, $status, '', $force_notification);
        $order = fn_get_order_info($order_id);
        return array(TRUE, $order);
    }

}

?>
