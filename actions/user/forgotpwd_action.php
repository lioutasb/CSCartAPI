<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class user_forgotpwd_action extends BaseAction {

    public function execute() {

        $email = !empty($this->getParam('email')) ? trim($this->getParam('email')) : '';
        $result = fn_recover_password_generate_key($email);

        if ($result) {
			$this->setSuccess();
            
        } else {
            $this->setError(KancartResult::ERROR_USER_INVALID_USER_DATA, 'username_not_match_email');
        }
    }

}

?>
