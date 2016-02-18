<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class shoppingcart_update_action extends BaseAction {

    public function validate() {
        if (!parent::validate()) {
            return false;
        }
        $cartItemId = $this->getParam('cart_item_id');
        $qty = $this->getParam('qty');
        $validateInfo = array();
        if (!isset($cartItemId)) {
            $validateInfo[] = 'Cart item id is not specified .';
        }
        if (!isset($qty) || !is_numeric($qty) || $qty < 0) {
            $validateInfo[] = 'Qty is not valid.';
        }
        if ($validateInfo) {
            $this->setError(KancartResult::CART_INPUT_PARAMETER_ERROR, $validateInfo);
            return false;
        }
        return true;
    }

    /**
     * Update shopping cart
     * 2012-09-04
     */
    public function execute() {
        $cartItemId = $this->getParam('cart_item_id');
        $qty = $this->getParam('qty');
        $product_options = array();
        $attributes = $this->getParam('attributes');
        $product_options = array();
        if ($attributes) {
            $attributes = json_decode(stripslashes(urldecode($attributes)));
            foreach ($attributes as $key => $attribute) {
                $product_options[$key] = $attribute;
            }
        }
        $shoppingCartService = ServiceFactory::factory('ShoppingCart');
        $updateInputParam = array('cart_item_id' => $cartItemId, 'product_options' => $product_options, 'qty' => $qty);
        $updateResult = $shoppingCartService->update($updateInputParam);
        if ($updateResult['result']) {
            $this->setSuccess($shoppingCartService->get());
            return;
        }
        $this->setError('', $updateResult['err_msg']);
    }

}

?>
