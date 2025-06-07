// Import the functions you need from the SDKs you need
import { initializeApp } from "firebase/app";
import { getAuth, onAuthStateChanged } from "firebase/auth";
// TODO: Add SDKs for Firebase products that you want to use
// https://firebase.google.com/docs/web/setup#available-libraries

// Your web app's Firebase configuration
// For Firebase JS SDK v7.20.0 and later, measurementId is optional
const firebaseConfig = {
    apiKey: "AIzaSyBWJ_fJ_1xfnyL5bQ5rrDUJkowKhmHQ_M4",
    authDomain: "parcel-box-iot.firebaseapp.com",
    databaseURL: "https://parcel-box-iot-default-rtdb.asia-southeast1.firebasedatabase.app",
    projectId: "parcel-box-iot",
    storageBucket: "parcel-box-iot.firebasestorage.app",
    messagingSenderId: "827603200268",
    appId: "1:827603200268:web:3e2ca22e29728b7d960129",
    measurementId: "G-X9JM37T7SM"
};

// Initialize Firebase
const firebaseApp = initializeApp({
    
});  
const auth = getAuth(firebaseApp);

onAuthStateChanged(auth, (user) => {
    if (user)  {};
});

// Store current status for each compartment
const compartmentStates = {
    compartment1: 'default',
    compartment2: 'default'
};

function getIconForStatus(status) {
switch(status) {
    case 'occupied': return '📋';
    case 'theft': return '🚨';
    default: return '📦';
}
}

function getStatusText(status) {
switch(status) {
    case 'occupied': return 'Occupied';
    case 'theft': return 'Alert';
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
} else {
    statusIndicator.classList.add('status-default');
}
}

function cycleStatus(id) {
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

function allowRider() {
Swal.fire({
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
}).then((result) => {
    if (result.isConfirmed) {
    fetch('/reset')
        .then(() => {
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
        })
        .catch(() => {
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
});
}

// Initialize default states
setStatus('compartment1', 'default');
setStatus('compartment2', 'default');

// Add custom SweetAlert2 styles
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
`;
document.head.appendChild(style)