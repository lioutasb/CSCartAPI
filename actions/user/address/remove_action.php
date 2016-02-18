<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class user_address_remove_action extends UserAuthorizedAction {

    public function execute() {
        $addressBookId = $this->getParam('address_book_id');
        $userService = ServiceFactory::factory('User');
        if ($userService->removeAddress($addressBookId)) {
            $this->setSuccess();
            return;
        }
        $this->setError('', 'Can not delete main address');
    }

}

?>
