<?php
require 'db_connect.php'; // Include database connection
session_start(); // Start session to store status messages



// Check if the user is logged in as admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    // Not logged in as admin, set error message and redirect to login page
    $_SESSION['login_error'] = 'Admin access required for this page.';
    header('Location: login.php');
    exit; // Stop script execution immediately
}

// If the script reaches here, the user IS an admin.
// You can now include db_connect and continue with the page logic.


// --- Input Validation ---
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid or missing ID for deletion.";
    header("Location: manage_hof.php"); // Redirect back to the list
    exit;
}

$hof_id = (int)$_GET['id'];

// --- Deletion Logic ---
// Optional: Check if there are related RSVPs and decide if deletion should be allowed
// For now, we assume the FOREIGN KEY constraint (ON DELETE CASCADE or RESTRICT) handles it.
// If ON DELETE RESTRICT is used in your DB, this delete will fail if RSVPs exist.
// If ON DELETE CASCADE is used, deleting the HoF will also delete their RSVPs.

$sql = "DELETE FROM heads_of_family WHERE id = ?";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    // Handle prepare error
     $_SESSION['error_message'] = "Database error (prepare): " . $conn->error;
     header("Location: manage_hof.php");
     exit;
}

$stmt->bind_param("i", $hof_id);

if ($stmt->execute()) {
    // Check if any row was actually affected
    if ($stmt->affected_rows > 0) {
        $_SESSION['success_message'] = "Head of Family record deleted successfully.";
    } else {
        // This could happen if the ID didn't exist (e.g., deleted in another tab)
        $_SESSION['warning_message'] = "Could not delete record. It might have already been deleted.";
    }
} else {
    // Check for foreign key constraint violation if using ON DELETE RESTRICT
    if ($conn->errno == 1451) { // MySQL error code for foreign key constraint failure
         $_SESSION['error_message'] = "Cannot delete Head of Family because they have existing RSVPs linked. Please delete the RSVPs first or contact support.";
    } else {
        $_SESSION['error_message'] = "Error deleting record: " . $stmt->error;
    }
}

$stmt->close();
$conn->close();

// --- Redirect back to the management page ---
header("Location: manage_hof.php");
exit;
?>