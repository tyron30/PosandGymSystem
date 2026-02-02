# TODO: Standardize Notifications Across Admin and Cashier Features

## Admin Features
- [x] admin/dashboard.php: Already uses showToast, verified consistency
- [x] admin/pos.php: Already uses showToast, verified consistency
- [x] admin/payments.php: Replace alert() with showToast()
- [x] admin/members.php: Already uses showToast for URL parameter notifications
- [x] admin/attendance.php: Already uses showToast, verified consistency
- [x] admin/employees.php: Add toast.js include (no current notifications)
- [x] admin/settings.php: Replace Bootstrap alerts with showToast()
- [x] admin/reports.php: Add toast.js include (no current notifications)

## Cashier Features
- [x] cashier/dashboard.php: Check and standardize notifications
- [x] cashier/pos.php: Already uses showToast, verified consistency
- [x] cashier/payments.php: Add toast.js, replace alert() and Bootstrap alerts with showToast()
- [x] cashier/members.php: Replace custom NotificationSystem with showToast()
- [x] cashier/attendance.php: Already uses showToast, verified consistency

## General
- [x] change_password.php: Replace Bootstrap alerts with showToast, add toast.js include
- [x] Ensure all files include <script src="../assets/toast.js"></script>
- [x] Replace all alert() calls with showToast()
- [x] Replace all Bootstrap alert divs with showToast()
- [x] Replace custom notification systems with showToast()
