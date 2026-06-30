<?php
include "../config/db.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$user = $_SESSION['user'];

// Fetch gym settings
$settings = $conn->query("SELECT * FROM gym_settings WHERE id = 1")->fetch_assoc();
if (!$settings) {
    $conn->query("INSERT INTO gym_settings (gym_name, logo_path, background_path) VALUES ('Gym Management System', 'gym logo.jpg', 'gym background.jpg')");
    $settings = $conn->query("SELECT * FROM gym_settings WHERE id = 1")->fetch_assoc();
}

/* ---------------------------------------------------------------------
 * Date ranges
 * ------------------------------------------------------------------- */
$curStart  = date('Y-m-01');
$curEnd    = date('Y-m-t');
$prevStart = date('Y-m-01', strtotime('-1 month'));
$prevEnd   = date('Y-m-t', strtotime('-1 month'));

/* ---------------------------------------------------------------------
 * Helpers
 * ------------------------------------------------------------------- */
function qval($conn, $sql) {
    $res = $conn->query($sql);
    if (!$res) return 0;
    $row = $res->fetch_assoc();
    if (!$row) return 0;
    $v = reset($row);
    return $v === null ? 0 : $v;
}

function percentChange($current, $previous) {
    $current = (float)$current;
    $previous = (float)$previous;
    if ($previous == 0) {
        return $current > 0 ? ['pct' => 100, 'dir' => 'up'] : ['pct' => 0, 'dir' => 'flat'];
    }
    $pct = (($current - $previous) / $previous) * 100;
    return ['pct' => abs($pct), 'dir' => $pct > 0.0001 ? 'up' : ($pct < -0.0001 ? 'down' : 'flat')];
}

function trendBadge($change) {
    $cls = $change['dir'] === 'up' ? 'o-trend-up' : ($change['dir'] === 'down' ? 'o-trend-down' : 'o-trend-flat');
    $arrow = $change['dir'] === 'up' ? '&#9650;' : ($change['dir'] === 'down' ? '&#9660;' : '&#9679;');
    return '<span class="o-stat-trend ' . $cls . '">' . $arrow . ' ' . number_format($change['pct'], 2) . '%</span>';
}

function peso($n) { return '&#8369;' . number_format((float)$n, 2); }

/* ---------------------------------------------------------------------
 * Core membership / attendance figures (existing)
 * ------------------------------------------------------------------- */
$total_members      = qval($conn, "SELECT COUNT(*) c FROM members");
$active_members      = qval($conn, "SELECT COUNT(*) c FROM members WHERE status = 'ACTIVE'");
$total_attendance    = qval($conn, "SELECT COUNT(*) c FROM attendance WHERE checkin_time BETWEEN '$curStart 00:00:00' AND '$curEnd 23:59:59'");

/* ---------------------------------------------------------------------
 * Revenue figures (membership payments + POS sales)
 * ------------------------------------------------------------------- */
$membership_cur  = qval($conn, "SELECT COALESCE(SUM(amount),0) t FROM payments WHERE payment_date BETWEEN '$curStart 00:00:00' AND '$curEnd 23:59:59'");
$membership_prev = qval($conn, "SELECT COALESCE(SUM(amount),0) t FROM payments WHERE payment_date BETWEEN '$prevStart 00:00:00' AND '$prevEnd 23:59:59'");

$pos_cur  = qval($conn, "SELECT COALESCE(SUM(total_amount),0) t FROM pos_sales WHERE sale_date BETWEEN '$curStart 00:00:00' AND '$curEnd 23:59:59'");
$pos_prev = qval($conn, "SELECT COALESCE(SUM(total_amount),0) t FROM pos_sales WHERE sale_date BETWEEN '$prevStart 00:00:00' AND '$prevEnd 23:59:59'");

$total_cur  = $membership_cur + $pos_cur;
$total_prev = $membership_prev + $pos_prev;

$chg_total      = percentChange($total_cur, $total_prev);
$chg_membership = percentChange($membership_cur, $membership_prev);
$chg_pos        = percentChange($pos_cur, $pos_prev);

$monthly_pos_items = qval($conn, "SELECT COALESCE(SUM(psi.quantity),0) t FROM pos_sale_items psi JOIN pos_sales ps ON psi.sale_id = ps.id WHERE ps.sale_date BETWEEN '$curStart 00:00:00' AND '$curEnd 23:59:59'");
$low_stock_items   = qval($conn, "SELECT COUNT(*) c FROM pos_items WHERE stock_quantity <= 10 AND is_active = 1");

/* ---------------------------------------------------------------------
 * Revenue by product category (this month)
 * ------------------------------------------------------------------- */
$catLabels = ['beverage' => 'Beverage', 'snack' => 'Snack', 'supplement' => 'Supplement', 'other' => 'Other'];
$catRevenue = ['beverage' => 0, 'snack' => 0, 'supplement' => 0, 'other' => 0];
$catRes = $conn->query("
    SELECT pi.category AS category, COALESCE(SUM(psi.total_price),0) AS rev
    FROM pos_sale_items psi
    JOIN pos_sales ps ON psi.sale_id = ps.id
    JOIN pos_items pi ON psi.item_id = pi.id
    WHERE ps.sale_date BETWEEN '$curStart 00:00:00' AND '$curEnd 23:59:59'
    GROUP BY pi.category
");
if ($catRes) {
    while ($row = $catRes->fetch_assoc()) {
        if (isset($catRevenue[$row['category']])) {
            $catRevenue[$row['category']] = (float)$row['rev'];
        }
    }
}
$catMax = max(1, max($catRevenue));
$hasCatData = array_sum($catRevenue) > 0;

/* ---------------------------------------------------------------------
 * Revenue split: Membership vs POS (donut)
 * ------------------------------------------------------------------- */
$splitTotal = $total_cur > 0 ? $total_cur : 1;
$membershipPct = round(($membership_cur / $splitTotal) * 100, 1);
$posPct = round(100 - $membershipPct, 1);
if ($total_cur <= 0) { $membershipPct = 0; $posPct = 0; }

/* ---------------------------------------------------------------------
 * Attendance rate gauge (current vs previous month)
 * eligible members = members whose enrollment overlaps that month
 * ------------------------------------------------------------------- */
function attendanceRate($conn, $start, $end) {
    $eligible = qval($conn, "SELECT COUNT(*) c FROM members WHERE start_date <= '$end' AND (end_date IS NULL OR end_date >= '$start')");
    $checkedIn = qval($conn, "SELECT COUNT(DISTINCT member_id) c FROM attendance WHERE checkin_time BETWEEN '$start 00:00:00' AND '$end 23:59:59'");
    if ($eligible <= 0) return 0;
    return min(100, round(($checkedIn / $eligible) * 100));
}
$attendanceRateCur  = attendanceRate($conn, $curStart, $curEnd);
$attendanceRatePrev = attendanceRate($conn, $prevStart, $prevEnd);

/* ---------------------------------------------------------------------
 * Low stock items (bottom 6 by stock quantity, active only, <=10)
 * ------------------------------------------------------------------- */
$lowStockList = [];
$lsRes = $conn->query("SELECT name, stock_quantity FROM pos_items WHERE is_active = 1 AND stock_quantity <= 10 ORDER BY stock_quantity ASC LIMIT 6");
if ($lsRes) {
    while ($row = $lsRes->fetch_assoc()) {
        $lowStockList[] = $row;
    }
}
$lowStockMax = 10;
foreach ($lowStockList as $li) { $lowStockMax = max($lowStockMax, (int)$li['stock_quantity']); }

/* ---------------------------------------------------------------------
 * Donut conic-gradient string
 * ------------------------------------------------------------------- */
$donutGradient = "conic-gradient(#3461ff 0% {$membershipPct}%, #ff6b00 {$membershipPct}% 100%)";

$sidebarTextClass = ($settings['sidebar_theme'] == 'light') ? 'text-dark' : 'text-white';

/* Small inline-SVG icon helper (replaces Font Awesome so the page needs
   zero external assets and renders identically with no internet). */
function icon($name) {
    $icons = [
        'dashboard'  => '<path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>',
        'users'      => '<path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>',
        'cart'       => '<path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12L8.1 13h7.45c.75 0 1.41-.41 1.75-1.03L20.93 6c.1-.18.07-.4-.07-.55-.13-.13-.32-.19-.51-.16L4.27 6 3.21 4H1zM17 18c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/>',
        'calendar'   => '<path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11zM7 11h5v5H7z"/>',
        'chart'      => '<path d="M5 9.2h3V19H5zM10.6 5h2.8v14h-2.8zm5.6 8H19v6h-2.8z"/>',
        'employee'   => '<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>',
        'cog'        => '<path d="M19.43 12.98c.04-.32.07-.64.07-.98s-.03-.66-.07-.98l2.11-1.65c.19-.15.24-.42.12-.64l-2-3.46c-.12-.22-.39-.3-.61-.22l-2.49 1c-.52-.4-1.08-.73-1.69-.98l-.38-2.65A.488.488 0 0 0 14 2h-4c-.24 0-.44.17-.48.41l-.38 2.65c-.61.25-1.17.59-1.69.98l-2.49-1a.493.493 0 0 0-.61.22l-2 3.46c-.12.22-.07.49.12.64l2.11 1.65c-.04.32-.07.65-.07.98s.03.66.07.98l-2.11 1.65c-.19.15-.24.42-.12.64l2 3.46c.12.22.39.3.61.22l2.49-1c.52.4 1.08.73 1.69.98l.38 2.65c.05.24.25.41.49.41h4c.24 0 .44-.17.48-.41l.38-2.65c.61-.25 1.17-.58 1.69-.98l2.49 1c.23.09.49 0 .61-.22l2-3.46c.12-.22.07-.49-.12-.64l-2.11-1.65zM12 15.5c-1.93 0-3.5-1.57-3.5-3.5s1.57-3.5 3.5-3.5 3.5 1.57 3.5 3.5-1.57 3.5-3.5 3.5z"/>',
        'logout'     => '<path d="M17 7l-1.41 1.41L17.17 10H9v2h8.17l-1.58 1.59L17 15l4-4zM5 5h7V3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h7v-2H5V5z"/>',
        'bars'       => '<path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>',
        'peso'       => '<path d="M7 3h6.5c2.76 0 5 2.24 5 5s-2.24 5-5 5H10v2h5v2h-5v3H7v-3H5v-2h2v-2H5V11h2V3zm3 8h3.5c1.38 0 2.5-1.12 2.5-2.5S14.88 6 13.5 6H10v5z"/>',
        'warning'    => '<path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>',
    ];
    $path = $icons[$name] ?? '';
    return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' . $path . '</svg>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Gym Management System</title>
    <!-- Offline-only: no CDN. All styling/charts are self-contained. -->
    <link href="../assets/reports-offline.css?v=1" rel="stylesheet">
</head>
<body class="offline-body">
    <div class="o-flex" id="appShell">
        <!-- Sidebar -->
        <nav id="sidebar" class="o-sidebar bg-<?php echo htmlspecialchars($settings['sidebar_theme']); ?> <?php echo $sidebarTextClass; ?>">
            <div class="o-brand">
                <img src="../<?php echo htmlspecialchars($settings['logo_path']); ?>" alt="Gym Logo">
                <h5><?php echo htmlspecialchars($settings['gym_name']); ?></h5>
            </div>
            <ul class="o-nav">
                <li><a href="dashboard.php"><?php echo icon('dashboard'); ?><span>Dashboard</span></a></li>
                <li><a href="members.php"><?php echo icon('users'); ?><span>Members</span></a></li>
                <li><a href="pos.php"><?php echo icon('cart'); ?><span>Point of Sale</span></a></li>
                <li><a href="attendance.php"><?php echo icon('calendar'); ?><span>Attendance</span></a></li>
                <li><a class="active" href="reports.php"><?php echo icon('chart'); ?><span>Reports</span></a></li>
                <li><a href="employees.php"><?php echo icon('employee'); ?><span>Employees</span></a></li>
                <li><a href="settings.php"><?php echo icon('cog'); ?><span>Settings</span></a></li>
                <li style="margin-top:1.5rem;"><a href="../logout.php"><?php echo icon('logout'); ?><span>Logout</span></a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <div class="o-grow" id="mainContent">
            <!-- Top Bar -->
            <div class="o-topbar">
                <button class="o-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
                <p class="o-title">Reports - <?php echo htmlspecialchars($user['fullname']); ?> (Admin)</p>
            </div>

            <div class="o-container">

                <!-- ============== Overview cards (existing) ============== -->
                <h1 class="o-section-title" style="margin-top:0;">Monthly Overview</h1>
                <div class="o-row">
                    <div class="o-col o-col-3">
                        <div class="o-card o-stat">
                            <div class="o-stat-label">Total Members</div>
                            <div class="o-stat-value"><?php echo (int)$total_members; ?></div>
                        </div>
                    </div>
                    <div class="o-col o-col-3">
                        <div class="o-card o-stat">
                            <div class="o-stat-label">Active Members</div>
                            <div class="o-stat-value"><?php echo (int)$active_members; ?></div>
                        </div>
                    </div>
                    <div class="o-col o-col-3">
                        <div class="o-card o-stat">
                            <div class="o-stat-label">Monthly Revenue</div>
                            <div class="o-stat-value"><?php echo peso($total_cur); ?></div>
                        </div>
                    </div>
                    <div class="o-col o-col-3">
                        <div class="o-card o-stat">
                            <div class="o-stat-label">Monthly Check-ins</div>
                            <div class="o-stat-value"><?php echo (int)$total_attendance; ?></div>
                        </div>
                    </div>
                </div>

                <!-- ============== Financial Analysis ============== -->
                <h1 class="o-section-title">Financial Analysis</h1>
                <div class="o-row">
                    <div class="o-col o-col-4">
                        <div class="o-card o-stat">
                            <div class="o-stat-label">Total Revenue</div>
                            <div class="o-stat-value"><?php echo peso($total_cur); ?></div>
                            <?php echo trendBadge($chg_total); ?>
                            <div class="o-stat-compare">
                                <span>Current Month<br><b><?php echo peso($total_cur); ?></b></span>
                                <span>Previous Month<br><b><?php echo peso($total_prev); ?></b></span>
                            </div>
                        </div>
                    </div>
                    <div class="o-col o-col-4">
                        <div class="o-card o-stat">
                            <div class="o-stat-label">Membership Revenue</div>
                            <div class="o-stat-value"><?php echo peso($membership_cur); ?></div>
                            <?php echo trendBadge($chg_membership); ?>
                            <div class="o-stat-compare">
                                <span>Current Month<br><b><?php echo peso($membership_cur); ?></b></span>
                                <span>Previous Month<br><b><?php echo peso($membership_prev); ?></b></span>
                            </div>
                        </div>
                    </div>
                    <div class="o-col o-col-4">
                        <div class="o-card o-stat">
                            <div class="o-stat-label">POS Sales Revenue</div>
                            <div class="o-stat-value"><?php echo peso($pos_cur); ?></div>
                            <?php echo trendBadge($chg_pos); ?>
                            <div class="o-stat-compare">
                                <span>Current Month<br><b><?php echo peso($pos_cur); ?></b></span>
                                <span>Previous Month<br><b><?php echo peso($pos_prev); ?></b></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="o-row">
                    <!-- Revenue by category (bar) -->
                    <div class="o-col o-col-6">
                        <div class="o-card">
                            <div class="o-card-header"><h6>Revenue by Product Category</h6></div>
                            <div class="o-card-body">
                                <?php if ($hasCatData): ?>
                                <div class="o-bars">
                                    <?php foreach ($catRevenue as $key => $val):
                                        $h = $catMax > 0 ? max(3, round(($val / $catMax) * 100)) : 3; ?>
                                        <div class="o-bar-col">
                                            <div class="o-bar-val"><?php echo peso($val); ?></div>
                                            <div class="o-bar" style="height: <?php echo $h; ?>%"></div>
                                            <div class="o-bar-label"><?php echo $catLabels[$key]; ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                    <div class="o-empty">No POS sales recorded this month yet.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Revenue split (donut) -->
                    <div class="o-col o-col-6">
                        <div class="o-card">
                            <div class="o-card-header"><h6>Revenue Split &mdash; Membership vs POS</h6></div>
                            <div class="o-card-body">
                                <?php if ($total_cur > 0): ?>
                                <div class="o-donut-wrap">
                                    <div class="o-donut" style="background:<?php echo $donutGradient; ?>;">
                                        <div class="o-donut-center">
                                            <div class="o-dc-num"><?php echo peso($total_cur); ?></div>
                                            <div class="o-dc-lbl">Total</div>
                                        </div>
                                    </div>
                                    <div class="o-legend">
                                        <div class="o-legend-item"><span class="o-legend-dot" style="background:#3461ff;"></span>Membership <b><?php echo $membershipPct; ?>%</b></div>
                                        <div class="o-legend-item"><span class="o-legend-dot" style="background:#ff6b00;"></span>POS Sales <b><?php echo $posPct; ?>%</b></div>
                                    </div>
                                </div>
                                <?php else: ?>
                                    <div class="o-empty">No revenue recorded this month yet.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="o-row">
                    <!-- Attendance rate gauges -->
                    <div class="o-col o-col-6">
                        <div class="o-card">
                            <div class="o-card-header"><h6>Attendance Rate</h6></div>
                            <div class="o-card-body">
                                <div class="o-gauge-row">
                                    <div class="o-gauge-block">
                                        <div class="o-gauge-title">Previous Month</div>
                                        <div class="o-gauge" style="--gc:#1e2530;--gp:<?php echo $attendanceRatePrev; ?>;">
                                            <div class="o-gauge-val"><?php echo $attendanceRatePrev; ?>%</div>
                                        </div>
                                    </div>
                                    <div class="o-gauge-block">
                                        <div class="o-gauge-title">Current Month</div>
                                        <div class="o-gauge" style="--gc:#ff6b00;--gp:<?php echo $attendanceRateCur; ?>;">
                                            <div class="o-gauge-val"><?php echo $attendanceRateCur; ?>%</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Low stock (bar) -->
                    <div class="o-col o-col-6">
                        <div class="o-card">
                            <div class="o-card-header"><h6>Low Stock Items (&le; 10 pcs)</h6></div>
                            <div class="o-card-body">
                                <?php if (count($lowStockList) > 0): ?>
                                <div class="o-bars">
                                    <?php foreach ($lowStockList as $li):
                                        $qty = (int)$li['stock_quantity'];
                                        $h = max(3, round(($qty / $lowStockMax) * 100)); ?>
                                        <div class="o-bar-col">
                                            <div class="o-bar-val"><?php echo $qty; ?></div>
                                            <div class="o-bar" style="height: <?php echo $h; ?>%"></div>
                                            <div class="o-bar-label"><?php echo htmlspecialchars($li['name']); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                    <div class="o-empty">No low-stock items right now.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============== Point of Sale Reports (existing) ============== -->
                <h1 class="o-section-title">Point of Sale Reports</h1>
                <div class="o-row">
                    <div class="o-col o-col-4">
                        <div class="o-card o-stat">
                            <div class="o-stat-label">Monthly POS Sales</div>
                            <div class="o-stat-value"><?php echo peso($pos_cur); ?></div>
                            <small style="color:#8a8f98;">Total sales this month</small>
                        </div>
                    </div>
                    <div class="o-col o-col-4">
                        <div class="o-card o-stat">
                            <div class="o-stat-label">Items Sold</div>
                            <div class="o-stat-value"><?php echo (int)$monthly_pos_items; ?></div>
                            <small style="color:#8a8f98;">Total items sold this month</small>
                        </div>
                    </div>
                    <div class="o-col o-col-4">
                        <div class="o-card o-stat">
                            <div class="o-stat-label">Low Stock Alert</div>
                            <div class="o-stat-value"><?php echo (int)$low_stock_items; ?></div>
                            <small style="color:#8a8f98;">Items with &le;10 stock</small>
                        </div>
                    </div>
                </div>

            </div>

            <div class="o-footer">Developed by Tyron Del Valle</div>
        </div>
    </div>

    <script src="../assets/sidebar.js"></script>
</body>
</html>
