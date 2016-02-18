<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class reviews_get_action extends UserAuthorizedAction {

    public function validate() {
        if (parent::validate()) {
            $itemId = $this->getParam('item_id');
            if (!isset($itemId) || $itemId == '') {
                $this->setError(KancartResult::ERROR_USER_INPUT_PARAMETER);
                return false;
            }
        }
        return true;
    }

    public function execute() {
        $pageNo = intval($_REQUEST['page_no']);
        $pageSize = intval($_REQUEST['page_size']);
        $itemId = intval($_REQUEST['item_id']);
        if ($pageNo <= 0) {
            $pageNo = 0;
        }
        if ($pageSize <= 0) {
            $pageSize = 10;
        }
        $row = fn_get_product_data($itemId, $_SESSION['auth']);
        fn_gather_additional_product_data($row, true, true);
        if (is_addon_enabled('discussion')) {
            $reviewService = ServiceFactory::factory('Review');
            $reviews = $reviewService->getReviews($row, $pageNo, $pageSize);
            $reviewCounts = $reviewService->getReviewsCount($itemId);
			$avgRatingScore = $reviewService->getAvgRatingScore($itemId);
            $this->setSuccess(array(
                'trade_rates' => $reviews,
                'total_results' => $reviewCounts,
				'average_rating' => $avgRatingScore
            ));
            return;
        }
        $this->setError('', 'Discussion addon is not enabled.');
    }

}

?>
