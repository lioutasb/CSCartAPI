<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class user_register_action extends BaseAction {

    public function execute() {

        $username = is_null($this->getParam('email')) ? '' : trim($this->getParam('email'));
        $password = is_null($this->getParam('pwd')) ? '' : trim($this->getParam('pwd'));
        //$password = CryptoUtil::Crypto($password, 'AES-256', KANCART_APP_SECRET, false);
        $email = is_null($this->getParam('email')) ? '' : trim($this->getParam('email'));
        $fax = is_null($this->getParam('fax')) ? '' : trim($this->getParam('fax'));
        $phone = is_null($this->getParam('mobile')) ? '' : trim($this->getParam('mobile'));
        $birthday = is_null($this->getParam('dob')) ? 0 : $this->getParam('dob');
        $lastname = is_null($this->getParam('lastname')) ? '' : $this->getParam('lastname');
        $firstname = is_null($this->getParam('firstname')) ? '' : $this->getParam('firstname');

        if (empty($username) || empty($password)) {
            //用户名或密码无效
            $this->setError(KancartResult::ERROR_USER_INVALID_USER_DATA);
            return;
        }
        $check_data = db_get_row("SELECT user_id FROM ?:users WHERE email = ?s", $username);
        if (!empty($check_data['user_id'])) {
            //用户名已存在
            $this->setError(KancartResult::ERROR_USER_NOT_LOGGED_IN);
            return;
        }
        $sql_data_array = array('password1' => $password,
            'password2' => $password,
            'email' => $username,
            'fax' => $fax,
            'phone' => $phone,
            'birthday' => $birthday,
            'lastname' => $lastname,
            'firstname' => $firstname
        );

        $_REQUEST['user_data'] = $sql_data_array;
        $_REQUEST['copy_address'] = false;
        $_REQUEST['notify_customer'] = false;
		$_SERVER['REQUEST_METHOD'] = 'POST';
        $mode = defined('KC_REGISTER_MOD') ? KC_REGISTER_MOD : 'update';
        $auth = &$_SESSION['auth'];
        Registry::set('settings.Image_verification.use_for_register', 'N', true);
        $core_post_controllers = fn_init_core_controllers('profiles', defined('KC_REGISTER_CONTROLLERS') ? KC_REGISTER_CONTROLLERS : GET_POST_CONTROLLERS);
        $register_file = current($core_post_controllers);
        list($result, ) = include $register_file;
        if ($result === CONTROLLER_STATUS_OK) {
            $this->setSuccess(array('user_id' => $auth['user_id'], 'user_name' => $_REQUEST['user_data']['firstname'].' '.$_REQUEST['user_data']['lastname'], 'email' => $_REQUEST['user_data']['email']));
        } else {
            $this->setError('Ox6000', 'Register failed.');
        }
    }

}

?>
