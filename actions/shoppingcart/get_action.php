<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class shoppingcart_get_action extends BaseAction {

    public function execute() {
        $shoppingCart = ServiceFactory::factory('ShoppingCart');
        $this->setSuccess($shoppingCart->get());
    }

}

?>
