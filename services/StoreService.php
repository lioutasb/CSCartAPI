<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class StoreService {

    public function getStoreInfo() {
        $storeInfo = array();
        //$storeInfo['general'] = $this->getGeneralInfo();
        $storeInfo['currencies'] = $this->getCurrencies();
        $storeInfo['countries'] = $this->getCountries();
        $storeInfo['languages'] = $this->getLanguages();
        $storeInfo['zones'] = $this->getZones();
        //$storeInfo['order_statuses'] = $this->getOrderStatuses();
        //$storeInfo['register_fields'] = $this->getRegisterFields();
        //$storeInfo['address_fields'] = $this->getAddressFields();
        $storeInfo['category_sort_options'] = $this->getCategorySortOptions();
        //$storeInfo['search_sort_options'] = $this->getSearchSortOptions();
        return $storeInfo;
    }

    public function getGeneralInfo() {
        return array(
            'cart_type' => 'cscart',
            'cart_version' => PRODUCT_VERSION,
            'plugin_version' => KANCART_PLUGIN_VERSION,
            'support_kancart_payment' => true,
            'login_by_mail' => true
        );
    }

    /**
     * get store currencies
     * @return type
     */
    public function getCurrencies() {
        $allActiveCurrencies = array();
        $currencies = Registry::get('currencies');
        if ($currencies) {
            foreach ($currencies as $currencyCode => $currency) {
                $allActiveCurrencies[] = array(
                    "currency_code" => $currencyCode,
                    "default" => ($currency['is_primary'] == 'Y') ? "true" : "false",
                    "currency_symbol" => $currency['symbol'],
                    "currency_symbol_right" => $currency['after'] == 'Y',
                    "decimal_symbol" => $currency['decimals_separator'],
                    "group_symbol" => $currency['thousands_separator'],
                    "decimal_places" => $currency['decimals'],
                    "description" => $currency['description']
                );
            }
        }
        return $allActiveCurrencies;
    }

    /**
     * get store languages
     */
    public function getLanguages() {

        $languages = Registry::get('languages');
        $avail_languages = array();
        $i = 0;
        foreach ($languages as $v) {
            if ($v['status'] == 'D') {
                continue;
            }
            $avail_languages[] = array(
                'language_id' => $v['lang_code'],
                'language_code' => strtolower($v['lang_code']),
                'language_name' => $v['name'],
                'position' => $i++
            );
        }

        return $avail_languages;
    }

    /**
     * get store countries 
     */
    public function getCountries() {
        //countries
        if (PRODUCT_VERSION > '4.0') {
            list($countries_info, $params) = fn_get_countries(array('only_avail' => true), false);
        } else {
            $countries_info = fn_get_countries(CART_LANGUAGE, true);
        }
        $results = array();
        if ($countries_info) {
            foreach ($countries_info as $country) {
                $eachCountry = array();
                $eachCountry['country_id'] = $country['code'];
                $eachCountry['country_name'] = $country['country'];
                $eachCountry['country_iso_code_2'] = $country['code'];
                $eachCountry['country_iso_code_3'] = $country['code_A3'];
                $results[] = $eachCountry;
            }
        }
        return $results;
    }

    public function getZones() {
        $zones = array();
        $allStates = $this->getAllZones();
        foreach ($allStates as $state) {
            $zone = array();
            $zone['zone_id'] = $state['state_id'];
            $zone['country_id'] = $state['country_code'];
            $zone['zone_name'] = $state['state'];
            $zone['zone_code'] = $state['code'];
            $zones[] = $zone;
        }
        return $zones;
    }

    private function getAllZones() {
        $avail_cond = " WHERE a.status = 'A' ";
        return db_get_array("SELECT a.state_id,a.country_code,a.code, b.state, c.country FROM ?:states as a LEFT JOIN ?:state_descriptions as b ON b.state_id = a.state_id AND b.lang_code = ?s LEFT JOIN ?:country_descriptions as c ON c.code = a.country_code AND c.lang_code = ?s $avail_cond ORDER BY a.country_code, b.state", CART_LANGUAGE, CART_LANGUAGE);
    }

    public function getOrderStatuses() {
        //order_statuses
        $order_statuses = fn_get_statuses();
        $statuses = array();
        foreach ($order_statuses as $s) {
            $status = array();
            $status['status_id'] = $s['description'];
            $status['status_name'] = $s['status'];
            $status['display_text'] = $s['description'];
            $statuses[] = $status;
        }
        return $statuses;
    }

    public function getRegisterFields() {
        $registeFields = array();
        $registeFields[] = array('type' => 'email', 'required' => true);
        $registeFields[] = array('type' => 'pwd', 'required' => true);
        return $registeFields;
    }

    public function getAddressFields() {
        //address_fields
        $addressFields = array();
        $addressFields[] = array('type' => 'firstname', 'required' => true);
        $addressFields[] = array('type' => 'lastname', 'required' => true);
        $addressFields[] = array('type' => 'country', 'required' => true);
        $addressFields[] = array('type' => 'city', 'required' => true);
        $addressFields[] = array('type' => 'zone', 'required' => true);
        $addressFields[] = array('type' => 'address1', 'required' => true);
        $addressFields[] = array('type' => 'address2', 'required' => false);
        $addressFields[] = array('type' => 'postcode', 'required' => true);
        $addressFields[] = array('type' => 'telephone', 'required' => true);
        return $addressFields;
    }

    public function getCategorySortOptions() {
        if (Registry::is_exist('settings.Appearance.available_product_list_sortings')) {
            return $this->getProductSortOptions();
        }
        $categorySortOptions = array();
        $sortOptions = fn_get_products_sorting(false);
        reset($sortOptions);
        list($key, $value) = each($sortOptions);
        $categorySortOptions[] = array(
                'title' => $value['description'],
                'code' => $key . ':' . $value['default_order'],
                'arrow_type' => '');

        while (list($key, $value) = each($sortOptions)) {
            $categorySortOptions[] = array(
                    'title' => $value['description'],
                    'code' => $key . ':asc',
                    'arrow_type' => 'asc'
                );
            $categorySortOptions[] = array(
                    'title' => $value['description'],
                    'code' => $key . ':desc',
                    'arrow_type' => 'desc'
                    );
        }

        return $categorySortOptions;
    }

    public function getProductSortOptions() {
        $sortings = fn_get_products_sorting(false);
        $result = array('desc', 'asc');
        fn_set_hook('get_products_sorting_orders', $result);
        $sorting_orders = $result;//fn_get_products_sorting_orders();
        $avail_sorting = Registry::get('settings.Appearance.available_product_list_sortings');

        $v4 = function_exists('__');
        foreach ($sortings as $option => $value) {
            foreach ($sorting_orders as $sort_order) {
                if (!$avail_sorting or !empty($avail_sorting[$option . '-' . $sort_order]) and $avail_sorting[$option . '-' . $sort_order] == 'Y') {
                    $label = 'sort_by_' . $option . '_' . $sort_order;
                    $categorySortOptions[] = array(
                            'title' => $v4 ? __($label) : fn_get_lang_var($label),
                            'code' => $option . ':' . $sort_order,
                            'arrow_type' => '');
                }
            }
        }

        return $categorySortOptions;
    }

    public function getSearchSortOptions() {
        return $this->getCategorySortOptions();
    }

}

?>
