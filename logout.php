<?php
session_start();    // Access the session
session_unset();    // Remove all session variables
session_destroy(); // Destroy the session data
header('Location: login.php'); // Redirect to login page
exit;
?>