<?php
/**
 * Notification System Setup Script
 * Run this once to create all necessary tables for the notification system
 */

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=bus_management;charset=utf8mb4",
        "root",
        ""
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create booking_notifications table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `booking_notifications` (
          `notification_id` INT AUTO_INCREMENT PRIMARY KEY,
          `booking_id` INT NOT NULL,
          `driver_id` INT NOT NULL,
          `status` ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
          `response_reason` TEXT,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `responded_at` TIMESTAMP NULL,
          FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`booking_id`) ON DELETE CASCADE,
          FOREIGN KEY (`driver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
          INDEX (`driver_id`),
          INDEX (`booking_id`),
          INDEX (`status`)
        )
    ");
    
    // Create activity_logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `activity_logs` (
          `log_id` INT AUTO_INCREMENT PRIMARY KEY,
          `user_id` INT NOT NULL,
          `action` VARCHAR(100) NOT NULL,
          `description` TEXT,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
          INDEX (`user_id`),
          INDEX (`action`),
          INDEX (`created_at`)
        )
    ");
    
    // Modify bookings table if needed
    $pdo->exec("
        ALTER TABLE `bookings` 
        ADD COLUMN IF NOT EXISTS `payment_status` ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
        ADD COLUMN IF NOT EXISTS `amount` DECIMAL(10, 2) DEFAULT 0.00
    ");
    
    // Modify users table if needed
    $pdo->exec("
        ALTER TABLE `users` 
        ADD COLUMN IF NOT EXISTS `notification_preference` ENUM('email', 'sms', 'both', 'none') DEFAULT 'both',
        ADD COLUMN IF NOT EXISTS `last_login` TIMESTAMP NULL
    ");
    
    // Create notification_preferences table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `notification_preferences` (
          `preference_id` INT AUTO_INCREMENT PRIMARY KEY,
          `user_id` INT NOT NULL,
          `notification_type` VARCHAR(50) NOT NULL,
          `enabled` BOOLEAN DEFAULT TRUE,
          `delivery_method` ENUM('email', 'sms', 'in-app', 'all') DEFAULT 'in-app',
          FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
          UNIQUE KEY (`user_id`, `notification_type`),
          INDEX (`enabled`)
        )
    ");
    
    echo json_encode([
        'success' => true,
        'message' => 'Notification system tables created successfully!'
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
