<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_shoppingcart_paypalec_start_action extends BaseAction {

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
        $result = $expressCheckoutService->startExpressCheckout();
        if (is_array($result) && isset($result['paypal_redirect_url'])) {
            $this->setSuccess($result);
            return;
        }
        $this->setError('', join('<br>', $result));
    }

}

?>
