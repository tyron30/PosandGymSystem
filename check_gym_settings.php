<?php
include "config/db.php";

$result = $conn->query("DESCRIBE gym_settings");
echo "gym_settings table structure:\n";
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . ' - ' . ($row['Null'] == 'YES' ? 'NULL' : 'NOT NULL') . ' - ' . ($row['Default'] ?? 'NULL') . "\n";
}
?>
