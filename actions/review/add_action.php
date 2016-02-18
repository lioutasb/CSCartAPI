<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class review_add_action extends UserAuthorizedAction {

    public function validate() {
        if (parent::validate()) {
            if (!is_addon_enabled('discussion')) {
                $this->setError('', 'Discussion addon is not enabled.');
                return false;
            }
            return true;
        }
        return false;
    }

    public function execute() {
        $itemId = $this->getParam('item_id');
        $content = $this->getParam('content');
		$name = $this->getParam('uname');
        $score = floatval($this->getParam('rating'));
        $thread = db_get_row('SELECT * FROM ?:discussion where object_id = ?i and object_type =?s', $itemId, 'P');
        if ($thread) {
			if($name){
				$uname = $name;
			}
			else{
				$uname = fn_get_user_name($_SESSION['auth']['user_id']);
			}
            $postData = array(
                'thread_id' => $thread['thread_id'],
                'name' => $uname ? $uname : 'Guest',
                'rating_value' => $score,
                'message' => $content,
                'ip_address' => $_SESSION['auth']['ip'],
                'status' => 'D'
            );
            $reviewService = ServiceFactory::factory('Review');
            $status = $reviewService->addReview($postData);
			$avgRating = $reviewService->getAvgRatingScore($itemId);
            $this->setSuccess(array('status' => $status, 'average_rating' => $avgRating, 'total_reviews' => $reviewService->getReviewsCount($itemId)));
            return;
        }
        $this->setError('', 'Can not get thread info.');
    }

}

?>
