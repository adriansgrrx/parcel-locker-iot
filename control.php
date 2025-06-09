<?php
$servername = "localhost";
$username = "root";
$password = ""; // Adjust accordingly
$dbname = "locker_system";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed"]));
}

$sql = "SELECT permission_granted, reset_triggered FROM control_flags LIMIT 1";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        "permission_granted" => filter_var($row['permission_granted'], FILTER_VALIDATE_BOOLEAN),
        "reset_triggered" => filter_var($row['reset_triggered'], FILTER_VALIDATE_BOOLEAN)
    ]);
} else {
    echo json_encode(["error" => "No data found"]);
}

$conn->close();
?>
