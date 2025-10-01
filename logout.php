<?php
/**
 * Logout Page
 * Destroys the user session and redirects to the login page.
 */
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

// Log the logout activity before destroying the session
if (is_logged_in()) {
    log_activity('logout', 'User logged out');
}

// Unset all of the session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
redirect('index.php');
exit;