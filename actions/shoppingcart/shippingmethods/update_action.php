<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class shoppingcart_shippingmethods_update_action extends BaseAction {

    public function execute() {
        $shippingMethodId = $this->getParam('shipping_method_id');
        $comments = $this->getParam('comments');
        if ($shippingMethodId) {
            $checkoutService = ServiceFactory::factory('Checkout');
            $checkoutService->updateShippingMethod($shippingMethodId, $comments);
        }
        $this->setSuccess($checkoutService->detail());
    }

}

?>
