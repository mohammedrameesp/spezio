<?php
/**
 * Spezio Apartments Admin - Bookings List
 */

require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pageTitle = 'Bookings';

// Filters
$status = $_GET['status'] ?? '';
$room = $_GET['room'] ?? '';
$search = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$where = ['1=1'];
$params = [];

if ($status) {
    $where[] = "b.booking_status = ?";
    $params[] = $status;
}

if ($room) {
    $where[] = "b.room_id = ?";
    $params[] = $room;
}

if ($search) {
    $where[] = "(b.booking_id LIKE ? OR b.guest_name LIKE ? OR b.guest_email LIKE ? OR b.guest_phone LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if ($dateFrom) {
    $where[] = "b.check_in >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $where[] = "b.check_in <= ?";
    $params[] = $dateTo;
}

$whereClause = implode(' AND ', $where);

// Get total count
$totalBookings = dbCount('bookings b', $whereClause, $params);
$totalPages = ceil($totalBookings / $perPage);

// Get bookings
$bookings = dbFetchAll(
    "SELECT b.*, r.name as room_name FROM bookings b
     JOIN rooms r ON b.room_id = r.id
     WHERE {$whereClause}
     ORDER BY b.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

// Get rooms for filter
$rooms = getRooms();

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Content -->
<main class="main-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">Bookings</h1>
            <p class="page-subtitle">Manage all bookings</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="form-row" style="grid-template-columns: repeat(5, 1fr) auto;">
                <div class="form-group mb-0">
                    <input type="text" name="search" class="form-control" placeholder="Search..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group mb-0">
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <select name="room" class="form-control">
                        <option value="">All Rooms</option>
                        <?php foreach ($rooms as $r): ?>
                            <option value="<?php echo $r['id']; ?>" <?php echo $room == $r['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($r['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <input type="date" name="date_from" class="form-control" placeholder="From date"
                           value="<?php echo htmlspecialchars($dateFrom); ?>">
                </div>
                <div class="form-group mb-0">
                    <input type="date" name="date_to" class="form-control" placeholder="To date"
                           value="<?php echo htmlspecialchars($dateTo); ?>">
                </div>
                <button type="submit" class="btn btn-primary">Filter</button>
            </form>
        </div>
    </div>

    <!-- Bookings Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">All Bookings (<?php echo $totalBookings; ?>)</h3>
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
                        <th>Nights</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted">No bookings found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($booking['booking_id']); ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($booking['guest_name']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($booking['guest_phone']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($booking['room_name']); ?></td>
                                <td><?php echo formatDate($booking['check_in'], 'd M Y'); ?></td>
                                <td><?php echo formatDate($booking['check_out'], 'd M Y'); ?></td>
                                <td><?php echo $booking['total_nights']; ?></td>
                                <td>
                                    <?php echo formatCurrency($booking['total_amount']); ?>
                                    <?php if ($booking['discount_amount'] > 0): ?>
                                        <br><small class="text-success">-<?php echo formatCurrency($booking['discount_amount']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $paymentClass = [
                                        'paid' => 'success',
                                        'pending' => 'warning',
                                        'failed' => 'danger',
                                        'refunded' => 'info'
                                    ][$booking['payment_status']] ?? 'secondary';
                                    ?>
                                    <span class="badge badge-<?php echo $paymentClass; ?>">
                                        <?php echo ucfirst($booking['payment_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = [
                                        'confirmed' => 'success',
                                        'pending' => 'warning',
                                        'cancelled' => 'danger',
                                        'completed' => 'info',
                                        'no_show' => 'secondary'
                                    ][$booking['booking_status']] ?? 'secondary';
                                    ?>
                                    <span class="badge badge-<?php echo $statusClass; ?>">
                                        <?php echo ucfirst($booking['booking_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="booking-view.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-secondary">View</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="card-body">
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Prev</a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
