<?php
session_start();
require 'db_connect.php'; // Need DB connection to fetch events

// Fetch active events for the dropdown
$events = [];
$result = $conn->query("SELECT id, event_name FROM events WHERE is_active = TRUE ORDER BY event_name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
}
$conn->close(); // Close connection after fetching
?>
<!DOCTYPE html>
<html>
<head>
    <title>Event RSVP</title>
    <!-- meta tags, css, js includes -->

<meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="style.css">
     <style>
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; border: 1px solid transparent; }
        .success { background-color: #dff0d8; color: #3c763d; border-color: #d6e9c6;}
        .error { background-color: #f2dede; color: #a94442; border-color: #ebccd1;}
        .warning { background-color: #fcf8e3; color: #8a6d3b; border-color: #faebcc;}
    </style>
</head>
<body>
    <h1>RSVP Form</h1>

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
     // Display WhatsApp link if generated
    if (isset($_SESSION['whatsapp_link'])) {
        echo '<div class="whatsapp-link">RSVP saved! <a href="' . htmlspecialchars($_SESSION['whatsapp_link']) . '" target="_blank">Click here to send WhatsApp confirmation</a> (Manual Send Required)</div>';
        unset($_SESSION['whatsapp_link']);
    }
    ?>

    <form action="submit_rsvp.php" method="POST" id="rsvpForm">

 <!-- Event Selection Dropdown -->
        <div class="form-group">
            <label for="event_id">Select Event:</label>
            <select id="event_id" name="event_id" required>
                <option value="">-- Please Select an Event --</option>
                <?php foreach ($events as $event): ?>
                    <option value="<?php echo $event['id']; ?>">
                        <?php echo htmlspecialchars($event['event_name']); ?>
                    </option>
                <?php endforeach; ?>
                 <?php if (empty($events)): ?>
                     <option value="" disabled>No active events available</option>
                 <?php endif; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="hof_search">Enter Name or ITS Number of Head of Family:</label>
            <input type="text" id="hof_search" name="hof_search" placeholder="Start typing Name or ITS..." required>
            <!-- Hidden field to store the actual HoF ID -->
            <input type="hidden" id="hof_id" name="hof_id">
            <div id="hof_display"></div> <!-- Optional: Display selected HoF -->
        </div>

        <div class="form-group">
            <label for="attendee_count">Enter count of members attending (family + guests):</label>
            <input type="number" id="attendee_count" name="attendee_count" min="1" required>
        </div>

        

        <button type="submit">SUBMIT RSVP & Send Invitation</button>
    </form>

    <hr>
<!-- <p><a href="reports.php">View Reports</a></p> -->
<!-- <p><a href="manage_hof.php">Manage Heads of Family</a></p> -->
<p><a href="login.php">Admin Login</a></p>

    <script>
        $(function() {
            $("#hof_search").autocomplete({
                source: "autocomplete_hof.php", // PHP script for searching
                minLength: 2, // Minimum characters before searching
                select: function(event, ui) {
                    event.preventDefault(); // Prevent default value insertion
                    $("#hof_search").val(ui.item.label); // Display Name (ITS) in search box
                    $("#hof_id").val(ui.item.id); // Set the hidden HoF ID field
                    $("#hof_display").text("Selected: " + ui.item.label); // Show selection
                },
                focus: function(event, ui) {
                     event.preventDefault(); // Prevent value insert on focus
                     $("#hof_search").val(ui.item.label);
                }
            });

            // Clear hidden ID if the user clears or changes the search box manually
             $("#hof_search").on('input', function() {
                 if ($(this).val() === '') {
                     $("#hof_id").val('');
                     $("#hof_display").text('');
                 }
                 // Optional: You might want to clear the ID if the text no longer matches a valid selection
                 // This requires more complex logic to track the last selected item vs current text.
             });

             // Form validation before submit (optional, basic)
             $("#rsvpForm").submit(function(event) {
                 if ($("#hof_id").val() === '') {
                     alert("Please select a Head of Family from the suggestions.");
                     $("#hof_search").focus();
                     event.preventDefault(); // Stop form submission
                     return false;
                 }
                 if ($("#attendee_count").val() <= 0) {
                      alert("Please enter a valid number of attendees (1 or more).");
                      $("#attendee_count").focus();
                      event.preventDefault(); // Stop form submission
                      return false;
                 }
             });
        });
    </script>

</body>
</html>