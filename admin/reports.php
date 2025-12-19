<?php
/**
 * Spezio Apartments Admin - Reports
 */

require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pageTitle = 'Reports';

// Date range
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Revenue stats
$revenueStats = dbFetchOne(
    "SELECT
        SUM(total_amount) as total_revenue,
        SUM(discount_amount) as total_discounts,
        COUNT(*) as total_bookings,
        SUM(total_nights) as total_nights
     FROM bookings
     WHERE payment_status = 'paid'
     AND DATE(created_at) BETWEEN ? AND ?",
    [$dateFrom, $dateTo]
);

// Daily revenue for chart
$dailyRevenue = dbFetchAll(
    "SELECT
        DATE(created_at) as date,
        SUM(total_amount) as revenue,
        COUNT(*) as bookings
     FROM bookings
     WHERE payment_status = 'paid'
     AND DATE(created_at) BETWEEN ? AND ?
     GROUP BY DATE(created_at)
     ORDER BY date ASC",
    [$dateFrom, $dateTo]
);

// Room-wise stats
$roomStats = dbFetchAll(
    "SELECT
        r.name as room_name,
        COUNT(b.id) as bookings,
        SUM(b.total_amount) as revenue,
        SUM(b.total_nights) as nights
     FROM rooms r
     LEFT JOIN bookings b ON r.id = b.room_id
        AND b.payment_status = 'paid'
        AND DATE(b.created_at) BETWEEN ? AND ?
     WHERE r.status = 'active'
     GROUP BY r.id
     ORDER BY revenue DESC",
    [$dateFrom, $dateTo]
);

// Coupon usage
$couponStats = dbFetchAll(
    "SELECT
        coupon_code,
        COUNT(*) as times_used,
        SUM(discount_amount) as total_discount
     FROM bookings
     WHERE coupon_code IS NOT NULL
     AND payment_status = 'paid'
     AND DATE(created_at) BETWEEN ? AND ?
     GROUP BY coupon_code
     ORDER BY times_used DESC",
    [$dateFrom, $dateTo]
);

// Booking status breakdown
$statusStats = dbFetchAll(
    "SELECT
        booking_status,
        COUNT(*) as count
     FROM bookings
     WHERE DATE(created_at) BETWEEN ? AND ?
     GROUP BY booking_status",
    [$dateFrom, $dateTo]
);

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Content -->
<main class="main-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">Reports</h1>
            <p class="page-subtitle">Revenue and booking analytics</p>
        </div>
    </div>

    <!-- Date Filter -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="form-row" style="grid-template-columns: auto auto auto 1fr;">
                <div class="form-group mb-0">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control"
                           value="<?php echo $dateFrom; ?>">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control"
                           value="<?php echo $dateTo; ?>">
                </div>
                <div class="form-group mb-0" style="align-self: end;">
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                </div>
                <div class="form-group mb-0" style="align-self: end; text-align: right;">
                    <a href="?date_from=<?php echo date('Y-m-01'); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-secondary btn-sm">This Month</a>
                    <a href="?date_from=<?php echo date('Y-01-01'); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-secondary btn-sm">This Year</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="stats-grid mb-3">
        <div class="stat-card">
            <div class="stat-icon green">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
            </div>
            <div class="stat-value"><?php echo formatCurrency($revenueStats['total_revenue'] ?? 0); ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon blue">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                </svg>
            </div>
            <div class="stat-value"><?php echo $revenueStats['total_bookings'] ?? 0; ?></div>
            <div class="stat-label">Total Bookings</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon orange">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                </svg>
            </div>
            <div class="stat-value"><?php echo $revenueStats['total_nights'] ?? 0; ?></div>
            <div class="stat-label">Total Nights</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon purple">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 12 20 22 4 22 4 12"></polyline>
                    <rect x="2" y="7" width="20" height="5"></rect>
                </svg>
            </div>
            <div class="stat-value"><?php echo formatCurrency($revenueStats['total_discounts'] ?? 0); ?></div>
            <div class="stat-label">Discounts Given</div>
        </div>
    </div>

    <div class="grid-2 mb-3">
        <!-- Room-wise Revenue -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Revenue by Room</h3>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>Bookings</th>
                            <th>Nights</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roomStats as $room): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($room['room_name']); ?></td>
                                <td><?php echo $room['bookings'] ?? 0; ?></td>
                                <td><?php echo $room['nights'] ?? 0; ?></td>
                                <td><strong><?php echo formatCurrency($room['revenue'] ?? 0); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Booking Status -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Booking Status</h3>
            </div>
            <div class="card-body">
                <?php if (empty($statusStats)): ?>
                    <p class="text-muted text-center">No data available</p>
                <?php else: ?>
                    <?php foreach ($statusStats as $stat): ?>
                        <?php
                        $statusClass = [
                            'confirmed' => 'success',
                            'pending' => 'warning',
                            'cancelled' => 'danger',
                            'completed' => 'info',
                            'no_show' => 'secondary'
                        ][$stat['booking_status']] ?? 'secondary';
                        ?>
                        <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--border);">
                            <span>
                                <span class="badge badge-<?php echo $statusClass; ?>" style="margin-right: 0.5rem;">
                                    <?php echo ucfirst($stat['booking_status']); ?>
                                </span>
                            </span>
                            <strong><?php echo $stat['count']; ?></strong>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Coupon Usage -->
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Coupon Usage</h3>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Coupon Code</th>
                        <th>Times Used</th>
                        <th>Total Discount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($couponStats)): ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted">No coupons used in this period</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($couponStats as $coupon): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($coupon['coupon_code']); ?></strong></td>
                                <td><?php echo $coupon['times_used']; ?></td>
                                <td><?php echo formatCurrency($coupon['total_discount']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Daily Revenue -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daily Revenue</h3>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Bookings</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dailyRevenue)): ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted">No data available</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dailyRevenue as $day): ?>
                            <tr>
                                <td><?php echo formatDate($day['date'], 'D, d M Y'); ?></td>
                                <td><?php echo $day['bookings']; ?></td>
                                <td><strong><?php echo formatCurrency($day['revenue']); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
