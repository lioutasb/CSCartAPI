<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class banners_get_action extends BaseAction {
	public function execute() {
		if (is_addon_enabled('banners')) {
			$bannerService = ServiceFactory::factory('Banner');
			$this->setSuccess(array('banners' => $bannerService->getBanners()));
		}
		else{
			$this->setError('', 'Banners addon is not enabled.');
		}
	}
}

?>