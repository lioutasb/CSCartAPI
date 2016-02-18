<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class dealday_get_action extends BaseAction {
    public function execute() {
        if (is_addon_enabled('plus_deal_of_day')) {
            $deal = fn_get_deal_of_day_data_to_display();
            //fn_print_die($deal['product_id']);
            $productTranslator = ServiceFactory::factory('ProductTranslator');
            $productTranslator->setProduct($deal);
            $productTranslator->getItemBaseInfo();
            $productTranslator->getItemPrices();
            $item = $productTranslator->getTranslatedItem();
            $this->setSuccess($item);
        }
        else{
            $this->setError('', 'Banners addon is not enabled.');
        }
    }
}

?>