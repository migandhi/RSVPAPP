<?php
require 'db_connect.php';
session_start();

// --- Admin Access Check ---
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    $_SESSION['login_error'] = 'Admin access required for this page.';
    header('Location: login.php');
    exit; // Stop script execution immediately
}

// --- Configuration ---
$items_per_page = 10; // Number of HoFs to display per page

// --- Page Level Variables ---
$page_error_message = $_SESSION['error_message'] ?? null;
$page_success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['error_message'], $_SESSION['success_message']); // Clear messages after retrieving

// --- Get Filter/Search Values & Current Page ---
$search_name = trim($_GET['search_name'] ?? '');
$search_its = trim($_GET['search_its'] ?? '');
$search_sabil = trim($_GET['search_sabil'] ?? '');
$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);

// --- Handle Add HoF Form Submission (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_hof'])) {
    // (Keep the existing Add HoF logic here - it doesn't interfere with GET-based search/pagination)
    $name = trim($_POST['name'] ?? '');
    $its = trim($_POST['its_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $whatsapp = trim($_POST['whatsapp_number'] ?? '');
    $sabil = trim($_POST['sabil_number'] ?? '');

    // Basic Validation
    if (!empty($name) && !empty($its)) {
        // Check if ITS already exists
        $checkSql = "SELECT id FROM heads_of_family WHERE its_number = ?";
        $stmtCheck = $conn->prepare($checkSql);

        if ($stmtCheck) {
            $stmtCheck->bind_param("s", $its);
            $stmtCheck->execute();
            $stmtCheck->store_result();

            if ($stmtCheck->num_rows == 0) {
                $insertSql = "INSERT INTO heads_of_family (name, its_number, email, whatsapp_number, sabil_number) VALUES (?, ?, ?, ?, ?)";
                $stmtInsert = $conn->prepare($insertSql);
                if ($stmtInsert) {
                    // Use null for optional fields if empty
                    $email_db = !empty($email) ? $email : null;
                    $whatsapp_db = !empty($whatsapp) ? $whatsapp : null;
                    $sabil_db = !empty($sabil) ? $sabil : null;
                    $stmtInsert->bind_param("sssss", $name, $its, $email_db, $whatsapp_db, $sabil_db);

                    if ($stmtInsert->execute()) {
                        $_SESSION['success_message'] = "Head of Family added successfully.";
                    } else {
                        $_SESSION['error_message'] = "Error adding Head of Family: " . $stmtInsert->error;
                    }
                    $stmtInsert->close();
                } else {
                    $_SESSION['error_message'] = "Error preparing insert statement: " . $conn->error;
                }
            } else {
                 $_SESSION['error_message'] = "Error: ITS Number '$its' already exists.";
            }
            $stmtCheck->close();
        } else {
             $_SESSION['error_message'] = "Error preparing check statement: " . $conn->error;
        }

    } else {
        $_SESSION['error_message'] = "Name and ITS Number are required.";
    }
    // Redirect to clear POST data and show message (redirect to page 1 without search terms after adding)
    header("Location: manage_hof.php");
    exit;
} // --- End Add HoF Handling ---


// --- Build Dynamic SQL Clauses and Parameters for Filtering ---
$whereClauses = [];
$params = []; // Parameters for binding WHERE clauses
$paramTypes = ""; // Parameter types string for WHERE clauses

if (!empty($search_name)) {
    $whereClauses[] = "name LIKE ?";
    $params[] = "%" . $search_name . "%";
    $paramTypes .= "s";
}
if (!empty($search_its)) {
    $whereClauses[] = "its_number LIKE ?";
    $params[] = "%" . $search_its . "%";
    $paramTypes .= "s";
}
if (!empty($search_sabil)) {
    $whereClauses[] = "sabil_number LIKE ?";
    $params[] = "%" . $search_sabil . "%";
    $paramTypes .= "s";
}

$whereSql = "";
if (!empty($whereClauses)) {
    $whereSql = "WHERE " . implode(" AND ", $whereClauses);
}

// --- Pagination Logic ---
$total_hofs = 0;
$total_pages = 1;

// 1. Get Total Count of HoFs MATCHING FILTERS
$sqlCount = "SELECT COUNT(*) as total FROM heads_of_family {$whereSql}";
$stmtCount = $conn->prepare($sqlCount);

if ($stmtCount) {
    if (!empty($paramTypes)) {
        try {
             $stmtCount->bind_param($paramTypes, ...$params);
        } catch (Exception $e) {
             // This might happen if $params is empty but $paramTypes is not, or vice versa.
             // Should ideally not occur with the current logic structure.
             $page_error_message = "Error binding count parameters: " . $e->getMessage();
             // Ensure statement is closed if binding fails
             $stmtCount->close();
             $stmtCount = false; // Mark as failed
        }
    }

    if ($stmtCount && $stmtCount->execute()) { // Check if $stmtCount is still valid
        $resultCount = $stmtCount->get_result();
        if ($rowCount = $resultCount->fetch_assoc()) {
            $total_hofs = (int)$rowCount['total'];
        }
    } elseif ($stmtCount) { // Only show execute error if prepare/bind didn't fail
         $page_error_message = "Error executing count query: " . $stmtCount->error;
    }

    // Close statement if it was successfully prepared
    if ($stmtCount) $stmtCount->close();

} else { // Prepare failed
    $page_error_message = "Error preparing count query: " . $conn->error;
}

// Calculate pagination details
if ($total_hofs > 0 && $page_error_message === null) {
    $total_pages = ceil($total_hofs / $items_per_page);
    // Ensure current page is within valid bounds
    if ($current_page > $total_pages) $current_page = $total_pages;
    if ($current_page < 1) $current_page = 1;
    $offset = ($current_page - 1) * $items_per_page;
} else {
    // If no results or an error occurred, reset pagination
    $current_page = 1;
    $total_pages = 1;
    $offset = 0;
    // Don't reset $total_hofs to 0 here if there was an error, keep the count from the DB if it succeeded
}

// --- Fetch Paginated List of HoFs MATCHING FILTERS ---
$hofList = [];
// Only attempt to fetch if no errors and there are records to fetch (or if no filters were applied)
if ($page_error_message === null && $total_hofs > 0) {
    $sqlList = "SELECT id, name, its_number, email, whatsapp_number, sabil_number
                FROM heads_of_family
                {$whereSql}
                ORDER BY name
                LIMIT ? OFFSET ?";

    $stmtList = $conn->prepare($sqlList);
    if ($stmtList) {
        // Combine WHERE params with LIMIT and OFFSET params
        $listParams = $params; // Copy search params
        $listParams[] = $items_per_page; // Add limit
        $listParams[] = $offset;         // Add offset
        $listParamTypes = $paramTypes . "ii"; // Add types for limit/offset

        try {
             // Use call_user_func_array for variable number of params if needed,
             // but splat operator (...) is generally preferred in modern PHP
             $stmtList->bind_param($listParamTypes, ...$listParams);

             if ($stmtList->execute()) {
                 $resultList = $stmtList->get_result();
                 while($row = $resultList->fetch_assoc()) {
                     $hofList[] = $row;
                 }
             } else {
                  $page_error_message = "Error executing list query: " . $stmtList->error;
             }
         } catch (Exception $e) {
             // Handle potential bind_param error if types/params mismatch
              $page_error_message = "Error binding parameters for list query: " . $e->getMessage();
         }
        $stmtList->close();
    } else { // Prepare failed
         $page_error_message = "Error preparing list query: " . $conn->error;
    }
} elseif ($page_error_message === null && $total_hofs === 0 && empty($base_query_params)) {
     // Special case: No error, 0 total families, and no search filters applied = Empty DB table
     // $hofList is already empty, message will be handled in HTML
} elseif ($page_error_message === null && $total_hofs === 0 && !empty($base_query_params)) {
     // Special case: No error, 0 total families, but search filters *were* applied = No results for search
      // $hofList is already empty, message will be handled in HTML
}

// Close DB connection only after all queries are done
$conn->close();

// --- Build Base Query String for Pagination Links (preserving search filters) ---
$base_query_params = [
    'search_name' => $search_name,
    'search_its' => $search_its,
    'search_sabil' => $search_sabil
];
// Remove empty search parameters to keep URLs clean
$base_query_params = array_filter($base_query_params, function($value) { return $value !== ''; });

?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Heads of Family</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css"> <!-- Link your main stylesheet -->
     <style>
        /* Reuse styles from manage_events.php for consistency */
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
        .warning { background-color: #fff3cd; color: #664d03; border-color: #ffecb5;} /* Optional Warning */

        /* Form Styles */
        .crud-form, .search-form { border: 1px solid #dee2e6; padding: 20px; margin-bottom: 30px; background-color: #fdfdff; border-radius: 5px; }
        .crud-form h2, .search-form h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 20px; font-size: 1.3em; color: #495057; font-weight: 600; }
        .form-row { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; min-width: 200px; } /* Allow wrapping */
        .form-group label { display: block; margin-bottom: 6px; font-weight: bold; font-size: 0.9em; color: #495057;}
        .form-group input[type=text],
        .form-group input[type=email],
        .form-group input[type=search] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 0.95em;
        }
        .crud-form button, .search-form button { padding: 10px 20px; color: white; border: none; cursor: pointer; border-radius: 4px; font-size: 1em; font-weight: 500; }
        .crud-form button { background-color: #198754; } /* Green for Add */
        .search-form button { background-color: #0d6efd; } /* Blue for Search */
        .crud-form button:hover { background-color: #157347; }
        .search-form button:hover { background-color: #0b5ed7; }

        /* Table Styles */
        h2.list-header { margin-top: 30px; border-bottom: 1px solid #eee; padding-bottom: 10px; font-size: 1.4em; color: #343a40;}
        .table-container { overflow-x: auto; margin-top: 20px; border: 1px solid #dee2e6; border-radius: 5px;}
        table { width: 100%; border-collapse: collapse; margin: 0; background-color: white; }
        th, td { border: none; border-bottom: 1px solid #dee2e6; padding: 10px 12px; text-align: left; vertical-align: middle; font-size: 0.95em; }
        thead tr:first-child th { border-top: none; } /* Remove top border if any */
        tbody tr:last-child td { border-bottom: none; } /* Remove bottom border on last row */
        th { background-color: #f8f9fa; font-weight: 600; position: sticky; top: 0; z-index: 1;} /* Sticky header */
        tbody tr:nth-child(odd) { background-color: #fdfdfe; }
        tbody tr:hover { background-color: #f1f1f1; }
        td a { margin-right: 8px; color: #0d6efd; text-decoration: none;}
        td a:hover { text-decoration: underline; }
        td a.delete-link { color: #dc3545; }
        td a.delete-link:hover { color: #a71d2a; }

        /* Pagination Styles */
         .pagination-container { display: flex; justify-content: space-between; align-items: center; margin: 30px 0 10px 0; padding: 0 5px; /* Align with table padding */}
         .pagination-info { color: #6c757d; font-size: 0.9em; }
         .pagination { text-align: right; /* Move links to the right */ margin: 0; padding: 0; }
         .pagination a, .pagination span { display: inline-block; padding: 8px 14px; margin-left: 5px; /* Spacing between items */ border: 1px solid #dee2e6; border-radius: 4px; color: #0d6efd; text-decoration: none; background-color: white; font-size: 0.9em;}
         .pagination a:hover { background-color: #e9ecef; border-color: #ced4da;}
         .pagination span.current-page { background-color: #0d6efd; color: white; border-color: #0d6efd; font-weight: bold;}
         .pagination span.disabled { color: #6c757d; border-color: #dee2e6; background-color: #f8f9fa; cursor: default;}

         .no-results { padding: 25px; text-align: center; font-style: italic; color: #6c757d; background-color: #f8f9fa; border: 1px dashed #dee2e6; border-radius: 4px; margin-top: 20px; }
     </style>
</head>
<body>

    <!-- Admin Navigation Bar -->
    <div class="admin-nav">
        <span>Welcome, Admin!</span>
        <a href="reports.php">View Reports</a>
        <a href="manage_hof.php">Manage HoF</a> <!-- Current Page -->
        <a href="manage_events.php">Manage Events</a>
        <a href="logout.php">Logout</a>
    </div>

<div class="container">
    <h1>Manage Heads of Family</h1>

    <?php
    // Display messages
    if ($page_success_message) {
        echo '<div class="message success">' . htmlspecialchars($page_success_message) . '</div>';
    }
    // Display error message ONLY if it's not null
    if ($page_error_message !== null) {
        echo '<div class="message error">' . htmlspecialchars($page_error_message) . '</div>';
    }
    ?>

    <!-- Add New Head of Family Form -->
    <div class="crud-form">
        <h2>Add New Head of Family</h2>
        <form action="manage_hof.php" method="POST">
            <input type="hidden" name="add_hof" value="1"> <!-- Identifier for the add action -->
            <div class="form-row">
                <div class="form-group">
                    <label for="add_name">Name:</label>
                    <input type="text" id="add_name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="add_its_number">ITS Number:</label>
                    <input type="text" id="add_its_number" name="its_number" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="add_sabil_number">Sabil Number:</label>
                    <input type="text" id="add_sabil_number" name="sabil_number">
                </div>
                <div class="form-group">
                    <label for="add_email">Email:</label>
                    <input type="email" id="add_email" name="email">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="add_whatsapp_number">WhatsApp Number (e.g., +91...):</label>
                    <input type="text" id="add_whatsapp_number" name="whatsapp_number">
                </div>
                <div class="form-group" style="align-self: flex-end; text-align: right;"> <!-- Align button right -->
                    <button type="submit">Add Head of Family</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Search Form -->
     <div class="search-form">
        <h2>Search / Filter HoF List</h2>
        <form action="manage_hof.php" method="GET">
            <div class="form-row">
                <div class="form-group">
                    <label for="search_name">Name Contains:</label>
                    <input type="search" id="search_name" name="search_name" value="<?php echo htmlspecialchars($search_name); ?>">
                </div>
                <div class="form-group">
                    <label for="search_its">ITS Contains:</label>
                    <input type="search" id="search_its" name="search_its" value="<?php echo htmlspecialchars($search_its); ?>">
                </div>
                <div class="form-group">
                    <label for="search_sabil">Sabil Contains:</label>
                    <input type="search" id="search_sabil" name="search_sabil" value="<?php echo htmlspecialchars($search_sabil); ?>">
                </div>
                <div class="form-group" style="align-self: flex-end; text-align: right;">
                     <button type="submit">Search</button>
                </div>
            </div>
             <input type="hidden" name="page" value="1"> <!-- Reset to page 1 on new search -->
        </form>
    </div>


    <h2 class="list-header">
        Existing Heads of Family
        <!-- Show count only if there was no error retrieving it -->
        <?php if ($page_error_message === null) : ?>
            (<?php echo number_format($total_hofs); ?> found<?php echo (!empty($base_query_params) ? ' matching filters' : ''); ?>)
        <?php endif; ?>
    </h2>

     <?php if (!empty($hofList)): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>ITS Number</th>
                        <th>Sabil Number</th>
                        <th>Email</th>
                        <th>WhatsApp</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hofList as $hof): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($hof['name']); ?></td>
                        <td><?php echo htmlspecialchars($hof['its_number']); ?></td>
                        <td><?php echo htmlspecialchars($hof['sabil_number'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($hof['email'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($hof['whatsapp_number'] ?? '-'); ?></td>
                        <td>
                            <a href="edit_hof.php?id=<?php echo $hof['id']; ?>" title="Edit <?php echo htmlspecialchars($hof['name']); ?>">Edit</a> |
                            <a href="delete_hof.php?id=<?php echo $hof['id']; ?>" class="delete-link" title="Delete <?php echo htmlspecialchars($hof['name']); ?>" onclick="return confirm('Are you sure you want to delete this person: \'<?php echo htmlspecialchars(addslashes($hof['name'])); ?>\' (ITS: <?php echo htmlspecialchars(addslashes($hof['its_number'])); ?>)? This cannot be undone.');">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div><!-- end table-container -->

        <!-- Pagination Controls -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $items_per_page, $total_hofs); ?> of <?php echo number_format($total_hofs); ?> results
                </div>
                <div class="pagination">
                    <?php // Previous Page Link ?>
                    <?php if ($current_page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($base_query_params, ['page' => $current_page - 1])); ?>">« Prev</a>
                    <?php else: ?>
                        <span class="disabled">« Prev</span>
                    <?php endif; ?>

                    <?php // Page Number Links (Simplified) - Add ellipsis logic if needed for many pages ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php // Basic logic to limit page numbers shown - adjust thresholds as needed
                              $showPage = false;
                              if ($total_pages <= 7 || // Show all if 7 or less pages
                                  $i == 1 || $i == $total_pages || // Always show first and last
                                  ($i >= $current_page - 2 && $i <= $current_page + 2) // Show pages around current
                              ) {
                                  $showPage = true;
                              }

                              // Logic for ellipsis (can be refined)
                              if (!$showPage && ($i == $current_page - 3 || $i == $current_page + 3)) {
                                  echo '<span class="disabled">...</span>'; // Show ellipsis
                              }
                              // Render the page link/span if $showPage is true
                              if ($showPage) {
                                  if ($i == $current_page): ?>
                                      <span class="current-page"><?php echo $i; ?></span>
                                  <?php else: ?>
                                      <a href="?<?php echo http_build_query(array_merge($base_query_params, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                  <?php endif;
                              }
                        ?>
                    <?php endfor; ?>

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
            <?php echo (!empty($base_query_params)) ? 'No Heads of Family found matching your search criteria.' : 'No Heads of Family found in the database. Please add one using the form above.'; ?>
        </p>
    <?php endif; // End check for empty $hofList and no error ?>

</div> <!-- end .container -->

</body>
</html>