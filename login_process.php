<?php
session_start(); // Start session to store login status

// --- WARNING: Using plain text password - highly insecure! ---
// --- Only for temporary local testing ---
$ADMIN_USERNAME = 'admin';
$ADMIN_PASSWORD = 'admin'; // The plain text password

// Get submitted data (use filter_input for basic safety)
$submitted_user = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
$submitted_pass = $_POST['password'] ?? ''; // Get password directly

// --- Direct comparison (INSECURE) ---
if ($submitted_user === $ADMIN_USERNAME && $submitted_pass === $ADMIN_PASSWORD) {
    // Login successful
    $_SESSION['is_admin'] = true; // Set admin flag in session
    unset($_SESSION['login_error']); // Clear any previous error
    header('Location: reports.php'); // Redirect to the main admin page
    exit;
} else {
    // Login failed
    $_SESSION['is_admin'] = false;
    $_SESSION['login_error'] = 'Invalid username or password.';
    header('Location: login.php'); // Redirect back to login page
    exit;
}
?>