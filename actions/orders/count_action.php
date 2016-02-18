<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class orders_count_action extends UserAuthorizedAction {

    public function execute() {
        $userId = $user_id = $_SESSION['auth']['user_id'];
        $result = array();
        $ordersCount = db_get_row("SELECT COUNT(*) as total FROM ?:orders WHERE user_id = ?i and status != ?s", $userId, STATUS_INCOMPLETED_ORDER);
        $result[] = array(
            'status_name' => 'My Orders',
            'count' => $ordersCount['total']
        );
        $this->setSuccess(array('order_counts' => $result));
    }

}

?>
