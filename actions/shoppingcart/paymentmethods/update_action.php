<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class shoppingcart_paymentmethods_update_action extends BaseAction {

    public function execute() {
        $shippingMethodId = $this->getParam('payment_method_id');
        if ($shippingMethodId) {
            $checkoutService = ServiceFactory::factory('Checkout');
            //$checkoutService->cart['payment_id'] = $$shippingMethodId;
           // $this->setSuccess($checkoutService->detail($shippingMethodId));
        }
        $this->setSuccess($checkoutService->detail($shippingMethodId));
    }

}

?>