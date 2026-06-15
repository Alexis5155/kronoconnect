<?php
define('ROOT_PATH', __DIR__);
define('APP_PATH', __DIR__ . '/app');
define('CONFIG_PATH', APP_PATH . '/config');
define('VIEW_PATH', APP_PATH . '/views');
require APP_PATH . '/core/Autoloader.php';
spl_autoload_register([new \KronoConnect\Core\Autoloader(), 'loadClass']);

require APP_PATH . '/core/helpers.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/kronoconnect/index.php';

try {
    \KronoConnect\Core\View::render('auth/login', [
        'appConfig' => require CONFIG_PATH.'/app.php', 
        'ssoClient' => null, 
        'allowRegister' => true, 
        'allowReset' => true, 
        'initialPanel' => 0
    ], 'auth');
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile();
}
