-- i-Bus System Database - FRESH SETUP
-- Drop existing database if exists
DROP DATABASE IF EXISTS bus_management;

-- Create Database
CREATE DATABASE bus_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bus_management;

-- Users Table (CORRECT - using 'id' not 'user_id')
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(15),
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    role ENUM('student', 'driver', 'admin') DEFAULT 'student',
    reward_points INT DEFAULT 0,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    login_attempts INT DEFAULT 0,
    locked_until DATETIME NULL,
    remember_token VARCHAR(255) NULL,
    token_expiry DATETIME NULL,
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Routes Table
CREATE TABLE routes (
    route_id INT PRIMARY KEY AUTO_INCREMENT,
    from_location VARCHAR(100) NOT NULL,
    to_location VARCHAR(100) NOT NULL,
    distance DECIMAL(5, 2),
    duration INT,
    base_fare DECIMAL(10, 2) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Buses Table
CREATE TABLE buses (
    bus_id INT PRIMARY KEY AUTO_INCREMENT,
    bus_number VARCHAR(20) UNIQUE NOT NULL,
    capacity INT NOT NULL,
    driver_id INT,
    status ENUM('available', 'maintenance', 'in_use') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Schedules Table
CREATE TABLE schedules (
    schedule_id INT PRIMARY KEY AUTO_INCREMENT,
    bus_id INT NOT NULL,
    route_id INT NOT NULL,
    departure_time TIME NOT NULL,
    arrival_time TIME NOT NULL,
    departure_date DATE NOT NULL,
    available_seats INT NOT NULL,
    total_seats INT NOT NULL,
    status ENUM('scheduled', 'ongoing', 'completed', 'cancelled') DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bus_id) REFERENCES buses(bus_id) ON DELETE CASCADE,
    FOREIGN KEY (route_id) REFERENCES routes(route_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bookings Table
CREATE TABLE bookings (
    booking_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    route_id INT NOT NULL,
    seat_number VARCHAR(10) NOT NULL,
    booking_date DATE NOT NULL,
    booking_time TIME NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('unpaid', 'paid', 'refunded') DEFAULT 'unpaid',
    amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (route_id) REFERENCES routes(route_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications Table
CREATE TABLE notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT,
    type ENUM('system', 'booking', 'reminder', 'alert') DEFAULT 'system',
    is_read BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity Logs Table
CREATE TABLE activity_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments Table
CREATE TABLE payments (
    payment_id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('credit_card', 'debit_card', 'online_banking', 'cash') DEFAULT 'credit_card',
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    transaction_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create Indexes for better query performance
CREATE INDEX idx_user_id ON bookings(user_id);
CREATE INDEX idx_route_id ON bookings(route_id);
CREATE INDEX idx_bus_id ON schedules(bus_id);
CREATE INDEX idx_schedule_date ON schedules(departure_date);
CREATE INDEX idx_notification_user ON notifications(user_id);
CREATE INDEX idx_activity_user ON activity_logs(user_id);
CREATE INDEX idx_username ON users(username);
CREATE INDEX idx_email ON users(email);

-- ================== SAMPLE DATA FOR TESTING ==================

-- Insert sample users (password: password123)
-- Hash: $2y$10$F1l.NKbNfxXjGPV/IVlF9.f9I5xGYvYG8JfP8Ls1lJkR0V5XH1N0.
INSERT INTO users (username, email, password, full_name, phone_number, role) VALUES
('student1', 'student1@example.com', '$2y$10$F1l.NKbNfxXjGPV/IVlF9.f9I5xGYvYG8JfP8Ls1lJkR0V5XH1N0.', 'John Doe', '0123456789', 'student'),
('student2', 'student2@example.com', '$2y$10$F1l.NKbNfxXjGPV/IVlF9.f9I5xGYvYG8JfP8Ls1lJkR0V5XH1N0.', 'Jane Smith', '0187654321', 'student'),
('driver1', 'driver1@example.com', '$2y$10$F1l.NKbNfxXjGPV/IVlF9.f9I5xGYvYG8JfP8Ls1lJkR0V5XH1N0.', 'Ahmed Hassan', '0198765432', 'driver'),
('admin1', 'admin1@example.com', '$2y$10$F1l.NKbNfxXjGPV/IVlF9.f9I5xGYvYG8JfP8Ls1lJkR0V5XH1N0.', 'Admin User', '0101234567', 'admin');

-- Insert sample routes
INSERT INTO routes (from_location, to_location, distance, duration, base_fare) VALUES
('Kuala Lumpur', 'Petaling Jaya', 20, 45, 15.00),
('Kuala Lumpur', 'Subang Jaya', 25, 50, 18.00),
('Kuala Lumpur', 'Shah Alam', 30, 60, 20.00),
('Kuala Lumpur', 'Seputih Perdana', 35, 70, 22.00);

-- Insert sample buses
INSERT INTO buses (bus_number, capacity, driver_id, status) VALUES
('B001', 50, 3, 'available'),
('B002', 50, NULL, 'available'),
('B003', 48, NULL, 'maintenance');

-- Insert sample bookings for student1
INSERT INTO bookings (user_id, route_id, seat_number, booking_date, booking_time, status, amount) VALUES
(1, 1, 'A01', CURDATE(), CURTIME(), 'confirmed', 15.00),
(1, 2, 'B05', DATE_ADD(CURDATE(), INTERVAL 1 DAY), '08:00:00', 'pending', 18.00);

-- Insert sample notification
INSERT INTO notifications (user_id, title, message, type) VALUES
(1, 'Booking Confirmed', 'Your booking to Petaling Jaya has been confirmed', 'booking');

COMMIT;

-- ================== CONFIRMATION ==================
-- Database setup complete!
-- Login credentials:
-- Username: student1
-- Password: password123


