<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_shoppingcart_paypalwps_done_action extends UserAuthorizedAction {

    public function execute() {
        if (fn_check_payment_script('paypal.php', $_REQUEST['order_id'])) {

            $order_info = fn_get_order_info($_REQUEST['order_id'], true);
            if ($order_info['status'] == 'N') {
                fn_change_order_status($_REQUEST['order_id'], 'O', '', false);
            }
            kc_order_placement_routines($_REQUEST['order_id'], false);

            $tx = max($_REQUEST['tx'], $_REQUEST['txn_id']);
            $orderService = ServiceFactory::factory('Order');
            $info = $orderService->getPaymentOrderInfo($order_info, $tx);
            $this->setSuccess($info);
        } else {
            $this->setError('', 'Paypal Website Payment Standard is not enabled.', KancartResult::SHOPPING_CART);
        }
    }

}

?>
