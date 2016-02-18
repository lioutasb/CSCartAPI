<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class ProductService {

    private function setDefaultFilterIfNeed(&$filter, &$page_size) {
        if (!$page_size) {
            $page_size = Registry::get('settings.Appearance.products_per_page');
        }
        if (!$filter['page_no']) {
            $filter['page_no'] = 1;
        }
        if (!$filter['sort_by']) {
            $filter['sort_by'] = Registry::get('settings.Appearance.default_products_sorting');
        }
        if (!$filter['sort_order']) {
            $filter['sort_order'] = 'desc';
        }
    }

    /**
     * Get the products,filter is specified by the $filter parameter
     * 
     * @param array $filter array
     * @return array
     * @author hujs
     */
    public function getProducts($filter, $page_size) {
        $this->setDefaultFilterIfNeed($filter, $page_size);
        $products = array('total_results' => 0, 'items' => array());
        if (isset($filter['item_ids'])) {
            // get by item ids
            $products = $this->getSpecifiedProducts($filter, $page_size);
        } else if ($filter['is_specials'] == true) {
            // get Special Products
            //$products = $this->getSpecialProducts($filter, $page_size, defined('INCLUDE_PROMOTION') && INCLUDE_PROMOTION);
        } else if ($filter['q'] != '') {
            // get by query
            $products = $this->getProductsByQuery($filter, $page_size);
        } else {
            // get by category
            $products = $this->getProductsByCategory($filter, $page_size);
        }
		
        return $products;
    }

    public function getProductsFiltersById($productID){
        $params['category_id'] = $productID;
        $params['get_all'] = true;
        $filters = current(fn_get_filters_products_count($params));
        $fs = array();
        foreach($filters as $filter){
            $f = array();
            $f['feature_id'] = $filter['feature_id'];
            $f['filter_id'] = $filter['filter_id'];
            $f['filter_name'] = $filter['filter'];
            $f['slider'] = empty($filter['slider'])?false:$filter['slider'];
            $f['condition_type'] = empty($filter['condition_type'])?'':$filter['condition_type'];
            $f['feature_type'] = empty($filter['feature_type'])?'':$filter['feature_type'];
            $f['field_type'] = empty($filter['field_type']) ? (in_array($filter['feature_type'], array('N', 'O', 'D')) ? 'R' : 'V') : $filter['field_type'];
            $f['ranges'] = array();
            foreach($filter['ranges'] as $range){
                $r = array();
                $r['feature_id'] = empty($range['feature_id'])?'':$range['feature_id'];
                $r['products'] = empty($range['products'])?'':$range['products'];
                $r['range_id'] = $range['range_id'];
                $r['range_name'] = $range['range_name'];
                $r['feature_type'] = empty($range['feature_type'])?'':$range['feature_type'];
                $r['filter_id'] = empty($range['filter_id'])?'':$range['filter_id'];
                $r['field_type'] = empty($range['field_type'])?'':$range['field_type'];
                $f['ranges'][] = $r;
            }

            $f['range_values'] = empty($filter['range_values'])?null:$filter['range_values'];
            $fs[] = $f;
        }
        return $fs;
    }

    /**
     * get product by name
     * @global type $languages_id
     * @param type $filter
     * @return int
     * @author hujs
     */
    public function getProductsByQuery($filter, $page_size) {
        if (is_null($filter['q'])) {
            return array('total_results' => 0, 'items' => array());
        }

        $params = array(
            'q' => $filter['q'],
            'page' => $filter['page'],
            'sort_by' => $filter['sort_by'],
            'sort_order' => $filter['sort_order'],
            'cid' => 0,
            'type' => 'extended',
            'match' => '',
            'status' => 'A',
            'objects' => array_keys(fn_search_get_customer_objects()),
			'search_performed' => 'Y',
			'pkeywords' => 'Y',
			'pname' => 'Y',
			'pfull' => 'Y',
			'pshort' => 'Y',
			'subcats' => 'Y'
        );

        $search = Registry::get('search_object');
        foreach ($search['conditions']['functions'] as $object => $function) {
            if ($search['default'] == $object) {
                continue;
            }

            if (!in_array($object, $params['objects'])) {
                unset($search['conditions']['functions'][$object]);
            }
        }
        $_params = fn_array_merge($params, $search['default_params']['products']);

        list($search_results, $param, $total) = fn_get_products($_params, $page_size);
        fn_gather_additional_products_data($search_results, array('get_icon' => true, 'get_detailed' => true, 'get_options' => true, 'get_discounts' => true, 'get_features' => false));
        $productTranslator = ServiceFactory::factory('ProductTranslator');
        $items = array();

        foreach ($search_results as $item) {
            $productTranslator->setProduct($item);
            $productTranslator->getItemBaseInfo();
            $productTranslator->getItemPrices();
            $productTranslator->getProductFeature();
            $items[] = $productTranslator->getTranslatedItem();
            $productTranslator->clear();
        }
        return array('items' => $items, 'total_results' => is_null($total) ? $param['total_items'] : $total);//, 'filters' => array());
    }

    public function getSpecifiedProducts($filter, $page_size) {
        $params = array(
            'page' => $filter['page'],
            'sort_by' => $filter['sort_by'],
            'sort_order' => $filter['sort_order'],
            'type' => 'extended', //apply for 2.1.2 no price
            'pid' => $filter['item_ids'],
            'extend' => array('category_ids', 'description')
        );

        list($products, $param, $total) = fn_get_products($params, $page_size);
        fn_gather_additional_products_data($products, array('get_icon' => true, 'get_detailed' => true, 'get_options' => true, 'get_discounts' => true, 'get_features' => false));
        $productTranslator = ServiceFactory::factory('ProductTranslator');
        $items = array();

        foreach ($products as $product) {
            $productTranslator->setProduct($product);
            $productTranslator->getItemBaseInfo();
            $productTranslator->getItemPrices();
            $productTranslator->getProductFeature();
            $items[] = $productTranslator->getTranslatedItem();
            $productTranslator->clear();
        }

        return array('items' => $items, 'total_results' => is_null($total) ? $param['total_items'] : $total);//, 'filters' => array());
    }

    public function getSpecialProducts($filter, $page_size, $include_promotion = false) {
        $sql = 'SELECT products.product_id, MIN(prices.price) AS price 
               FROM ?:products AS products 
               LEFT JOIN ?:product_prices AS prices ON prices.product_id = products.product_id AND prices.lower_limit = 1
               WHERE products.status IN (\'A\') AND products.list_price > price
               group by products.product_id';
        $result = db_get_array($sql);
        $ids = array();
        foreach ($result as $value) {
            $ids[] = $value['product_id'];
        }

        if ($include_promotion) {
            $cids = array();
            $condition = array(
                'active' => true,
                'expand' => true,
                'zone' => 'catalog');
            list($promotions) = fn_get_promotions($condition);
            if ($promotions) {
                foreach ($promotions as $promotion) {
                    $yes = false;
                    foreach ($promotion['bonuses'] as $bonus) {
                        if (fn_promotions_calculate_discount($bonus['discount_bonus'], 100, $bonus['discount_value']) > 0) {
                            $yes = true;
                            break;
                        }
                    }
                    if ($yes && strpos($promotion['conditions_hash'], 'categories') !== false) {
                        $idstr = substr($promotion['conditions_hash'], strpos($promotion['conditions_hash'], '=') + 1);
                        $cids = array_merge($cids, explode(',', $idstr));
                    }
                }
                if (sizeof($cids)) {
                    $result = db_get_array('SELECT pc.product_id FROM ?:products_categories AS pc LEFT JOIN ?:categories AS ca ON ca.category_id = pc.category_id WHERE ca.category_id in(?n) AND ca.status IN (\'A\')', $cids);
                    foreach ($result as $value) {
                        $ids[] = $value['product_id'];
                    }
                }
            }
        }

        $params = array(
            'page' => $filter['page'],
            'sort_by' => $filter['sort_by'],
            'sort_order' => $filter['sort_order'],
            'type' => 'extended', //apply for 2.1.2 no price
            'pid' => $ids,
            'extend' => array('category_ids', 'description')
        );

        list($products, $param, $total) = fn_get_products($params, $page_size);
        fn_gather_additional_products_data($products, array('get_icon' => true, 'get_detailed' => true, 'get_options' => true, 'get_discounts' => true, 'get_features' => false));
        $productTranslator = ServiceFactory::factory('ProductTranslator');
        $items = array();

        foreach ($products as $product) {
            $productTranslator->setProduct($product);
            $productTranslator->getItemBaseInfo();
            $productTranslator->getItemPrices();
            $productTranslator->getProductFeature();
            $items[] = $productTranslator->getTranslatedItem();
            $productTranslator->clear();
        }

        return array('items' => $items, 'total_results' => is_null($total) ? $param['total_items'] : $total);//, 'filters' => array());
    }

    /**
     * get products
     * 
     * @param array $filter
     * @return array
     */
    public function getProductsByCategory($filter, $page_size) {
        if ($filter['cid']) {
            $_statuses = array('A', 'H');
            $_condition = ' AND (' . fn_find_array_in_set($_SESSION['auth']['usergroup_ids'], 'usergroup_ids', true) . ')';
            $_condition .= fn_get_localizations_condition('localization', true);

            if ($_SESSION['auth']['area'] != 'A') {
                $_condition .= db_quote(' AND status IN (?a)', $_statuses);
            }

            $is_avail = db_get_field("SELECT category_id FROM ?:categories WHERE category_id = ?i ?p", $filter['cid'], $_condition);
            if (empty($is_avail)) {
                return array('items' => array(), 'total_results' => 0);
            } else {
                // Save current category id to session
                $_SESSION['current_category_id'] = $_REQUEST['category_id'];
            }
        }

        $params = array(
            'page' => $filter['page'],
            'sort_by' => $filter['sort_by'],
            'sort_order' => $filter['sort_order'],
            'cid' => (int) $filter['cid'],
			'features_hash' => $filter['features_hash'],
			'status' => 'A',
            'type' => 'extended', //apply for 2.1.2 no price
            'subcats' => Registry::get('settings.General.show_products_from_subcategories') || $filter['cid'] < 1,
            'extend' => array('category_ids', 'description')
        );

        list($products, $param, $total) = fn_get_products($params, $page_size);
        fn_gather_additional_products_data($products, array('get_icon' => true, 'get_detailed' => true, 'get_additional' => true, 'get_extra' => true, 'get_options' => true, 'get_discounts' => true, 'get_features' => true));
        $productTranslator = ServiceFactory::factory('ProductTranslator');
        $items = array();

        foreach ($products as $product) {
            $productTranslator->setProduct($product);
            $productTranslator->getItemBaseInfo();
            $productTranslator->getItemPrices();
            $items[] = $productTranslator->getTranslatedItem();
            $productTranslator->clear();
        }

        $returnResult = array('items' => $items, 'total_results' => is_null($total) ? $param['total_items'] : $total);//, 'filters' => $this->getProductsFiltersById($filter['cid']));
        return $returnResult;
    }

    /**
     * get product by id
     * @param integer $product_id
     * @return array
     */
    public function getProduct($product_id) {
        $row = fn_get_product_data($product_id, $_SESSION['auth'], CART_LANGUAGE, '', true, true, true, true);
        fn_gather_additional_product_data($row, true, true, true, true, true);
        if ($row != false) {
            $productTranslator = ServiceFactory::factory('ProductTranslator');
            $productTranslator->setProduct($row);
            return $productTranslator->getFullItemInfo();
        } else {
            return false;
        }
    }

}

?>