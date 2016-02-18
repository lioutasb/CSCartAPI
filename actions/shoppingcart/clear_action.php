<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class shoppingcart_clear_action extends BaseAction {

    public function execute() {
        $shoppingCartService = ServiceFactory::factory('ShoppingCart');
        $shoppingCartService->clearCart();
        $this->setSuccess($shoppingCartService->get());
    }

}

?>