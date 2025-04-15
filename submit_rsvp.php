<?php
require 'db_connect.php';
require 'config.php';       // For Telegram Bot Token
require 'notifications.php'; // For sendTelegramNotification function
session_start();

// --- Form Data Retrieval & Basic Validation ---
$hof_id = filter_input(INPUT_POST, 'hof_id', FILTER_VALIDATE_INT);
$attendee_count = filter_input(INPUT_POST, 'attendee_count', FILTER_VALIDATE_INT);
$event_id = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT); // Get selected event ID

// --- Basic Validation ---

// --- Basic Validation ---
if (empty($event_id)) {
    $_SESSION['error_message'] = "Please select a valid event.";
    header("Location: index.php");
    exit;
}

if (empty($hof_id)) {
    $_SESSION['error_message'] = "Invalid Head of Family selected.";
    header("Location: index.php");
    exit;
}
if (empty($attendee_count) || $attendee_count <= 0) {
    $_SESSION['error_message'] = "Please enter a valid number of attendees (must be 1 or more).";
    header("Location: index.php");
    exit;
}


// --- Fetch Event Details (Name and Custom Message) ---
$event_details = null;
$stmtEvent = $conn->prepare("SELECT event_name, custom_message FROM events WHERE id = ?");
$stmtEvent->bind_param("i", $event_id);
$stmtEvent->execute();
$resultEvent = $stmtEvent->get_result();
if ($resultEvent->num_rows === 1) {
    $event_details = $resultEvent->fetch_assoc();
} else {
     $_SESSION['error_message'] = "Selected event not found.";
     header("Location: index.php");
     exit;
}
$stmtEvent->close();
$event_name = $event_details['event_name']; // Use fetched event name
$custom_message_template = $event_details['custom_message'] ?? ''; // Get template





// --- Check for Existing RSVP ---
$existing_rsvp_id = null;
$sqlCheck = "SELECT id FROM rsvps WHERE hof_id = ? AND event_id = ? LIMIT 1";
$stmtCheck = $conn->prepare($sqlCheck);
$stmtCheck->bind_param("is", $hof_id, $event_id);
$stmtCheck->execute();
$resultCheck = $stmtCheck->get_result();
if ($rowCheck = $resultCheck->fetch_assoc()) {
    $existing_rsvp_id = $rowCheck['id'];
}
$stmtCheck->close();

// --- Prepare statement variables ---
$stmt = null;
$success = false;
$is_update = false;

// --- Perform INSERT or UPDATE ---
if ($existing_rsvp_id !== null) {
    // --- UPDATE Existing RSVP ---
    $is_update = true;
    $sqlUpdate = "UPDATE rsvps SET attendee_count = ?, rsvp_timestamp = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sqlUpdate);
    if ($stmt) {
        $stmt->bind_param("ii", $attendee_count, $existing_rsvp_id);
        if ($stmt->execute()) {
            $success = true;
            $_SESSION['success_message'] = "RSVP updated successfully!";
        } else {
            $_SESSION['error_message'] = "Error updating RSVP: " . $stmt->error;
        }
    } else {
         $_SESSION['error_message'] = "Database error preparing update: " . $conn->error;
    }
     $rsvp_id = $existing_rsvp_id; // Use existing ID for notification logic

} else {
    // --- INSERT New RSVP ---
    $sqlInsert = "INSERT INTO rsvps (hof_id, event_id, attendee_count) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sqlInsert);
     if ($stmt) {
        $stmt->bind_param("iii", $hof_id, $event_id, $attendee_count);
        if ($stmt->execute()) {
            $success = true;
            $rsvp_id = $stmt->insert_id; // Get the new ID
            $_SESSION['success_message'] = "RSVP recorded successfully!";
        } else {
             // Check for unique constraint violation if DB constraint was added
            if ($conn->errno == 1062) { // 1062 is MySQL code for duplicate entry
                 $_SESSION['error_message'] = "Error: An RSVP for this family and event might already exist (Constraint Violation). Please try again or contact support.";
                 // You might want to re-query here to get the existing ID and attempt an update,
                 // but the initial check should ideally prevent this state.
                 $success = false; // Explicitly mark as failed insert
            } else {
                $_SESSION['error_message'] = "Error recording RSVP: " . $stmt->error;
            }
        }
    } else {
         $_SESSION['error_message'] = "Database error preparing insert: " . $conn->error;
    }
}

// Close the main statement if it was prepared
if ($stmt !== null) {
    $stmt->close();
}

// --- Send Notification ONLY if Insert/Update was successful ---
if ($success) {
    // --- Get HoF Details (including Telegram Chat ID) ---
    $hofSql = "SELECT name, its_number, email, whatsapp_number, telegram_chat_id
               FROM heads_of_family WHERE id = ?";
    $stmtHof = $conn->prepare($hofSql);
    $stmtHof->bind_param("i", $hof_id);
    $stmtHof->execute();
    $hofResult = $stmtHof->get_result();
    $hofDetails = $hofResult->fetch_assoc();
    $stmtHof->close();

    $notification_sent_to_user = false;

    if ($hofDetails) {
        // Check if HoF has provided their Telegram Chat ID
        if (!empty($hofDetails['telegram_chat_id'])) {
            $userChatId = trim($hofDetails['telegram_chat_id']);

            
            // --- Prepare Custom Message using Placeholders ---
            $hof_name = $hofDetails['name'];
            $its_number = $hofDetails['its_number'];

            // Replace placeholders in the template
            $placeholders = [
                '{NAME}' => $hof_name,
                '{ITS}' => $its_number,
                '{COUNT}' => $attendee_count,
                '{EVENT_NAME}' => $event_name // Use fetched event name
            ];
            $telegramMessage = str_replace(array_keys($placeholders), array_values($placeholders), $custom_message_template);

  // Add note if it was an update
            if ($is_update) {
                $telegramMessage = "*RSVP Updated*\n" . $telegramMessage; // Prepend update note
            }


            // Send Telegram Notification TO THE USER
            $telegramSent = sendTelegramNotification(TELEGRAM_BOT_TOKEN, $userChatId, $telegramMessage);

            if ($telegramSent) {
                $notification_sent_to_user = true;
                 // Append notification status to existing success message
                 $_SESSION['success_message'] .= " Telegram confirmation sent to user.";
                 error_log("Telegram confirmation sent to user (ChatID: " . $userChatId . ") for RSVP ID: " . $rsvp_id);
            } else {
                 $_SESSION['success_message'] .= " (User Telegram notification failed).";
                 error_log("Failed to send Telegram confirmation to user (ChatID: " . $userChatId . ") for RSVP ID: " . $rsvp_id);
            }
        } else {
            // HoF has not provided their Chat ID
             $_SESSION['success_message'] .= " (User Telegram notification not sent - Chat ID missing).";
             error_log("No Telegram Chat ID found for HoF ID: " . $hof_id . " - Cannot send user notification.");
        }

        // --- Update RSVP Record with Notification Status ---
        $confirmation_method_used = $notification_sent_to_user ? 'telegram_user' : 'none';
        $confirmation_sent_at = $notification_sent_to_user ? date('Y-m-d H:i:s') : null;

        $updateSql = "UPDATE rsvps SET confirmation_method = ?, confirmation_sent_at = ? WHERE id = ?";
        $stmtUpdate = $conn->prepare($updateSql);
        // Check if prepare succeeded before binding/executing
        if ($stmtUpdate) {
            $stmtUpdate->bind_param("ssi", $confirmation_method_used, $confirmation_sent_at, $rsvp_id);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        } else {
             error_log("Error preparing statement to update notification status for RSVP ID: " . $rsvp_id . " - Error: " . $conn->error);
        }


    } else {
         // Error fetching HoF details, update main success message
         $_SESSION['success_message'] .= " (Could not fetch HoF details for notification).";
    }
} // End if ($success)

// --- Close DB Connection & Redirect ---
$conn->close();
header("Location: index.php");
exit;
?>