<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class shoppingcart_remove_action extends BaseAction {

    public function validate() {
        if (!parent::validate()) {
            return false;
        }
        $cartItemId = $this->getParam('cart_item_id');
        if (!isset($cartItemId)) {
            $this->setError('', 'cart item id is not specified .');
            return false;
        }
        return true;
    }

    public function execute() {
        $cartItemId = $this->getParam('cart_item_id');
        $shoppingCartService = ServiceFactory::factory('ShoppingCart');
        $shoppingCartService->dropCartGoods($cartItemId);
        $this->setSuccess($shoppingCartService->get());
    }

}

?>
