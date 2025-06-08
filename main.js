// Local storage for compartment states
let compartmentStates = {
    compartment1: {
        status: 'Available',
        distance: 25.4,
        parcelDetected: false,
        securityMode: false,
        icon: '📦'
    },
    compartment2: {
        status: 'Available',
        distance: 30.2,
        parcelDetected: false,
        securityMode: false,
        icon: '📦'
    }
};

let systemState = {
    solenoidState: 'LOCKED',
    buzzerState: 'OFF',
    lastPermission: false,
    lastReset: false
};

let eventsLog = [
    {
        type: 'system_init',
        compartment: null,
        message: 'System initialized',
        timestamp: new Date().toLocaleString()
    }
];

// Status cycling for compartments
const statusCycle = ['Available', 'Occupied', 'Security Alert'];
const statusClasses = {
    'Available': 'status-available',
    'Occupied': 'status-occupied',
    'Security Alert': 'status-security'
};
const statusIcons = {
    'Available': '📦',
    'Occupied': '📮',
    'Security Alert': '🚨'
};

function cycleStatus(compartmentId) {
    const state = compartmentStates[compartmentId];
    const currentIndex = statusCycle.indexOf(state.status);
    const nextIndex = (currentIndex + 1) % statusCycle.length;
    const newStatus = statusCycle[nextIndex];
    
    // Update state
    state.status = newStatus;
    state.icon = statusIcons[newStatus];
    
    // Update based on status
    switch(newStatus) {
        case 'Available':
            state.distance = Math.random() * 10 + 25; // 25-35 cm
            state.parcelDetected = false;
            state.securityMode = false;
            break;
        case 'Occupied':
            state.distance = Math.random() * 5 + 5; // 5-10 cm
            state.parcelDetected = true;
            state.securityMode = false;
            break;
        case 'Security Alert':
            state.distance = Math.random() * 5 + 15; // 15-20 cm
            state.parcelDetected = true;
            state.securityMode = true;
            break;
    }
    
    // Log event
    logEvent('status_change', compartmentId, `Compartment ${compartmentId.slice(-1)} status changed to ${newStatus}`);
    
    // Update UI
    updateCompartmentDisplay(compartmentId);
    updateEventsDisplay();
    
    // Show feedback
    Swal.fire({
        title: 'Status Updated',
        text: `Compartment ${compartmentId.slice(-1)} is now ${newStatus}`,
        icon: 'info',
        timer: 1500,
        showConfirmButton: false
    });
}

function updateCompartmentDisplay(compartmentId) {
    const compartment = document.getElementById(compartmentId);
    const state = compartmentStates[compartmentId];
    const compartmentNum = compartmentId.slice(-1);
    
    // Update status indicator
    const statusIndicator = compartment.querySelector('.status-indicator');
    statusIndicator.textContent = state.status;
    statusIndicator.className = `status-indicator ${statusClasses[state.status]}`;
    
    // Update icon
    const icon = compartment.querySelector('.compartment-icon');
    icon.textContent = state.icon;
    
    // Update details
    document.getElementById(`distance${compartmentNum}`).textContent = `${state.distance.toFixed(1)} cm`;
    document.getElementById(`parcel${compartmentNum}`).textContent = state.parcelDetected ? 'Yes' : 'No';
    document.getElementById(`security${compartmentNum}`).textContent = state.securityMode ? 'On' : 'Off';
}

function allowRider() {
    systemState.solenoidState = 'UNLOCKED';
    systemState.lastPermission = true;
    
    logEvent('permission_granted', null, 'Access granted - Locker unlocked');
    updateEventsDisplay();
    
    Swal.fire({
        title: 'Access Granted!',
        text: 'Locker has been unlocked',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
    });
    
    // Auto-lock after 10 seconds
    setTimeout(() => {
        systemState.solenoidState = 'LOCKED';
        logEvent('auto_lock', null, 'Locker automatically locked');
        updateEventsDisplay();
    }, 10000);
}

function resetSecurity() {
    // Reset all compartments
    Object.keys(compartmentStates).forEach(compartmentId => {
        const state = compartmentStates[compartmentId];
        if (state.securityMode) {
            state.status = 'Available';
            state.securityMode = false;
            state.parcelDetected = false;
            state.distance = Math.random() * 10 + 25;
            state.icon = '📦';
            updateCompartmentDisplay(compartmentId);
        }
    });
    
    // Reset system state
    systemState.solenoidState = 'LOCKED';
    systemState.buzzerState = 'OFF';
    systemState.lastReset = true;
    
    logEvent('security_reset', null, 'Security system reset - All alerts cleared');
    updateEventsDisplay();
    
    Swal.fire({
        title: 'Security Reset!',
        text: 'All security alerts have been cleared',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
    });
}

function logEvent(type, compartment, message) {
    eventsLog.unshift({
        type: type,
        compartment: compartment,
        message: message,
        timestamp: new Date().toLocaleString()
    });
    
    // Keep only last 20 events
    if (eventsLog.length > 20) {
        eventsLog = eventsLog.slice(0, 20);
    }
}

function updateEventsDisplay() {
    const container = document.getElementById('events-container');
    container.innerHTML = '';
    
    eventsLog.slice(0, 10).forEach(event => {
        const eventElement = document.createElement('div');
        eventElement.className = 'event-item';
        eventElement.innerHTML = `
            <div class="event-info">
                <div class="event-type">${event.message}</div>
                <div class="event-details">Type: ${event.type}${event.compartment ? ` | Compartment: ${event.compartment.slice(-1)}` : ''}</div>
            </div>
            <div class="event-time">${event.timestamp}</div>
        `;
        container.appendChild(eventElement);
    });
}

// Initialize display
document.addEventListener('DOMContentLoaded', function() {
    updateCompartmentDisplay('compartment1');
    updateCompartmentDisplay('compartment2');
    updateEventsDisplay();
    
    // Simulate some random distance changes
    setInterval(() => {
        Object.keys(compartmentStates).forEach(compartmentId => {
            const state = compartmentStates[compartmentId];
            if (state.status === 'Available') {
                // Small random variations for available compartments
                state.distance += (Math.random() - 0.5) * 2;
                state.distance = Math.max(20, Math.min(35, state.distance));
                updateCompartmentDisplay(compartmentId);
            }
        });
    }, 5000);
});
document.head.appendChild(style)