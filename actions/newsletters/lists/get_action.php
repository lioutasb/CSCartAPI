<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class newsletters_lists_get_action extends UserAuthorizedAction {

    public function execute() {
        if (is_addon_enabled('news_and_emails')) {
            $newslettersService = ServiceFactory::factory('Newsletters');
			$auth = &$_SESSION['auth'];
			$this->setSuccess($newslettersService->getUserMailingList($auth));
            return;
        }
        $this->setError('', 'news_and_emails addon is not enabled.');
    }

}

?>