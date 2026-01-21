<?php
// Koneksi database
function getDBConnection() {
    $host = "localhost";
    $username = "root";
    $password = "";
    $database = "bus_management";
    
    $conn = new mysqli($host, $username, $password, $database);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Get all buses
function getAllBuses() {
    $conn = getDBConnection();
    $result = $conn->query("SELECT * FROM buses ORDER BY bus_number");
    $buses = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $buses[] = $row;
        }
    }
    $conn->close();
    return $buses;
}

// Get available buses
function getAvailableBuses() {
    $conn = getDBConnection();
    $result = $conn->query("SELECT * FROM buses WHERE status = 'available' ORDER BY bus_number");
    $buses = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $buses[] = $row;
        }
    }
    $conn->close();
    return $buses;
}

// Format date
function formatDate($date) {
    return date('d-m-Y', strtotime($date));
}

// Format time
function formatTime($time) {
    return date('h:i A', strtotime($time));
}

// Get greeting message based on time
function get_greeting() {
    $hour = date('H');
    if ($hour < 12) {
        return "Good Morning";
    } elseif ($hour < 18) {
        return "Good Afternoon";
    } else {
        return "Good Evening";
    }
}

// Get total bookings for student
function get_total_bookings($user_id) {
    $conn = getDBConnection();
    $query = "SELECT COUNT(*) as total FROM bookings WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $conn->close();
    return $row['total'] ?? 0;
}

// Get confirmed bookings for student
function get_confirmed_bookings($user_id) {
    $conn = getDBConnection();
    $query = "SELECT COUNT(*) as total FROM bookings WHERE user_id = ? AND status = 'confirmed'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $conn->close();
    return $row['total'] ?? 0;
}

// Get pending bookings for student
function get_pending_bookings($user_id) {
    $conn = getDBConnection();
    $query = "SELECT COUNT(*) as total FROM bookings WHERE user_id = ? AND status = 'pending'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $conn->close();
    return $row['total'] ?? 0;
}

// Get reward points for student
function get_reward_points($user_id) {
    $conn = getDBConnection();
    $query = "SELECT reward_points FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $conn->close();
    return $row['reward_points'] ?? 0;
}

// Get recent bookings for student
function get_recent_bookings($user_id, $limit = 5) {
    $conn = getDBConnection();
    $query = "SELECT b.booking_id, b.route_id, b.booking_date, b.booking_time, b.seat_number, b.status, r.from_location, r.to_location 
              FROM bookings b 
              JOIN routes r ON b.route_id = r.route_id 
              WHERE b.user_id = ? 
              ORDER BY b.booking_date DESC, b.booking_time DESC 
              LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $bookings = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $bookings[] = $row;
        }
    }
    $conn->close();
    return $bookings;
}

// Check if user has unread notifications
function has_unread_notifications($user_id) {
    $conn = getDBConnection();
    $query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $conn->close();
    return $row['count'] > 0;
}

// Get unread notifications count
function get_unread_notifications_count($user_id) {
    $conn = getDBConnection();
    $query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $conn->close();
    return $row['count'] ?? 0;
}

// Get unread booking notifications for driver
function get_unread_driver_notifications($driver_id) {
    try {
        $pdo = new PDO(
            "mysql:host=localhost;dbname=bus_management;charset=utf8mb4",
            "root",
            ""
        );
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM booking_notifications 
            WHERE driver_id = ? AND status = 'pending'
        ");
        $stmt->execute([$driver_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    } catch(PDOException $e) {
        return 0;
    }
}

// Send notification to driver
function send_driver_notification($driver_id, $booking_id, $type = 'booking_request') {
    try {
        $pdo = new PDO(
            "mysql:host=localhost;dbname=bus_management;charset=utf8mb4",
            "root",
            ""
        );
        
        // Check if notification already exists
        $stmt = $pdo->prepare("
            SELECT * FROM booking_notifications 
            WHERE booking_id = ? AND driver_id = ?
        ");
        $stmt->execute([$booking_id, $driver_id]);
        
        if ($stmt->rowCount() === 0) {
            // Create new notification
            $stmt = $pdo->prepare("
                INSERT INTO booking_notifications (booking_id, driver_id, status, created_at) 
                VALUES (?, ?, 'pending', NOW())
            ");
            return $stmt->execute([$booking_id, $driver_id]);
        }
        return true;
    } catch(PDOException $e) {
        error_log("Error sending notification: " . $e->getMessage());
        return false;
    }
}

// Log user activity
function log_activity($user_id, $action, $description = '') {
    try {
        $pdo = new PDO(
            "mysql:host=localhost;dbname=bus_management;charset=utf8mb4",
            "root",
            ""
        );
        
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, description, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        return $stmt->execute([$user_id, $action, $description]);
    } catch(PDOException $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}

// Get driver by route
function get_driver_for_route($route_id) {
    try {
        $pdo = new PDO(
            "mysql:host=localhost;dbname=bus_management;charset=utf8mb4",
            "root",
            ""
        );
        
        $stmt = $pdo->prepare("
            SELECT u.id, u.full_name, u.phone_number, u.email 
            FROM users u
            JOIN buses b ON u.id = b.driver_id
            WHERE b.status = 'available' AND u.role = 'driver' AND u.status = 'active'
            LIMIT 1
        ");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return null;
    }
}
?>
