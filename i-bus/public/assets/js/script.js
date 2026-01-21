// System-wide JavaScript functions
document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if(!confirm('Are you sure you want to delete this item?')) {
                e.preventDefault();
            }
        });
    });

    // Real-time clock
    function updateClock() {
        const now = new Date();
        const clockElement = document.getElementById('real-time-clock');
        if(clockElement) {
            clockElement.textContent = now.toLocaleTimeString();
        }
    }
    setInterval(updateClock, 1000);
    updateClock();

    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    // Auto-format phone numbers
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if(value.length > 3 && value.length <= 6) {
                value = value.replace(/(\d{3})(\d+)/, '$1-$2');
            } else if(value.length > 6) {
                value = value.replace(/(\d{3})(\d{3})(\d+)/, '$1-$2-$3');
            }
            e.target.value = value;
        });
    });
});

// AJAX functions
function loadNotifications(userId) {
    fetch(`../api/notifications.php?user_id=${userId}&unread=1`)
        .then(response => response.json())
        .then(data => {
            if(data.length > 0) {
                updateNotificationBadge(data.length);
            }
        })
        .catch(error => console.error('Error loading notifications:', error));
}

function updateNotificationBadge(count) {
    const badge = document.getElementById('notification-badge');
    if(badge) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.classList.remove('d-none');
    }
}

function markNotificationAsRead(notificationId) {
    fetch('../api/notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            notification_id: notificationId,
            action: 'mark_read'
        })
    });
}

// QR Code Scanner (if implemented)
function startQRScanner() {
    const video = document.createElement('video');
    const canvasElement = document.getElementById('qr-canvas');
    const canvas = canvasElement.getContext('2d');
    
    navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } })
        .then(function(stream) {
            video.srcObject = stream;
            video.setAttribute("playsinline", true);
            video.play();
            requestAnimationFrame(tick);
        });

    function tick() {
        if (video.readyState === video.HAVE_ENOUGH_DATA) {
            canvasElement.height = video.videoHeight;
            canvasElement.width = video.videoWidth;
            canvas.drawImage(video, 0, 0, canvasElement.width, canvasElement.height);
            
            // QR code decoding logic here
            
            requestAnimationFrame(tick);
        }
    }
}