async function fetchData() {
    try {
        const response = await fetch("index.php");
        const data = await response.json();

        updateUIWithBackend(data.compartments, data.events);
    } catch (err) {
        console.error("Failed to fetch data:", err);
    }
}

function updateUIWithBackend(compartments, events) {
    compartments.forEach(comp => {
        const compId = `compartment${comp.compartment_id.slice(-1)}`;
        const statusMap = {
            'Empty': 'Available',
            'Occupied': 'Occupied',
            'Theft': 'Security Alert',
            'Retrieved': 'Available'
        };
        const visualStatus = statusMap[comp.status] || 'Available';

        compartmentStates[compId] = {
            status: visualStatus,
            distance: comp.distance_cm,
            parcelDetected: comp.is_parcel_detected,
            securityMode: comp.is_security_mode,
            icon: statusIcons[visualStatus]
        };
        updateCompartmentDisplay(compId);
    });

    // Replace log
    eventsLog = events.map(e => ({
        type: e.event_type,
        compartment: e.compartment_id ? `compartment${e.compartment_id.slice(-1)}` : null,
        message: `${e.event_type.replace(/_/g, ' ')} on ${e.compartment_id || 'System'}`,
        timestamp: new Date(e.timestamp).toLocaleString()
    }));
    updateEventsDisplay();
}

// Initial fetch
document.addEventListener('DOMContentLoaded', function () {
    fetchData();
    setInterval(fetchData, 5000); // Fetch every 5 seconds
});