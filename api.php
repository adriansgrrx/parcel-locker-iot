<?php
header('Content-Type: application/json');

$host = 'localhost';
$db = 'locker_system';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $compartment_id = $_POST['compartment_id'];
        $distance_cm = floatval($_POST['distance_cm']);
        $is_parcel_detected = intval($_POST['is_parcel_detected']);
        $is_security_mode = intval($_POST['is_security_mode']);
        $status = $_POST['status'];
        $event_type = $_POST['event_type'] ?? 'parcel_detected';

        // Update compartment state
        $stmt = $pdo->prepare("UPDATE compartments SET distance_cm=?, is_parcel_detected=?, is_security_mode=?, status=? WHERE compartment_id=?");
        $stmt->execute([$distance_cm, $is_parcel_detected, $is_security_mode, $status, $compartment_id]);

        // Log event
        $stmt = $pdo->prepare("INSERT INTO events_log (event_type, compartment_id, status, distance_cm) VALUES (?, ?, ?, ?)");
        $stmt->execute([$event_type, $compartment_id, $status, $distance_cm]);

        echo json_encode(["success" => true, "message" => "Data saved."]);
    } else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $compartments = $pdo->query("SELECT * FROM current_compartments")->fetchAll();
        $events = $pdo->query("SELECT * FROM recent_events")->fetchAll();
        echo json_encode(["compartments" => $compartments, "events" => $events]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
