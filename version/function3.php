<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

function kc_order_placement_routines($order_id, $force_notification = array(), $clear_cart = true, $action = '') {
    $order_info = fn_get_order_info($order_id, true);
    $display_notification = true;

    fn_set_hook('placement_routines', $order_id, $order_info, $force_notification, $clear_cart, $action, $display_notification);

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
        if ($display_notification) {
            fn_set_notification('N', fn_get_lang_var('congratulations'), fn_get_lang_var('text_order_saved_successfully'));
        }
    } else {
        if ($order_info['status'] == STATUS_PARENT_ORDER) {
            $child_orders = db_get_hash_single_array("SELECT order_id, status FROM ?:orders WHERE parent_order_id = ?i", array('order_id', 'status'), $order_id);
            $status = reset($child_orders);
            $child_orders = array_keys($child_orders);
        } else {
            $status = $order_info['status'];
        }
        if (in_array($status, fn_get_order_paid_statuses())) {
            if ($action == 'repay') {
                fn_set_notification('N', fn_get_lang_var('congratulations'), fn_get_lang_var('text_order_repayed_successfully'));
            } else {
                fn_set_notification('N', fn_get_lang_var('order_placed'), fn_get_lang_var('text_order_placed_successfully'));
            }
        } elseif ($status == 'B') {
            fn_set_notification('W', fn_get_lang_var('important'), fn_get_lang_var('text_order_backordered'));
        } else {
            if (AREA == 'A' || $action == 'repay') {
                if ($status != 'I') {
                    $_payment_info = db_get_field("SELECT data FROM ?:order_data WHERE order_id = ?i AND type = 'P'", $order_id);
                    if (!empty($_payment_info)) {
                        $_payment_info = unserialize(fn_decrypt_text($_payment_info));
                        $_msg = !empty($_payment_info['reason_text']) ? $_payment_info['reason_text'] : '';
                        $_msg .= empty($_msg) ? fn_get_lang_var('text_order_placed_error') : '';
                        fn_set_notification('E', '', $_msg);
                    }
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
                fn_set_notification('W', fn_get_lang_var('important'), fn_get_lang_var('text_transaction_cancelled'));
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
        $_SESSION['shipping_rates'] = array();
        unset($_SESSION['shipping_hash']);

        db_query('DELETE FROM ?:user_session_products WHERE session_id = ?s AND type = ?s', Session::get_id(), 'C');
    }

    fn_set_hook('order_placement_routines', $order_id, $force_notification, $order_info, $_error);
    $_SESSION['auth']['skip_redirect_validation'] = true;
}

?>
