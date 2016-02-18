<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class NewslettersService {
	public function getUserMailingList($auth){
		$m_list = array();
		$mailing_lists_all = fn_get_mailing_lists();
		$mailing_lists_all = current($mailing_lists_all);
        $mode = 'update';
        $_SESSION['auth'] = $auth;
        $core_post_controllers = fn_init_addon_controllers('profiles.post');
        $auth_file = current($core_post_controllers);
		$auth_file = current($auth_file);
        include $auth_file;
		foreach($mailing_lists_all as $m_list_tmp){
			$m_list_item['list_id'] = $m_list_tmp['list_id'];
			$m_list_item['show_on_checkout'] = $m_list_tmp['show_on_checkout']=='1'?true:false;
			$m_list_item['show_on_registration'] = $m_list_tmp['show_on_registration']=='1'?true:false;
			$m_list_item['name'] = $m_list_tmp['object'];
			$m_list_item['object_holder'] = $m_list_tmp['object_holder'];
			$m_list_item['is_user_subcribed'] = isset($mailing_lists[$m_list_tmp['list_id']])?true:false;
			$m_list[] = $m_list_item;
		}
		return $mailing_lists;
	}

}

?>