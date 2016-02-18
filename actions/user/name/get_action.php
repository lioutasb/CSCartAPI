<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class user_name_get_action extends UserAuthorizedAction {

    public function execute() {
        $userService = ServiceFactory::factory('User');
        $username = $userService->getUserName();
        $this->setSuccess($username);
    }

}

?>