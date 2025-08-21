<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth_check.php';

// Get payment ID from URL
$paymentId = $_GET['id'] ?? '';

if (empty($paymentId)) {
    flash('error', 'Payment ID is required to delete.');
    header('Location: index.php');
    exit();
}

try {
    $supabase = supabase();

    // Check if payment exists
    $paymentData = $supabase->select('payments', 'payment_id,student_id', ['payment_id' => $paymentId]);

    if (empty($paymentData)) {
        flash('error', 'Payment not found.');
        header('Location: index.php');
        exit();
    }

    // Delete the payment
    $result = $supabase->delete('payments', ['payment_id' => $paymentId]);

    if ($result) {
        flash('success', 'Payment record deleted successfully.');
    } else {
        flash('error', 'Failed to delete payment record.');
    }
} catch (Exception $e) {
    flash('error', 'Error deleting payment: ' . $e->getMessage());
    error_log('Delete payment error: ' . $e->getMessage());
}

// Redirect back to payments listing
header('Location: index.php');
exit();
