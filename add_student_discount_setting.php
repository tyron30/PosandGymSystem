<?php
include "config/db.php";

try {
    $conn->query("ALTER TABLE gym_settings ADD COLUMN student_discount_enabled BOOLEAN DEFAULT TRUE AFTER sidebar_theme");
    echo "✓ Added student_discount_enabled column to gym_settings table<br>";
    echo "Default value: TRUE (enabled)<br>";
} catch (Exception $e) {
    echo "Note: student_discount_enabled column might already exist in gym_settings table<br>";
    echo "Error: " . $e->getMessage() . "<br>";
}

// Set default value for existing records
try {
    $conn->query("UPDATE gym_settings SET student_discount_enabled = TRUE WHERE student_discount_enabled IS NULL");
    echo "✓ Set default value for existing records<br>";
} catch (Exception $e) {
    echo "Error setting default value: " . $e->getMessage() . "<br>";
}

echo "Migration completed successfully!<br>";
?>
