<?php
include "../config/db.php";

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

$user = $_SESSION['user'];
if ($user['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$message = '';
$message_type = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['sql_file'])) {
    $file = $_FILES['sql_file'];

    // Check for errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = "File upload error.";
        $message_type = "danger";
    } elseif (!preg_match('/\.sql$/i', $file['name'])) {
        $message = "Only SQL files are allowed.";
        $message_type = "danger";
    } elseif ($file['size'] > 50 * 1024 * 1024) { // 50MB limit
        $message = "File size exceeds 50MB.";
        $message_type = "danger";
    } else {
        // Read the SQL file content
        $sql_content = file_get_contents($file['tmp_name']);
        if ($sql_content === false) {
            $message = "Error reading uploaded file.";
            $message_type = "danger";
        } else {
            // Create a new database connection for import
            $import_conn = new mysqli($host, $user_db, $pass, $db_name);
            if ($import_conn->connect_error) {
                $message = "Database connection failed for import.";
                $message_type = "danger";
            } else {
                // Execute the SQL queries
                if ($import_conn->multi_query($sql_content)) {
                    // Consume all results to ensure all queries are processed
                    do {
                        if ($result = $import_conn->store_result()) {
                            $result->free();
                        }
                    } while ($import_conn->more_results() && $import_conn->next_result());

                    if ($import_conn->errno) {
                        $message = "Error importing database: " . $import_conn->error;
                        $message_type = "danger";
                    } else {
                        $message = "Database imported successfully!";
                        $message_type = "success";
                    }
                } else {
                    $message = "Error importing database: " . $import_conn->error;
                    $message_type = "danger";
                }
                $import_conn->close();
            }
        }
    }
}

// Store message in session and redirect
$_SESSION['message'] = $message;
$_SESSION['message_type'] = $message_type;
header("Location: settings.php");
exit();
?>
