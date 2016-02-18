<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

function fn_discussion_get_object_by_thread($thread_id) {
    static $cache = array();

    if (empty($cache[$thread_id])) {
        $cache[$thread_id] = db_get_row("SELECT object_type, object_id, type FROM ?:discussion WHERE thread_id = ?i", $thread_id);
    }

    return $cache[$thread_id];
}

class ReviewService {

    public function getReviewsCount($productId) {
        if ($productId) {
            return db_get_field("SELECT count(*) as total FROM ?:discussion d 
                                 JOIN ?:discussion_posts as p ON d.thread_id = p.thread_id 
                                 WHERE p.status = 'A' AND object_id = ?i 
                                 AND object_type = ?s", $productId, 'P');
        }
    }

    public function getAvgRatingScore($productId) {
        if ($productId) {
			$discussion = fn_get_discussion($productId, 'P');
            return fn_get_average_rating($discussion);
        }
    }

    public function getReviews($product, $pageNo = 1, $pageSize = 10) {
        $reviews = array();

        if (!Registry::is_exist('addons.discussion.status') || Registry::get('addons.discussion.status') == 'A') {
            $discussion = fn_get_discussion($product['product_id'], 'P');

            if (!empty($discussion['thread_id'])) {
                $limit = max($pageNo, 0) * $pageSize . ',' . $pageSize;
				unset($params);
				$params['page'] = $pageNo;
				$params['thread_id'] = $discussion['thread_id'];
				$params['avail_only'] = true;
                $rows = fn_get_discussion_posts($params, $pageSize);
            }
        }

        if ($rows) {
            foreach ($rows[0] as $row) {
                $format = Registry::get('settings.Appearance.date_format') . ', ' . Registry::get('settings.Appearance.time_format');
                $reviews[] = array(
                    'uname' => $row['name'],
                    'item_id' => $product['product_id'],
                    'rate_score' => $row['rating_value'],
                    'rate_content' => $row['message'],
                    'rate_date' => fn_date_format($row['timestamp'], $format)
                );
            }
        }

        return $reviews;
    }

    public function addReview($post_data) {

        if (!empty($post_data['thread_id'])) {
            $discussion_settings = Registry::get('addons.discussion');
            if (!function_exists('fn_get_discussion_objects')) {
                include_once '../addons/discussion/func.php';
            }
            $discussion_object_types = fn_get_discussion_objects();
            Registry::set('discussion_settings', $discussion_settings);
            $object = fn_discussion_get_object_by_thread($post_data['thread_id']);
            if (empty($object)) {
                fn_set_notification('E', fn_get_lang_var('error'), fn_get_lang_var('cant_find_thread'));
                return false;
            }
            $object_name = $discussion_object_types[$object['object_type']];
            $object_data = fn_get_discussion_object_data($object['object_id'], $object['object_type']);
            $ip = fn_get_ip();
            $post_data['ip_address'] = $ip['host'];
            $post_data['status'] = 'A';

            // Check if post is permitted from this IP address
            if (AREA != 'A' && !empty($discussion_settings[$object_name . '_post_ip_check']) && $discussion_settings[$object_name . '_post_ip_check'] == 'Y') {
                $is_exists = db_get_field("SELECT COUNT(*) FROM ?:discussion_posts WHERE thread_id = ?i AND ip_address = ?s", $post_data['thread_id'], $ip['host']);
                if (!empty($is_exists)) {
                    fn_set_notification('E', fn_get_lang_var('error'), fn_get_lang_var('error_already_posted'));
                    return false;
                }
            }

            $auth = $_SESSION['auth'];
            // Check if post needs to be approved
            if (AREA != 'A' && !empty($discussion_settings[$object_name . '_post_approval'])) {
                if ($discussion_settings[$object_name . '_post_approval'] == 'any' || ($discussion_settings[$object_name . '_post_approval'] == 'anonymous' && empty($auth['user_id']))) {
                    fn_set_notification('W', fn_get_lang_var('text_thank_you_for_post'), fn_get_lang_var('text_post_pended'));
                    $post_data['status'] = 'D';
                }
            }

            $_data = fn_check_table_fields($post_data, 'discussion_posts');
            $_data['timestamp'] = TIME;
            $_data['user_id'] = $auth['user_id'];
            $_data['name'] = $post_data['name'];
            $post_data['post_id'] = db_query("INSERT INTO ?:discussion_posts ?e", $_data);
            $_data = fn_check_table_fields($post_data, 'discussion_messages');
            db_query("REPLACE INTO ?:discussion_messages ?e", $_data);
            $_data = fn_check_table_fields($post_data, 'discussion_rating');
            db_query("REPLACE INTO ?:discussion_rating ?e", $_data);
			return $post_data['status'];
        }
		return 'false';
    }

}

?>
