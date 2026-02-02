<?php
include "config/db.php";

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'cashier'])) {
    header("Location: index.php");
    exit();
}

$user = $_SESSION['user'];

// Fetch gym settings
$settings = $conn->query("SELECT * FROM gym_settings WHERE id = 1")->fetch_assoc();
if (!$settings) {
    // Insert default settings if not exists
    $conn->query("INSERT INTO gym_settings (gym_name, logo_path, background_path) VALUES ('Gym Management System', 'gym logo.jpg', 'gym background.jpg')");
    $settings = $conn->query("SELECT * FROM gym_settings WHERE id = 1")->fetch_assoc();
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $errors = [];

    // Validate current password
    if (!password_verify($current_password, $user['password'])) {
        $errors[] = "Current password is incorrect.";
    }

    // Validate new password
    if (empty($new_password)) {
        $errors[] = "New password is required.";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters long.";
    }

    // Validate password confirmation
    if ($new_password !== $confirm_password) {
        $errors[] = "New password and confirmation do not match.";
    }

    if (empty($errors)) {
        // Hash new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update password in database
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user['id']);

        if ($stmt->execute()) {
            $success = "Password changed successfully!";
            // Update session with new password hash
            $_SESSION['user']['password'] = $hashed_password;
        } else {
            $errors[] = "Failed to update password. Please try again.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Gym Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
    <script src="assets/toast.js"></script>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <nav id="sidebar" class="bg-<?php echo htmlspecialchars($settings['sidebar_theme']); ?> <?php echo ($settings['sidebar_theme'] == 'light') ? 'text-dark' : 'text-white'; ?> vh-100" style="width: 250px;">
            <div class="p-3">
                <div class="text-center mb-4">
                    <img src="<?php echo htmlspecialchars($settings['logo_path']); ?>" alt="Gym Logo" class="rounded-circle mb-2" style="width: 80px; height: 80px;">
                    <h5 class="fw-bold"><?php echo htmlspecialchars($settings['gym_name']); ?></h5>
                </div>
                <ul class="nav flex-column">
                    <?php if ($user['role'] === 'admin'): ?>
                    <li class="nav-item mb-2">
                        <a class="nav-link <?php echo ($settings['sidebar_theme'] == 'light') ? 'text-dark' : 'text-white'; ?>" href="admin/dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link <?php echo ($settings['sidebar_theme'] == 'light') ? 'text-dark' : 'text-white'; ?>" href="admin/members.php">
                            <i class="fas fa-users me-2"></i>Members
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link <?php echo ($settings['sidebar_theme'] == 'light') ? 'text-dark' : 'text-white'; ?>" href="admin/pos.php">
                            <i class="fas fa-cash-register me-2"></i>Point of Sale
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link <?php echo ($settings['sidebar_theme'] == 'light') ? 'text-dark' : 'text-white'; ?>" href="admin/attendance.php">
                            <i class="fas fa-calendar-check me-2"></i>Attendance
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link <?php echo ($settings['sidebar_theme'] == 'light') ? 'text-dark' : 'text-white'; ?>" href="admin/reports.php">
                            <i class="fas fa-chart-bar me-2"></i>Reports
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link <?php echo ($settings['sidebar_theme'] == 'light') ? 'text-dark' : 'text-white'; ?>" href="admin/employees.php">
                            <i class="fas fa-user-tie me-2"></i>Employees
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link <?php echo ($settings['sidebar_theme'] == 'light') ? 'text-dark' : 'text-white'; ?>" href="admin/settings.php">
                            <i class="fas fa-cog me-2"></i>Settings
                        </a>
                    </li>
                    <?php else: ?>
                    <li class="nav-item mb-2">
                        <a class="nav-link <?php echo ($settings['sidebar_theme'] == 'light') ? 'text-dark' : 'text-white'; ?>" href="cashier/dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link <?php echo ($settings['sidebar_theme'] == 'light') ? 'text-dark' : 'text-white'; ?>" href="cashier/members.php">
                            <i class="fas fa-users me-2"></i>Members
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link <?php echo ($settings['sidebar_theme'] == 'light') ? 'text-dark' : 'text-white'; ?>" href="cashier/payments.php">
                            <i class="fas fa-credit-card me-2"></i>Payments
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link <?php echo ($settings['sidebar_theme'] == 'light') ? 'text-dark' : 'text-white'; ?>" href="cashier/pos.php">
                            <i class="fas fa-cash-register me-2"></i>Point of Sale
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link <?php echo ($settings['sidebar_theme'] == 'light') ? 'text-dark' : 'text-white'; ?>" href="cashier/attendance.php">
                            <i class="fas fa-calendar-check me-2"></i>Attendance
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item mb-2">
                        <a class="nav-link <?php echo ($settings['sidebar_theme'] == 'light') ? 'text-dark' : 'text-white'; ?> active" href="change_password.php">
                            <i class="fas fa-key me-2"></i>Change Password
                        </a>
                    </li>
                    <li class="nav-item mt-4">
                        <a class="nav-link <?php echo ($settings['sidebar_theme'] == 'light') ? 'text-dark' : 'text-white'; ?>" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="flex-grow-1">
            <!-- Top Bar -->
            <nav class="navbar navbar-light bg-light border-bottom">
                <div class="container-fluid">
                    <button class="btn btn-outline-secondary me-3" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <span class="navbar-brand mb-0 h1">Change Password - <?php echo htmlspecialchars($user['fullname']); ?> (<?php echo ucfirst($user['role']); ?>)</span>
                </div>
            </nav>

            <div class="container-fluid mt-4">
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-key me-2"></i>Change Your Password</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password *</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password *</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                        <div class="form-text">Password must be at least 6 characters long.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" name="change_password" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Change Password
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-shield-alt me-2"></i>Password Security Tips</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>Use at least 6 characters</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Include a mix of letters and numbers</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Avoid using common words</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Change your password regularly</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;

            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        // Sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.flex-grow-1');

            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('sidebar-collapsed');
                mainContent.classList.toggle('main-expanded');

                // Update toggle icon
                const icon = sidebarToggle.querySelector('i');
                if (sidebar.classList.contains('sidebar-collapsed')) {
                    icon.className = 'fas fa-times'; // Close icon when collapsed
                } else {
                    icon.className = 'fas fa-bars'; // Bars icon when expanded
                }
            });
        });
    </script>
</body>
</html>
