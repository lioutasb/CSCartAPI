<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class store_currency_change_action extends BaseAction {
	public function execute() {
		$currency = is_null($this->getParam('currency')) ? '' : trim($this->getParam('currency'));
		if(!empty($currency)){
			unset($params);
			$params['currency'] = $currency;
			fn_init_currency($params, 'C');
			$this->setSuccess();
		}
		else{
			$this->setError('', 'Currency cannot be empty');
		}
	}
}

?>