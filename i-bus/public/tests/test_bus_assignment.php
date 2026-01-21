<?php
// Test bus assignment requirements

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=bus_management;charset=utf8mb4",
        "root",
        ""
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Bus Assignment System Test ===\n\n";
    
    // Check available buses
    $stmt = $pdo->query("SELECT * FROM buses WHERE status IN ('available', 'in_use') ORDER BY bus_number");
    $buses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Available Buses:\n";
    foreach ($buses as $bus) {
        $assigned = $bus['driver_id'] ? "Assigned to Driver #" . $bus['driver_id'] : "Not assigned";
        echo "  - " . $bus['bus_number'] . " (Capacity: " . $bus['capacity'] . " seats) - " . $assigned . "\n";
    }
    
    echo "\n\nActive Drivers:\n";
    $stmt = $pdo->query("SELECT u.id, u.full_name, b.bus_number FROM users u LEFT JOIN buses b ON b.driver_id = u.id WHERE u.role = 'driver' AND u.status = 'active'");
    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($drivers as $driver) {
        $bus = $driver['bus_number'] ? "Assigned: " . $driver['bus_number'] : "NO BUS ASSIGNED";
        echo "  - Driver #{$driver['id']}: {$driver['full_name']} [$bus]\n";
    }
    
    echo "\n\n✅ Test Complete!\n";
    echo "\nTo assign a bus:\n";
    echo "1. Log in as a driver\n";
    echo "2. Go to: driver/assign_bus.php\n";
    echo "3. Select a bus and submit\n";
    
} catch(PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}
?>
