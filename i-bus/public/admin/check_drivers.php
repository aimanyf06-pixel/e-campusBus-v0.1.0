<?php
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

echo "<h3>Available Drivers (no bus assigned):</h3>";
$stmt = $pdo->query("
    SELECT u.id, u.full_name, u.username 
    FROM users u
    WHERE u.role = 'driver' 
    AND u.id NOT IN (SELECT driver_id FROM buses WHERE driver_id IS NOT NULL)
    ORDER BY u.full_name
");
$available = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<p>Count: " . count($available) . "</p>";
foreach($available as $d) {
    echo "<li>" . htmlspecialchars($d['full_name']) . " (" . htmlspecialchars($d['username']) . ")</li>";
}

echo "<h3>All Drivers:</h3>";
$stmt2 = $pdo->query("SELECT id, full_name, username FROM users WHERE role = 'driver' ORDER BY full_name");
$all = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "<p>Count: " . count($all) . "</p>";
foreach($all as $d) {
    echo "<li>" . htmlspecialchars($d['full_name']) . " (" . htmlspecialchars($d['username']) . ")</li>";
}

echo "<h3>Buses with Assigned Drivers:</h3>";
$stmt3 = $pdo->query("SELECT bus_number, driver_id FROM buses WHERE driver_id IS NOT NULL");
$buses = $stmt3->fetchAll(PDO::FETCH_ASSOC);
echo "<p>Count: " . count($buses) . "</p>";
foreach($buses as $b) {
    echo "<li>Bus " . htmlspecialchars($b['bus_number']) . " - Driver ID: " . $b['driver_id'] . "</li>";
}
?>
