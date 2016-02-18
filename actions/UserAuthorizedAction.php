<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class UserAuthorizedAction extends BaseAction {

    public function validate() {
        if (!parent::validate()) {
            return false;
        }
        if (isset($_SESSION['auth']) && $_SESSION['auth']['user_id'] > 0) {
            return true;
        }
        $this->setError(KancartResult::ERROR_SYSTEM_INVALID_SESSION_KEY);
        return false;
    }

}

?>
