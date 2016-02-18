<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class shoppingcart_add_action extends BaseAction {

    public function validate() {
        if (!parent::validate()) {
            return false;
        }
        $itemId = $this->getParam('item_id');
        $validateInfo = array();
        if (!isset($itemId)) {
            $validateInfo['err_msg'][] = 'Item id is not specified .';
        }
        if (empty($_SESSION['auth']['user_id']) && Registry::get('settings.General.allow_anonymous_shopping') != 'Y') {
            $validateInfo['err_msg'][] = 'please login first.';
        }
        if ($validateInfo) {
            $this->setError(KancartResult::CART_INPUT_PARAMETER_ERROR, $validateInfo);
            return false;
        }
        return true;
    }

    public function execute() {
        $product_id = $this->getParam('item_id');
        $amount = $this->getParam('qty');
        $attributes = $this->getParam('attributes');
        $product_options = array();
        if ($attributes) {
            $attributes = json_decode(stripslashes(urldecode($attributes)));
            foreach ($attributes as $key => $attribute) {
                $product_options[$key] = $attribute;
            }
        }
        $is_edp = 'N';
        $product_data = array();
        $product_data[$product_id] = array('product_id' => $product_id, 'amount' => $amount, 'product_options' => $product_options, 'is_edp' => $is_edp);
        $cartService = ServiceFactory::factory('ShoppingCart');
        $result = $cartService->add($product_data);
        if (!empty($result)) {
            $this->setSuccess($cartService->get());
        }else{
            $cartInfo = $cartService->get();                       
            $cartInfo['messages'][] = 'Error：this product cant not be added into shopping cart.';
            $this->setSuccess($cartInfo);
        }

    }

}

?>
