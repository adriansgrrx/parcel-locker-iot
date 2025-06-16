let alertShown = false;
let parcelAlertShown = false;
let previousParcelCounts = { c1: 0, c2: 0, total: 0 }; // Track previous counts for animations

// Mock initial values - replace with actual PHP values
let initialAlertUser = 0; // Replace with <?= $alertUser ?>
let initialSubmitDelivery = 0; // Replace with <?= $submittedDelivery ?>

let weeklyChartInstance = null;

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

// Function to update parcel counters with animation
function updateParcelCounters(counts) {
    const c1Counter = document.getElementById('c1-counter');
    const c2Counter = document.getElementById('c2-counter');
    const totalCounter = document.getElementById('total-counter');

    // Animate if counts increased
    if (counts.c1 > previousParcelCounts.c1) {
        c1Counter.classList.add('counter-bounce');
        setTimeout(() => c1Counter.classList.remove('counter-bounce'), 600);
    }
    
    if (counts.c2 > previousParcelCounts.c2) {
        c2Counter.classList.add('counter-bounce');
        setTimeout(() => c2Counter.classList.remove('counter-bounce'), 600);
    }
    
    if (counts.total > previousParcelCounts.total) {
        totalCounter.classList.add('counter-bounce');
        setTimeout(() => totalCounter.classList.remove('counter-bounce'), 600);
    }

    // Update the display
    c1Counter.textContent = counts.c1;
    c2Counter.textContent = counts.c2;
    totalCounter.textContent = counts.total;

    // Store current counts for next comparison
    previousParcelCounts = { ...counts };
}

// Function to fetch parcel counts separately
async function fetchParcelCounts() {
    try {
        const response = await fetch('?get_parcel_counts=1');
        const data = await response.json();
        
        if (data.success) {
            updateParcelCounters({
                c1: data.c1_count,
                c2: data.c2_count,
                total: data.total_count
            });
        } else {
            console.error('Failed to fetch parcel counts:', data.error);
        }
    } catch (error) {
        console.error('Error fetching parcel counts:', error);
    }
}

async function fetchWeeklyParcelData() {
    try {
        const res = await fetch("your_php_script.php?weekly_data=1");
        const data = await res.json();

        updateWeeklyChart(data);
    } catch (error) {
        console.error("Error fetching weekly parcel data:", error);
    }
}

function updateWeeklyChart(data) {
    const ctx = document.getElementById("weeklyChart").getContext("2d");
    const labels = data.labels;
    const values = data.values;

    // Update stats
    document.getElementById("thisWeekCount").textContent = data.total;
    document.getElementById("dailyAverage").textContent = data.average;
    document.getElementById("peakDay").textContent = data.peak_day;

    if (weeklyChartInstance) {
        weeklyChartInstance.data.labels = labels;
        weeklyChartInstance.data.datasets[0].data = values;
        weeklyChartInstance.update();
    } else {
        weeklyChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Parcels per Day',
                    data: values,
                    backgroundColor: '#6366f1'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    }
}

function refreshChart() {
    fetchWeeklyParcelData();
}

document.addEventListener("DOMContentLoaded", () => {
    fetchWeeklyParcelData();
});