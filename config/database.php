<?php
require_once 'config.php';

class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        try {
            $dsn = sprintf(
                "pgsql:host=%s;port=%d;dbname=%s",
                DB_HOST,
                DB_PORT,
                DB_NAME
            );

            $this->connection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->connection;
    }
}

class SupabaseClient
{
    private $url;
    private $apiKey;
    private $headers;

    public function __construct()
    {
        $this->url = SUPABASE_URL;
        $this->apiKey = SUPABASE_SERVICE_KEY;
        $this->headers = [
            'apikey: ' . $this->apiKey,
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];
    }

    public function select($table, $columns = '*', $filters = [])
    {
        // Start with base URL and select parameter
        $url = $this->url . "/rest/v1/{$table}?select=" . urlencode($columns);

        // FIXED: Build query parameters exactly like the working manual call
        foreach ($filters as $key => $value) {
            // Use the exact same format as the working manual call
            $url .= "&" . urlencode($key) . "=eq." . urlencode($value);
        }

        // Debug: Log the built URL to compare with manual call
        error_log("PHP Client URL: " . $url);

        return $this->makeRequest('GET', $url);
    }

    public function insert($table, $data)
    {
        $url = $this->url . "/rest/v1/{$table}";
        return $this->makeRequest('POST', $url, $data);
    }

    public function update($table, $data, $filters)
    {
        $url = $this->url . "/rest/v1/{$table}";

        // Build query parameters for WHERE clause
        $conditions = [];
        foreach ($filters as $key => $value) {
            $conditions[] = urlencode($key) . "=eq." . urlencode($value);
        }

        if (!empty($conditions)) {
            $url .= "?" . implode('&', $conditions);
        }

        return $this->makeRequest('PATCH', $url, $data);
    }

    public function delete($table, $filters)
    {
        $url = $this->url . "/rest/v1/{$table}";

        // Build query parameters for WHERE clause
        $conditions = [];
        foreach ($filters as $key => $value) {
            $conditions[] = urlencode($key) . "=eq." . urlencode($value);
        }

        if (!empty($conditions)) {
            $url .= "?" . implode('&', $conditions);
        }

        return $this->makeRequest('DELETE', $url);
    }

    private function makeRequest($method, $url, $data = null)
    {
        // Enhanced debug logging
        error_log("=== Supabase API Request ===");
        error_log("Method: $method");
        error_log("URL: $url");
        if ($data) {
            error_log("Data: " . json_encode($data));
        }

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $this->headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true
        ]);

        if ($data && in_array($method, ['POST', 'PATCH', 'PUT'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Enhanced debug logging
        error_log("HTTP Code: $httpCode");
        error_log("Response: $response");
        error_log("=== End API Request ===");

        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }

        if ($httpCode >= 400) {
            throw new Exception("API Error: HTTP {$httpCode} - " . $response);
        }

        return json_decode($response, true) ?: [];
    }
}

// Global database instance
function db()
{
    return Database::getInstance()->getConnection();
}

// Global Supabase instance
function supabase()
{
    return new SupabaseClient();
}
