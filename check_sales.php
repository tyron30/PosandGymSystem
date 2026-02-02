<?php
include "config/db.php";

echo "Checking sales data...\n";

$result = $conn->query('SELECT COUNT(*) as count FROM pos_sales');
$row = $result->fetch_assoc();
echo 'Total sales: ' . $row['count'] . "\n";

$result = $conn->query('SELECT COUNT(*) as count FROM pos_sales WHERE DATE(sale_date) = CURDATE()');
$row = $result->fetch_assoc();
echo 'Today sales: ' . $row['count'] . "\n";

$result = $conn->query('SELECT * FROM pos_sales LIMIT 5');
while ($row = $result->fetch_assoc()) {
    echo 'Sale ID: ' . $row['id'] . ', Date: ' . $row['sale_date'] . ', Created: ' . $row['created_at'] . "\n";
}

$result = $conn->query('SELECT COUNT(*) as count FROM pos_sale_items');
$row = $result->fetch_assoc();
echo 'Total sale items: ' . $row['count'] . "\n";

$result = $conn->query('SELECT COUNT(*) as count FROM pos_items');
$row = $result->fetch_assoc();
echo 'Total pos items: ' . $row['count'] . "\n";
?>
