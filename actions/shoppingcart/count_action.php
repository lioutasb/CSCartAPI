<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class shoppingcart_count_action extends UserAuthorizedAction {

    public function execute() {
        $shoppingCart = ServiceFactory::factory('ShoppingCart');
        $this->setSuccess($shoppingCart->getItemsCount());
    }

}

?>