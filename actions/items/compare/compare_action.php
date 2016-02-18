<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class items_compare_compare_action extends BaseAction {

    public function execute() {
        //if (is_addon_enabled('news_and_emails')) {
            //$newslettersService = ServiceFactory::factory('Newsletters');
            //$auth = &$_SESSION['auth'];
            $core_post_controllers = fn_init_core_controllers('product_features');
            $newsletters_file = current($core_post_controllers);
            //$newsletters_file = current($newsletters_file);
            list($result, ) = include $newsletters_file;
            $tmp = array();
            $tmp[0] = 219;
            $tmp[1] = 220;
            $tmp[1] = 329;
            $this->setSuccess(fn_get_product_data_for_compare($tmp, 'asd'));
            return;
        //}
       // $this->setError('', 'news_and_emails addon is not enabled.');
    }

}

?>