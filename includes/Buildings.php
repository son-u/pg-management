<?php
class Buildings {
    private static $cache = null;
    private static $cacheTime = null;
    private static $cacheExpiry = 300; // 5 minutes

    /**
     * Ensure database connection is available
     */
    private static function ensureDatabase() {
        if (!function_exists('supabase')) {
            // Try to include database config if not already loaded
            $databasePath = __DIR__ . '/../config/database.php';
            if (file_exists($databasePath)) {
                require_once $databasePath;
            } else {
                throw new Exception('Database configuration not found');
            }
        }
        
        if (!function_exists('supabase')) {
            throw new Exception('Supabase function not available');
        }
    }

    /**
     * Get all active buildings from database
     */
    public static function getAll($forceRefresh = false) {
        // Check cache first
        if (!$forceRefresh && self::$cache !== null && 
            self::$cacheTime && (time() - self::$cacheTime) < self::$cacheExpiry) {
            return self::$cache;
        }

        try {
            self::ensureDatabase();
            $supabase = supabase();
            
            $buildings = $supabase->select('buildings', '*', [
                'status' => 'active'
            ]);

            if (!is_array($buildings)) {
                throw new Exception('Failed to fetch buildings from database');
            }

            // Sort by building_code for consistency
            usort($buildings, function($a, $b) {
                return strcmp($a['building_code'], $b['building_code']);
            });

            // Cache the results
            self::$cache = $buildings;
            self::$cacheTime = time();

            return $buildings;
            
        } catch (Exception $e) {
            error_log('Buildings::getAll() error: ' . $e->getMessage());
            
            // Return empty array instead of fallback
            // This ensures the application fails gracefully
            return [];
        }
    }

    /**
     * Get building codes array (replacement for BUILDINGS constant)
     */
    public static function getCodes() {
        $buildings = self::getAll();
        return array_column($buildings, 'building_code');
    }

    /**
     * Get building names mapping (replacement for BUILDING_NAMES constant)
     */
    public static function getNames() {
        $buildings = self::getAll();
        $names = [];
        foreach ($buildings as $building) {
            $names[$building['building_code']] = $building['building_name'];
        }
        return $names;
    }

    /**
     * Get single building by code
     */
    public static function getByCode($code) {
        $buildings = self::getAll();
        foreach ($buildings as $building) {
            if ($building['building_code'] === $code) {
                return $building;
            }
        }
        return null;
    }

    /**
     * Get building name by code (convenience method)
     */
    public static function getNameByCode($code) {
        $building = self::getByCode($code);
        return $building ? $building['building_name'] : $code;
    }

    /**
     * Check if building code exists
     */
    public static function exists($code) {
        return self::getByCode($code) !== null;
    }

    /**
     * Get building statistics
     */
    public static function getStats($code = null) {
        $buildings = self::getAll();
        
        if ($code) {
            // Return stats for specific building
            $building = self::getByCode($code);
            return $building ? [
                'total_rooms' => $building['total_rooms'] ?? 0,
                'occupied_rooms' => $building['occupied_rooms'] ?? 0,
                'total_capacity' => $building['total_capacity'] ?? 0,
                'current_occupancy' => $building['current_occupancy'] ?? 0,
            ] : null;
        }
        
        // Return aggregate stats for all buildings
        $stats = [
            'total_buildings' => count($buildings),
            'total_rooms' => 0,
            'occupied_rooms' => 0,
            'total_capacity' => 0,
            'current_occupancy' => 0,
        ];
        
        foreach ($buildings as $building) {
            $stats['total_rooms'] += $building['total_rooms'] ?? 0;
            $stats['occupied_rooms'] += $building['occupied_rooms'] ?? 0;
            $stats['total_capacity'] += $building['total_capacity'] ?? 0;
            $stats['current_occupancy'] += $building['current_occupancy'] ?? 0;
        }
        
        return $stats;
    }

    /**
     * Clear cache (call after building updates)
     */
    public static function clearCache() {
        self::$cache = null;
        self::$cacheTime = null;
    }

    /**
     * Validate building code format
     */
    public static function isValidCode($code) {
        return preg_match('/^[A-Z][0-9]+$/', $code);
    }

    /**
     * Create new building (for admin use)
     */
    public static function create($data) {
        try {
            self::ensureDatabase();
            $supabase = supabase();
            
            $buildingData = [
                'building_code' => strtoupper(trim($data['building_code'])),
                'building_name' => trim($data['building_name']),
                'building_address' => trim($data['building_address'] ?? ''),
                'contact_person' => trim($data['contact_person'] ?? ''),
                'contact_phone' => trim($data['contact_phone'] ?? ''),
                'status' => 'active',
                'created_at' => date('c'),
                'updated_at' => date('c')
            ];
            
            $result = $supabase->insert('buildings', $buildingData);
            
            // Clear cache after creation
            self::clearCache();
            
            return $result;
            
        } catch (Exception $e) {
            error_log('Buildings::create() error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update building
     */
    public static function update($id, $data) {
        try {
            self::ensureDatabase();
            $supabase = supabase();
            
            $updateData = array_merge($data, [
                'updated_at' => date('c')
            ]);
            
            $result = $supabase->update('buildings', $updateData, ['id' => $id]);
            
            // Clear cache after update
            self::clearCache();
            
            return $result;
            
        } catch (Exception $e) {
            error_log('Buildings::update() error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * ✅ REMOVED: getFallbackBuildings() method
     * The system now relies entirely on the database
     */
}
?>
