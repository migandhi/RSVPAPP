<?php
require 'db_connect.php'; // Include database connection

$results = [];
$searchTerm = $_GET['term'] ?? ''; // Get search term from query string

if (strlen($searchTerm) >= 2) { // Only search if term is reasonably long
    $searchTermSafe = $conn->real_escape_string($searchTerm);
    $sql = "SELECT id, name, its_number FROM heads_of_family
            WHERE name LIKE ? OR its_number LIKE ?";
    $likeTerm = "%" . $searchTermSafe . "%";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $likeTerm, $likeTerm);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // Format for jQuery UI Autocomplete or similar library
        $results[] = [
            'id' => $row['id'], // Send the HoF ID back
            'label' => $row['name'] . ' (' . $row['its_number'] . ')', // Text displayed in dropdown
            'value' => $row['name'], // Value placed in input field upon selection (optional)
            'its' => $row['its_number'] // Additional data
        ];
    }
    $stmt->close();
}

$conn->close();
header('Content-Type: application/json'); // Set header for JSON response
echo json_encode($results);
?>