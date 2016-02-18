<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class shoppingcart_checkout_detail_action extends UserAuthorizedAction {

    /*public function validate() {  //EC also can checkout
        if ($this->getParam('payment_method_id') == 'paypalwpp') {
            $actionInstance = ActionFactory::factory('KanCart.ShoppingCart.PayPalEC.Detail');
            $actionInstance->execute();
            return true;
        } else {
            return parent::validate();
        }
    }*/

    public function execute() {
        if (!isset($_SESSION['cart']['products']) || sizeof($_SESSION['cart']['products']) < 1) {
            $this->setSuccess(array(
                'redirect_to_page' => 'shopping_cart',
                'messages' => array('Shopping Cart is empty.')
            ));
        } else {
            $this->setSuccess(ServiceFactory::factory('Checkout')->detail());
        }
    }

}

?>
