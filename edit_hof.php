<?php
require 'db_connect.php'; // Include database connection
session_start(); // Start session for messages




// Check if the user is logged in as admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    // Not logged in as admin, set error message and redirect to login page
    $_SESSION['login_error'] = 'Admin access required for this page.';
    header('Location: login.php');
    exit; // Stop script execution immediately
}

// If the script reaches here, the user IS an admin.
// You can now include db_connect and continue with the page logic.


$hof_id = null;
$hof_details = null;
$error_message = ''; // Specific error for this page load
$success_message = ''; // Specific success for this page load

// --- Part 1: Handle Form Submission (Update Logic) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_hof'])) {

    // Get and sanitize submitted data
    $hof_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $name = trim($_POST['name'] ?? '');
    $its = trim($_POST['its_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $whatsapp = trim($_POST['whatsapp_number'] ?? '');
    $sabil = trim($_POST['sabil_number'] ?? '');

    // Basic Validation
    if (empty($hof_id)) {
        $error_message = "Invalid record ID for update.";
    } elseif (empty($name) || empty($its)) {
        $error_message = "Name and ITS Number cannot be empty.";
    } else {
        // Check if the *new* ITS number already exists for a *different* person
        $checkSql = "SELECT id FROM heads_of_family WHERE its_number = ? AND id != ?";
        $stmtCheck = $conn->prepare($checkSql);
        $stmtCheck->bind_param("si", $its, $hof_id);
        $stmtCheck->execute();
        $stmtCheck->store_result();

        if ($stmtCheck->num_rows > 0) {
            $error_message = "Error: The ITS Number '$its' is already assigned to another Head of Family.";
        } else {
            // Proceed with the update
            $updateSql = "UPDATE heads_of_family
                          SET name = ?, its_number = ?, email = ?, whatsapp_number = ?, sabil_number = ?
                          WHERE id = ?";
            $stmtUpdate = $conn->prepare($updateSql);

             // Handle potentially empty optional fields -> store as NULL in DB if desired
             $email_db = !empty($email) ? $email : null;
             $whatsapp_db = !empty($whatsapp) ? $whatsapp : null;
             $sabil_db = !empty($sabil) ? $sabil : null;

            $stmtUpdate->bind_param("sssssi", $name, $its, $email_db, $whatsapp_db, $sabil_db, $hof_id);

            if ($stmtUpdate->execute()) {
                $_SESSION['success_message'] = "Head of Family details updated successfully.";
                header("Location: manage_hof.php"); // Redirect on success
                exit;
            } else {
                $error_message = "Error updating record: " . $stmtUpdate->error;
            }
            $stmtUpdate->close();
        }
        $stmtCheck->close();
    }
    // If update failed or validation failed, we fall through to redisplay the form
    // We need to repopulate $hof_details with the *submitted* (but failed) data
     $hof_details = [
         'id' => $hof_id,
         'name' => $name,
         'its_number' => $its,
         'email' => $email,
         'whatsapp_number' => $whatsapp,
         'sabil_number' => $sabil
     ];

} else {
    // --- Part 2: Display Edit Form (Fetch Existing Data) ---
    if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
        $_SESSION['error_message'] = "Invalid or missing ID for editing.";
        header("Location: manage_hof.php");
        exit;
    }

    $hof_id = (int)$_GET['id'];

    $sql = "SELECT id, name, its_number, email, whatsapp_number, sabil_number
            FROM heads_of_family WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
         $_SESSION['error_message'] = "Database error (prepare): " . $conn->error;
         header("Location: manage_hof.php");
         exit;
    }

    $stmt->bind_param("i", $hof_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $hof_details = $result->fetch_assoc();
    } else {
        $_SESSION['error_message'] = "Head of Family record not found.";
        header("Location: manage_hof.php");
        exit;
    }
    $stmt->close();
}

$conn->close(); // Close connection only after all DB operations for the request are done
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Head of Family</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css"> <!-- Link your stylesheet -->
     <style>
        /* Add basic form styling if not fully covered by style.css */
        .edit-form { max-width: 500px; margin: 20px auto; padding: 20px; border: 1px solid #ccc; border-radius: 5px; }
        .edit-form label { display: block; margin-bottom: 5px; font-weight: bold; }
        .edit-form input[type=text],
        .edit-form input[type=email] { width: 95%; padding: 8px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 3px;}
        .edit-form button { padding: 10px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .edit-form button:hover { background-color: #0056b3; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .error { background-color: #f2dede; color: #a94442; border: 1px solid #ebccd1;}
     </style>
</head>
<body>
    <div class="edit-form">
        <h1>Edit Head of Family</h1>

        <?php
        // Display specific error message for this page (e.g., update failure)
        if (!empty($error_message)) {
            echo '<div class="message error">' . htmlspecialchars($error_message) . '</div>';
        }
        ?>

        <?php if ($hof_details): // Only show form if details were fetched or submitted ?>
            <form action="edit_hof.php" method="POST">
                <!-- Hidden field to identify the record -->
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($hof_details['id']); ?>">
                <!-- Hidden field to identify the action -->
                <input type="hidden" name="update_hof" value="1">

                <div>
                    <label for="name">Name:</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($hof_details['name'] ?? ''); ?>" required>
                </div>
                <div>
                    <label for="its_number">ITS Number:</label>
                    <input type="text" id="its_number" name="its_number" value="<?php echo htmlspecialchars($hof_details['its_number'] ?? ''); ?>" required>
                </div>
                <div>
                    <label for="sabil_number">Sabil Number:</label>
                    <input type="text" id="sabil_number" name="sabil_number" value="<?php echo htmlspecialchars($hof_details['sabil_number'] ?? ''); ?>">
                </div>
                <div>
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($hof_details['email'] ?? ''); ?>">
                </div>
                <div>
                    <label for="whatsapp_number">WhatsApp Number:</label>
                    <input type="text" id="whatsapp_number" name="whatsapp_number" value="<?php echo htmlspecialchars($hof_details['whatsapp_number'] ?? ''); ?>">
                </div>
                <div>
                    <button type="submit">Update Details</button>
                </div>
            </form>
        <?php else: ?>
            <p>Could not load Head of Family details.</p>
        <?php endif; ?>

        <p style="margin-top: 20px;"><a href="manage_hof.php">Cancel and return to List</a></p>
    </div>
</body>
</html>