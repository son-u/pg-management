<?php
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $building = $_GET['building'] ?? '';

    if (empty($building)) {
        echo json_encode(['success' => false, 'error' => 'Building code required']);
        exit;
    }

    $supabase = supabase();
    $rooms = $supabase->select('rooms', 'room_number,capacity,current_occupancy,status', [
        'building_code' => $building,
        'status' => 'available'
    ]);

    echo json_encode(['success' => true, 'rooms' => $rooms ?: []]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
