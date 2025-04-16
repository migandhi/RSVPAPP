<?php
session_start();
require 'db_connect.php'; // Need DB connection to fetch events

// Fetch active events for the dropdown
$events = [];
$active_event_found = false; // Flag to check if any event is active
$result = $conn->query("SELECT id, event_name FROM events WHERE is_active = TRUE ORDER BY event_name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
        $active_event_found = true;
    }
}
$conn->close(); // Close connection after fetching
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event RSVP</title>

    <!-- Link jQuery and jQuery UI -->
    <link rel="stylesheet" href="//code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

    <!-- Custom Styles -->
    <style>
        :root {
            --primary-color: #007bff;
            --primary-hover: #0056b3;
            --success-bg: #d1e7dd;
            --success-text: #0f5132;
            --success-border: #badbcc;
            --error-bg: #f8d7da;
            --error-text: #842029;
            --error-border: #f5c2c7;
            --warning-bg: #fff3cd;
            --warning-text: #664d03;
            --warning-border: #ffecb5;
            --info-bg: #cff4fc;
            --info-text: #055160;
            --info-border: #b6effb;
            --light-gray: #f8f9fa;
            --gray-border: #dee2e6;
            --text-color: #212529;
            --label-color: #495057;
            --white: #fff;
            --card-shadow: 0 4px 8px rgba(0,0,0,0.05);
            --border-radius: 5px;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            background-color: var(--light-gray);
            color: var(--text-color);
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: flex-start; /* Align top */
            min-height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 600px; /* Limit form width on larger screens */
            background-color: var(--white);
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            border: 1px solid var(--gray-border);
            box-sizing: border-box;
        }

        h1 {
            text-align: center;
            color: var(--primary-color);
            margin-top: 0;
            margin-bottom: 25px;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: var(--label-color);
            font-size: 0.95em;
        }

        input[type="text"],
        input[type="number"],
        select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--gray-border);
            border-radius: var(--border-radius);
            box-sizing: border-box; /* Include padding in width */
            font-size: 1em;
            transition: border-color 0.2s ease-in-out;
        }
        input:focus, select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }
        /* Specific style for disabled dropdown */
        select:disabled {
            background-color: #e9ecef;
            cursor: not-allowed;
        }

        /* Autocomplete result display */
        #hof_display {
            margin-top: 10px;
            font-size: 0.9em;
            padding: 10px;
            background-color: var(--info-bg);
            color: var(--info-text);
            border: 1px solid var(--info-border);
            border-radius: var(--border-radius);
            display: none; /* Hidden by default */
        }
        #hof_display strong {
            color: var(--primary-color);
        }

        button[type="submit"] {
            background-color: var(--primary-color);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 1.1em;
            font-weight: bold;
            width: 100%; /* Full width button */
            transition: background-color 0.2s ease-in-out;
            margin-top: 10px; /* Add space above button */
        }
        button[type="submit"]:hover {
            background-color: var(--primary-hover);
        }
        button[type="submit"]:disabled {
             background-color: #6c757d; /* Gray out if disabled */
             cursor: not-allowed;
        }

        /* jQuery UI Autocomplete Styles */
        .ui-autocomplete {
            max-height: 250px; /* Increased height */
            overflow-y: auto;
            overflow-x: hidden;
            background: var(--white);
            border: 1px solid var(--gray-border);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            z-index: 1000; /* Ensure it's above other elements */
        }
        .ui-menu-item {
            padding: 8px 12px; /* Slightly more padding */
            cursor: pointer;
            font-size: 0.95em;
        }
        .ui-menu-item .ui-menu-item-wrapper {
            display: block; /* Make wrapper block */
        }
        .ui-menu-item .ui-menu-item-wrapper:hover {
            background-color: #e9ecef;
            color: var(--text-color);
        }
        .ui-menu-item-wrapper.ui-state-active {
            background-color: var(--primary-color); /* Highlight focused item */
            color: white;
            border: none;
            margin: 0;
            border-radius: 0;
        }
        .ui-helper-hidden-accessible {
            display: none;
        }
        /* Style within autocomplete item */
        .autocomplete-label { font-weight: bold; display: block; }
        .autocomplete-its { font-size: 0.9em; color: #6c757d; }

        /* Message Styles */
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            border: 1px solid transparent;
            font-size: 0.95em;
            text-align: center;
        }
        .message.success { background-color: var(--success-bg); color: var(--success-text); border-color: var(--success-border); }
        .message.error { background-color: var(--error-bg); color: var(--error-text); border-color: var(--error-border); }
        .message.warning { background-color: var(--warning-bg); color: var(--warning-text); border-color: var(--warning-border); }

        /* Admin Link */
        .admin-link {
            text-align: center;
            margin-top: 30px;
            font-size: 0.9em;
        }
        .admin-link a {
            color: var(--primary-hover);
            text-decoration: none;
        }
        .admin-link a:hover {
            text-decoration: underline;
        }

        /* Responsive Adjustments */
        @media (max-width: 640px) {
            body {
                padding: 10px;
            }
            .container {
                padding: 20px;
            }
            h1 {
                font-size: 1.5em;
            }
        }

    </style>
</head>
<body>
    <div class="container">
        <h1>Event RSVP</h1>

        <?php
        // Display feedback messages
        if (isset($_SESSION['success_message'])) {
            echo '<div class="message success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo '<div class="message error">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
            unset($_SESSION['error_message']);
        }
        if (isset($_SESSION['warning_message'])) {
            echo '<div class="message warning">' . htmlspecialchars($_SESSION['warning_message']) . '</div>';
            unset($_SESSION['warning_message']);
        }
        // Display WhatsApp link if generated (Optional, kept from original if needed)
        /* if (isset($_SESSION['whatsapp_link'])) {
            echo '<div class="message info">RSVP saved! <a href="' . htmlspecialchars($_SESSION['whatsapp_link']) . '" target="_blank">Click here to send WhatsApp confirmation</a> (Manual Send Required)</div>';
            unset($_SESSION['whatsapp_link']);
        } */
        ?>

        <form action="submit_rsvp.php" method="POST" id="rsvpForm">

            <!-- Event Selection Dropdown -->
            <div class="form-group">
                <label for="event_id">1. Select Event:</label>
                <select id="event_id" name="event_id" required <?php echo !$active_event_found ? 'disabled' : ''; ?>>
                    <option value="">-- Please Select an Event --</option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?php echo $event['id']; ?>">
                            <?php echo htmlspecialchars($event['event_name']); ?>
                        </option>
                    <?php endforeach; ?>
                     <?php if (!$active_event_found): ?>
                         <option value="" disabled>No active events available</option>
                     <?php endif; ?>
                </select>
                <?php if (!$active_event_found): ?>
                     <small style="color: var(--error-text); margin-top: 5px;">There are currently no active events to RSVP for.</small>
                <?php endif; ?>
            </div>

            <!-- HoF Search -->
            <div class="form-group">
                <label for="hof_search">2. Find Head of Family (Type Name or ITS):</label>
                <input type="text" id="hof_search" name="hof_search" placeholder="Start typing Name or ITS Number..." required>
                <!-- Hidden field to store the actual HoF ID -->
                <input type="hidden" id="hof_id" name="hof_id">
                <!-- Display selected HoF -->
                <div id="hof_display">Selected: <strong><span id="selected_hof_name"></span></strong> (<span id="selected_hof_its"></span>)</div>
            </div>

            <!-- Attendee Count -->
            <div class="form-group">
                <label for="attendee_count">3. Enter Number of Attendees (Including Yourself):</label>
                <input type="number" id="attendee_count" name="attendee_count" min="1" required placeholder="e.g., 3">
            </div>

            <!-- Submit Button -->
            <button type="submit" id="submitButton" <?php echo !$active_event_found ? 'disabled' : ''; ?>>
                Submit RSVP & Receive Invitation
            </button>
        </form>

        <div class="admin-link">
            <a href="login.php">Admin Login</a>
        </div>
    </div> <!-- end .container -->

    <script>
    $(function() {
        // --- Autocomplete Setup ---
        $("#hof_search").autocomplete({
            source: function(request, response) {
                 $("#hof_search").addClass('ui-autocomplete-loading'); // Add loading indicator
                $.ajax({
                    url: "autocomplete_hof.php",
                    dataType: "json",
                    data: {
                        term: request.term // Send current search term
                    },
                    success: function(data) {
                         $("#hof_search").removeClass('ui-autocomplete-loading'); // Remove loading indicator
                         // --- THIS IS THE CRITICAL MAPPING ---
                         // Ensure the object returned here matches what PHP sends
                         response($.map(data, function(item_from_php) {
                            return {
                                id: item_from_php.id,        // HoF ID
                                label: item_from_php.label,  // Full text for dropdown "Name (ITS)"
                                value: item_from_php.value,  // Just the Name - used for display later
                                its: item_from_php.its       // The ITS number
                            };
                        }));
                        // ------------------------------------
                    },
                    error: function() {
                         $("#hof_search").removeClass('ui-autocomplete-loading');
                         console.error("Autocomplete request failed.");
                         response([]); // Return empty on error
                    }
                });
            },
            minLength: 2, // Minimum characters before searching
            select: function(event, ui) {
                // ui.item contains the object returned from the corrected $.map above
                event.preventDefault(); // Prevent default value insertion
                $("#hof_search").val(ui.item.label); // Display "Name (ITS)" in search box
                $("#hof_id").val(ui.item.id);       // Set the hidden HoF ID field

                // Update and show the display div using the correct fields
                $("#selected_hof_name").text(ui.item.value); // <-- Use ui.item.value for the NAME
                $("#selected_hof_its").text(ui.item.its);
                $("#hof_display").slideDown(200); // Show the display div smoothly
            },
            focus: function(event, ui) {
                 event.preventDefault(); // Prevent value insert on keyboard focus/navigation
            }
            // Use _renderItem to customize dropdown display
        }).autocomplete("instance")._renderItem = function(ul, item) {
             // 'item' here is the object returned from the corrected $.map
             // Ensure you use item.value for the name part
             return $("<li>")
                .append("<div class='autocomplete-label'>" + item.value + // <-- Use item.value for the Name
                        "<span class='autocomplete-its'> (ITS: " + item.its + ")</span></div>")
                .appendTo(ul);
        };


        // --- Clear hidden ID and display if user clears/changes the search box manually ---
         $("#hof_search").on('input', function() {
             // If they type anything after selecting, clear the ID and hide display.
             // Forces re-selection from the list.
             if ($("#hof_id").val() !== '') {
                 $("#hof_id").val('');
                 $("#hof_display").slideUp(200);
             }
         });

         // --- Form validation before submit ---
         $("#rsvpForm").submit(function(event) {
             let isValid = true;
             let $firstErrorField = null;

            // Clear previous error styles
            $("#event_id, #hof_search, #attendee_count").css("border-color", "var(--gray-border)");

             // Check Event Selected
             if ($("#event_id").val() === '') {
                 alert("Please select an Event.");
                 $("#event_id").css("border-color", "var(--error-border)");
                 if(!$firstErrorField) $firstErrorField = $("#event_id");
                 isValid = false;
             }

             // Check HoF Selected (via hidden ID)
             if ($("#hof_id").val() === '') {
                 alert("Please select a Head of Family from the suggestions after typing.");
                  $("#hof_search").css("border-color", "var(--error-border)");
                 if(!$firstErrorField) $firstErrorField = $("#hof_search");
                 isValid = false;
             }

             // Check Attendee Count
             const attendeeCount = parseInt($("#attendee_count").val(), 10);
             if (isNaN(attendeeCount) || attendeeCount <= 0) {
                  alert("Please enter a valid number of attendees (1 or more).");
                  $("#attendee_count").css("border-color", "var(--error-border)");
                  if(!$firstErrorField) $firstErrorField = $("#attendee_count");
                  isValid = false;
             }

             if (!isValid) {
                 event.preventDefault(); // Stop form submission
                 if ($firstErrorField) {
                    $firstErrorField.focus(); // Focus the first invalid field
                 }
                 return false;
             }

             // Disable button on submit
             $("#submitButton").prop('disabled', true).text('Submitting...');
         });
    });
</script>
</body>
</html>