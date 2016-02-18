<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class orders_statuses_get_simple_action extends BaseAction {

    public function execute() {
		$statuses = fn_get_simple_statuses(STATUSES_ORDER);
		$results = array();
		foreach($statuses as $key => $status){
			$res['status'] = $key;
			$res['name'] = $status;
			$results[] = $res;
		}
        $this->setSuccess(array('statuses' => $results));
    }

}

?>