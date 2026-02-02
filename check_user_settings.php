<?php
include "config/db.php";

// Check if user_settings table exists
$result = $conn->query("SHOW TABLES LIKE 'user_settings'");
if ($result->num_rows == 0) {
    echo "Creating user_settings table...\n";

    // Create the table
    $create_sql = "CREATE TABLE user_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        setting_key VARCHAR(255) NOT NULL,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_setting (user_id, setting_key)
    )";

    if ($conn->query($create_sql)) {
        echo "user_settings table created successfully!\n";
    } else {
        echo "Error creating table: " . $conn->error . "\n";
    }
} else {
    echo "user_settings table already exists\n";

    // Check if unique constraint exists
    $index_result = $conn->query("SHOW INDEX FROM user_settings WHERE Key_name = 'unique_user_setting'");
    if ($index_result->num_rows == 0) {
        echo "Adding unique constraint...\n";
        if ($conn->query("ALTER TABLE user_settings ADD UNIQUE KEY unique_user_setting (user_id, setting_key)")) {
            echo "Unique constraint added successfully!\n";
        } else {
            echo "Error adding constraint: " . $conn->error . "\n";
        }
    } else {
        echo "Unique constraint already exists\n";
    }
}

echo "Setup complete!\n";
?>
