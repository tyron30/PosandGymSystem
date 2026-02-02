<?php
include 'config/db.php';

echo "Testing database structure...\n\n";

$result = $conn->query('DESCRIBE gym_settings');
echo "gym_settings table structure:\n";
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . ' - ' . ($row['Null'] == 'YES' ? 'NULL' : 'NOT NULL') . ' - ' . ($row['Default'] ?? 'NULL') . "\n";
}

echo "\n\nTesting student discount setting...\n";
$settings = $conn->query("SELECT * FROM gym_settings WHERE id = 1")->fetch_assoc();
if ($settings) {
    echo "Current student_discount_enabled: " . (isset($settings['student_discount_enabled']) ? $settings['student_discount_enabled'] : 'NOT SET') . "\n";
} else {
    echo "No settings found!\n";
}

echo "\n\nTesting membership fees...\n";
$fees = $conn->query("SELECT per_session_fee, one_month_fee FROM gym_settings WHERE id = 1")->fetch_assoc();
if ($fees) {
    echo "Per session fee: " . ($fees['per_session_fee'] ?? 'NOT SET') . "\n";
    echo "One month fee: " . ($fees['one_month_fee'] ?? 'NOT SET') . "\n";
} else {
    echo "No fees found!\n";
}

echo "\nTest completed.\n";
?>
