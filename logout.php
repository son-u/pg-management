<?php
require_once 'config/config.php';

// Clear all session data
session_destroy();

// Redirect to login page
redirect(route('index.php'));
?>
