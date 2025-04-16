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
if (empty($event_id)) {
    $_SESSION['error_message'] = "Please select a valid event.";
    header("Location: index.php");
    exit;
}
if (empty($hof_id)) {
    $_SESSION['error_message'] = "Invalid Head of Family selected. Please search and select again.";
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
$event_name = 'Unknown Event'; // Default
$custom_message_template = ''; // Default
$stmtEvent = $conn->prepare("SELECT event_name, custom_message FROM events WHERE id = ? AND is_active = TRUE"); // Also check if event is active
$stmtEvent->bind_param("i", $event_id);
$stmtEvent->execute();
$resultEvent = $stmtEvent->get_result();
if ($resultEvent->num_rows === 1) {
    $event_details = $resultEvent->fetch_assoc();
    $event_name = $event_details['event_name'];
    $custom_message_template = $event_details['custom_message'] ?? "Salaam {NAME},\n\nThank you for your RSVP for the {EVENT_NAME}.\nITS: {ITS}\nAttendees: {COUNT}\n\nWe look forward to seeing you!"; // Provide default if missing
} else {
     $_SESSION['error_message'] = "Selected event not found or is not active.";
     $stmtEvent->close();
     $conn->close();
     header("Location: index.php");
     exit;
}
$stmtEvent->close();


// --- *** NEW: Fetch Current HoF Details *** ---
$current_hof_details = null;
$stmtHof = $conn->prepare("SELECT name, its_number, sabil_number, email, whatsapp_number, telegram_chat_id FROM heads_of_family WHERE id = ?"); // Fetch all needed details
if ($stmtHof) {
    $stmtHof->bind_param("i", $hof_id);
    $stmtHof->execute();
    $resultHof = $stmtHof->get_result();
    if ($resultHof->num_rows === 1) {
        $current_hof_details = $resultHof->fetch_assoc();
    } else {
        // Handle error - HoF ID somehow became invalid between selection and submission?
        $_SESSION['error_message'] = "Selected Head of Family record not found (ID: $hof_id). Please try selecting again.";
        $stmtHof->close();
        $conn->close();
        header("Location: index.php");
        exit;
    }
    $stmtHof->close();
} else {
    // Handle prepare error
     $_SESSION['error_message'] = "Database error fetching Head of Family details: " . $conn->error;
     $conn->close();
     header("Location: index.php");
     exit;
}

// Store fetched details in variables for clarity
$hof_name_now = $current_hof_details['name'];
$hof_its_now = $current_hof_details['its_number'];
$hof_sabil_now = $current_hof_details['sabil_number']; // Can be NULL


// --- Check for Existing RSVP ---
$existing_rsvp_id = null;
$sqlCheck = "SELECT id FROM rsvps WHERE hof_id = ? AND event_id = ? LIMIT 1";
$stmtCheck = $conn->prepare($sqlCheck);
$stmtCheck->bind_param("ii", $hof_id, $event_id); // Both are integers now
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
$rsvp_id = null; // Initialize RSVP ID

// --- Perform INSERT or UPDATE ---
if ($existing_rsvp_id !== null) {
    // --- UPDATE Existing RSVP ---
    $is_update = true;
    // Include the new historical fields in the UPDATE
    $sqlUpdate = "UPDATE rsvps SET
                    attendee_count = ?,
                    hof_name_at_rsvp = ?,
                    hof_its_at_rsvp = ?,
                    hof_sabil_at_rsvp = ?,
                    rsvp_timestamp = NOW(),
                    confirmation_method = NULL,       -- Reset confirmation status on update
                    confirmation_sent_at = NULL
                  WHERE id = ?";
    $stmt = $conn->prepare($sqlUpdate);
    if ($stmt) {
        // Parameters: count(i), name(s), its(s), sabil(s), id(i)
        $stmt->bind_param("isssi",
            $attendee_count,
            $hof_name_now,
            $hof_its_now,
            $hof_sabil_now,
            $existing_rsvp_id
        );
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
    // Include the new historical fields in the INSERT
    $sqlInsert = "INSERT INTO rsvps
                    (hof_id, event_id, hof_name_at_rsvp, hof_its_at_rsvp, hof_sabil_at_rsvp, attendee_count)
                  VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sqlInsert);
     if ($stmt) {
        // Parameters: hof_id(i), event_id(i), name(s), its(s), sabil(s), count(i)
        $stmt->bind_param("iisssi",
            $hof_id,
            $event_id,
            $hof_name_now,
            $hof_its_now,
            $hof_sabil_now,
            $attendee_count
        );
        if ($stmt->execute()) {
            $success = true;
            $rsvp_id = $stmt->insert_id; // Get the new ID
            $_SESSION['success_message'] = "RSVP recorded successfully!";
        } else {
             // Check for unique constraint violation (shouldn't happen with prior check, but good safeguard)
            if ($conn->errno == 1062) {
                 $_SESSION['error_message'] = "Error: Duplicate RSVP detected. Please refresh and try again or contact support.";
                 // Set success to false to prevent notification attempts on failed insert
                 $success = false;
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

// --- Send Notification ONLY if Insert/Update was successful AND we have an RSVP ID ---
if ($success && $rsvp_id !== null) {
    // $current_hof_details should still contain the necessary contact info (email, whatsapp, telegram_chat_id)
    $notification_sent_to_user = false;
    $confirmation_method_used = 'none'; // Default

    if ($current_hof_details) {
        // Check if HoF has provided their Telegram Chat ID
        if (!empty($current_hof_details['telegram_chat_id'])) {
            $userChatId = trim($current_hof_details['telegram_chat_id']);

            // --- Prepare Custom Message using Placeholders ---
            // Use details fetched earlier ($hof_name_now, $hof_its_now)
            $placeholders = [
                '{NAME}' => $hof_name_now,
                '{ITS}' => $hof_its_now,
                '{COUNT}' => $attendee_count,
                '{EVENT_NAME}' => $event_name // Use fetched event name
            ];
            $telegramMessage = str_replace(array_keys($placeholders), array_values($placeholders), $custom_message_template);

             // Add note if it was an update
            if ($is_update) {
                $telegramMessage = "*RSVP Updated*\n" . $telegramMessage; // Prepend update note
            }

            // Send Telegram Notification TO THE USER
            // Ensure TELEGRAM_BOT_TOKEN is defined in config.php
            if (defined('TELEGRAM_BOT_TOKEN')) {
                $telegramSent = sendTelegramNotification(TELEGRAM_BOT_TOKEN, $userChatId, $telegramMessage);

                if ($telegramSent) {
                    $notification_sent_to_user = true;
                    $confirmation_method_used = 'telegram_user'; // Use a consistent identifier
                     // Append notification status to existing success message
                     $_SESSION['success_message'] .= " Telegram confirmation sent.";
                     error_log("Telegram confirmation sent to user (ChatID: " . $userChatId . ") for RSVP ID: " . $rsvp_id);
                } else {
                     $_SESSION['success_message'] .= " (User Telegram notification failed).";
                     error_log("Failed to send Telegram confirmation to user (ChatID: " . $userChatId . ") for RSVP ID: " . $rsvp_id);
                }
            } else {
                $_SESSION['success_message'] .= " (Telegram Bot Token not configured - cannot send confirmation).";
                error_log("TELEGRAM_BOT_TOKEN not defined. Cannot send confirmation for RSVP ID: " . $rsvp_id);
            }
        } else {
            // HoF has not provided their Chat ID
             $_SESSION['success_message'] .= " (Telegram confirmation not sent - User Chat ID missing).";
             error_log("No Telegram Chat ID found for HoF ID: " . $hof_id . " - Cannot send user notification for RSVP ID: " . $rsvp_id);
        }

        // --- Update RSVP Record with Notification Status ---
        // Note: We reset confirmation_method/sent_at during UPDATE above, so only need to set it if successful now
        if ($notification_sent_to_user) {
            $confirmation_sent_at = date('Y-m-d H:i:s');
            $updateSql = "UPDATE rsvps SET confirmation_method = ?, confirmation_sent_at = ? WHERE id = ?";
            $stmtUpdate = $conn->prepare($updateSql);
            if ($stmtUpdate) {
                $stmtUpdate->bind_param("ssi", $confirmation_method_used, $confirmation_sent_at, $rsvp_id);
                if (!$stmtUpdate->execute()) {
                    error_log("Error updating notification status for RSVP ID: " . $rsvp_id . " - Error: " . $stmtUpdate->error);
                    // Don't overwrite user success message, just log the error
                }
                $stmtUpdate->close();
            } else {
                 error_log("Error preparing statement to update notification status for RSVP ID: " . $rsvp_id . " - Error: " . $conn->error);
            }
        }
        // No need for an 'else' block here as the fields were reset or are default NULL on insert


    } else {
         // Error fetching HoF details, update main success message (shouldn't happen due to earlier check)
         $_SESSION['success_message'] .= " (Could not fetch HoF details for notification).";
         error_log("HoF details were unexpectedly null during notification phase for RSVP ID: " . $rsvp_id);
    }
} elseif (!$success) {
    // Insert/Update failed, error message should already be in session. Log it.
    error_log("RSVP Insert/Update failed for HoF ID: " . $hof_id . ", Event ID: " . $event_id . ". Error in session: " . ($_SESSION['error_message'] ?? 'N/A'));
}
// --- End Notification Logic ---

// --- Close DB Connection & Redirect ---
$conn->close();
header("Location: index.php");
exit;
?>