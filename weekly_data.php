<?php
header('Content-Type: application/json');

// DB connection
$host = 'localhost';
$db   = 'locker_system';
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
} catch (\PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

if (isset($_GET['weekly_data'])) {
    try {
        // Build array of weekdays
        $daysOfWeek = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
        $dayCounts = array_fill_keys($daysOfWeek, 0);

        // Get events from past 7 days
        $stmt = $pdo->query("
            SELECT DAYNAME(timestamp) AS day, COUNT(*) AS count
            FROM parcel_events
            WHERE timestamp >= CURDATE() - INTERVAL 6 DAY
            GROUP BY day
        ");

        while ($row = $stmt->fetch()) {
            $shortDay = substr($row['day'], 0, 3);
            if (isset($dayCounts[$shortDay])) {
                $dayCounts[$shortDay] = (int)$row['count'];
            }
        }

        // Reorder to Wed → Tue
        $orderedDays = ["Wed", "Thu", "Fri", "Sat", "Sun", "Mon", "Tue"];
        $labels = $orderedDays;
        $values = array_map(fn($d) => $dayCounts[$d], $orderedDays);

        $total = array_sum($values);
        $average = round($total / 7, 2);
        $peakIndex = array_keys($values, max($values))[0];
        $peakDay = $labels[$peakIndex];

        echo json_encode([
            'labels'    => $labels,
            'values'    => $values,
            'total'     => $total,
            'average'   => $average,
            'peak_day'  => $peakDay
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
