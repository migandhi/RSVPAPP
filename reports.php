<?php
session_start(); // Start session first

// --- Admin Access Check ---
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    $_SESSION['login_error'] = 'Admin access required for this page.';
    header('Location: login.php');
    exit; // Stop script execution immediately
}

// --- Database Connection ---
require 'db_connect.php';

// --- Configuration ---
$items_per_page = 15; // Families per page in the table
$members_per_thaal = 8; // For prediction calculation

// --- Page Level Variables ---
$page_error_message = null; // To store general page errors (like DB connection)

// --- Get Filter/Selection Values from URL (with defaults/sanitization) ---
$search_term = trim($_GET['search_event'] ?? '');         // Event search string
$selected_event_id_from_get = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT); // Event ID from URL
$search_hof = trim($_GET['search_hof'] ?? '');            // HoF/ITS search string (filters based on *current* HoF details)
$filter_attendees_op = $_GET['filter_attendees_op'] ?? 'gte'; // Comparison operator (gte, lte, eq) - default to >=
// Validate attendee count filter - must be non-negative integer or null if blank/invalid
$filter_attendees_count_input = trim($_GET['filter_attendees_count'] ?? '');
$filter_attendees_count = null;
if ($filter_attendees_count_input !== '') {
    $filtered_count = filter_var($filter_attendees_count_input, FILTER_VALIDATE_INT);
    if ($filtered_count !== false && $filtered_count >= 0) {
        $filter_attendees_count = $filtered_count;
    } else {
         // Maybe set a warning, but treating as null is okay for filtering
    }
}
$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);

// --- Event Handling: Fetch All Events & Determine Selected ---
$all_events = [];
$selected_event_id = null;
$selected_event_details = null;

// Fetch all events for the selector list/validation
$eventSql = "SELECT id, event_name, is_active, created_at, actual_thaal_count FROM events ORDER BY created_at DESC";
$eventResult = $conn->query($eventSql);
if ($eventResult) {
    while ($row = $eventResult->fetch_assoc()) {
        $all_events[] = $row; // Populate the array with all events
    }
} else {
    $page_error_message = "Error fetching events list: " . htmlspecialchars($conn->error);
}

// Determine the selected event based on GET parameter or default to the most recent
if ($selected_event_id_from_get !== null) {
    foreach ($all_events as $event) {
        if ($event['id'] == $selected_event_id_from_get) {
            $selected_event_id = $event['id'];
            $selected_event_details = $event; // Store all details of the found event
            break;
        }
    }
     // If ID from GET wasn't found, explicitly nullify
     if ($selected_event_id === null) $selected_event_details = null;
} elseif (!empty($all_events)) {
    // Default to the first (most recent) event if no valid ID provided and events exist
    $selected_event_id = $all_events[0]['id'];
    $selected_event_details = $all_events[0];
}

// Set display name and actual thaal count based on selected event
$selected_event_name = $selected_event_details ? $selected_event_details['event_name'] : "No Event Selected / Found";
$actual_thaal_count = $selected_event_details ? $selected_event_details['actual_thaal_count'] : null;

// --- Initialize Report & Pagination Variables ---
$familyCounts = []; // Paginated results for display
$totalAttendees = 0; // Overall total for the selected event (unfiltered)
$predicted_thaal_count = 0; // Based on overall total
$total_families = 0; // Total families *matching filters* (for pagination)
$total_pages = 1; // Default
$offset = ($current_page - 1) * $items_per_page;

// --- Build Dynamic SQL Clauses and Parameters for Filtering ---
$whereClauses = [];
$params = []; // Parameters for binding WHERE clauses
$paramTypes = ""; // Parameter types string for WHERE clauses

// Base WHERE clause for event (always needed if an event is selected)
if ($selected_event_id !== null) {
    $whereClauses[] = "r.event_id = ?";
    $params[] = $selected_event_id;
    $paramTypes .= "i";
}

// Add HoF/ITS filter if provided (searches *current* details via JOIN)
if (!empty($search_hof)) {
    // Note: Requires JOIN to heads_of_family (aliased as 'h')
    $whereClauses[] = "(h.name LIKE ? OR h.its_number LIKE ?)";
    $like_search_hof = "%" . $search_hof . "%";
    $params[] = $like_search_hof;
    $params[] = $like_search_hof;
    $paramTypes .= "ss";
}

// Prepare HAVING clause for Attendee count filter (applies *after* GROUP BY)
// Uses the actual aggregate function because alias isn't available in count subquery
$havingClause = "";
$havingParams = []; // Separate params for HAVING part of query
$havingParamTypes = "";
if ($filter_attendees_count !== null) { // Only apply if count is valid non-negative integer
    $op = "=";
    if ($filter_attendees_op == 'gte') $op = ">=";
    if ($filter_attendees_op == 'lte') $op = "<=";
    // Use the aggregate function directly here
    $havingClause = "HAVING SUM(r.attendee_count) " . $op . " ?";
    $havingParams[] = $filter_attendees_count;
    $havingParamTypes .= "i";
}

// Combine WHERE clauses into SQL string
$whereSql = "";
if (!empty($whereClauses)) {
    $whereSql = "WHERE " . implode(" AND ", $whereClauses);
}

// --- Fetch Report Data ONLY if a valid event is selected and no page error yet ---
if ($selected_event_id !== null && $page_error_message === null) {

    // 1. Get Total Count of Families MATCHING FILTERS (for pagination)
    // Note: JOIN is needed here *if* $search_hof filter is active
    $sqlCountFamilies = "SELECT COUNT(*) as total FROM (
                            SELECT r.hof_id -- Select distinct HoF ID
                            FROM rsvps r ";
    // Add JOIN only if HoF filter is present
    if (!empty($search_hof)) {
        $sqlCountFamilies .= " JOIN heads_of_family h ON r.hof_id = h.id ";
    }
    $sqlCountFamilies .= " {$whereSql}          -- Apply WHERE filters
                            GROUP BY r.hof_id     -- Group by HoF
                            {$havingClause}       -- Apply HAVING filters on attendee sum
                         ) AS filtered_families";

    $stmtCount = $conn->prepare($sqlCountFamilies);
    if ($stmtCount) {
        // Combine WHERE and HAVING parameters for this query
        $countParams = array_merge($params, $havingParams);
        $countParamTypes = $paramTypes . $havingParamTypes;
        if (!empty($countParamTypes)) {
            try {
                $stmtCount->bind_param($countParamTypes, ...$countParams);
                $stmtCount->execute();
                $resultCount = $stmtCount->get_result();
                if ($rowCount = $resultCount->fetch_assoc()) {
                    $total_families = (int)$rowCount['total'];
                } else {
                     // Handle case where query runs but returns no rows (e.g., fetch_assoc fails)
                     $total_families = 0;
                }
            } catch (Exception $e) {
                 $page_error_message = "Error executing family count query: " . htmlspecialchars($e->getMessage());
            }
        } else {
             // Execute without params if no filters applied (only event_id)
            try {
                 // Since event_id is always present, we should always have params.
                 // This block might be redundant but kept for safety.
                 // Re-evaluating: $params WILL contain event_id, so bind_param is always needed.
                 // If bind_param somehow fails above, this block shouldn't execute.
                 // Let's simplify: assume bind_param is always attempted if params exist.
                 // $stmtCount->execute(); // Potentially problematic without bind_param if params exist
                 // $resultCount = $stmtCount->get_result();
                 // if ($rowCount = $resultCount->fetch_assoc()) {
                 //     $total_families = (int)$rowCount['total'];
                 // } else {
                 //      $total_families = 0;
                 // }
                 // If we reach here without params, it likely means only event_id filter exists.
                 // The bind_param logic should handle this. If not, there's a logic error above.
                 // Let's rely on the bind_param path.
            } catch (Exception $e) {
                 // Should not be reached if bind_param logic is correct.
                 $page_error_message = "Error executing parameterless family count query: " . htmlspecialchars($e->getMessage());
            }
        }
        $stmtCount->close();
    } else { // Prepare failed
        $page_error_message = "Error preparing family count total query: " . htmlspecialchars($conn->error);
    }

    // Calculate pagination details based on filtered count
    if ($total_families > 0 && $page_error_message === null) {
        $total_pages = ceil($total_families / $items_per_page);
        if ($current_page > $total_pages) $current_page = $total_pages; // Adjust page if out of bounds
        if ($current_page < 1) $current_page = 1;
        $offset = ($current_page - 1) * $items_per_page;
    } else {
        $current_page = 1; // Reset if no families or error occurred
        $total_pages = 1;
        $offset = 0;
    }

    // 2. Get Paginated Family Counts MATCHING FILTERS for display (only if no prior errors and families exist)
    if ($page_error_message === null && $total_families > 0) {
        // Select the `_at_rsvp` fields for display
        $sqlFamily = "SELECT
                        r.hof_id, -- Keep for potential future use
                        r.hof_name_at_rsvp AS hof_name,
                        r.hof_its_at_rsvp AS its_number,
                        r.hof_sabil_at_rsvp AS sabil_number,
                        SUM(r.attendee_count) as total_attendees
                      FROM rsvps r ";
        // Add JOIN only if HoF filter is present (required for the filter)
        if (!empty($search_hof)) {
            $sqlFamily .= " JOIN heads_of_family h ON r.hof_id = h.id ";
        }
        $sqlFamily .= " {$whereSql}          -- Apply WHERE filters (might use h.name/h.its)
                      GROUP BY r.hof_id, r.hof_name_at_rsvp, r.hof_its_at_rsvp, r.hof_sabil_at_rsvp -- Group by RSVP-time details
                      {$havingClause}       -- Apply HAVING filters on attendee sum
                      ORDER BY hof_name     -- Order results by the name stored at RSVP time
                      LIMIT ? OFFSET ?";

        $stmtFamily = $conn->prepare($sqlFamily);
        if ($stmtFamily === false) {
            $page_error_message = "Error preparing family list query: " . htmlspecialchars($conn->error);
        } else {
            // Combine WHERE, HAVING, and LIMIT/OFFSET parameters
            $familyParams = array_merge($params, $havingParams);
            $familyParams[] = $items_per_page; // Add limit
            $familyParams[] = $offset;         // Add offset
            $familyParamTypes = $paramTypes . $havingParamTypes . "ii"; // Add types for limit/offset

            if (!empty($familyParamTypes)) {
                try {
                     $stmtFamily->bind_param($familyParamTypes, ...$familyParams);
                     $stmtFamily->execute();
                     $resultFamily = $stmtFamily->get_result();
                     while($row = $resultFamily->fetch_assoc()) {
                         $familyCounts[] = $row; // Populate array for current page display
                     }
                 } catch (Exception $e) {
                      $page_error_message = "Error executing family list query: " . htmlspecialchars($e->getMessage());
                 }
            } else {
                 // Should not happen if event_id is always set, but handle defensively
                 $page_error_message = "Parameter types missing for family list query.";
            }
            $stmtFamily->close();
        }
    } // end if $total_families > 0

    // 3. Get Overall Total Attendee Count for the selected event (UNFILTERED - for summary) (only if no prior error)
    if ($page_error_message === null) {
        $sqlTotalOverall = "SELECT SUM(attendee_count) as total FROM rsvps WHERE event_id = ?";
        $stmtTotalOverall = $conn->prepare($sqlTotalOverall);
        if ($stmtTotalOverall) {
            $stmtTotalOverall->bind_param("i", $selected_event_id);
            $stmtTotalOverall->execute();
            $resultTotalOverall = $stmtTotalOverall->get_result();
            if ($rowTotalOverall = $resultTotalOverall->fetch_assoc()) {
                $totalAttendees = $rowTotalOverall['total'] ?? 0; // Get overall total
            } else {
                $totalAttendees = 0; // Ensure it's 0 if query returns no rows
            }
            $stmtTotalOverall->close();
             // Calculate predicted count based on overall total
             if ($totalAttendees > 0) {
                 $predicted_thaal_count = ceil($totalAttendees / $members_per_thaal);
             }
        } else { // Prepare failed
             $page_error_message = "Error preparing overall total attendee count: " . htmlspecialchars($conn->error);
        }
    }

} // end if ($selected_event_id !== null && $page_error_message === null)


// --- Handle CSV Export Request ---
// Check for export request *after* main data fetching logic so we have totals etc.
// But execute *before* HTML output begins.
if ($selected_event_id !== null && isset($_GET['export']) && $_GET['export'] == 'csv' && $page_error_message === null) {
    // No need to reopen DB connection if $conn is still valid.
    // Reconnect if necessary (e.g., if closed previously, though it shouldn't be here)
    if (!$conn || !$conn->ping()) {
         require 'db_connect.php'; // Re-establish connection
         if ($conn->connect_error) {
              // Set error and prevent export if reconnect fails
              $page_error_message = "Database connection error during export. Please try again.";
              // Exit export attempt if connection failed
              goto end_export_handling; // Jump past export logic
         }
    }


    $exportData = [];
    // Fetch ALL family counts MATCHING FILTERS (no pagination for export)
    // Select `_at_rsvp` details for export
    $sqlExport = "SELECT
                    r.hof_name_at_rsvp AS hof_name,
                    r.hof_its_at_rsvp AS its_number,
                    r.hof_sabil_at_rsvp AS sabil_number,
                    SUM(r.attendee_count) as attendee_count,
                    e.event_name,
                    MIN(r.rsvp_timestamp) as rsvp_timestamp
                  FROM rsvps r
                  JOIN events e ON r.event_id = e.id ";
     // Add JOIN only if HoF filter is present (required for the filter)
     if (!empty($search_hof)) {
        $sqlExport .= " JOIN heads_of_family h ON r.hof_id = h.id ";
     }
     $sqlExport .= " {$whereSql}          -- Apply WHERE filters (might use h.name/h.its)
                  GROUP BY r.hof_id, e.event_name, r.hof_name_at_rsvp, r.hof_its_at_rsvp, r.hof_sabil_at_rsvp -- Group by RSVP-time details
                  {$havingClause}       -- Apply HAVING filter from main page load
                  ORDER BY hof_name";    // Order by RSVP-time name

    $stmtExport = $conn->prepare($sqlExport);

    if($stmtExport) {
        // Bind filter parameters (WHERE + HAVING) - No LIMIT/OFFSET
        $exportParams = array_merge($params, $havingParams);
        $exportParamTypes = $paramTypes . $havingParamTypes;
        $exportSuccess = false; // Flag for successful export execution

        if (!empty($exportParamTypes)) {
             try {
                 $stmtExport->bind_param($exportParamTypes, ...$exportParams);
                 $stmtExport->execute();
                 $resultExport = $stmtExport->get_result();
                 while ($row = $resultExport->fetch_assoc()) {
                     $exportData[] = $row; // Collect all rows for export
                 }
                 $exportSuccess = true;
             } catch (Exception $e) {
                  error_log("Error executing export query with params: " . $e->getMessage());
             }
        } else {
             // Execute without params if no filters applied beyond event_id (should always have event_id param)
             // Let's assume the bind_param path handles the case with only event_id correctly.
             // If $exportParamTypes is truly empty, it implies no event_id filter, which shouldn't happen here.
             // This block might indicate a logic error if reached.
             // try {
             //      $stmtExport->execute();
             //      $resultExport = $stmtExport->get_result();
             //      while ($row = $resultExport->fetch_assoc()) {
             //          $exportData[] = $row;
             //      }
             //      $exportSuccess = true;
             // } catch (Exception $e) {
             //      error_log("Error executing parameterless export query: " . $e->getMessage());
             // }
        }
        $stmtExport->close();

        // Proceed with download only if DB query was successful
        if ($exportSuccess) {
            // --- Generate CSV Output ---
            $safe_event_name = preg_replace('/[^a-z0-9_]/i', '_', $selected_event_name);
            $filename = "Filtered_RSVP_Report_" . $safe_event_name . "_" . date('Ymd') . ".csv";

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            $output = fopen('php://output', 'w');

            // Header Row - Added Sabil Number
            fputcsv($output, ['Head of Family (at RSVP)', 'ITS Number (at RSVP)', 'Sabil Number (at RSVP)', 'Count of Attendees', 'Event Name', 'First RSVP Date']);

            // Data Rows
            foreach ($exportData as $row) {
                fputcsv($output, [
                    $row['hof_name'],
                    $row['its_number'],
                    $row['sabil_number'] ?? '', // Handle potential NULL
                    $row['attendee_count'],
                    $row['event_name'],
                    date('Y-m-d H:i:s', strtotime($row['rsvp_timestamp']))
                ]);
            }

            // Summary Rows
            fputcsv($output, []); // Blank row
            fputcsv($output, ['Filters Applied to This Export']);
            fputcsv($output, ['Event:', $selected_event_name]);
            if (!empty($search_hof)) fputcsv($output, ['Current HoF/ITS Search:', $search_hof]); // Clarify it searches current details
            if ($filter_attendees_count !== null) fputcsv($output, ['Attendee Filter:', $filter_attendees_op . ' ' . $filter_attendees_count]);
            fputcsv($output, []); // Blank row
            fputcsv($output, ['Overall Event Summary (Unfiltered)']);
            fputcsv($output, ['Actual Thaal Count:', isset($actual_thaal_count) ? $actual_thaal_count : 'Not Set']);
            fputcsv($output, ['Total Attendees RSVPd:', $totalAttendees]);
            fputcsv($output, ['Predicted Thaal Count (Based on RSVPs):', $predicted_thaal_count]);
            fputcsv($output, ['Members Per Thaal Used for Prediction:', $members_per_thaal]);

            fclose($output);
            if ($conn && $conn->ping()) { // Close connection if still open
               $conn->close();
            }
            exit; // IMPORTANT: Stop script execution after sending CSV file
        } else {
             // Set page error if export query failed
             $page_error_message = "Error preparing data for export. Please try again.";
        }

    } else { // Prepare failed
         // Handle prepare error for export query
         $page_error_message = "Error preparing database query for export: " . htmlspecialchars($conn->error);
    }
}
// Label for goto jump target
end_export_handling:

// --- End CSV Export Handling ---


// Close the initial DB connection if it's still open and export didn't happen/close it
if ($conn && $conn->ping()) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Enhanced RSVP Reports</title>
    <link rel="stylesheet" href="style.css"> <!-- Your base styles -->
    <style>
         /* General Styles */
         body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; margin: 0; background-color: #f4f7f6; color: #333; font-size: 15px; line-height: 1.5;}
         .container { max-width: 1300px; margin: 20px auto; padding: 0 20px; } /* Wider container */
         a { text-decoration: none; color: #007bff; }
         a:hover { text-decoration: underline; }
         h1, h2, h3 { color: #343a40; margin-top: 1.5em; margin-bottom: 0.8em;}
         h1 { font-size: 2em;} h2 { font-size: 1.6em;} h3 { font-size: 1.3em;}

         /* Admin Nav */
         .admin-nav { background-color: #ffffff; padding: 12px 25px; margin-bottom: 30px; border-radius: 0; /* Full width */ text-align: right; border-bottom: 1px solid #dee2e6; box-shadow: 0 1px 4px rgba(0,0,0,0.06); display: flex; justify-content: flex-end; align-items: center;}
         .admin-nav span { margin-right: auto; font-weight: bold; color: #495057; font-size: 1.1em;}
         .admin-nav a { margin-left: 20px; font-weight: 500; color: #0d6efd;}

         /* Report Header & Filters */
         .report-header { background-color: #fff; padding: 25px 30px; border-radius: 5px; border: 1px solid #dee2e6; margin-bottom: 30px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
         .report-header h1 { margin: 0 0 25px 0; padding-bottom: 15px; border-bottom: 1px solid #eee; text-align: center; font-size: 1.8em; }
         .report-filters { display: flex; flex-wrap: wrap; gap: 25px; align-items: flex-end; } /* Increased gap */
         .filter-group { display: flex; flex-direction: column; }
         .filter-group label { font-weight: bold; margin-bottom: 6px; font-size: 0.9em; color: #495057;}
         .filter-group input[type="search"],
         .filter-group input[type="number"],
         .filter-group select {
             padding: 9px 12px;
             border: 1px solid #ced4da;
             border-radius: 4px;
             font-size: 0.95em;
             height: 40px; /* Consistent height */
             box-sizing: border-box;
         }
         .filter-group input[type="search"]#search_event_input { min-width: 280px; } /* Wider event search */
         .filter-group input[type="search"]#search_hof { min-width: 200px; }
         .filter-group input[type="number"] { width: 90px; text-align: center; }
         .attendee-filter { display: flex; align-items: center; gap: 5px; }
         .report-filters button[type="submit"] {
             padding: 0 25px; cursor: pointer; background-color: #0d6efd; color: white; border: none; border-radius: 4px; font-weight: bold; height: 40px; /* Align height */
             line-height: 40px; /* Vertical center text */
         }
         .report-filters button[type="submit"]:hover { background-color: #0b5ed7;}

         /* Event List (for search) */
         .event-list-container { position: relative; }
         .event-list { display: none; position: absolute; background-color: white; border: 1px solid #ced4da; border-radius: 0 0 4px 4px; /* Match input */ max-height: 300px; overflow-y: auto; list-style: none; margin: -1px 0 0 0; /* Overlap border */ padding: 0; z-index: 1000; min-width: 300px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
         .event-list li { padding: 10px 15px; cursor: pointer; border-bottom: 1px solid #eee; }
         .event-list li:last-child { border-bottom: none; }
         .event-list li:hover { background-color: #e9ecef; }
         .event-list .event-name { font-weight: bold; display: block; margin-bottom: 3px;}
         .event-list .event-details { font-size: 0.85em; color: #6c757d; }
         .event-list .event-details .status-active { color: #198754; font-weight: bold;}
         .event-list .event-details .status-inactive { color: #dc3545; font-weight: bold;}
         .event-list .no-results { padding: 10px 15px; color: #6c757d; font-style: italic; cursor: default;}

        /* Report Title Section */
        .report-title { text-align: center; margin-bottom: 25px; font-size: 1.7em; color: #343a40; font-weight: 500;}
        .report-title #current_event_name_display { font-weight: bold; color: #0d6efd; }

         /* Total Count Display */
         .total-count-display { background-color: #0d6efd; color: white; padding: 30px; text-align: center; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 4px 10px rgba(13, 110, 253, 0.3); }
         .total-count-display p { margin: 0; font-size: 1.4em; opacity: 0.9; font-weight: 300;}
         .total-count-display strong { font-size: 4em; font-weight: 700; display: block; margin-top: 5px; letter-spacing: -1px; line-height: 1; }

         /* Summary Section */
         .summary-section { display: flex; justify-content: space-around; background-color: #ffffff; padding: 25px; border-radius: 5px; margin-bottom: 30px; border: 1px solid #dee2e6; flex-wrap: wrap; gap: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
         .summary-item { text-align: center; padding: 10px; flex-grow: 1; min-width: 180px; }
         .summary-item span { display: block; font-size: 1em; color: #6c757d; margin-bottom: 8px; }
         .summary-item strong { font-size: 2em; color: #343a40; font-weight: 600;}
         .summary-item small { display: block; font-size: 0.8em; color: #6c757d; margin-top: 8px;}

         /* Export Button */
         .export-button-container { text-align: right; margin-bottom: 20px; }
         .export-button-container button { padding: 10px 20px; cursor: pointer; background-color: #198754; color: white; border: none; border-radius: 4px; font-size: 1em; font-weight: bold; }
         .export-button-container button:hover { background-color: #157347; }

         /* Table Styles */
         .table-container { overflow-x: auto; background-color: white; border-radius: 5px; border: 1px solid #dee2e6; box-shadow: 0 1px 4px rgba(0,0,0,0.06); margin-top: 10px;}
         table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
         th, td { border: none; border-bottom: 1px solid #dee2e6; padding: 12px 15px; text-align: left; vertical-align: middle; font-size: 0.95em; }
         th { background-color: #f8f9fa; font-weight: 600; position: sticky; top: 0; z-index: 1;}
         tbody tr:last-child td { border-bottom: none; }
         tbody tr:nth-child(odd) { background-color: #f8f9fa; }
         tbody tr:hover { background-color: #e9ecef; }

         /* Pagination */
         .pagination-container { display: flex; justify-content: space-between; align-items: center; margin: 30px 0 10px 0; padding: 0 5px; }
         .pagination-info { color: #6c757d; font-size: 0.9em; }
         .pagination { text-align: right; margin: 0; padding: 0; }
         .pagination a, .pagination span { display: inline-block; padding: 8px 14px; margin-left: 5px; border: 1px solid #dee2e6; border-radius: 4px; color: #0d6efd; text-decoration: none; background-color: white; font-size: 0.9em;}
         .pagination a:hover { background-color: #e9ecef; border-color: #ced4da;}
         .pagination span.current-page { background-color: #0d6efd; color: white; border-color: #0d6efd; font-weight: bold;}
         .pagination span.disabled { color: #6c757d; border-color: #dee2e6; background-color: #f8f9fa; cursor: default;}
         .pagination span.ellipsis { border: none; background: none; color: #6c757d; padding: 8px 5px;}

         /* Helper Classes */
         .error-message { background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; padding: 12px 15px; border-radius: 4px; margin-bottom: 20px; }
         .no-results-message { text-align:center; font-style:italic; color:#6c757d; padding: 25px; background-color: #f8f9fa; border: 1px dashed #dee2e6; border-radius: 5px; margin-top: 20px;}
    </style>
</head>
<body>
<div class="container">

    <!-- Admin Navigation Bar -->
    <div class="admin-nav">
        <span>Welcome, Admin!</span>
        <a href="reports.php">View Reports</a>
        <a href="manage_hof.php">Manage HoF</a>
        <a href="manage_events.php">Manage Events</a>
        <a href="logout.php">Logout</a>
    </div>

     <!-- Display Page Level Errors -->
     <?php if ($page_error_message !== null): ?>
         <div class="error-message"><?php echo htmlspecialchars($page_error_message); ?></div>
     <?php endif; ?>


    <div class="report-header">
        <h1>RSVP Reports</h1>
        <!-- Filter Form -->
        <form action="reports.php" method="GET" id="report-filter-form">
            <div class="report-filters">
                <!-- Event Filter -->
                <div class="filter-group">
                    <label for="search_event_input">Event:</label>
                     <div class="event-list-container">
                         <input type="search" id="search_event_input" name="search_event" placeholder="Search events..." value="<?php echo htmlspecialchars($search_term); ?>" autocomplete="off">
                         <input type="hidden" name="event_id" id="selected_event_id_hidden" value="<?php echo htmlspecialchars($selected_event_id ?? ''); ?>">
                         <ul class="event-list" id="event_list_ul">
                            <?php if (empty($all_events)): ?>
                                <li class="no-results">No events found. Add events via Manage Events.</li>
                            <?php else: ?>
                                <?php foreach ($all_events as $event): ?>
                                    <li data-id="<?php echo $event['id']; ?>" data-name="<?php echo htmlspecialchars($event['event_name']); ?>">
                                        <span class="event-name"><?php echo htmlspecialchars($event['event_name']); ?></span>
                                        <span class="event-details">
                                            (<?php echo date('M d, Y', strtotime($event['created_at'])); ?>)
                                            <span class="<?php echo $event['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $event['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                                 <!-- Add a placeholder for no results -->
                                 <li class="no-results" style="display: none;">No events match your search.</li>
                            <?php endif; ?>
                         </ul>
                     </div>
                 </div>

                <!-- HoF/ITS Filter -->
                <div class="filter-group">
                    <label for="search_hof">HoF/ITS (Current):</label> <!-- Clarify it searches current -->
                    <input type="search" id="search_hof" name="search_hof" placeholder="Name or ITS Number..." value="<?php echo htmlspecialchars($search_hof); ?>">
                </div>

                <!-- Attendee Filter -->
                <div class="filter-group">
                    <label for="filter_attendees_op">Attendees:</label>
                    <div class="attendee-filter">
                         <select id="filter_attendees_op" name="filter_attendees_op">
                             <option value="gte" <?php echo ($filter_attendees_op == 'gte') ? 'selected' : ''; ?>>>=</option>
                             <option value="lte" <?php echo ($filter_attendees_op == 'lte') ? 'selected' : ''; ?>><=</option>
                             <option value="eq" <?php echo ($filter_attendees_op == 'eq') ? 'selected' : ''; ?>>=</option>
                         </select>
                         <input type="number" id="filter_attendees_count" name="filter_attendees_count" min="0" step="1" value="<?php echo htmlspecialchars($filter_attendees_count ?? ''); ?>" placeholder="Count">
                    </div>
                </div>

                <button type="submit">Apply Filters / Load Report</button>
            </div> <!-- end .report-filters -->
        </form>
    </div>


    <h2 class="report-title">Report for: <span id="current_event_name_display"><?php echo htmlspecialchars($selected_event_name); ?></span></h2>

    <?php if ($selected_event_id === null && $page_error_message === null): // Only show if no event selected AND no other error ?>
        <p class="no-results-message">Please select a valid event using the search above to view the report.</p>
    <?php elseif($page_error_message !== null): ?>
        <!-- Error message already shown at the top -->
         <p class="no-results-message">Could not load report data due to an error.</p>
    <?php else: // Event selected and no page error, proceed with report display ?>

        <!-- Prominent Total Count Display (Overall Event) -->
        <div class="total-count-display">
            <p>Total People Attending Event (RSVPd)</p>
            <strong><?php echo number_format($totalAttendees); ?></strong>
        </div>

        <!-- Summary Section: Predicted vs Actual Thaals -->
        <div class="summary-section">
            <div class="summary-item">
                <span>Predicted Thaal Count</span>
                <strong><?php echo number_format($predicted_thaal_count); ?></strong>
                <small>(Based on <?php echo $members_per_thaal; ?> members/thaal from RSVPs)</small>
            </div>
            <div class="summary-item">
                 <span>Actual Thaal Count</span>
                 <strong><?php echo isset($actual_thaal_count) ? number_format($actual_thaal_count) : '<em style="color:#999;">Not Set</em>'; ?></strong>
                 <small>(Entered in Event Settings)</small>
            </div>
        </div>

         <!-- Export Button -->
         <div class="export-button-container">
            <form action="reports.php" method="GET" style="display: inline;">
                 <!-- Include ALL current filters for export -->
                <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($selected_event_id); ?>">
                <input type="hidden" name="search_event" value="<?php echo htmlspecialchars($search_term); ?>">
                <input type="hidden" name="search_hof" value="<?php echo htmlspecialchars($search_hof); ?>">
                <input type="hidden" name="filter_attendees_op" value="<?php echo htmlspecialchars($filter_attendees_op); ?>">
                <input type="hidden" name="filter_attendees_count" value="<?php echo htmlspecialchars($filter_attendees_count ?? ''); ?>">
                <input type="hidden" name="export" value="csv">
                <button type="submit">Export Filtered List to Excel (CSV)</button>
            </form>
         </div>


        <!-- Paginated Family List -->
        <h3>
            Filtered Family List
            <?php if ($page_error_message === null) : ?>
                (<?php echo number_format($total_families); ?> families match<?php echo (!empty($search_hof) || $filter_attendees_count !== null) ? 'ing filters' : ''; ?>)
            <?php endif; ?>
        </h3>
        <?php if (!empty($familyCounts)): ?>
             <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Head of Family (at RSVP)</th>
                            <th>ITS Number (at RSVP)</th>
                            <th>Sabil Number (at RSVP)</th>
                            <th>Attendees Confirmed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($familyCounts as $family): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($family['hof_name']); ?></td>
                            <td><?php echo htmlspecialchars($family['its_number']); ?></td>
                            <td><?php echo htmlspecialchars($family['sabil_number'] ?? '-'); // Display sabil ?></td>
                            <td><?php echo number_format($family['total_attendees']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div> <!-- end table-container -->

            <!-- Pagination Controls -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">
                         Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $items_per_page, $total_families); ?> of <?php echo number_format($total_families); ?> results
                    </div>
                    <div class="pagination">
                        <?php // Build base query string for pagination links
                            $page_base_query_params = [
                                'event_id' => $selected_event_id,
                                'search_event' => $search_term,
                                'search_hof' => $search_hof,
                                'filter_attendees_op' => $filter_attendees_op,
                                'filter_attendees_count' => $filter_attendees_count ?? ''
                            ];
                            $page_base_query_params = array_filter($page_base_query_params, function($v){ return $v !== '' && $v !== null;});
                        ?>
                        <?php // Previous Page Link ?>
                        <?php if ($current_page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($page_base_query_params, ['page' => $current_page - 1])); ?>">« Prev</a>
                        <?php else: ?>
                            <span class="disabled">« Prev</span>
                        <?php endif; ?>

                        <?php // Page Number Links (with ellipsis)
                            $link_count = 0;
                            for ($i = 1; $i <= $total_pages; $i++):
                                 $showPage = false;
                                 if ($total_pages <= 7 || $i == 1 || $i == $total_pages || ($i >= $current_page - 2 && $i <= $current_page + 2)) {
                                     $showPage = true;
                                 }
                                 // Ellipsis before
                                 if (!$showPage && $i > 1 && $i == $current_page - 3 && $total_pages > 7) {
                                     echo '<span class="ellipsis">...</span>';
                                 }
                                 // Show link
                                 if ($showPage) {
                                     $link_count++;
                                     if ($i == $current_page): ?>
                                         <span class="current-page"><?php echo $i; ?></span>
                                     <?php else: ?>
                                         <a href="?<?php echo http_build_query(array_merge($page_base_query_params, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                     <?php endif;
                                 }
                                 // Ellipsis after
                                 if (!$showPage && $i < $total_pages && $i == $current_page + 3 && $total_pages > 7) {
                                      echo '<span class="ellipsis">...</span>';
                                 }
                            endfor;
                        ?>

                        <?php // Next Page Link ?>
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($page_base_query_params, ['page' => $current_page + 1])); ?>">Next »</a>
                        <?php else: ?>
                            <span class="disabled">Next »</span>
                        <?php endif; ?>
                    </div>
                 </div>
             <?php endif; // end if total_pages > 1 ?>

        <?php elseif ($page_error_message === null): // Show only if no families AND no general error ?>
             <p class="no-results-message">
                <?php echo ($total_families == 0 && (!empty($search_hof) || $filter_attendees_count !== null)) ? 'No RSVPs match the current filters for this event.' : 'No RSVPs found for this event yet.'; ?>
             </p>
        <?php endif; ?>

    <?php endif; // End check for selected event & no page error ?>

</div> <!-- end .container -->

<script>
    // Client-Side Event Search/Filter and Selection Javascript
    const searchInput = document.getElementById('search_event_input');
    const eventList = document.getElementById('event_list_ul');
    const eventListItems = eventList.querySelectorAll('li[data-id]'); // Select only items with data-id
    const noResultsLi = eventList.querySelector('.no-results');
    const hiddenEventIdInput = document.getElementById('selected_event_id_hidden');
    const reportFilterForm = document.getElementById('report-filter-form');
    const currentEventNameDisplay = document.getElementById('current_event_name_display');

    // Function to filter the list based on search input
    function filterEvents() {
        const searchTerm = searchInput.value.toLowerCase();
        let hasVisibleItems = false;
        eventListItems.forEach(item => {
            const eventName = item.dataset.name.toLowerCase();
            const eventDetails = item.querySelector('.event-details').textContent.toLowerCase();
            if (eventName.includes(searchTerm) || eventDetails.includes(searchTerm)) {
                item.style.display = ''; // Show item
                hasVisibleItems = true;
            } else {
                item.style.display = 'none'; // Hide item
            }
        });
         // Handle 'no results' message visibility
         if(noResultsLi) {
            noResultsLi.style.display = hasVisibleItems ? 'none' : '';
         }
    }

    // Event listeners for the search input
    searchInput.addEventListener('focus', () => {
        eventList.style.display = 'block';
        filterEvents(); // Filter immediately on focus
    });
    searchInput.addEventListener('blur', () => {
        // Delay hiding to allow click event on list item to register
        setTimeout(() => { eventList.style.display = 'none'; }, 200);
    });
    searchInput.addEventListener('input', filterEvents); // Filter as user types

    // Handle clicks on list items to select an event and submit form
    eventList.addEventListener('click', (e) => {
        let targetLi = e.target;
        // Traverse up to find the LI element if user clicked on inner span
        while (targetLi && targetLi.tagName !== 'LI') { targetLi = targetLi.parentElement; }

        if (targetLi && targetLi.tagName === 'LI' && targetLi.dataset.id) { // Ensure it's a valid item li
            const selectedId = targetLi.dataset.id;
            const selectedName = targetLi.dataset.name;

            hiddenEventIdInput.value = selectedId;       // Update hidden ID field
            searchInput.value = selectedName;            // Update visible search box text (optional, could leave search term)
            // No need to update H2 here, the page reload will handle it
            eventList.style.display = 'none';            // Hide the dropdown list

            // Reset page number to 1 when selecting a new event via the dropdown
             const pageInput = reportFilterForm.querySelector('input[name="page"]');
             if(pageInput) {
                 pageInput.value = '1';
             } else {
                 // If page input doesn't exist (e.g., first load), it will default to 1 anyway
             }

            // Submit the form to load the report for the newly selected event
            // This effectively applies the selected event_id filter
            reportFilterForm.submit();
        }
    });

     // Initial filter on page load if search term exists
     if (searchInput.value) { filterEvents(); }

</script>

</body>
</html>