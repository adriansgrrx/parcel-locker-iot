<?php
// config.php - Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "locker_system";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die(json_encode(['error' => 'Connection failed: ' . $e->getMessage()]));
}

// Set CORS headers for API access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Handle API requests
$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

// Route API requests
if (strpos($request_uri, '/api/') !== false) {
    header('Content-Type: application/json');
    
    switch (true) {
        case strpos($request_uri, '/api/get_status') !== false:
            handleGetStatus($pdo);
            break;
        case strpos($request_uri, '/api/allow') !== false:
            handleAllow($pdo);
            break;
        case strpos($request_uri, '/api/reset') !== false:
            handleReset($pdo);
            break;
        case strpos($request_uri, '/api/update_compartments') !== false:
            handleUpdateCompartments($pdo);
            break;
        case strpos($request_uri, '/api/get_events') !== false:
            handleGetEvents($pdo);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'API endpoint not found']);
    }
    exit();
}

// API Handler Functions
function handleGetStatus($pdo) {
    try {
        // Get compartments data
        $compartmentsStmt = $pdo->query("SELECT * FROM compartments ORDER BY compartment_id");
        $compartments = $compartmentsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get system status
        $systemStmt = $pdo->query("SELECT * FROM system_status WHERE id = 1");
        $systemStatus = $systemStmt->fetch(PDO::FETCH_ASSOC);
        
        // Get recent events
        $eventsStmt = $pdo->query("SELECT * FROM events_log ORDER BY timestamp DESC LIMIT 10");
        $events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format response
        $response = [
            'compartments' => [],
            'events_log' => [],
            'system_status' => [
                'solenoid_state' => $systemStatus['solenoid_state'] ?? 'LOCKED',
                'buzzer_state' => $systemStatus['buzzer_state'] ?? 'OFF',
                'last_permission' => boolval($systemStatus['last_permission'] ?? false),
                'last_reset' => boolval($systemStatus['last_reset'] ?? false),
                'updated_at' => $systemStatus['updated_at'] ?? date('Y-m-d H:i:s')
            ]
        ];
        
        // Format compartments
        foreach ($compartments as $comp) {
            $response['compartments'][$comp['compartment_id']] = [
                'compartment_id' => $comp['compartment_id'],
                'distance_cm' => floatval($comp['distance_cm']),
                'is_parcel_detected' => boolval($comp['is_parcel_detected']),
                'is_security_mode' => boolval($comp['is_security_mode']),
                'status' => $comp['status'],
                'timestamp' => $comp['timestamp']
            ];
        }
        
        // Format events
        foreach ($events as $event) {
            $eventId = 'event_' . $event['id'];
            $response['events_log'][$eventId] = [
                'event_type' => $event['event_type'],
                'compartment_id' => $event['compartment_id'],
                'status' => $event['status'],
                'permission_granted' => boolval($event['permission_granted']),
                'reset_triggered' => boolval($event['reset_triggered']),
                'distance_cm' => floatval($event['distance_cm']),
                'timestamp' => $event['timestamp']
            ];
        }
        
        echo json_encode($response);
        
    } catch(Exception $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleAllow($pdo) {
    try {
        // Update system status
        $stmt = $pdo->prepare("
            UPDATE system_status SET
            solenoid_state = 'UNLOCKED',
            last_permission = TRUE,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = 1
        ");
        $stmt->execute();
        
        // Log the permission event
        $eventStmt = $pdo->prepare("
            INSERT INTO events_log (event_type, compartment_id, status, permission_granted, distance_cm)
            VALUES ('permission_granted', NULL, 'Empty', TRUE, 0)
        ");
        $eventStmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Access granted',
            'solenoid_state' => 'UNLOCKED',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch(Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error granting access: ' . $e->getMessage()
        ]);
    }
}

function handleReset($pdo) {
    try {
        $pdo->beginTransaction();
        
        // Reset compartments security mode
        $compStmt = $pdo->prepare("
            UPDATE compartments SET
            is_security_mode = FALSE,
            timestamp = CURRENT_TIMESTAMP
        ");
        $compStmt->execute();
        
        // Reset system status
        $sysStmt = $pdo->prepare("
            UPDATE system_status SET
            solenoid_state = 'LOCKED',
            buzzer_state = 'OFF',
            last_reset = TRUE,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = 1
        ");
        $sysStmt->execute();
        
        // Log the reset event
        $eventStmt = $pdo->prepare("
            INSERT INTO events_log (event_type, compartment_id, status, reset_triggered, distance_cm)
            VALUES ('security_reset', NULL, 'Empty', TRUE, 0)
        ");
        $eventStmt->execute();
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Security reset completed',
            'solenoid_state' => 'LOCKED',
            'buzzer_state' => 'OFF',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch(Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Error resetting security: ' . $e->getMessage()
        ]);
    }
}

function handleUpdateCompartments($pdo) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                $input = $_POST;
            }
            
            $pdo->beginTransaction();
            
            // Update compartments
            foreach (['C1', 'C2'] as $compartmentId) {
                $key = strtolower($compartmentId);
                
                if (isset($input[$key])) {
                    $data = $input[$key];
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO compartments (compartment_id, distance_cm, is_parcel_detected, is_security_mode, status)
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        distance_cm = VALUES(distance_cm),
                        is_parcel_detected = VALUES(is_parcel_detected),
                        is_security_mode = VALUES(is_security_mode),
                        status = VALUES(status),
                        timestamp = CURRENT_TIMESTAMP
                    ");
                    
                    $stmt->execute([
                        $compartmentId,
                        floatval($data['distance_cm'] ?? 0),
                        boolval($data['is_parcel_detected'] ?? false),
                        boolval($data['is_security_mode'] ?? false),
                        $data['status'] ?? 'Empty'
                    ]);
                    
                    // Log events for status changes
                    if (isset($data['event_type'])) {
                        $eventStmt = $pdo->prepare("
                            INSERT INTO events_log (event_type, compartment_id, status, permission_granted, reset_triggered, distance_cm)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        
                        $eventStmt->execute([
                            $data['event_type'],
                            $compartmentId,
                            $data['status'] ?? 'Empty',
                            boolval($data['permission_granted'] ?? false),
                            boolval($data['reset_triggered'] ?? false),
                            floatval($data['distance_cm'] ?? 0)
                        ]);
                    }
                }
            }
            
            // Update system status
            if (isset($input['system_status'])) {
                $systemData = $input['system_status'];
                
                $systemStmt = $pdo->prepare("
                    UPDATE system_status SET
                    solenoid_state = ?,
                    buzzer_state = ?,
                    last_permission = ?,
                    last_reset = ?,
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = 1
                ");
                
                $systemStmt->execute([
                    $systemData['solenoid_state'] ?? 'LOCKED',
                    $systemData['buzzer_state'] ?? 'OFF',
                    boolval($systemData['last_permission'] ?? false),
                    boolval($systemData['last_reset'] ?? false)
                ]);
            }
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Data updated successfully',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } catch(Exception $e) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Error updating data: ' . $e->getMessage()
            ]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    }
}

function handleGetEvents($pdo) {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $eventType = $_GET['event_type'] ?? null;

    try {
        $whereClause = '';
        $params = [$limit, $offset];
        
        if ($eventType) {
            $whereClause = 'WHERE event_type = ?';
            array_unshift($params, $eventType);
        }
        
        $stmt = $pdo->prepare("
            SELECT * FROM events_log 
            $whereClause
            ORDER BY timestamp DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count
        $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM events_log $whereClause");
        if ($eventType) {
            $countStmt->execute([$eventType]);
        } else {
            $countStmt->execute();
        }
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo json_encode([
            'success' => true,
            'events' => $events,
            'total' => intval($total),
            'limit' => $limit,
            'offset' => $offset
        ]);
        
    } catch(Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching events: ' . $e->getMessage()
        ]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="UTF-8">
    <title>Parcel Locker Management System</title>
    <link rel="stylesheet" href="style.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="container">
        <div class="header-content">
            <div class="logo">
                <div class="logo-icon">📦</div>
                <div class="logo-text">LockerHub</div>
            </div>
            <div class="status-badge">
            <div class="status-dot"></div>
                System Online
            </div>
        </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main">
        <div class="container">
        <!-- Section Title -->
        <div class="section-title">
            <h1>Parcel Locker Management</h1>
            <p>Monitor and control your smart locker compartments in real-time</p>
        </div>

        <!-- Locker Grid -->
        <div class="locker-grid">
            <div id="compartment1" class="compartment-card" onclick="cycleStatus('compartment1')">
                <div class="compartment-header">
                    <div class="compartment-title">Compartment 1</div>
                    <div class="status-indicator status-default">Available</div>
                </div>
                <div class="compartment-icon">📦</div>
                <div class="compartment-info">Click to cycle through status modes</div>
            </div>

            <div id="compartment2" class="compartment-card" onclick="cycleStatus('compartment2')">
                <div class="compartment-header">
                    <div class="compartment-title">Compartment 2</div>
                    <div class="status-indicator status-default">Available</div>
                </div>
                <div class="compartment-icon">📦</div>
                <div class="compartment-info">Click to cycle through status modes</div>
            </div>
        </div>

        <!-- Control Panel -->
        <div class="control-panel">
            <div class="control-title">System Controls</div>
            <div class="system-actions">
            <button class="system-btn success" onclick="allowRider()">
                🔓 Open Locker
            </button>
            <button class="system-btn danger" onclick="resetSecurity()">
                🔄 Reset Security
            </button>
            </div>
        </div>
        </div>
    </div>
    <script src="main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>