<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class store_information_get_action extends BaseAction {

    public function execute() {
        //$parent_cid = is_null($this->getParam('parent_cid')) ? '' : trim($this->getParam('parent_cid'));
        //$all_cat = is_null($this->getParam('all_cat')) ? false : trim($this->getParam('all_cat'));

        //$p_cid = 0;//($all_cat == true) ? 0 : max($parent_cid, 0);
        //$ItemCat = array();

        //fn_get_categories($fields);
        /*if (intval($p_cid) >= 0) {
            $parent = array();
            $parent_cat = fn_get_categories_tree($p_cid, true);
            foreach ($parent_cat as $row) {
                $cid = $row['category_id'];
                $row['parent_id'] != 0 && $parent[$row['parent_id']] = true;
                list($products) = fn_get_products(array(
                    'cid' => $cid,
                    'status' => 'A'
                ));
				if($row['status'] == 'A'){
					$ItemCat[$cid] = array(
						'cid' => $cid,
						'parent_cid' => $row['parent_id'] == 0 ? '-1' : $row['parent_id'],
						'name' => $row['category'],
						'is_parent' => is_null($row['subcategories'])?false:true,
						'count' => is_null($row['subcategories'])?count($products):intval(count($row['subcategories'])),//$row['product_count']),
						'position' => $row['position'],
						'level' => $row['level']
					);
				}
            }
		}*/

        $freeShip = array();
        $freeShip['has_free_ship'] = false;
        list($proms) = fn_get_promotions(array('get_hidden' => false));
        foreach($proms as $prom){
            if($prom['zone'] == 'cart'){
                if((strpos($prom['conditions_hash'],'subtotal') !== false) && strpos($prom['bonuses'],'free_shipping') !== false
                    && ((strpos($prom['conditions'],'"gte"') !== false) || (strpos($prom['conditions'],'"gt"') !== false))){
                    $freeShip['has_free_ship'] = true;
                    $freeShip['promotion_id'] = $prom['promotion_id'];
                    $freeShip['zone'] = $prom['zone'];
                    $freeShip['conditions_hash'] = $prom['conditions_hash'];
                    $freeShip['name'] = $prom['name'];
                    $promdata = fn_get_promotion_data($prom['promotion_id']);
                    foreach($promdata['conditions']['conditions'] as $p){
                        if($p['condition'] == "subtotal"){
                            $freeShip['value'] = convert_price($p['value']);
                        }
                    }
                }
            }
        }


        $storeService = ServiceFactory::factory('Store');
        if (is_addon_enabled('banners')) {
            $bannerService = ServiceFactory::factory('Banner');
            $this->setSuccess(array('store_info' => $storeService->getStoreInfo(), 'free_shipping' => $freeShip, 'banners' => $bannerService->getBanners()));
        }
        else{
            $this->setSuccess(array('store_info' => $storeService->getStoreInfo(), 'free_shipping' => $freeShip));
        }

    }

    /*private function getProductTotal(&$cats, $pids) {
        if (!($count = sizeof($pids))) {//depth=1
            return;
        }

        $parents = array();
        $newPids = array();
        foreach ($cats as $key => &$cat) {
            if (isset($pids[$key])) {
                $cat['is_parent'] = true;
                $parents[$key] = &$cat;
                $newPids[$cat['parent_cid']] = true;
            } elseif ($cat['parent_cid'] != -1) {
                $cats[$cat['parent_cid']]['count'] += intval($cat['count']);
            }
        }
        $pcount = sizeof($newPids);

        while ($pcount > 1 && $count != $pcount) { //one parent or only children
            $count = $pcount;
            $pids = array();
            foreach ($parents as $key => &$parent) {
                if (!isset($newPids[$key])) {
                    if ($parent['parent_cid'] != -1) {
                        $parents[$parent['parent_cid']]['count'] += intval($parent['count']);
                    }
                    unset($parents[$key]);
                } else {
                    $pids[$parent['parent_cid']] = true;
                }
            }
            $pcount = sizeof($pids);
            $newPids = $pids;
        }
    }*/

}

?>
