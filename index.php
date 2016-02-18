<?php

error_reporting(0);
ini_set('display_errors', 0);
define('IN_KANCART', true);
define('API_VERSION', '1.1');

$CS_ROOT = str_replace('\\', '/', dirname(dirname(__FILE__)));
define('KANCART_ROOT', str_replace('\\', '/', dirname(__FILE__)));
define('SKIP_SESSION_VALIDATION', true);

define('AREA', 'C');
defined('AREA_NAME') || define('AREA_NAME', 'customer');
defined('ACCOUNT_TYPE') || define('ACCOUNT_TYPE', 'customer');

//must before set $_REQUEST['sl'] otherwise the language chang doesn't work
file_exists($CS_ROOT . '/prepare.php') && require_once $CS_ROOT . '/prepare.php';

if (!empty($_REQUEST['language'])) {
    $_REQUEST['sl'] = $_REQUEST['language'];
}

require_once $CS_ROOT . '/init.php';
require_once KANCART_ROOT . '/KancartHelper.php';

kc_include_once(KANCART_ROOT . '/ErrorHandler.php');
kc_include_once(KANCART_ROOT . '/Logger.php');
//kc_include_once(KANCART_ROOT . '/configure.php');
kc_include_once(KANCART_ROOT . '/Exceptions.php');
kc_include_once(KANCART_ROOT . '/ActionFactory.php');
kc_include_once(KANCART_ROOT . '/ServiceFactory.php');
kc_include_once(KANCART_ROOT . '/actions/BaseAction.php');
kc_include_once(KANCART_ROOT . '/actions/UserAuthorizedAction.php');
kc_include_once(KANCART_ROOT . '/util/CryptoUtil.php');
kc_include_once(KANCART_ROOT . '/KancartResult.php');
kc_include_once(KANCART_ROOT . '/common-functions.php');
kc_include_once(KANCART_ROOT . '/configure.deploy.php');

try {
    $actionInstance = ActionFactory::factory(isset($_REQUEST['method']) ? $_REQUEST['method'] : '');
    $actionInstance->init();
    if ($actionInstance->validate()) {
        $actionInstance->execute();
    }
    $result = $actionInstance->getResult();
    die(json_encode($result->returnResult()));
} catch (EmptyMethodException $e) {
    die('KanCart OpenAPI v' . API_VERSION . ' is installed on CS-Cart v' . PRODUCT_VERSION . '. CS-Cart Plugin v' . KANCART_PLUGIN_VERSION);
} catch (Exception $e) {
    die(json_encode(array('result' => KancartResult::STATUS_FAIL, 'code' => KancartResult::ERROR_UNKNOWN_ERROR, 'info' => $e->getMessage() . ',' . $e->getTraceAsString())));
}
?>



