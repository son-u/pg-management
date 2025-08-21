<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Set JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Initialize response
$response = ['success' => false, 'rooms' => [], 'message' => ''];

try {
    // Get building code from query parameter
    $buildingCode = $_GET['building'] ?? '';
    
    if (empty($buildingCode)) {
        throw new Exception('Building code is required');
    }
    
    $supabase = supabase();
    
    // Get available rooms for the building
    $rooms = $supabase->select('rooms', '*', [
        'building_code' => $buildingCode
    ]);
    
    if (empty($rooms)) {
        $response['message'] = 'No rooms found for this building';
        $response['success'] = true; // Still success, just no rooms
    } else {
        // Sort rooms by room number
        usort($rooms, function($a, $b) {
            return strcmp($a['room_number'], $b['room_number']);
        });
        
        $response['success'] = true;
        $response['rooms'] = $rooms;
        $response['message'] = count($rooms) . ' rooms found';
    }
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log('Get rooms API error: ' . $e->getMessage());
}

// Return JSON response
echo json_encode($response);
?>

