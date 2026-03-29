<?php
// Database connection
require_once 'config.php';
function get_db_connection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die('Database connection failed: ' . $conn->connect_error);
    }
    return $conn;
}
