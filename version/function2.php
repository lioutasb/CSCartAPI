<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

define('KC_REGISTER_MOD', 'add');
define('GET_PAYMENTS_FROM_SQL', true);

function kc_order_placement_routines($order_id, $force_notification = array(), $clear_cart = true, $action = '') {
    $order_info = fn_get_order_info($order_id, true);

    if (!empty($_SESSION['cart']['placement_action'])) {
        if (empty($action)) {
            $action = $_SESSION['cart']['placement_action'];
        }
        unset($_SESSION['cart']['placement_action']);
    }

    if (AREA == 'C' && !empty($order_info['user_id'])) {
        $__fake = '';
        fn_save_cart_content($__fake, $order_info['user_id']);
    }

    $edp_data = fn_generate_ekeys_for_edp(array(), $order_info);
    fn_order_notification($order_info, $edp_data, $force_notification);

    $_error = false;

    if ($action == 'save') {
        fn_set_notification('N', fn_get_lang_var('congratulations'), fn_get_lang_var('text_order_saved_successfully'));
    } else {
        if ($order_info['status'] == STATUS_PARENT_ORDER) {
            $child_orders = db_get_hash_single_array("SELECT order_id, status FROM ?:orders WHERE parent_order_id = ?i", array('order_id', 'status'), $order_id);
            $status = reset($child_orders);
            $child_orders = array_keys($child_orders);
        } else {
            $status = $order_info['status'];
        }
        if (substr_count('OP', $status) > 0) {
            if ($action == 'repay') {
                fn_set_notification('N', fn_get_lang_var('congratulations'), fn_get_lang_var('text_order_repayed_successfully'));
            } else {
                fn_set_notification('N', fn_get_lang_var('order_placed'), fn_get_lang_var('text_order_placed_successfully'));
            }
        } elseif ($status == 'B') {
            fn_set_notification('N', fn_get_lang_var('order_placed'), fn_get_lang_var('text_order_backordered'));
        } else {
            if (AREA == 'A' || $action == 'repay') {
                if ($status != 'I') {
                    fn_set_notification('E', fn_get_lang_var('order_placed'), fn_get_lang_var('text_order_placed_error'));
                }
            } else {
                $_error = true;
                if (!empty($child_orders)) {
                    array_unshift($child_orders, $order_id);
                } else {
                    $child_orders = array();
                    $child_orders[] = $order_id;
                }
                $_SESSION['cart'][($status == 'N' ? 'processed_order_id' : 'failed_order_id')] = $child_orders;
            }
            if ($status == 'N' || ($action == 'repay' && $status == 'I')) {
                fn_set_notification('N', fn_get_lang_var('cancelled'), fn_get_lang_var('text_transaction_cancelled'));
            }
        }
    }

    // Empty cart
    if ($clear_cart == true && $_error == false) {
        $_SESSION['cart'] = array(
            'user_data' => !empty($_SESSION['cart']['user_data']) ? $_SESSION['cart']['user_data'] : array(),
            'profile_id' => !empty($_SESSION['cart']['profile_id']) ? $_SESSION['cart']['profile_id'] : 0,
            'user_id' => !empty($_SESSION['cart']['user_id']) ? $_SESSION['cart']['user_id'] : 0,
        );

        db_query('DELETE FROM ?:user_session_products WHERE session_id = ?s AND type = ?s', Session::get_id(), 'C');
    }

    fn_set_hook('order_placement_routines', $order_id, $force_notification, $order_info);
}

if (!function_exists('fn_update_payment_surcharge')) {

    function fn_update_payment_surcharge(&$cart) {
        $cart['payment_surcharge'] = 0;
        if (!empty($cart['payment_id'])) {
            $_data = db_get_row("SELECT a_surcharge, p_surcharge FROM ?:payments WHERE payment_id = ?i", $cart['payment_id']);
            if (floatval($_data['a_surcharge'])) {
                $cart['payment_surcharge'] += $_data['a_surcharge'];
            }
            if (floatval($_data['p_surcharge'])) {
                $cart['payment_surcharge'] += fn_format_price($cart['total'] * $_data['p_surcharge'] / 100);
            }
        }
    }

}

if (!function_exists('fn_gather_additional_products_data')) {

    function fn_gather_additional_products_data(&$products, $params) {
        extract($params);
        foreach ($products as &$product) {
            fn_gather_additional_product_data($product, $get_icon, $get_detailed, $get_options, $get_discounts, $get_features);
        }
    }

}
?>
