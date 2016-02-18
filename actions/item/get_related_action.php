<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}


class item_get_related_action extends BaseAction {

    public function execute() {
        $params = array();
        $x = 10;
        $lang = 'el';
        $params = array(
            'page' => 1,
            'product_id' => 3221,
            'sort_by' => 'popularity',
            'sort_order' => 'desc',
            'cid' => 574,
            'features_hash' => '',
            'status' => 'A',
            'type' => 'extended', //apply for 2.1.2 no price
            'subcats' => Registry::get('settings.General.show_products_from_subcategories') || $filter['cid'] < 1,
            'extend' => array('category_ids', 'description')
        );

        //fn_plus_prodsfromcategory_get_products_pre($params,$x, $lang);
        list($products, $params) = fn_get_products($params, 9);

        $this->setSuccess(array('item' => $products));
    }

}

?>