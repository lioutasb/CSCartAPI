<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class newsletter_subscribe_action extends BaseAction {

    public function execute() {
        $email = is_null($this->getParam('email')) ? '' : trim($this->getParam('email'));

        if (empty($email)) {
            $this->setError('', 'Email is empty.');
        } else {
            $_REQUEST['subscribe_email'] = $email;
			$_SERVER['REQUEST_METHOD'] = 'POST';
            $mode = 'add_subscriber';
            $core_post_controllers = fn_init_addon_controllers('newsletters');
            $newsletters_file = current($core_post_controllers);
			$newsletters_file = current($newsletters_file);
            list($result, ) = include $newsletters_file;
            if ($result === CONTROLLER_STATUS_REDIRECT) {
                $this->setSuccess();
            } else {
                $this->setError("error");
            }
        }
    }

}

?>