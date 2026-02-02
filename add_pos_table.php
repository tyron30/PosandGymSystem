<?php
include "config/db.php";

// Create pos_items table
$sql1 = "CREATE TABLE IF NOT EXISTS pos_items (
    id INT(11) NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    category ENUM('beverage', 'snack', 'supplement', 'other') NOT NULL DEFAULT 'beverage',
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock_quantity INT(11) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_item_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

if ($conn->query($sql1) === TRUE) {
    echo "POS items table created successfully!<br>";
} else {
    echo "Error creating pos_items table: " . $conn->error . "<br>";
}

// Create pos_sales table
$sql2 = "CREATE TABLE IF NOT EXISTS pos_sales (
    id INT(11) NOT NULL AUTO_INCREMENT,
    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method ENUM('cash', 'gcash', 'maya', 'card') NOT NULL DEFAULT 'cash',
    reference_no VARCHAR(50) NULL,
    customer_name VARCHAR(100) NULL,
    customer_phone VARCHAR(20) NULL,
    member_id INT(11) NULL,
    created_by INT(11) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

if ($conn->query($sql2) === TRUE) {
    echo "POS sales table created successfully!<br>";
} else {
    echo "Error creating pos_sales table: " . $conn->error . "<br>";
}

// Create pos_sale_items table
$sql3 = "CREATE TABLE IF NOT EXISTS pos_sale_items (
    id INT(11) NOT NULL AUTO_INCREMENT,
    sale_id INT(11) NOT NULL,
    item_id INT(11) NOT NULL,
    quantity INT(11) NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (sale_id) REFERENCES pos_sales(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES pos_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

if ($conn->query($sql3) === TRUE) {
    echo "POS sale items table created successfully!<br>";
} else {
    echo "Error creating pos_sale_items table: " . $conn->error . "<br>";
}

// Insert some default POS items
$default_items = [
    ['name' => 'Mineral Water (500ml)', 'category' => 'beverage', 'price' => 15.00, 'stock_quantity' => 100],
    ['name' => 'Mineral Water (1L)', 'category' => 'beverage', 'price' => 25.00, 'stock_quantity' => 50],
    ['name' => 'Coca-Cola (330ml)', 'category' => 'beverage', 'price' => 20.00, 'stock_quantity' => 80],
    ['name' => 'Sprite (330ml)', 'category' => 'beverage', 'price' => 20.00, 'stock_quantity' => 80],
    ['name' => 'Red Bull Energy Drink', 'category' => 'beverage', 'price' => 85.00, 'stock_quantity' => 30],
    ['name' => 'Monster Energy Drink', 'category' => 'beverage', 'price' => 80.00, 'stock_quantity' => 25],
    ['name' => 'Protein Bar', 'category' => 'snack', 'price' => 45.00, 'stock_quantity' => 40],
    ['name' => 'Mixed Nuts (100g)', 'category' => 'snack', 'price' => 35.00, 'stock_quantity' => 60],
    ['name' => 'Banana', 'category' => 'snack', 'price' => 10.00, 'stock_quantity' => 200],
    ['name' => 'Apple', 'category' => 'snack', 'price' => 15.00, 'stock_quantity' => 150],
    ['name' => 'Whey Protein (1kg)', 'category' => 'supplement', 'price' => 1200.00, 'stock_quantity' => 10],
    ['name' => 'Creatine Monohydrate', 'category' => 'supplement', 'price' => 800.00, 'stock_quantity' => 15],
    ['name' => 'Gym Towel', 'category' => 'other', 'price' => 50.00, 'stock_quantity' => 20],
    ['name' => 'Lockers Key', 'category' => 'other', 'price' => 5.00, 'stock_quantity' => 50]
];

foreach ($default_items as $item) {
    $stmt = $conn->prepare("INSERT IGNORE INTO pos_items (name, category, price, stock_quantity) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssdi", $item['name'], $item['category'], $item['price'], $item['stock_quantity']);
    if ($stmt->execute()) {
        echo "POS item '{$item['name']}' added successfully!<br>";
    } else {
        echo "Error adding POS item '{$item['name']}': " . $stmt->error . "<br>";
    }
    $stmt->close();
}

echo "POS system setup completed!";
?>
