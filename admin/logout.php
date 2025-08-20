<?php
require_once('config.php');

// Logout the admin
$adminAuth->logout();

// Redirect to login page
header('Location: login.php');
exit();
?>