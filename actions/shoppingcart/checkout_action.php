<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class shoppingcart_checkout_action extends UserAuthorizedAction {

    public function execute() {
       /* $payment = trim($_REQUEST['payment_method_id']);
        switch ($payment) {
            case 'paypalwpp':
                $this->paypalwpp();
                break;
            case 'paypal':
                $this->paypal();
                break;
            default:
                $this->payorder($payment);
                break;
        }*/

        if (!isset($_SESSION['cart']['products']) || sizeof($_SESSION['cart']['products']) < 1) {
            $this->setError('', 'Error: ShoppingCart is empty.');
        } else {
            $payment = ServiceFactory::factory('Payment');
            list($result, $order, $message) = $payment->placeOrder($method);
            if ($result === true) {
                $orderService = ServiceFactory::factory('Order');
                $info = $orderService->getPaymentOrderInfo($order);
                $this->setSuccess($info);
            } else {
                is_array($message) && $message = join('<br>', $message);
                $this->setError('', $message);
            }
        }
    }

    public function paypalwpp() { //paypal express checkout
        $expressCheckoutService = ServiceFactory::factory('PaypalExpressCheckout');
        $_SESSION['commit'] = true;
        $result = $expressCheckoutService->startExpressCheckout();
        if (is_array($result) && isset($result['paypal_redirect_url'])) {
            $this->setSuccess($result);
            return;
        }
        $this->setError('', join('<br>', $result));
    }

    public function paypal() { //Website Payments Standard     
        $checkoutService = ServiceFactory::factory('Checkout');
        $params = $checkoutService->placeOrder();
        if ($params === false) {
            $message = get_error_message();
            $this->setError('', join('<br/>', $message));
            return;
        }
        $this->setSuccess($params);
    }

    public function payorder($method) {
        //if (empty($method)) {
            //$this->setError('', 'Error: payment_method_id is empty.');
        if (!isset($_SESSION['cart']['products']) || sizeof($_SESSION['cart']['products']) < 1) {
            $this->setError('', 'Error: ShoppingCart is empty.');
        } else {
            $payment = ServiceFactory::factory('Payment');
            list($result, $order, $message) = $payment->placeOrder($method);
            if ($result === true) {
                $orderService = ServiceFactory::factory('Order');
                $info = $orderService->getPaymentOrderInfo($order);
                $this->setSuccess($info);
            } else {
                is_array($message) && $message = join('<br>', $message);
                $this->setError('', $message);
            }
        }
    }

}

?>