<?php
define('ROOT_PATH',    __DIR__);
define('APP_PATH',     ROOT_PATH . '/app');
define('VIEW_PATH',    APP_PATH  . '/views');
define('CONFIG_PATH',  APP_PATH  . '/config');
define('STORAGE_PATH', ROOT_PATH . '/storage');

require 'vendor/autoload.php';
require 'app/core/Autoloader.php';
$autoloader = new \KronoConnect\Core\Autoloader();
$autoloader->register();

// Set dummy server parameters for test environment
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/index.php';

\KronoConnect\Core\Session::start();

$db = \KronoConnect\Core\Database::getInstance();
$user = $db->fetchOne("SELECT * FROM " . $db->t('users') . " LIMIT 1");
if ($user) {
    // Mock user login
    $user['role'] = 'user'; // mock
    \KronoConnect\Core\Session::login($user);
    echo "Logged in as user ID: " . $user['id'] . "\n";
} else {
    echo "No users found!\n";
    exit;
}

try {
    $profileCtrl = new \KronoConnect\Controllers\ProfileController();
    
    // Obtains register options
    echo "Calling webauthnRegisterOptions...\n";
    ob_start();
    $profileCtrl->webauthnRegisterOptions();
    $output = ob_get_clean();
    echo "Output:\n$output\n";
} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
