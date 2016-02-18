<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_shoppingcart_paypalec_detail_action extends BaseAction {

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
        $paypalCheckoutService = ServiceFactory::factory('PaypalExpressCheckout');
        $paypalCheckoutService->returnFromPaypal();
        $_SESSION['cart']['payment_id'] = $paypalCheckoutService->getPaypalExpressChekcoutPaymentId();
        $checkoutService = ServiceFactory::factory('Checkout');
        $this->setSuccess($checkoutService->detail());
    }

}

?>
