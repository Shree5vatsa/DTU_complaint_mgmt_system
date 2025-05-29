<?php
// Include required files with absolute paths
$root_path = __DIR__;
require_once $root_path . '/includes/db_config.php';
require_once $root_path . '/includes/auth.php';

$auth = new Auth($pdo);
$auth->logout();

header('Location: login.php');
exit();
?> 