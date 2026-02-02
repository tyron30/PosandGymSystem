<?php
include "config/db.php";

// Add membership fee columns to gym_settings table
$query = "ALTER TABLE gym_settings
          ADD COLUMN per_session_fee DECIMAL(10,2) DEFAULT 50.00,
          ADD COLUMN half_month_fee DECIMAL(10,2) DEFAULT 300.00,
          ADD COLUMN one_month_fee DECIMAL(10,2) DEFAULT 500.00,
          ADD COLUMN two_months_fee DECIMAL(10,2) DEFAULT 900.00,
          ADD COLUMN three_months_fee DECIMAL(10,2) DEFAULT 1300.00,
          ADD COLUMN four_months_fee DECIMAL(10,2) DEFAULT 1700.00,
          ADD COLUMN five_months_fee DECIMAL(10,2) DEFAULT 2100.00,
          ADD COLUMN six_months_fee DECIMAL(10,2) DEFAULT 2500.00,
          ADD COLUMN seven_months_fee DECIMAL(10,2) DEFAULT 2900.00,
          ADD COLUMN eight_months_fee DECIMAL(10,2) DEFAULT 3300.00,
          ADD COLUMN nine_months_fee DECIMAL(10,2) DEFAULT 3700.00,
          ADD COLUMN ten_months_fee DECIMAL(10,2) DEFAULT 4100.00,
          ADD COLUMN eleven_months_fee DECIMAL(10,2) DEFAULT 4500.00,
          ADD COLUMN one_year_fee DECIMAL(10,2) DEFAULT 5000.00,
          ADD COLUMN two_years_fee DECIMAL(10,2) DEFAULT 9000.00,
          ADD COLUMN three_years_fee DECIMAL(10,2) DEFAULT 13000.00";

if ($conn->query($query) === TRUE) {
    echo "Membership fee columns added successfully to gym_settings table.\n";

    // Update existing settings with default values
    $update_query = "UPDATE gym_settings SET
                     per_session_fee = 50.00,
                     half_month_fee = 300.00,
                     one_month_fee = 500.00,
                     two_months_fee = 900.00,
                     three_months_fee = 1300.00,
                     four_months_fee = 1700.00,
                     five_months_fee = 2100.00,
                     six_months_fee = 2500.00,
                     seven_months_fee = 2900.00,
                     eight_months_fee = 3300.00,
                     nine_months_fee = 3700.00,
                     ten_months_fee = 4100.00,
                     eleven_months_fee = 4500.00,
                     one_year_fee = 5000.00,
                     two_years_fee = 9000.00,
                     three_years_fee = 13000.00
                     WHERE id = 1";

    if ($conn->query($update_query) === TRUE) {
        echo "Default membership fees set successfully.\n";
    } else {
        echo "Error setting default fees: " . $conn->error . "\n";
    }
} else {
    echo "Error adding columns: " . $conn->error . "\n";
}

$conn->close();
?>
