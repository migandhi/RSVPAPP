<?php
$servername = "localhost";
$username = "root"; // Default XAMPP username
$password = "";     // Default XAMPP password (often empty)
$dbname = "rsvp_app"; // Choose a database name

// Create connection using MySQLi (Object-oriented style)
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Set character set to utf8mb4 for broader character support
$conn->set_charset("utf8mb4");
?>