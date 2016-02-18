<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class user_login_action extends BaseAction {

    public function execute() {
        $user_login = is_null($this->getParam('uname')) ? '' : trim($this->getParam('uname'));
        $password = is_null($this->getParam('pwd')) ? '' : trim($this->getParam('pwd'));
        //$password = CryptoUtil::Crypto($password, 'AES-256', KANCART_APP_SECRET, false);

        if (empty($user_login) || empty($password)) {
            $this->setError('', 'UserName or password is empty.');
        } else {
            $_REQUEST['user_login'] = $user_login;
            $_POST['password'] = $password;
			$_REQUEST['remember_me'] = 'yes';
			$_SERVER['REQUEST_METHOD'] = 'POST';
            $mode = 'login';
            $auth = &$_SESSION['auth'];
            $core_post_controllers = fn_init_core_controllers('auth');
            $auth_file = current($core_post_controllers);
            Registry::set('settings.Image_verification.use_for_login', 'N', true);
            Registry::set('settings.General.use_email_as_login', 'Y', true);
            list($result, ) = include $auth_file;
            if ($result === CONTROLLER_STATUS_OK) {
                $_SESSION['kancart_session_key'] = md5($user_login . uniqid(mt_rand(), true));
                $this->setSuccess(array('sessionkey' => $_SESSION['kancart_session_key'], 'user_id' => $user_data['user_id'], 'user_name' => $user_data['firstname'].' '.$user_data['lastname'], 'email' => $user_data['email']));
            } else {
                $this->setError(KancartResult::ERROR_USER_INVALID_LOGIN_OR_PASSWORD);
            }
        }
    }

}

?>
