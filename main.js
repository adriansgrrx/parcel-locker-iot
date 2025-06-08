// Updated main.js with integrated API endpoints
const API_BASE_URL = window.location.origin + '/api/'; // Use current domain with /api/ path
const UPDATE_INTERVAL = 3000; // Update every 3 seconds

// Store current status for each compartment
const compartmentStates = {
    compartment1: 'default',
    compartment2: 'default'
};

// Global variables for database integration
let isOnlineMode = false;
let updateInterval;
let lastUpdateTime = null;

// Initialize the system
document.addEventListener('DOMContentLoaded', function() {
    initializeSystem();
    setupEventListeners();
    checkDatabaseConnection();
});

function initializeSystem() {
    console.log('🚀 Initializing Enhanced Locker Management System...');
    
    // Set initial states
    setStatus('compartment1', 'default');
    setStatus('compartment2', 'default');
    
    // Add custom SweetAlert2 styles
    addCustomStyles();
    
    // Add status indicators to UI
    addDatabaseStatusIndicator();
}

function setupEventListeners() {
    // Button handlers are now integrated with the new functions
    console.log('✅ Event listeners configured');
}

// Database connection and status checking
async function checkDatabaseConnection() {
    try {
        const response = await fetch(API_BASE_URL + 'get_status');
        const data = await response.json();
        
        if (data.compartments) {
            isOnlineMode = true;
            updateDatabaseStatus('online');
            startAutoUpdate();
            loadCurrentStatus();
            console.log('✅ Database connected successfully');
        } else {
            throw new Error('Invalid response structure');
        }
    } catch (error) {
        console.warn('⚠️ Database connection failed, running in offline mode:', error);
        isOnlineMode = false;
        updateDatabaseStatus('offline');
    }
}

function startAutoUpdate() {
    if (updateInterval) clearInterval(updateInterval);
    
    if (isOnlineMode) {
        updateInterval = setInterval(loadCurrentStatus, UPDATE_INTERVAL);
        console.log(`🔄 Auto-update started (${UPDATE_INTERVAL}ms interval)`);
    }
}

async function loadCurrentStatus() {
    if (!isOnlineMode) return;
    
    try {
        const response = await fetch(API_BASE_URL + 'get_status');
        const data = await response.json();
        
        if (data.compartments) {
            updateUIFromDatabase(data);
            lastUpdateTime = new Date();
            updateDatabaseStatus('online');
        } else {
            throw new Error('No compartment data received');
        }
    } catch (error) {
        console.error('❌ Error loading status:', error);
        updateDatabaseStatus('error');
        
        // Fallback to offline mode after multiple failures
        setTimeout(checkDatabaseConnection, 10000);
    }
}

function updateUIFromDatabase(data) {
    // Update compartments based on database structure
    const compartments = data.compartments;
    
    if (compartments.C1) {
        const c1Status = mapDatabaseStatusToUI(compartments.C1.status);
        setStatus('compartment1', c1Status);
        updateCompartmentInfo('compartment1', compartments.C1);
    }
    
    if (compartments.C2) {
        const c2Status = mapDatabaseStatusToUI(compartments.C2.status);
        setStatus('compartment2', c2Status);
        updateCompartmentInfo('compartment2', compartments.C2);
    }
    
    // Update system status
    if (data.system_status) {
        updateSystemStatusDisplay(data.system_status);
    }
    
    // Update last update time
    updateLastUpdateDisplay();
}

function mapDatabaseStatusToUI(dbStatus) {
    const statusMap = {
        'Empty': 'default',
        'Occupied': 'occupied',
        'Theft': 'theft',
        'Retrieved': 'default'
    };
    return statusMap[dbStatus] || 'default';
}

function mapUIStatusToDatabase(uiStatus) {
    const statusMap = {
        'default': 'Empty',
        'occupied': 'Occupied',
        'theft': 'Theft'
    };
    return statusMap[uiStatus] || 'Empty';
}

// Enhanced status functions
function getIconForStatus(status) {
    switch(status) {
        case 'occupied': return '📋';
        case 'theft': return '🚨';
        case 'loading': return '⏳';
        case 'offline': return '⚠️';
        default: return '📦';
    }
}

function getStatusText(status) {
    switch(status) {
        case 'occupied': return 'Occupied';
        case 'theft': return 'Alert';
        case 'loading': return 'Loading...';
        case 'offline': return 'Offline';
        default: return 'Available';
    }
}

function setStatus(id, status) {
    const card = document.getElementById(id);
    const icon = card.querySelector('.compartment-icon');
    const statusIndicator = card.querySelector('.status-indicator');
    
    // Store the current state
    compartmentStates[id] = status;
    
    // Reset classes
    card.className = 'compartment-card';
    statusIndicator.className = 'status-indicator';
    
    // Update icon and styling based on status
    icon.textContent = getIconForStatus(status);
    statusIndicator.textContent = getStatusText(status);
    
    if (status === 'occupied') {
        card.classList.add('occupied');
        statusIndicator.classList.add('status-occupied');
    } else if (status === 'theft') {
        card.classList.add('theft');
        statusIndicator.classList.add('status-theft');
    } else if (status === 'loading') {
        card.classList.add('loading');
        statusIndicator.classList.add('status-loading');
    } else if (status === 'offline') {
        card.classList.add('offline');
        statusIndicator.classList.add('status-offline');
    } else {
        statusIndicator.classList.add('status-default');
    }
}

function updateCompartmentInfo(compartmentId, compartmentData) {
    const card = document.getElementById(compartmentId);
    let infoElement = card.querySelector('.compartment-details');
    
    if (!infoElement) {
        infoElement = document.createElement('div');
        infoElement.className = 'compartment-details';
        card.appendChild(infoElement);
    }
    
    infoElement.innerHTML = `
        <small>
            Distance: ${compartmentData.distance_cm.toFixed(1)}cm<br>
            Parcel: ${compartmentData.is_parcel_detected ? 'Yes' : 'No'}<br>
            Security: ${compartmentData.is_security_mode ? 'Active' : 'Inactive'}<br>
            Updated: ${new Date(compartmentData.timestamp).toLocaleTimeString()}
        </small>
    `;
}

// Enhanced cycling function with database sync
async function cycleStatus(id) {
    if (isOnlineMode) {
        // In online mode, don't allow manual cycling
        showNotification('Manual cycling disabled in online mode', 'info');
        return;
    }
    
    const currentStatus = compartmentStates[id];
    let nextStatus;
    
    switch(currentStatus) {
        case 'default':
            nextStatus = 'occupied';
            break;
        case 'occupied':
            nextStatus = 'theft';
            break;
        case 'theft':
            nextStatus = 'default';
            break;
        default:
            nextStatus = 'default';
    }
    
    setStatus(id, nextStatus);
}

// Enhanced allow rider function with database integration
async function allowRider() {
    const result = await Swal.fire({
        title: 'Someone is Requesting Access',
        text: 'Are you expecting any delivery\nat this moment?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Allow Access',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        customClass: {
            confirmButton: 'swal2-confirm-custom',
            cancelButton: 'swal2-cancel-custom'
        },
        buttonsStyling: false
    });
    
    if (result.isConfirmed) {
        try {
            const response = await fetch(API_BASE_URL + 'allow', {
                method: 'GET'
            });
            const data = await response.json();
            
            if (data.success) {
                await Swal.fire({
                    title: 'Access Granted!',
                    text: 'Locker is now open.',
                    icon: 'success',
                    confirmButtonText: 'Got it',
                    customClass: {
                        confirmButton: 'swal2-confirm-custom'
                    },
                    buttonsStyling: false
                });
                
                // Refresh status if online
                if (isOnlineMode) {
                    setTimeout(loadCurrentStatus, 1000);
                }
            } else {
                throw new Error(data.message || 'Unknown error');
            }
        } catch (error) {
            await Swal.fire({
                title: 'Connection Error',
                text: 'Unable to communicate with the server.',
                icon: 'error',
                confirmButtonText: 'Retry',
                customClass: {
                    confirmButton: 'swal2-confirm-custom'
                },
                buttonsStyling: false
            });
        }
    }
}

// Reset security function
async function resetSecurity() {
    try {
        const response = await fetch(API_BASE_URL + 'reset', {
            method: 'GET'
        });
        const data = await response.json();
        
        if (data.success) {
            showNotification('Security system reset successfully', 'success');
            
            if (isOnlineMode) {
                setTimeout(loadCurrentStatus, 1000);
            } else {
                // Reset UI states in offline mode
                setStatus('compartment1', 'default');
                setStatus('compartment2', 'default');
            }
        } else {
            throw new Error(data.message || 'Reset failed');
        }
    } catch (error) {
        showNotification('Failed to reset security system', 'error');
    }
}

// UI Helper Functions
function addDatabaseStatusIndicator() {
    const header = document.querySelector('.header-content');
    if (header) {
        const dbStatus = document.createElement('div');
        dbStatus.id = 'db-status';
        dbStatus.className = 'db-status';
        dbStatus.innerHTML = `
            <div class="db-indicator offline"></div>
            <span>Database: Checking...</span>
        `;
        header.appendChild(dbStatus);
    }
}

function updateDatabaseStatus(status) {
    const dbStatus = document.getElementById('db-status');
    if (!dbStatus) return;
    
    const indicator = dbStatus.querySelector('.db-indicator');
    const text = dbStatus.querySelector('span');
    
    indicator.className = `db-indicator ${status}`;
    
    switch(status) {
        case 'online':
            text.textContent = 'Database: Connected';
            break;
        case 'offline':
            text.textContent = 'Database: Offline';
            break;
        case 'error':
            text.textContent = 'Database: Error';
            break;
        default:
            text.textContent = 'Database: Unknown';
    }
}

function updateSystemStatusDisplay(systemStatus) {
    // Update solenoid status
    const solenoidStatus = document.querySelector('.solenoid-status') || createStatusElement('solenoid-status');
    solenoidStatus.textContent = `Solenoid: ${systemStatus.solenoid_state}`;
    solenoidStatus.className = `solenoid-status ${systemStatus.solenoid_state.toLowerCase()}`;
    
    // Update buzzer status
    const buzzerStatus = document.querySelector('.buzzer-status') || createStatusElement('buzzer-status');
    buzzerStatus.textContent = `Buzzer: ${systemStatus.buzzer_state}`;
    buzzerStatus.className = `buzzer-status ${systemStatus.buzzer_state.toLowerCase()}`;
}

function createStatusElement(className) {
    const element = document.createElement('div');
    element.className = className;
    
    const controlPanel = document.querySelector('.control-panel');
    if (controlPanel) {
        controlPanel.appendChild(element);
    }
    
    return element;
}

function updateLastUpdateDisplay() {
    let updateDisplay = document.getElementById('last-update');
    
    if (!updateDisplay) {
        updateDisplay = document.createElement('div');
        updateDisplay.id = 'last-update';
        updateDisplay.className = 'last-update';
        
        const controlPanel = document.querySelector('.control-panel');
        if (controlPanel) {
            controlPanel.appendChild(updateDisplay);
        }
    }
    
    if (lastUpdateTime) {
        updateDisplay.textContent = `Last update: ${lastUpdateTime.toLocaleTimeString()}`;
    }
}

function showNotification(message, type = 'info') {
    const toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
    
    toast.fire({
        icon: type,
        title: message
    });
}

function addCustomStyles() {
    const style = document.createElement('style');
    style.textContent = `
        .swal2-confirm-custom {
            background: linear-gradient(135deg, #6366f1, #4f46e5) !important;
            color: white !important;
            border: none !important;
            border-radius: 12px !important;
            padding: 12px 24px !important;
            font-weight: 600 !important;
            margin: 0 8px !important;
        }
        .swal2-cancel-custom {
            background: #f1f5f9 !important;
            color: #64748b !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 12px !important;
            padding: 12px 24px !important;
            font-weight: 600 !important;
            margin: 0 8px !important;
        }
        .db-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #64748b;
        }
        .db-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #94a3b8;
        }
        .db-indicator.online { 
            background: #10b981; 
        }
        .db-indicator.offline { 
            background: #f59e0b; 
        }
        .db-indicator.error { 
            background: #ef4444; 
        }
    `;
}

document.head.appendChild(style)