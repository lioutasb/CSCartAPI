<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class items_get_action extends BaseAction {

    public function execute() {

        $item_ids = is_null($this->getParam('item_ids')) ? '' : $this->getParam('item_ids');
        $filter = array();
        $filter['cid'] = is_null($this->getParam('cid')) ? 0 : intval($this->getParam('cid'));
        $filter['cid'] = ($filter['cid'] == -1) ? 0 : $filter['cid'];
		$filter['features_hash'] = is_null($this->getParam('features_hash')) ? '' : $this->getParam('features_hash');
        //$filter['is_specials'] = is_null($this->getParam('is_specials')) ? false : $this->getParam('is_specials') == 'true';
        $filter['q'] = is_null($this->getParam('q')) ? '' : trim($this->getParam('q'));
        $order_option = explode(":", $_POST['order_by']);
        $filter['sort_by'] = is_null($this->getParam('sort_by')) ? Registry::get('settings.Appearance.default_products_sorting') : $this->getParam('sort_by');
        $filter['sort_order'] = is_null($this->getParam('sort_order')) ? 'desc' : $this->getParam('sort_order');

        $filter['page'] = is_null($this->getParam('page_no')) && is_numeric($this->getParam('page_no')) ? 1 : max(intval($this->getParam('page_no')), 1);
        $page_size = is_null($this->getParam('page_size')) ? 10 : min(intval($this->getParam('page_size')), 200);

        if (!empty($item_ids)) {
            $filter['item_ids'] = explode(',', $item_ids);
        }
        $productService = ServiceFactory::factory('Product');
        $this->setSuccess($productService->getProducts($filter, $page_size));
    }

}

?>
