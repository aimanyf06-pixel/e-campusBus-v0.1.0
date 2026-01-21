<?php
// Check if activity_logs table exists and what columns it has

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=bus_management;charset=utf8mb4",
        "root",
        ""
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if table exists
    $stmt = $pdo->query("SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME='activity_logs' AND TABLE_SCHEMA='bus_management'");
    $table_exists = $stmt->fetch();
    
    echo "Activity Logs Table Exists: " . ($table_exists ? "YES" : "NO") . "\n\n";
    
    if ($table_exists) {
        // Get table structure
        $stmt = $pdo->query("DESCRIBE activity_logs");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Columns in activity_logs table:\n";
        foreach ($columns as $col) {
            echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
        }
    } else {
        echo "Creating activity_logs table now...\n";
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
        echo "Table created successfully!\n";
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
