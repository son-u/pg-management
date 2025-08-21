<?php
require_once '../config/config.php';

// Only allow AJAX requests
if (
    !isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    http_response_code(403);
    exit;
}

// Set JSON header
header('Content-Type: application/json');

try {
    if (isset($_SESSION['admin_id']) && !is_session_expired()) {
        extend_session();

        echo json_encode([
            'success' => true,
            'remaining_time' => get_session_remaining_time(),
            'message' => 'Session refreshed successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Session expired or invalid'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error refreshing session'
    ]);
}