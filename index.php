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
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
    exit;
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
                        <span class="text-gray-600">Distance:</span>
                        <span id="distance1" class="font-mono font-medium">--</span>
                    </div>
                    <div class="flex justify-between text-xs sm:text-sm">
                        <span class="text-gray-600">Parcel Detected:</span>
                        <span id="parcel1" class="font-mono font-medium">--</span>
                    </div>
                    <div class="flex justify-between text-xs sm:text-sm">
                        <span class="text-gray-600">Security Mode:</span>
                        <span id="security1" class="font-mono font-medium">--</span>
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
                        <span class="text-gray-600">Distance:</span>
                        <span id="distance2" class="font-mono font-medium">--</span>
                    </div>
                    <div class="flex justify-between text-xs sm:text-sm">
                        <span class="text-gray-600">Parcel Detected:</span>
                        <span id="parcel2" class="font-mono font-medium">--</span>
                    </div>
                    <div class="flex justify-between text-xs sm:text-sm">
                        <span class="text-gray-600">Security Mode:</span>
                        <span id="security2" class="font-mono font-medium">--</span>
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
                <span class="text-gray-700 font-medium">🔄 Security Mode</span>
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
            <div class="text-xl sm:text-2xl font-semibold mb-6 text-gray-800">Recent Events</div>
            
            <!-- Events Table -->
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b-2 border-gray-200">
                            <th class="text-left py-3 px-2 sm:px-4 font-semibold text-gray-700 text-sm sm:text-base">Compartment No.</th>
                            <th class="text-left py-3 px-2 sm:px-4 font-semibold text-gray-700 text-sm sm:text-base">Status</th>
                            <th class="text-left py-3 px-2 sm:px-4 font-semibold text-gray-700 text-sm sm:text-base">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody id="events-table-body" class="divide-y divide-gray-100">
                        <!-- Events will be populated here -->
                    </tbody>
                </table>
                
                <!-- Empty state -->
                <div id="no-events" class="text-center py-8 text-gray-500 hidden">
                    <div class="text-3xl sm:text-4xl mb-2">📋</div>
                    <p class="text-sm sm:text-base">No recent events to display</p>
                </div>
            </div>
        </div>
    </div>
</main>

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

//     async function openLocker() {
//     const { value: compartmentId } = await Swal.fire({
//         title: 'Select Compartment',
//         input: 'select',
//         inputOptions: {
//             'compartment1': 'Compartment 1',
//             'compartment2': 'Compartment 2'
//         },
//         inputPlaceholder: 'Choose a compartment',
//         showCancelButton: true,
//         confirmButtonText: 'Open',
//         confirmButtonColor: '#10b981',
//         cancelButtonColor: '#6b7280',
//     });

//     if (compartmentId) {
//         // Send permission trigger
//         const formData = new FormData();
//         formData.append('action', 'toggle_permission');
//         formData.append('value', 'on');

//         await fetch('control_handler.php', { method: 'POST', body: formData });

//         Swal.fire({
//             title: 'Opening Locker...',
//             text: `${compartmentId.replace('compartment', 'Compartment ')} is being opened`,
//             icon: 'success',
//             timer: 2000,
//             showConfirmButton: false,
//             timerProgressBar: true
//         });
//     }
// }


//     async function resetSecurity() {
//     const { value: compartmentId } = await Swal.fire({
//         title: 'Reset Security Mode',
//         text: 'Select compartment to reset security',
//         input: 'select',
//         inputOptions: {
//             'compartment1': 'Compartment 1',
//             'compartment2': 'Compartment 2'
//         },
//         inputPlaceholder: 'Choose a compartment',
//         showCancelButton: true,
//         confirmButtonText: 'Reset Security',
//         confirmButtonColor: '#ef4444',
//         cancelButtonColor: '#6b7280',
//         icon: 'warning'
//     });

//     if (compartmentId) {
//         try {
//             Swal.fire({
//                 title: 'Resetting Security...',
//                 allowOutsideClick: false,
//                 showConfirmButton: false,
//                 willOpen: () => {
//                     Swal.showLoading();
//                 }
//             });

//             // First, reset the selected compartment
//             const formData = new FormData();
//             formData.append('action', 'reset_security');
//             formData.append('compartment_id', compartmentId);

//             const response = await fetch(window.location.href, {
//                 method: 'POST',
//                 body: formData
//             });

//             const result = await response.json();

//             if (result.success) {
//                 // Trigger reset flag
//                 const flagForm = new FormData();
//                 flagForm.append('action', 'toggle_reset');
//                 flagForm.append('value', 'on');

//                 await fetch('control_handler.php', {
//                     method: 'POST',
//                     body: flagForm
//                 });

//                 Swal.fire({
//                     title: 'Security Reset Successfully!',
//                     text: `${compartmentId.replace('compartment', 'Compartment ')} has been reset to default state`,
//                     icon: 'success',
//                     confirmButtonColor: '#10b981',
//                     timer: 3000,
//                     timerProgressBar: true
//                 });

//                 fetchData();
//             } else {
//                 throw new Error(result.error || 'Reset failed');
//             }
//         } catch (error) {
//             Swal.fire({
//                 title: 'Reset Failed',
//                 text: 'Could not reset security mode. Please try again.',
//                 icon: 'error',
//                 confirmButtonColor: '#ef4444'
//             });
//         }
//     }
// }

// 
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
                
                document.getElementById(`distance${cid}`).textContent = comp.distance_cm + " cm";
                document.getElementById(`parcel${cid}`).textContent = comp.is_parcel_detected ? "Yes" : "No";
                document.getElementById(`security${cid}`).textContent = comp.is_security_mode ? "On" : "Off";
                
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
</body>
</html>