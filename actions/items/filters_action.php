<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class items_filters_action extends BaseAction {

    public function execute() {
        $cid = is_null($this->getParam('cid')) ? 0 : intval($this->getParam('cid'));
        $productService = ServiceFactory::factory('Product');
        $this->setSuccess(array('filters' => $productService->getProductsFiltersById($cid)));
    }

}

?>