<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class item_get_action extends BaseAction {

    public function execute() {
        $product_id = is_null($this->getParam('item_id')) ? '' : intval($this->getParam('item_id'));
        if (empty($product_id)) {
            $this->setError(KancartResult::ERROR_ITEM_INPUT_PARAMETER);
            return;
        }
        $productService = ServiceFactory::factory('Product');
        $ret = $productService->getProduct($product_id);
        if ($ret) {
            $this->setSuccess(array('item' => $ret));
        } else {
            $this->setError(KancartResult::ERROR_ITEM_INPUT_PARAMETER);
        }
    }

}

?>
