<?php
/**
 * Spezio Apartments Admin - Dashboard
 */

require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pageTitle = 'Dashboard';

// Get stats
$today = date('Y-m-d');
$thisMonth = date('Y-m');

// Today's check-ins
$todayCheckIns = dbFetchAll(
    "SELECT b.*, r.name as room_name FROM bookings b
     JOIN rooms r ON b.room_id = r.id
     WHERE b.check_in = ? AND b.booking_status = 'confirmed'",
    [$today]
);

// Today's check-outs
$todayCheckOuts = dbFetchAll(
    "SELECT b.*, r.name as room_name FROM bookings b
     JOIN rooms r ON b.room_id = r.id
     WHERE b.check_out = ? AND b.booking_status IN ('confirmed', 'completed')",
    [$today]
);

// Monthly revenue
$monthlyRevenue = dbFetchOne(
    "SELECT SUM(total_amount) as total FROM bookings
     WHERE payment_status = 'paid' AND DATE_FORMAT(created_at, '%Y-%m') = ?",
    [$thisMonth]
);

// Total bookings this month
$monthlyBookings = dbCount(
    'bookings',
    "DATE_FORMAT(created_at, '%Y-%m') = ? AND booking_status != 'cancelled'",
    [$thisMonth]
);

// Pending bookings
$pendingBookings = dbCount('bookings', "booking_status = 'pending'");

// Recent bookings
$recentBookings = dbFetchAll(
    "SELECT b.*, r.name as room_name FROM bookings b
     JOIN rooms r ON b.room_id = r.id
     ORDER BY b.created_at DESC LIMIT 10"
);

// Room occupancy today
$occupiedRooms = dbCount(
    'bookings',
    "check_in <= ? AND check_out > ? AND booking_status = 'confirmed'",
    [$today, $today]
);

$totalRooms = dbCount('rooms', "status = 'active'");

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Content -->
<main class="main-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Welcome back, <?php echo htmlspecialchars($admin['name']); ?>!</p>
        </div>
        <div class="quick-actions">
            <a href="bookings.php" class="btn btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                </svg>
                View Bookings
            </a>
            <a href="calendar.php" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                Calendar
            </a>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-icon blue">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                    </svg>
                </div>
            </div>
            <div class="stat-value"><?php echo $monthlyBookings; ?></div>
            <div class="stat-label">Bookings This Month</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-icon green">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"></line>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                </div>
            </div>
            <div class="stat-value"><?php echo CURRENCY_SYMBOL . number_format($monthlyRevenue['total'] ?? 0); ?></div>
            <div class="stat-label">Revenue This Month</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-icon orange">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                </div>
            </div>
            <div class="stat-value"><?php echo $pendingBookings; ?></div>
            <div class="stat-label">Pending Bookings</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-icon purple">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                </div>
            </div>
            <div class="stat-value"><?php echo $occupiedRooms; ?>/<?php echo $totalRooms; ?></div>
            <div class="stat-label">Rooms Occupied Today</div>
        </div>
    </div>

    <!-- Today's Activity -->
    <div class="grid-2 mb-3">
        <!-- Today's Check-ins -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Today's Check-ins (<?php echo count($todayCheckIns); ?>)</h3>
            </div>
            <div class="card-body">
                <?php if (empty($todayCheckIns)): ?>
                    <p class="text-muted text-center">No check-ins today</p>
                <?php else: ?>
                    <ul class="activity-list">
                        <?php foreach ($todayCheckIns as $booking): ?>
                            <li class="activity-item">
                                <div class="activity-icon" style="background: #e8f5e9; color: #2e7d32;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                                        <polyline points="10 17 15 12 10 7"></polyline>
                                        <line x1="15" y1="12" x2="3" y2="12"></line>
                                    </svg>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-text">
                                        <strong><?php echo htmlspecialchars($booking['guest_name']); ?></strong>
                                        - <?php echo htmlspecialchars($booking['room_name']); ?>
                                    </div>
                                    <div class="activity-time">
                                        <?php echo $booking['total_nights']; ?> nights | <?php echo htmlspecialchars($booking['guest_phone']); ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Today's Check-outs -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Today's Check-outs (<?php echo count($todayCheckOuts); ?>)</h3>
            </div>
            <div class="card-body">
                <?php if (empty($todayCheckOuts)): ?>
                    <p class="text-muted text-center">No check-outs today</p>
                <?php else: ?>
                    <ul class="activity-list">
                        <?php foreach ($todayCheckOuts as $booking): ?>
                            <li class="activity-item">
                                <div class="activity-icon" style="background: #fff3e0; color: #f57c00;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                        <polyline points="16 17 21 12 16 7"></polyline>
                                        <line x1="21" y1="12" x2="9" y2="12"></line>
                                    </svg>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-text">
                                        <strong><?php echo htmlspecialchars($booking['guest_name']); ?></strong>
                                        - <?php echo htmlspecialchars($booking['room_name']); ?>
                                    </div>
                                    <div class="activity-time">
                                        <?php echo htmlspecialchars($booking['booking_id']); ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Bookings -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Recent Bookings</h3>
            <a href="bookings.php" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Guest</th>
                        <th>Room</th>
                        <th>Check-in</th>
                        <th>Check-out</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentBookings)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">No bookings yet</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentBookings as $booking): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($booking['booking_id']); ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($booking['guest_name']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($booking['guest_email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($booking['room_name']); ?></td>
                                <td><?php echo formatDate($booking['check_in'], 'd M Y'); ?></td>
                                <td><?php echo formatDate($booking['check_out'], 'd M Y'); ?></td>
                                <td><?php echo formatCurrency($booking['total_amount']); ?></td>
                                <td>
                                    <?php
                                    $statusClass = [
                                        'confirmed' => 'success',
                                        'pending' => 'warning',
                                        'cancelled' => 'danger',
                                        'completed' => 'info'
                                    ][$booking['booking_status']] ?? 'secondary';
                                    ?>
                                    <span class="badge badge-<?php echo $statusClass; ?>">
                                        <?php echo ucfirst($booking['booking_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="booking-view.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-secondary">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
