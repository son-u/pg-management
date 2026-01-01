<?php
require_once __DIR__ . '/../config/config.php';


function isLoggedIn()
{
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_email']);
}


function requireLogin()
{
    if (!isLoggedIn()) {
        redirect(route('index.php'));
    }
}


function getCurrentAdmin()
{
    if (!isLoggedIn()) {
        return null;
    }


    return [
        'id' => $_SESSION['admin_id'],
        'email' => $_SESSION['admin_email'],
        'name' => $_SESSION['admin_name'] ?? 'Admin',
        'role' => $_SESSION['admin_role'] ?? 'admin'
    ];
}


function login($admin)
{
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['admin_name'] = $admin['full_name'];
    $_SESSION['admin_role'] = $admin['role'];
    $_SESSION['login_time'] = time();

    
    $_SESSION['last_activity'] = time();
    $_SESSION['created'] = time();
}


function logout()
{
   
    destroy_session();
    redirect(route('index.php'));
}


function checkSessionTimeout()
{
   
    if (is_session_expired()) {
        flash('error', 'Your session has expired. Please log in again.');
        destroy_session();
        redirect(route('index.php') . '?error=session_expired');
        return false;
    }

    

    
    $remaining_time = get_session_remaining_time();
    if ($remaining_time < 600 && $remaining_time > 0) { 
        flash('warning', 'Your session will expire in ' . ceil($remaining_time / 60) . ' minutes.');
    }

    return true;
}



if (
    basename($_SERVER['SCRIPT_NAME']) !== 'index.php' &&
    basename($_SERVER['SCRIPT_NAME']) !== 'logout.php'
) {
    requireLogin();

    
    checkSessionTimeout();
}
