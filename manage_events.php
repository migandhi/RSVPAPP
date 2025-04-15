<?php
session_start(); // Start session first

// Check if the user is logged in as admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    $_SESSION['login_error'] = 'Admin access required for this page.';
    header('Location: login.php');
    exit; // Stop script execution immediately
}

require 'db_connect.php'; // Include database connection

// --- Configuration ---
$items_per_page = 10; // Number of events per page

// --- Page Level Variables ---
$page_error_message = $_SESSION['error_message'] ?? null;
$page_success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['error_message'], $_SESSION['success_message']); // Clear messages after retrieving

$edit_event = null; // Variable to hold event data for editing form

// --- Get Filter/Search Values & Current Page ---
$search_name = trim($_GET['search_name'] ?? '');
$search_active = $_GET['search_active'] ?? ''; // '1', '0', or '' (All)
// Use FILTER_VALIDATE_INT but allow null if not set or invalid
$search_thaals_min_input = filter_input(INPUT_GET, 'search_thaals_min', FILTER_VALIDATE_INT);
$search_thaals_min = ($search_thaals_min_input === false || $search_thaals_min_input < 0) ? null : $search_thaals_min_input;

$search_thaals_max_input = filter_input(INPUT_GET, 'search_thaals_max', FILTER_VALIDATE_INT);
$search_thaals_max = ($search_thaals_max_input === false || $search_thaals_max_input < 0) ? null : $search_thaals_max_input;

$search_created_after = trim($_GET['search_created_after'] ?? '');
$search_created_before = trim($_GET['search_created_before'] ?? '');
$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);

// --- Handle Edit Request (GET - User clicked Edit link) ---
// This needs to run before the list fetching if an edit ID is present
if (isset($_GET['edit_id']) && filter_var($_GET['edit_id'], FILTER_VALIDATE_INT)) {
    $edit_id = (int)$_GET['edit_id'];
    // Fetch the specific event details for editing, including the thaal count
    $stmt = $conn->prepare("SELECT id, event_name, custom_message, is_active, actual_thaal_count FROM events WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $edit_event = $result->fetch_assoc(); // Load data into $edit_event for the form
        } else {
            // Use session message for redirect consistency
            $_SESSION['error_message'] = "Event not found for editing (ID: " . $edit_id . ").";
            header("Location: manage_events.php"); // Redirect if edit target not found
            exit;
        }
        $stmt->close();
    } else {
        $page_error_message = "Error preparing edit query: " . $conn->error; // Show error on current page
    }
}


// --- Handle Form Submission (POST - User submitted Add or Update form) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // *** Keep the existing POST handling logic for Add/Update here ***
    // It redirects, so it won't interfere directly with GET search/pagination display
    $event_id = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT); // For updates
    $event_name = trim($_POST['event_name'] ?? '');
    $custom_message = trim($_POST['custom_message'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0; // Checkbox value (1 if checked, 0 if not)

    // Get and validate actual_thaal_count
    $actual_thaal_count_input = trim($_POST['actual_thaal_count'] ?? '');
    $actual_thaal_count = null; // Default to null

    if ($actual_thaal_count_input !== '') { // Only validate if something was entered
        $filtered_count = filter_var($actual_thaal_count_input, FILTER_VALIDATE_INT);
        // Allow 0 as a valid count
        if ($filtered_count === false || $filtered_count < 0) { // Check if it's a non-negative integer
            $_SESSION['error_message'] = "Actual Thaal Count must be a whole non-negative number (or blank).";
        } else {
            $actual_thaal_count = $filtered_count; // Assign the valid integer
        }
    } else {
         $actual_thaal_count = null; // Ensure it's NULL if submitted blank
    }

    // Basic Validation for Event Name
    if (empty($event_name)) {
        $_SESSION['error_message'] = "Event Name is required.";
    }

    // Only proceed if no validation errors occurred so far
    if (!isset($_SESSION['error_message'])) {
        // Check for duplicate event name (on Add or when changing name on Update)
        $sqlCheck = "SELECT id FROM events WHERE event_name = ? AND id != ?";
        $check_id = $event_id ?? 0; // Use 0 if adding, event_id if updating (to exclude self)
        $stmtCheck = $conn->prepare($sqlCheck);

        if ($stmtCheck) {
            $stmtCheck->bind_param("si", $event_name, $check_id);
            $stmtCheck->execute();
            $stmtCheck->store_result();

            if ($stmtCheck->num_rows > 0) {
                $_SESSION['error_message'] = "An event with this name ('" . htmlspecialchars($event_name) . "') already exists.";
            } else {
                // Proceed with INSERT or UPDATE
                $stmtAction = null; // Renamed from $stmt to avoid conflict later
                $action = "";
                if ($event_id) { // UPDATE existing event
                    $sql = "UPDATE events SET event_name = ?, custom_message = ?, is_active = ?, actual_thaal_count = ? WHERE id = ?";
                    $stmtAction = $conn->prepare($sql);
                    if ($stmtAction) {
                        // Bind parameters: string, string, integer, integer/null, integer
                        $stmtAction->bind_param("ssiii", $event_name, $custom_message, $is_active, $actual_thaal_count, $event_id);
                        $action = "updated";
                    }
                } else { // INSERT new event
                    $sql = "INSERT INTO events (event_name, custom_message, is_active, actual_thaal_count) VALUES (?, ?, ?, ?)";
                    $stmtAction = $conn->prepare($sql);
                    if ($stmtAction) {
                        // Parameters: string, string, integer, integer/null
                        $stmtAction->bind_param("ssii", $event_name, $custom_message, $is_active, $actual_thaal_count);
                        $action = "added";
                    }
                }

                // Execute the prepared statement if it was prepared successfully
                if ($stmtAction) {
                    if ($stmtAction->execute()) {
                        $_SESSION['success_message'] = "Event " . $action . " successfully.";
                    } else {
                        $_SESSION['error_message'] = "Error " . ($action ?: 'processing') . " event: " . $stmtAction->error;
                    }
                    $stmtAction->close();
                } else {
                     $_SESSION['error_message'] = "Error preparing database statement: " . $conn->error;
                }
            }
            $stmtCheck->close();
        } else {
            $_SESSION['error_message'] = "Error preparing duplicate check query: " . $conn->error;
        }
    }

    // Redirect back to the manage page to show messages and clear POST data
    // If there was an error AND we were editing, redirect back *with the edit_id*
    if (isset($_SESSION['error_message']) && $event_id && $action === "updated") {
         header("Location: manage_events.php?edit_id=" . $event_id);
    } else {
         // Redirect to clean URL (no search params) after successful add/update or non-edit error
         header("Location: manage_events.php");
    }
    exit;
} // --- End POST Handler ---

// --- Handle Delete Request (GET - User clicked Delete link) ---
if (isset($_GET['delete_id']) && filter_var($_GET['delete_id'], FILTER_VALIDATE_INT)) {
    // *** Keep the existing Delete handling logic here ***
    // It also redirects, so it won't interfere directly with display
     $delete_id = (int)$_GET['delete_id'];

     // Check if RSVPs exist for this event (using ON DELETE RESTRICT is recommended)
     $stmtCheckRsvp = $conn->prepare("SELECT id FROM rsvps WHERE event_id = ? LIMIT 1");
     if ($stmtCheckRsvp) {
         $stmtCheckRsvp->bind_param("i", $delete_id);
         $stmtCheckRsvp->execute();
         $stmtCheckRsvp->store_result();

         if ($stmtCheckRsvp->num_rows > 0) {
             $_SESSION['error_message'] = "Cannot delete event: RSVPs are linked to it. Please delete associated RSVPs first or contact support.";
         } else {
             // No RSVPs linked, proceed with deletion
             $stmtDelete = $conn->prepare("DELETE FROM events WHERE id = ?");
             if ($stmtDelete) {
                 $stmtDelete->bind_param("i", $delete_id);
                 if ($stmtDelete->execute()) {
                     // Check if row was actually deleted
                     if ($stmtDelete->affected_rows > 0) {
                         $_SESSION['success_message'] = "Event deleted successfully.";
                     } else {
                          // Use warning message session variable if available, otherwise error
                          $msg_key = isset($_SESSION['warning_message']) ? 'warning_message' : 'error_message';
                          $_SESSION[$msg_key] = "Event not found for deletion (ID: " . $delete_id . "). It might have already been deleted.";
                     }
                 } else {
                      $_SESSION['error_message'] = "Error deleting event: " . $stmtDelete->error;
                 }
                 $stmtDelete->close();
             } else {
                  $_SESSION['error_message'] = "Error preparing delete statement: " . $conn->error;
             }
         }
         $stmtCheckRsvp->close();
     } else {
          $_SESSION['error_message'] = "Error preparing RSVP check query: " . $conn->error;
     }

     // Redirect to clear GET parameters and show messages (clean URL)
     header("Location: manage_events.php");
     exit;
} // --- End Delete Handler ---


// --- Build Dynamic SQL Clauses and Parameters for Filtering (for list display) ---
$whereClauses = [];
$params = []; // Parameters for binding WHERE clauses
$paramTypes = ""; // Parameter types string for WHERE clauses

if (!empty($search_name)) {
    $whereClauses[] = "event_name LIKE ?";
    $params[] = "%" . $search_name . "%";
    $paramTypes .= "s";
}
if ($search_active === '1' || $search_active === '0') { // Check specific values
    $whereClauses[] = "is_active = ?";
    $params[] = (int)$search_active;
    $paramTypes .= "i";
}

// --- CORRECTED Actual Thaal Count Filtering ---
$thaalConditions = [];
// Check if either min or max is provided and is a valid non-negative integer
$valid_thaal_filter_exists = ($search_thaals_min !== null || $search_thaals_max !== null);

if ($valid_thaal_filter_exists) {
    // Check for invalid range first (only if both are provided)
    if ($search_thaals_min !== null && $search_thaals_max !== null && $search_thaals_min > $search_thaals_max) {
         if (empty($page_error_message)) { // Avoid overwriting other errors
            $page_error_message = "Thaal Count: Min value cannot be greater than Max value.";
         }
        // Prevent adding any thaal conditions if range is invalid
        $valid_thaal_filter_exists = false;
    } else {
        // Add min condition if provided
        if ($search_thaals_min !== null) {
            // This condition naturally excludes NULLs because NULL >= X is false/unknown
            $thaalConditions[] = "actual_thaal_count >= ?";
            $params[] = $search_thaals_min;
            $paramTypes .= "i";
        }
        // Add max condition if provided
        if ($search_thaals_max !== null) {
            // This condition naturally excludes NULLs because NULL <= X is false/unknown
            $thaalConditions[] = "actual_thaal_count <= ?";
            $params[] = $search_thaals_max;
            $paramTypes .= "i";
        }
    }
}
// Add the combined condition group to the main WHERE clause ONLY if valid thaal conditions were generated
if ($valid_thaal_filter_exists && !empty($thaalConditions)) {
    $whereClauses[] = "(" . implode(" AND ", $thaalConditions) . ")";
}
// --- END CORRECTED Thaal Filtering ---


// Created Date Filtering
if (!empty($search_created_after)) {
    // Optional: Add stronger date validation if needed
    $whereClauses[] = "DATE(created_at) >= ?"; // Compare date part only
    $params[] = $search_created_after;
    $paramTypes .= "s";
}
if (!empty($search_created_before)) {
    // Optional: Add stronger date validation if needed
    $whereClauses[] = "DATE(created_at) <= ?"; // Compare date part only
    $params[] = $search_created_before;
    $paramTypes .= "s";
     // Optional: Check if 'after' is later than 'before'
     if (!empty($search_created_after) && $search_created_after > $search_created_before) {
          if (empty($page_error_message)) { // Avoid overwriting other errors
                $page_error_message = "Created Date: 'From' date cannot be later than 'To' date.";
          }
     }
}

$whereSql = "";
if (!empty($whereClauses)) {
    $whereSql = "WHERE " . implode(" AND ", $whereClauses);
}

// --- Pagination Logic ---
$total_events = 0;
$total_pages = 1;

// 1. Get Total Count of Events MATCHING FILTERS (only if no major error yet)
if ($page_error_message === null) {
    $sqlCount = "SELECT COUNT(*) as total FROM events {$whereSql}";
    $stmtCount = $conn->prepare($sqlCount);

    if ($stmtCount) {
        if (!empty($paramTypes)) {
            try {
                $stmtCount->bind_param($paramTypes, ...$params);
            } catch (Exception $e) {
                $page_error_message = "Error binding count parameters: " . $e->getMessage();
                $stmtCount->close(); $stmtCount = false;
            }
        }
        if ($stmtCount && $stmtCount->execute()) {
            $resultCount = $stmtCount->get_result();
            if ($rowCount = $resultCount->fetch_assoc()) {
                $total_events = (int)$rowCount['total'];
            }
        } elseif ($stmtCount) {
            // Don't overwrite filter logic errors with execution errors if possible
            if (empty($page_error_message)) {
                 $page_error_message = "Error executing count query: " . $stmtCount->error;
            }
        }
        if ($stmtCount) $stmtCount->close();
    } else { // Prepare failed
        if (empty($page_error_message)) {
            $page_error_message = "Error preparing count query: " . $conn->error;
        }
    }
}

// Calculate pagination details
if ($total_events > 0 && $page_error_message === null) {
    $total_pages = ceil($total_events / $items_per_page);
    // Ensure current page is within valid bounds
    if ($current_page > $total_pages) $current_page = $total_pages;
    if ($current_page < 1) $current_page = 1;
    $offset = ($current_page - 1) * $items_per_page;
} else {
    // If no results or an error occurred, reset pagination
    $current_page = 1;
    $total_pages = 1;
    $offset = 0;
}

// --- Fetch Paginated List of Events MATCHING FILTERS ---
$eventsList = []; // Use a different variable name to avoid confusion with $edit_event
// Only attempt to fetch if no errors and there are records potentially matching filters
if ($page_error_message === null && $total_events > 0) {
    $sqlList = "SELECT id, event_name, custom_message, is_active, created_at, actual_thaal_count
                FROM events
                {$whereSql}
                ORDER BY created_at DESC -- Order descending by creation date
                LIMIT ? OFFSET ?";

    $stmtList = $conn->prepare($sqlList);
    if ($stmtList) {
        $listParams = $params; // Copy search params
        $listParams[] = $items_per_page;
        $listParams[] = $offset;
        $listParamTypes = $paramTypes . "ii"; // Add types for limit/offset

        try {
             $stmtList->bind_param($listParamTypes, ...$listParams);
             if ($stmtList->execute()) {
                 $resultList = $stmtList->get_result();
                 while($row = $resultList->fetch_assoc()) {
                     $eventsList[] = $row; // Populate array for current page display
                 }
             } else {
                if (empty($page_error_message)) {
                    $page_error_message = "Error executing event list query: " . $stmtList->error;
                }
             }
         } catch (Exception $e) {
            if (empty($page_error_message)) {
                $page_error_message = "Error binding parameters for event list query: " . $e->getMessage();
            }
         }
        $stmtList->close();
    } else { // Prepare failed
        if (empty($page_error_message)) {
            $page_error_message = "Error preparing event list query: " . $conn->error;
        }
    }
} elseif ($page_error_message === null && $total_events === 0) {
     // No error, but 0 total matching events. $eventsList remains empty.
     // Message handled in HTML.
}


$conn->close(); // Close DB connection after all operations for this request

// --- Build Base Query String for Pagination Links (preserving search filters) ---
$base_query_params = [
    'search_name' => $search_name,
    'search_active' => $search_active,
    // Pass the original input values for thaals, even if null, to repopulate form
    'search_thaals_min' => filter_input(INPUT_GET, 'search_thaals_min'),
    'search_thaals_max' => filter_input(INPUT_GET, 'search_thaals_max'),
    'search_created_after' => $search_created_after,
    'search_created_before' => $search_created_before
];
// Remove empty/null search parameters to keep URLs clean
$base_query_params = array_filter($base_query_params, function($value) { return $value !== '' && $value !== null; });

?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin - Manage Events</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="style.css"> <!-- Link your main stylesheet -->
    <style>
        /* Reuse styles from manage_hof.php and reports.php */
        body { font-family: sans-serif; margin: 0; padding:0; background-color: #f8f9fa; font-size: 15px; line-height: 1.5; }
        .admin-nav { background-color: #ffffff; padding: 10px 20px; margin-bottom: 20px; border-radius: 0; /* Full width */ text-align: right; border-bottom: 1px solid #dee2e6; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: flex-end;}
        .admin-nav span { margin-right: auto; font-weight: bold; color: #495057;} /* Welcome msg on left */
        .admin-nav a { margin-left: 15px; text-decoration: none; color: #007bff; font-weight: 500; }
        .admin-nav a:hover { text-decoration: underline; }

        .container { max-width: 1200px; margin: 20px auto; background-color: #fff; padding: 25px; border-radius: 5px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); border: 1px solid #dee2e6;}
        h1 { text-align: center; margin-top: 0; margin-bottom: 25px; color: #343a40; }

        /* Message Styles */
        .message { padding: 12px 15px; margin-bottom: 20px; border-radius: 4px; border: 1px solid transparent; font-size: 0.95em; }
        .success { background-color: #d1e7dd; color: #0f5132; border-color: #badbcc;}
        .error { background-color: #f8d7da; color: #842029; border-color: #f5c2c7;}
        .warning { background-color: #fff3cd; color: #664d03; border-color: #ffecb5;}

        /* Form Styles */
        .form-container, .search-form { border: 1px solid #dee2e6; padding: 20px; margin-bottom: 30px; background-color: #fdfdff; border-radius: 5px; }
        .form-container h2, .search-form h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 20px; font-size: 1.3em; color: #495057; font-weight: 600; }
        .form-row { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; min-width: 180px; } /* Adjust min-width as needed */
        .form-group.full-width { flex-basis: 100%; min-width: 100%;}
        .form-group label { display: block; margin-bottom: 6px; font-weight: bold; font-size: 0.9em; color: #495057;}
        .form-group input[type=text],
        .form-group input[type=number],
        .form-group input[type=search],
        .form-group input[type=date],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 0.95em;
        }
        .form-group textarea { min-height: 100px; font-family: inherit; resize: vertical; }
        .form-group input[type=checkbox] { margin-right: 5px; vertical-align: middle; width: auto; height: auto; }
        .form-group label.checkbox-label { display: inline-block; font-weight: normal; margin-bottom: 0; margin-left: 3px;}
        .form-group small { display: block; margin-top: 5px; font-size: 0.85em; color: #6c757d; }
        .form-container button, .search-form button { padding: 10px 20px; color: white; border: none; cursor: pointer; border-radius: 4px; font-size: 1em; font-weight: 500; }
        .form-container button { background-color: #198754; } /* Green for Add/Update */
        .search-form button { background-color: #0d6efd; /* Blue for Search */ margin-top: 28px; /* Align with labels */}
        .form-container button:hover { background-color: #157347; }
        .search-form button:hover { background-color: #0b5ed7; }
        .form-container .cancel-button { background-color: #6c757d; margin-left: 10px; color: white; text-decoration: none; padding: 10px 20px; border-radius: 4px; font-size: 1em; display: inline-block; }
        .form-container .cancel-button:hover { background-color: #5a6268; }
        .range-filter-group { display: flex; gap: 10px; align-items: center; }
        .range-filter-group .form-group { flex-grow: 1; flex-basis: auto; } /* Let inputs grow */
        .range-filter-group span { margin: 0 5px; align-self: flex-end; padding-bottom: 10px;}

        /* Table Styles */
        h2.list-header { margin-top: 30px; border-bottom: 1px solid #eee; padding-bottom: 10px; font-size: 1.4em; color: #343a40;}
        .table-container { overflow-x: auto; margin-top: 20px; border: 1px solid #dee2e6; border-radius: 5px;}
        table { width: 100%; border-collapse: collapse; margin: 0; background-color: white; }
        th, td { border: none; border-bottom: 1px solid #dee2e6; padding: 10px 12px; text-align: left; vertical-align: top; /* Changed to top for message preview */ font-size: 0.95em; }
        thead tr:first-child th { border-top: none; }
        tbody tr:last-child td { border-bottom: none; }
        th { background-color: #f8f9fa; font-weight: 600; position: sticky; top: 0; z-index: 1;}
        tbody tr:nth-child(odd) { background-color: #fdfdfe; }
        tbody tr:hover { background-color: #f1f1f1; }
        td a { margin-right: 5px; text-decoration: none; color: #0d6efd;}
        td a:hover { text-decoration: underline;}
        td .delete-link { color: #dc3545; }
        td .delete-link:hover { color: #a71d2a; }
        .message-preview { max-height: 60px; overflow: hidden; display: block; } /* Limit height */

        /* Pagination Styles */
        .pagination-container { display: flex; justify-content: space-between; align-items: center; margin: 30px 0 10px 0; padding: 0 5px; }
        .pagination-info { color: #6c757d; font-size: 0.9em; }
        .pagination { text-align: right; margin: 0; padding: 0; }
        .pagination a, .pagination span { display: inline-block; padding: 8px 14px; margin-left: 5px; border: 1px solid #dee2e6; border-radius: 4px; color: #0d6efd; text-decoration: none; background-color: white; font-size: 0.9em;}
        .pagination a:hover { background-color: #e9ecef; border-color: #ced4da;}
        .pagination span.current-page { background-color: #0d6efd; color: white; border-color: #0d6efd; font-weight: bold;}
        .pagination span.disabled { color: #6c757d; border-color: #dee2e6; background-color: #f8f9fa; cursor: default;}
        .pagination span.ellipsis { border: none; background: none; color: #6c757d; padding: 8px 5px;}


        .no-results { padding: 25px; text-align: center; font-style: italic; color: #6c757d; background-color: #f8f9fa; border: 1px dashed #dee2e6; border-radius: 4px; margin-top: 20px; }

    </style>
</head>
<body>

    <!-- Admin Navigation Bar -->
    <div class="admin-nav">
        <span>Welcome, Admin!</span>
        <a href="reports.php">View Reports</a>
        <a href="manage_hof.php">Manage HoF</a>
        <a href="manage_events.php">Manage Events</a> <!-- Current Page -->
        <a href="logout.php">Logout</a>
    </div>

<div class="container">
    <h1>Manage Events</h1>

    <!-- Display Success/Warning/Error Messages -->
    <?php if ($page_success_message): ?>
        <div class="message success"><?php echo htmlspecialchars($page_success_message); ?></div>
    <?php endif; ?>
    <?php if ($_SESSION['warning_message'] ?? null): /* Check session directly for warnings after delete redirect */ ?>
        <div class="message warning"><?php echo htmlspecialchars($_SESSION['warning_message']); unset($_SESSION['warning_message']); ?></div>
    <?php endif; ?>
    <?php if ($page_error_message): ?>
        <div class="message error"><?php echo htmlspecialchars($page_error_message); ?></div>
    <?php endif; ?>

    <!-- Add/Edit Form -->
    <div class="form-container">
        <h2><?php echo $edit_event ? 'Edit Event' : 'Add New Event'; ?></h2>
        <form action="manage_events.php" method="POST">
            <?php if ($edit_event): ?>
                <input type="hidden" name="event_id" value="<?php echo $edit_event['id']; ?>">
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="event_name">Event Name:</label>
                    <input type="text" id="event_name" name="event_name" value="<?php echo htmlspecialchars($edit_event['event_name'] ?? ''); ?>" required>
                </div>
                 <div class="form-group">
                    <label for="actual_thaal_count">Actual Thaal (Table) Count:</label>
                    <input type="number" id="actual_thaal_count" name="actual_thaal_count" min="0" step="1" value="<?php echo htmlspecialchars($edit_event['actual_thaal_count'] ?? ''); ?>" placeholder="Leave blank if not set">
                     <small>Enter the final number of thaals used (optional).</small>
                </div>
            </div>

            <div class="form-group full-width">
                <label for="custom_message">Custom Telegram Message:</label>
                <textarea id="custom_message" name="custom_message"><?php echo htmlspecialchars($edit_event['custom_message'] ?? "Salaam {NAME},\n\nThank you for your RSVP for the {EVENT_NAME}.\nITS: {ITS}\nAttendees: {COUNT}\n\nWe look forward to seeing you!"); ?></textarea>
                <small>Use placeholders: {NAME}, {ITS}, {COUNT}, {EVENT_NAME}</small>
            </div>


            <div class="form-group">
                 <label for="is_active" class="checkbox-label">
                     <input type="checkbox" id="is_active" name="is_active" value="1" <?php
                        // Checked if: Adding new OR Editing and is_active is 1
                        $checked = (!isset($edit_event) || (isset($edit_event['is_active']) && $edit_event['is_active'] == 1));
                        echo $checked ? 'checked' : '';
                     ?>>
                     Active (Show in RSVP form dropdown)
                 </label>
            </div>

            <div>
                <button type="submit"><?php echo $edit_event ? 'Update Event' : 'Add Event'; ?></button>
                <?php if ($edit_event): ?>
                    <a href="manage_events.php" class="cancel-button">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Search Form -->
     <div class="search-form">
        <h2>Search / Filter Event List</h2>
        <form action="manage_events.php" method="GET">
            <div class="form-row">
                <div class="form-group">
                    <label for="search_name">Event Name Contains:</label>
                    <input type="search" id="search_name" name="search_name" value="<?php echo htmlspecialchars($search_name); ?>">
                </div>
                <div class="form-group">
                    <label for="search_active">Status:</label>
                    <select id="search_active" name="search_active">
                        <option value="" <?php echo ($search_active == '') ? 'selected' : ''; ?>>All</option>
                        <option value="1" <?php echo ($search_active == '1') ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo ($search_active == '0') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                 <div class="form-group" style="flex-basis: 35%;"> <!-- Adjust width -->
                     <label>Actual Thaals Range:</label>
                     <div class="range-filter-group">
                        <div class="form-group">
                            <input type="number" id="search_thaals_min" name="search_thaals_min" min="0" value="<?php echo htmlspecialchars(filter_input(INPUT_GET, 'search_thaals_min') ?? ''); ?>" placeholder="Min">
                        </div>
                        <span>-</span>
                        <div class="form-group">
                             <input type="number" id="search_thaals_max" name="search_thaals_max" min="0" value="<?php echo htmlspecialchars(filter_input(INPUT_GET, 'search_thaals_max') ?? ''); ?>" placeholder="Max">
                        </div>
                    </div>
                     <small>Filters events with a non-blank count in range.</small>
                </div>
            </div>
             <div class="form-row">
                 <div class="form-group" style="flex-basis: 45%;"> <!-- Adjust width -->
                    <label>Created Date Range:</label>
                    <div class="range-filter-group">
                        <div class="form-group">
                            <input type="date" id="search_created_after" name="search_created_after" value="<?php echo htmlspecialchars($search_created_after); ?>" title="Created on or after">
                        </div>
                        <span>-</span>
                        <div class="form-group">
                             <input type="date" id="search_created_before" name="search_created_before" value="<?php echo htmlspecialchars($search_created_before); ?>" title="Created on or before">
                        </div>
                    </div>
                </div>
                 <div class="form-group" style="align-self: flex-end; text-align: right; flex: 1;"> <!-- Push button right -->
                     <button type="submit">Search</button>
                </div>
            </div>
             <input type="hidden" name="page" value="1"> <!-- Reset to page 1 on new search -->
        </form>
    </div>


    <!-- List Existing Events -->
    <h2 class="list-header">
        Existing Events List
         <?php if ($page_error_message === null) : ?>
            (<?php echo number_format($total_events); ?> found<?php echo (!empty($base_query_params) ? ' matching filters' : ''); ?>)
        <?php endif; ?>
    </h2>

    <?php if (!empty($eventsList)): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Message Preview</th>
                        <th>Active</th>
                        <th>Actual Thaals</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($eventsList as $eventItem): // Use different variable name ?>
                    <tr>
                        <td><?php echo htmlspecialchars($eventItem['event_name']); ?></td>
                        <td>
                            <span class="message-preview" title="<?php echo htmlspecialchars($eventItem['custom_message']); ?>">
                                <?php echo nl2br(htmlspecialchars(substr($eventItem['custom_message'], 0, 100))) . (strlen($eventItem['custom_message']) > 100 ? '...' : ''); ?>
                            </span>
                        </td>
                        <td><?php echo $eventItem['is_active'] ? 'Yes' : 'No'; ?></td>
                        <td><?php echo isset($eventItem['actual_thaal_count']) ? number_format($eventItem['actual_thaal_count']) : '<em style="color:#999;">Not Set</em>'; ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($eventItem['created_at'])); ?></td>
                        <td>
                            <a href="manage_events.php?edit_id=<?php echo $eventItem['id']; ?>" title="Edit Event">Edit</a> |
                             <a href="manage_events.php?delete_id=<?php echo $eventItem['id']; ?>" class="delete-link" title="Delete Event" onclick="return confirm('Are you sure you want to delete the event \'<?php echo htmlspecialchars(addslashes($eventItem['event_name'])); ?>\'?\nThis cannot be undone and might fail if RSVPs exist.');">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div> <!-- end table-container -->

        <!-- Pagination Controls -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                 <div class="pagination-info">
                    Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $items_per_page, $total_events); ?> of <?php echo number_format($total_events); ?> results
                </div>
                <div class="pagination">
                    <?php // Previous Page Link ?>
                    <?php if ($current_page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($base_query_params, ['page' => $current_page - 1])); ?>">« Prev</a>
                    <?php else: ?>
                        <span class="disabled">« Prev</span>
                    <?php endif; ?>

                    <?php // Page Number Links (Simplified with basic ellipsis)
                        $link_count = 0; // Track links shown to manage ellipsis
                        for ($i = 1; $i <= $total_pages; $i++):
                             $showPage = false;
                             // Determine if the page link should be shown
                             if ($total_pages <= 7 || $i == 1 || $i == $total_pages || ($i >= $current_page - 2 && $i <= $current_page + 2)) {
                                 $showPage = true;
                             }

                             // Handle Ellipsis
                             // Ellipsis before current group
                             if ($i > 1 && $i == $current_page - 3 && $total_pages > 7) {
                                 echo '<span class="ellipsis">...</span>';
                             }

                             // Show the page link/span
                             if ($showPage) {
                                 $link_count++;
                                 if ($i == $current_page): ?>
                                     <span class="current-page"><?php echo $i; ?></span>
                                 <?php else: ?>
                                     <a href="?<?php echo http_build_query(array_merge($base_query_params, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                 <?php endif;
                             }

                              // Ellipsis after current group
                              if ($i < $total_pages && $i == $current_page + 3 && $total_pages > 7) {
                                   echo '<span class="ellipsis">...</span>';
                              }
                        endfor;
                    ?>

                    <?php // Next Page Link ?>
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($base_query_params, ['page' => $current_page + 1])); ?>">Next »</a>
                    <?php else: ?>
                        <span class="disabled">Next »</span>
                    <?php endif; ?>
                </div>
            </div>
         <?php endif; // end if total_pages > 1 ?>

    <?php elseif ($page_error_message === null): // Only show 'no results' if there wasn't a general error ?>
        <p class="no-results">
            <?php echo (!empty($base_query_params)) ? 'No events found matching your search criteria.' : 'No events found in the database. Please add one using the form above.'; ?>
        </p>
    <?php endif; // End check for empty $eventsList and no error ?>

</div> <!-- end .container -->

</body>
</html>