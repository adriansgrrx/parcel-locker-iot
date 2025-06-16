<?php
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
    $flagsCheck = $pdo->query("SELECT alert_user, submitted_delivery FROM control_flags WHERE id = 1")->fetch();
    $alertUser = $flagsCheck['alert_user'];
    $submittedDelivery = $flagsCheck['submitted_delivery'];
    
    // === Handle AJAX requests for checking alerts ===
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['check_alert_user']) || isset($_GET['check_submit_delivery']))) {
        try {
            $stmt = $pdo->query("SELECT alert_user, submitted_delivery FROM control_flags WHERE id = 1");
            $row = $stmt->fetch();
            
            if ($row) {
                // Return both values for polling
                echo json_encode([
                    'alert_user' => (int)$row['alert_user'],
                    'submit_delivery' => (int)$row['submitted_delivery']
                ]);
            } else {
                // No row found, return default values
                echo json_encode([
                    'alert_user' => 0,
                    'submit_delivery' => 0
                ]);
            }
        } catch (PDOException $e) {
            // Handle database query error specifically for polling
            echo json_encode([
                'success' => false,
                'error' => 'Database query failed: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    // Handle control flag toggles
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'toggle_permission') {
            $value = $_POST['value'] === 'on' ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE control_flags SET permission_granted = ?");
            $stmt->execute([$value]);
            echo json_encode(["success" => true, "message" => "Permission flag updated."]);
            exit;
        }

        if ($_POST['action'] === 'toggle_reset') {
            $value = $_POST['value'] === 'on' ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE control_flags SET reset_triggered = ?");
            $stmt->execute([$value]);
            echo json_encode(["success" => true, "message" => "Reset flag updated."]);
            exit;
        }
    }

    // === Toggle submitted_delivery flag ===
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_submitted_delivery') {
        $newValue = ($_POST['value'] === 'on') ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE control_flags SET submitted_delivery = ? WHERE id = 1");
        $stmt->execute([$newValue]);

        echo json_encode(["success" => true, "message" => "submitted_delivery updated."]);
        exit;
    }

    // === Toggle submit_delivery flag (alternative action name for JavaScript compatibility) ===
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_submit_delivery') {
        $newValue = ($_POST['value'] === 'on') ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE control_flags SET submitted_delivery = ? WHERE id = 1");
        $stmt->execute([$newValue]);

        echo json_encode(["success" => true, "message" => "submit_delivery updated."]);
        exit;
    }

    // === Toggle alert_user flag ===
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_alert_user') {
        $newValue = ($_POST['value'] === 'on') ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE control_flags SET alert_user = ? WHERE id = 1");
        $stmt->execute([$newValue]);

        echo json_encode(["success" => true, "message" => "alert_user updated."]);
        exit;
    }

    // ESP32 GET request for current control flags
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_control_flags'])) {
        $stmt = $pdo->query("SELECT permission_granted, reset_triggered FROM control_flags LIMIT 1");
        $flags = $stmt->fetch();
        echo json_encode($flags);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $compartment_id = $_POST['compartment_id'] ?? null;

        // === Toggle permission_granted flag ===
        if (isset($_POST['action']) && $_POST['action'] === 'toggle_permission') {
            $newValue = ($_POST['value'] === 'on') ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE control_flags SET permission_granted = ? WHERE id = 1");
            $stmt->execute([$newValue]);

            echo json_encode(["success" => true, "message" => "permission_granted updated."]);
            exit;
        }

        // === Toggle reset_triggered flag ===
        if (isset($_POST['action']) && $_POST['action'] === 'toggle_reset') {
            $newValue = ($_POST['value'] === 'on') ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE control_flags SET reset_triggered = ? WHERE id = 1");
            $stmt->execute([$newValue]);

            echo json_encode(["success" => true, "message" => "reset_triggered updated."]);
            exit;
        }

        // === Handle reset security ===
        if (isset($_POST['action']) && $_POST['action'] === 'reset_security') {
            $stmt = $pdo->prepare("UPDATE compartments SET distance_cm=?, is_parcel_detected=?, is_security_mode=?, status=? WHERE compartment_id=?");
            $stmt->execute([30.0, 0, 0, 'Empty', $compartment_id]);

            $stmt = $pdo->prepare("INSERT INTO events_log (event_type, compartment_id, status, distance_cm) VALUES (?, ?, ?, ?)");
            $stmt->execute(['security_reset', $compartment_id, 'Empty', 30.0]);

            echo json_encode(["success" => true, "message" => "Security reset successfully."]);
            exit;
        }

        // === Regular data update ===
        $distance_cm = floatval($_POST['distance_cm'] ?? 0);
        $is_parcel_detected = intval($_POST['is_parcel_detected'] ?? 0);
        $is_security_mode = intval($_POST['is_security_mode'] ?? 0);
        $status = $_POST['status'] ?? '';
        $event_type = $_POST['event_type'] ?? 'parcel_detected';

        $stmt = $pdo->prepare("UPDATE compartments SET distance_cm=?, is_parcel_detected=?, is_security_mode=?, status=? WHERE compartment_id=?");
        $stmt->execute([$distance_cm, $is_parcel_detected, $is_security_mode, $status, $compartment_id]);

        $stmt = $pdo->prepare("INSERT INTO events_log (event_type, compartment_id, status, distance_cm) VALUES (?, ?, ?, ?)");
        $stmt->execute([$event_type, $compartment_id, $status, $distance_cm]);

        echo json_encode(["success" => true, "message" => "Data saved."]);
        exit;
    } 
    
    // === Fetch control flags for ESP32 ===
    else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_control_flags'])) {
        $stmt = $pdo->query("SELECT permission_granted, reset_triggered FROM control_flags WHERE id = 1");
        $flags = $stmt->fetch();

        echo json_encode($flags);
        exit;
    }

    // === Fetch frontend dashboard data ===
    else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['fetch'])) {
        $compartments = $pdo->query("SELECT * FROM current_compartments")->fetchAll();
        $events = $pdo->query("SELECT * FROM recent_events")->fetchAll();
        echo json_encode(["compartments" => $compartments, "events" => $events]);
        exit;
    }
} catch (PDOException $e) {
    // Handle initial connection errors
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['check_alert_user']) || isset($_GET['check_submit_delivery']))) {
        echo json_encode([
            'success' => false,
            'error' => 'Database connection failed: ' . $e->getMessage()
        ]);
        exit;
    } else {
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Parcel Locker Management System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            plugins: [daisyui],
            theme: {
                extend: {
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                        'mono': ['JetBrains Mono', 'monospace']
                    },
                    animation: {
                        'pulse-dot': 'pulse 2s infinite',
                        'fade-highlight': 'fadeHighlight 1s ease-in-out',
                        'blink-red': 'blinkRed 1s infinite',
                        'shake': 'shake 0.5s ease-in-out',
                        'glow': 'glow 2s ease-in-out infinite alternate'
                    }
                }
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/daisyui@3.8.1/dist/full.js"></script>
    <style>
        @keyframes fadeHighlight {
            0% { background-color: #e8f5e9; }
            100% { background-color: #f9f9f9; }
        }
        
        @keyframes blinkRed {
            0%, 50% { background-color: rgba(239, 68, 68, 0.1); }
            51%, 100% { background-color: rgba(239, 68, 68, 0.3); }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        @keyframes glow {
            from { box-shadow: 0 0 20px rgba(34, 197, 94, 0.3); }
            to { box-shadow: 0 0 30px rgba(34, 197, 94, 0.6); }
        }
        
        .fade-highlight { animation: fadeHighlight 1s ease-in-out; }
        .blink-red { animation: blinkRed 1s infinite; }
        .shake { animation: shake 0.5s ease-in-out; }
        .glow-green { animation: glow 2s ease-in-out infinite alternate; }
        
        /* Responsive font sizes */
        @media (max-width: 640px) {
            .responsive-title { font-size: 2rem !important; }
            .responsive-subtitle { font-size: 1rem !important; }
            .responsive-card-title { font-size: 1.125rem !important; }
            .responsive-button { font-size: 0.875rem !important; padding: 0.5rem 1rem !important; }
        }
        
        @media (min-width: 641px) and (max-width: 1024px) {
            .responsive-title { font-size: 2.5rem !important; }
            .responsive-subtitle { font-size: 1.125rem !important; }
            .responsive-card-title { font-size: 1.25rem !important; }
        }
        
        /* Status-based backgrounds */
        .status-available {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.1) 0%, rgba(34, 197, 94, 0.05) 100%);
            border: 2px solid rgba(34, 197, 94, 0.3);
        }
        
        .status-occupied {
            background: linear-gradient(135deg, rgba(251, 146, 60, 0.1) 0%, rgba(251, 146, 60, 0.05) 100%);
            border: 2px solid rgba(251, 146, 60, 0.3);
        }
        
        .status-theft {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(239, 68, 68, 0.1) 100%);
            border: 2px solid rgba(239, 68, 68, 0.4);
        }
        
        /* Pulse animation for available compartments */
        .status-available:hover {
            animation: glow 1s ease-in-out;
        }
        .swal2-confirm-custom {
            background-color: #3085d6;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            border: none;
        }
        .swal2-cancel-custom {
            background-color: #aaa;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            border: none;
        }

        
    </style>
</head>
<body class="font-inter bg-gradient-to-br from-indigo-500 via-purple-500 to-purple-700 min-h-screen text-gray-800">

<!-- Header -->
<header class="bg-white/10 backdrop-blur-lg border-b border-white/20 py-4">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-3">
                <div class="text-2xl sm:text-3xl">📦</div>
                <div class="text-xl sm:text-2xl font-bold text-white">LockerHub</div>
            </div>
            <div class="flex items-center gap-2 bg-green-500/20 px-3 sm:px-4 py-2 rounded-full text-white font-medium text-sm sm:text-base">
                <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                System Online
            </div>
        </div>
    </div>
</header>

<!-- Main Content -->
<main class="py-6 sm:py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Section Title -->
        <div class="text-center mb-8 sm:mb-12 text-white">
            <h1 class="text-3xl sm:text-4xl md:text-5xl responsive-title font-extrabold mb-2">Parcel Locker Management</h1>
            <p class="text-base sm:text-lg responsive-subtitle opacity-90">Monitor and control your smart locker compartments in real-time</p>
        </div>

        <!-- Locker Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8 mb-8 sm:mb-12">
            <!-- Compartment 1 -->
            <div id="compartment1" class="bg-white/95 rounded-2xl p-6 sm:p-8 text-center transition-all duration-500 cursor-pointer shadow-xl hover:shadow-2xl hover:-translate-y-2">
                <div class="flex justify-between items-center mb-6">
                    <div class="text-lg sm:text-xl responsive-card-title font-semibold text-gray-800">Compartment 1</div>
                    <div id="status1" class="px-3 py-1 rounded-xl text-xs font-medium uppercase tracking-wide bg-gray-100 text-gray-600">Loading...</div>
                </div>
                <div class="text-4xl sm:text-6xl mb-4">📦</div>
                <div class="mt-4 pt-4 border-t border-gray-200 text-left space-y-2">
                    <div class="flex justify-between text-xs sm:text-sm">
                        <span class="text-gray-600">Parcel Detected:</span>
                        <span id="parcel-detected1" class="font-mono font-medium">--</span>
                    </div>
                    <div class="flex justify-between text-xs sm:text-sm">
                        <span class="text-gray-600">Date:</span>
                        <span id="date1" class="font-mono font-medium">--</span>
                    </div>
                    <div class="flex justify-between text-xs sm:text-sm">
                        <span class="text-gray-600">Time:</span>
                        <span id="time1" class="font-mono font-medium">--</span>
                    </div>
                </div>
            </div>

            <!-- Compartment 2 -->
            <div id="compartment2" class="bg-white/95 rounded-2xl p-6 sm:p-8 text-center transition-all duration-500 cursor-pointer shadow-xl hover:shadow-2xl hover:-translate-y-2">
                <div class="flex justify-between items-center mb-6">
                    <div class="text-lg sm:text-xl responsive-card-title font-semibold text-gray-800">Compartment 2</div>
                    <div id="status2" class="px-3 py-1 rounded-xl text-xs font-medium uppercase tracking-wide bg-gray-100 text-gray-600">Loading...</div>
                </div>
                <div class="text-4xl sm:text-6xl mb-4">📦</div>
                <div class="mt-4 pt-4 border-t border-gray-200 text-left space-y-2">
                    <div class="flex justify-between text-xs sm:text-sm">
                        <span class="text-gray-600">Parcel Detected:</span>
                        <span id="parcel-detected2" class="font-mono font-medium">--</span>
                    </div>
                    <div class="flex justify-between text-xs sm:text-sm">
                        <span class="text-gray-600">Date:</span>
                        <span id="date2" class="font-mono font-medium">--</span>
                    </div>
                    <div class="flex justify-between text-xs sm:text-sm">
                        <span class="text-gray-600">Time:</span>
                        <span id="time2" class="font-mono font-medium">--</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Control Panel -->
        <div class="bg-white/95 rounded-2xl p-6 sm:p-8 text-center shadow-xl mb-8 sm:mb-12">
            <div class="text-xl sm:text-2xl font-semibold mb-6 text-gray-800">System Controls</div>
            <div class="flex flex-col sm:flex-row gap-6 justify-center items-center">

            <!-- Open Locker Toggle -->
            <label class="flex items-center gap-3 cursor-pointer">
                <span class="text-gray-700 font-medium">🔓 Open Locker</span>
                <div class="relative flex items-center">
                    <input type="checkbox" id="openLockerToggle" class="sr-only peer" onchange="handleOpenLockerToggle(this)">
                    <div class="w-14 h-8 bg-gray-300 rounded-full peer-checked:bg-green-500 transition-colors duration-300"></div>
                    <div class="w-6 h-6 bg-white rounded-full absolute top-1 left-1 peer-checked:translate-x-6 transition-transform duration-300"></div>
                    <span class="absolute text-xs text-white font-bold left-1.5 top-1.5 peer-checked:hidden">OFF</span>
                    <span class="absolute text-xs text-white font-bold right-1.5 top-1.5 hidden peer-checked:inline">ON</span>
                </div>
            </label>

            <!-- Reset Security Toggle -->
            <label class="flex items-center gap-3 cursor-pointer">
                <span class="text-gray-700 font-medium">🔄 Disable Security Mode</span>
                <div class="relative flex items-center">
                    <input type="checkbox" id="resetSecurityToggle" class="sr-only peer" onchange="handleResetSecurityToggle(this)">
                    <div class="w-14 h-8 bg-gray-300 rounded-full peer-checked:bg-red-500 transition-colors duration-300"></div>
                    <div class="w-6 h-6 bg-white rounded-full absolute top-1 left-1 peer-checked:translate-x-6 transition-transform duration-300"></div>
                    <span class="absolute text-xs text-white font-bold left-1.5 top-1.5 peer-checked:hidden">OFF</span>
                    <span class="absolute text-xs text-white font-bold right-1.5 top-1.5 hidden peer-checked:inline">ON</span>
                </div>
            </label>
            </div>
        </div>

        <!-- Recent Events Section -->
        <div class="bg-white/95 rounded-2xl p-6 sm:p-8 shadow-xl">
            <!-- Heading and Dropdown in one row -->
            <div class="flex justify-between items-center mb-6">
                <div class="text-xl sm:text-2xl font-semibold text-gray-800">Activity Log</div>
                <div class="flex items-center gap-2">
                    <label for="event-limit" class="text-sm text-gray-700 font-medium">Show:</label>
                    <select id="event-limit" class="border border-gray-300 rounded-lg text-sm px-3 py-2 bg-white shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200">
                        <option value="25">Last 25</option>
                        <option value="50" selected>Last 50</option>
                        <option value="100">Last 100</option>
                        <option value="200">Last 200</option>
                    </select>
                    <!-- Loading indicator -->
                    <div id="events-loading" class="hidden">
                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-indigo-600"></div>
                    </div>
                </div>
            </div>

            <!-- Events Table -->
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b-2 border-gray-200 bg-gray-50">
                            <th class="text-left py-3 px-2 sm:px-4 font-semibold text-gray-700 text-sm sm:text-base">Compartment</th>
                            <th class="text-left py-3 px-2 sm:px-4 font-semibold text-gray-700 text-sm sm:text-base">Status</th>
                            <th class="text-left py-3 px-2 sm:px-4 font-semibold text-gray-700 text-sm sm:text-base">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody id="events-table-body" class="divide-y divide-gray-100">
                        <!-- Events will be populated here -->
                    </tbody>
                </table>

                <!-- Empty state -->
                <div id="no-events" class="text-center py-12 text-gray-500 hidden">
                    <div class="text-4xl mb-3">📋</div>
                    <p class="text-base font-medium">No recent events to display</p>
                    <p class="text-sm text-gray-400 mt-1">Events will appear here when compartment activities occur</p>
                </div>

                <!-- Error state -->
                <div id="events-error" class="text-center py-12 text-red-500 hidden">
                    <div class="text-4xl mb-3">⚠️</div>
                    <p class="text-base font-medium">Failed to load events</p>
                    <button onclick="fetchEventsWithLimit(document.getElementById('event-limit').value)" 
                            class="mt-2 px-4 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors">
                        Try Again
                    </button>
                </div>
            </div>
        </div>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let alertShown = false;
let parcelAlertShown = false;

//* Initial values from PHP*
let initialAlertUser = <?= $alertUser ?>;
let initialSubmitDelivery = <?= $submittedDelivery ?>; // Fixed variable name

if (initialAlertUser == 1) {
    triggerAccessAlert();
}

if (initialSubmitDelivery == 1) {
    triggerParcelArrivedAlert();
}

document.addEventListener('DOMContentLoaded', () => {
    setInterval(() => {
        // Create a proper URL for polling
        const pollUrl = new URL(window.location.origin + window.location.pathname);
        pollUrl.searchParams.set('check_alert_user', '1');
        pollUrl.searchParams.set('check_submit_delivery', '1');
        
        fetch(pollUrl.toString())
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Polled data:', data);
                
                // Check if the response indicates an error
                if (data.success === false) {
                    console.error('Server error:', data.error);
                    return;
                }
                
                // Handle alerts
                if (data.alert_user == 1 && !alertShown) {
                    triggerAccessAlert();
                }

                if (data.submit_delivery == 1 && !parcelAlertShown) {
                    triggerParcelArrivedAlert();
                }
            })
            .catch(error => {
                console.error('Polling error:', error);
                // Optionally show a less intrusive error notification
                // Only show after multiple consecutive failures
            });
    }, 3000);
});

function triggerAccessAlert() {
    alertShown = true;
    Swal.fire({
    title: 'Someone is Requesting Access',
    html: 'A rider is at the locker.<br>Please enable the <strong>Open Locker</strong> switch manually if you are expecting a delivery.',
    icon: 'info',
    showConfirmButton: false,
    showCloseButton: true,
    customClass: {
        popup: '!rounded-xl !shadow-md',
        closeButton: 'text-gray-500 hover:text-red-500 focus:outline-none text-xl',
        title: '!text-lg !font-semibold text-gray-800',
        htmlContainer: '!text-sm text-gray-600'
    },
    buttonsStyling: false
}).then((result) => {
        if (result.isConfirmed) {
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=toggle_reset&value=on'
            }).then(() => {
                Swal.fire({
                    title: 'Access Granted!',
                    text: 'Locker is now open.',
                    icon: 'success',
                    confirmButtonText: 'Got it',
                    customClass: {
                        confirmButton: 'swal2-confirm-custom'
                    },
                    buttonsStyling: false
                });
            }).catch(() => {
                Swal.fire({
                    title: 'Connection Error',
                    text: 'Unable to communicate with the server.',
                    icon: 'error',
                    confirmButtonText: 'Retry',
                    customClass: {
                        confirmButton: 'swal2-confirm-custom'
                    },
                    buttonsStyling: false
                });
            });
        }
        //* Always reset alert_user*
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=toggle_alert_user&value=off'
        }).then(() => {
            alertShown = false;
        });
    });
}

function triggerParcelArrivedAlert() {
    parcelAlertShown = true;
    Swal.fire({
        title: 'Parcel Delivered!',
        html: 'A parcel has just been placed in your locker. You may now retrieve it.',
        icon: 'success',
        timer: 5000,
        timerProgressBar: true,
        showConfirmButton: false,
        showCloseButton: true,
        customClass: {
            popup: '!rounded-xl !shadow-md',
            closeButton: 'text-gray-500 hover:text-green-500 focus:outline-none text-xl',
            title: '!text-lg !font-semibold text-gray-800',
            htmlContainer: '!text-sm text-gray-600'
        },
        buttonsStyling: false
    }).then((result) => {
        //* Always reset submit_delivery after alert is shown/closed*
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=toggle_submit_delivery&value=off'
        }).then(() => {
            parcelAlertShown = false;
        });
    });
}
</script>
</body>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const statusMap = {
        "Empty": ["Available", "bg-green-100 text-green-800"],
        "Occupied": ["Occupied", "bg-orange-100 text-orange-800"],
        "Theft": ["Security Alert", "bg-red-100 text-red-800"],
        "Retrieved": ["Available", "bg-green-100 text-green-800"]
    };

    const statusBackgroundMap = {
        "Empty": "status-available",
        "Occupied": "status-occupied", 
        "Theft": "status-theft",
        "Retrieved": "status-available"
    };

    function formatTimestamp(timestamp) {
        const date = new Date(timestamp);
        const options = {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        };
        return date.toLocaleDateString('en-US', options);
    }

    function getStatusBadge(status) {
        const [label, classes] = statusMap[status] || ["Unknown", "bg-gray-100 text-gray-800"];
        return `<span class="px-2 py-1 rounded-full text-xs font-medium ${classes}">${label}</span>`;
    }

    function updateCompartmentVisuals(compartmentId, status) {
        const compartmentElement = document.getElementById(`compartment${compartmentId}`);
        
        // Remove all status classes
        compartmentElement.classList.remove('status-available', 'status-occupied', 'status-theft', 'blink-red', 'glow-green');
        
        // Add appropriate status class
        const bgClass = statusBackgroundMap[status] || 'bg-white/95';
        compartmentElement.classList.add(bgClass);
        
        // Add special animations
        if (status === 'Theft') {
            compartmentElement.classList.add('blink-red');
            // Add shake effect for theft detection
            setTimeout(() => {
                compartmentElement.classList.add('shake');
                setTimeout(() => compartmentElement.classList.remove('shake'), 500);
            }, 100);
        } else if (status === 'Empty' || status === 'Retrieved') {
            compartmentElement.classList.add('glow-green');
        }
    }


    async function handleOpenLockerToggle(el) {
        const state = el.checked ? 'on' : 'off';

        Swal.fire({
            title: `${state === 'on' ? 'Enabling' : 'Disabling'} Locker Access...`,
            text: `Sending ${state} command to locker system...`,
            icon: 'info',
            allowOutsideClick: false,
            showConfirmButton: false,
            willOpen: () => Swal.showLoading()
        });

        try {
            const formData = new FormData();
            formData.append('action', 'toggle_permission');
            formData.append('value', state);

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (result.success) {
                Swal.fire({
                    title: `Locker ${state === 'on' ? 'Enabled' : 'Disabled'}!`,
                    text: `Locker access has been ${state === 'on' ? 'enabled' : 'disabled'}.`,
                    icon: 'success',
                    confirmButtonColor: '#10b981',
                    timer: 2000,
                    showConfirmButton: false,
                    timerProgressBar: true
                });
            } else {
                throw new Error(result.error || 'Failed to update locker state.');
            }
        } catch (error) {
            Swal.fire({
                title: 'Error',
                text: error.message || 'An error occurred while updating locker state.',
                icon: 'error',
                confirmButtonColor: '#ef4444'
            });
            el.checked = !el.checked; // Revert toggle on error
        }
    }

    async function handleResetSecurityToggle(el) {
        const state = el.checked ? 'on' : 'off';

        Swal.fire({
            title: `${state === 'on' ? 'Enabling' : 'Disabling'} Security Reset...`,
            text: `Sending ${state} command to locker system...`,
            icon: 'warning',
            allowOutsideClick: false,
            showConfirmButton: false,
            willOpen: () => Swal.showLoading()
        });

        try {
            const formData = new FormData();
            formData.append('action', 'toggle_reset');
            formData.append('value', state);

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (result.success) {
                Swal.fire({
                    title: `Security Reset ${state === 'on' ? 'Enabled' : 'Disabled'}!`,
                    text: `Security reset feature has been ${state === 'on' ? 'enabled' : 'disabled'}.`,
                    icon: 'success',
                    confirmButtonColor: '#10b981',
                    timer: 2000,
                    showConfirmButton: false,
                    timerProgressBar: true
                });
            } else {
                throw new Error(result.error || 'Failed to update reset state.');
            }
        } catch (error) {
            Swal.fire({
                title: 'Error',
                text: error.message || 'An error occurred while updating security reset.',
                icon: 'error',
                confirmButtonColor: '#ef4444'
            });
            el.checked = !el.checked; // Revert toggle on error
        }
    }



    async function fetchData() {
        try {
            const res = await fetch("?fetch=1");
            const data = await res.json();

            // Update compartments
            data.compartments.forEach(comp => {
                const cid = comp.compartment_id.slice(-1);
                const [label, cssClass] = statusMap[comp.status] || ["Unknown", "bg-gray-100 text-gray-800"];

                const statusElement = document.getElementById(`status${cid}`);
                statusElement.textContent = label;
                statusElement.className = `px-3 py-1 rounded-xl text-xs font-medium uppercase tracking-wide ${cssClass}`;
                
                document.getElementById(`parcel-detected${cid}`).textContent = comp.is_parcel_detected ? "Yes" : "No";
                

                const timestamp = new Date(comp.timestamp);

                // Format date: "June 23, 2025"
                const formattedDate = timestamp.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });

                // Format time: "1:24 PM"
                const formattedTime = timestamp.toLocaleTimeString('en-US', {
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });

                // Set values
                document.getElementById(`date${cid}`).textContent = formattedDate;
                document.getElementById(`time${cid}`).textContent = formattedTime;
                                
                // Update visual appearance
                updateCompartmentVisuals(cid, comp.status);
            });

            // Update events table
            const tableBody = document.getElementById("events-table-body");
            const noEventsDiv = document.getElementById("no-events");
            
            if (data.events && data.events.length > 0) {
                tableBody.innerHTML = "";
                noEventsDiv.classList.add("hidden");
                
                data.events.forEach(ev => {
                    const row = document.createElement("tr");
                    row.className = "hover:bg-gray-50 transition-colors duration-200";
                    
                    const compartmentNo = ev.compartment_id.replace('compartment', '');
                    
                    row.innerHTML = `
                        <td class="py-3 px-2 sm:px-4 font-mono font-medium text-sm sm:text-base">${compartmentNo}</td>
                        <td class="py-3 px-2 sm:px-4">${getStatusBadge(ev.status)}</td>
                        <td class="py-3 px-2 sm:px-4 font-mono text-xs sm:text-sm text-gray-600">${formatTimestamp(ev.timestamp)}</td>
                    `;
                    
                    tableBody.appendChild(row);
                });
            } else {
                tableBody.innerHTML = "";
                noEventsDiv.classList.remove("hidden");
            }
        } catch (e) {
            console.error("Fetch failed", e);
            
            // Show error notification
            if (window.Swal) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Failed to fetch latest data',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            }
        }
    }

    document.addEventListener("DOMContentLoaded", () => {
        // Initial load with welcome message
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'info',
            title: 'LockerHub System',
            text: 'Loading compartment data...',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });
        
        fetchData();
        setInterval(fetchData, 5000);
    });
</script>
</html>