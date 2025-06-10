<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'todo_list');

try {
    // Create a connection to the database
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

    // Check the connection
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error . " (Host: " . DB_HOST . ")");
    }
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Ensure the 'users' table exists
$tableCheckQuery = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if (!$mysqli->query($tableCheckQuery)) {
    die("Error creating table: " . $mysqli->error);
}
?>
