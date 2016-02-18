<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class shoppingcart_shippingmethods_get_action extends BaseAction {

    public function execute() {
        //$shippingMethodId = $this->getParam('shipping_method_id');
        //if ($shippingMethodId) {
            $checkoutService = ServiceFactory::factory('Checkout');
            //$checkoutService->updateShippingMethod($shippingMethodId);
        //}
        $this->setSuccess($checkoutService->getOrderShippingMethods());
    }

}

?>