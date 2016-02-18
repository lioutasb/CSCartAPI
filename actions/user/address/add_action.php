<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class user_address_add_action extends UserAuthorizedAction {

    public function execute() {
        $userService = ServiceFactory::factory('User');
        $address = array_merge(prepare_address($_REQUEST, 'billing'), prepare_address($_REQUEST, 'shipping'));
        $result = $userService->addAddress($address);
        if (is_numeric($result)) {
            $this->setSuccess();
            return;
        }
        $this->setError(KancartResult::ERROR_USER_INPUT_PARAMETER, $result);
    }

}
?>
