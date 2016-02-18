<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class orders_get_action extends UserAuthorizedAction {

    public function execute() {
        $pageNo = $this->getParam('page_no');
        $pageSize = $this->getParam('page_size');
		$statuses = $this->getParam('statuses');
		$order_id = $this->getParam('order_id');
		$total_from = $this->getParam('total_from');
		$total_to = $this->getParam('total_to');
        $statuses_array = array();
        if (!empty($statuses)) {
            $statuses = json_decode(stripslashes(urldecode($statuses)));
            foreach ($statuses as $status) {
                $statuses_array[] = $status;
            }
        }
        $pageNo = max(intval($pageNo) , 1);
        $pageSize = isset($pageSize)? intval($pageSize) : 0;
		$params['status'] = $statuses_array;
		$params['order_id'] = isset($order_id)?$order_id:'';
		$params['total_from'] = isset($total_from)?$total_from:'';
		$params['total_to'] = isset($total_to)?$total_to:'';
		$params['page'] = $pageNo;
		$params['items_per_page'] = $pageSize;
		$params['user_id'] = $_SESSION['auth']['user_id'];
        $orderService = ServiceFactory::factory('Order');
        $result = $orderService->getOrderInfos($params, $_SESSION['auth']['user_id'], $pageNo, $pageSize);
        $this->setSuccess($result);
    }

}

?>
