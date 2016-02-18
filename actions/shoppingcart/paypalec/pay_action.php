<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_shoppingcart_paypalec_pay_action extends BaseAction {

    public function validate() {
        if (parent::validate()) {
            $expressCheckoutService = ServiceFactory::factory('PaypalExpressCheckout');
            if (!$expressCheckoutService->getPaypalExpressChekcoutPaymentId()) {
                $this->setError(KancartResult::ERROR_SYSTEM_SERVICE_UNAVAILABLE, 'Paypal Express Checkout Standard is not enabled.');
                return false;
            }
            return true;
        }
    }

    public function execute() {
        $expressCheckoutService = ServiceFactory::factory('PaypalExpressCheckout');
        isset($_SESSION['pp_express_details']) || $expressCheckoutService->returnFromPaypal();
        $result = $expressCheckoutService->pay();
        if ($result['result'] === false) {
            $this->setError('', $result['error_msg']);
            return;
        }
        $order = $result['order'];
        $paypalResponse = $result['paypal_response'];

        /*         * * complete ** */
        $auth = $_SESSION['auth'];
        if (empty($auth['user_id'])) {  //if user not login save order id
            if (empty($auth['order_ids'])) {
                $auth['order_ids'][] = $order['order_id'];
                $allowed_id = true;
            } else {
                $allowed_id = in_array($order['order_id'], $auth['order_ids']);
            }
        } else {
            $allowed_id = db_get_field("SELECT user_id FROM ?:orders WHERE user_id = ?i AND order_id = ?i", $auth['user_id'], $order['order_id']);
        }

        fn_set_hook('is_order_allowed', $order['order_id'], $allowed_id);
        $orderService = ServiceFactory::factory('Order');
        $info = $orderService->getPaymentOrderInfo($order, $paypalResponse['transaction_id']);
        $this->setSuccess($info);
    }

}

?>
