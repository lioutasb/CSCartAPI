<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class user_logout_action extends UserAuthorizedAction {

    public function execute() {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $mode = 'logout';
        $core_post_controllers = fn_init_core_controllers('auth');
        $auth_file = current($core_post_controllers);
        list($result, ) = include $auth_file;
        if ($result === CONTROLLER_STATUS_OK) {
            $this->setSuccess();
        } else {
            $this->setError('Ox6001');
        }
    }

}

?>
