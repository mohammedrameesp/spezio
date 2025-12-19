<?php
/**
 * Spezio Apartments Admin - Blocked Dates
 */

require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pageTitle = 'Blocked Dates';

$rooms = getRooms();
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'block') {
        $roomId = (int)($_POST['room_id'] ?? 0);
        $dateFrom = $_POST['date_from'] ?? '';
        $dateTo = $_POST['date_to'] ?? '';
        $reason = trim($_POST['reason'] ?? '');

        if (!$roomId || !$dateFrom) {
            $error = 'Please select a room and date.';
        } else {
            $dateTo = $dateTo ?: $dateFrom;

            // Insert blocked dates
            $current = new DateTime($dateFrom);
            $end = new DateTime($dateTo);
            $end->modify('+1 day');

            $blocked = 0;
            while ($current < $end) {
                $date = $current->format('Y-m-d');

                // Check if already blocked
                $exists = dbExists('blocked_dates', 'room_id = ? AND blocked_date = ?', [$roomId, $date]);

                if (!$exists) {
                    dbInsert('blocked_dates', [
                        'room_id' => $roomId,
                        'blocked_date' => $date,
                        'reason' => $reason
                    ]);
                    $blocked++;
                }

                $current->modify('+1 day');
            }

            logActivity($_SESSION['admin_id'], 'dates_blocked', 'blocked_dates', $roomId, [
                'from' => $dateFrom,
                'to' => $dateTo,
                'count' => $blocked
            ]);

            $success = "{$blocked} date(s) blocked successfully.";
        }
    } elseif ($action === 'unblock') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            dbDelete('blocked_dates', 'id = ?', [$id]);
            $success = 'Date unblocked.';
        }
    }
}

// Get blocked dates
$blockedDates = dbFetchAll(
    "SELECT bd.*, r.name as room_name FROM blocked_dates bd
     JOIN rooms r ON bd.room_id = r.id
     WHERE bd.blocked_date >= CURDATE()
     ORDER BY bd.blocked_date ASC"
);

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Content -->
<main class="main-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">Blocked Dates</h1>
            <p class="page-subtitle">Manually block dates for rooms</p>
        </div>
        <a href="calendar.php" class="btn btn-secondary">View Calendar</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="grid-2">
        <!-- Block Dates Form -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Block Dates</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="block">

                    <div class="form-group">
                        <label class="form-label">Room *</label>
                        <select name="room_id" class="form-control" required>
                            <option value="">Select Room</option>
                            <?php foreach ($rooms as $room): ?>
                                <option value="<?php echo $room['id']; ?>">
                                    <?php echo htmlspecialchars($room['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">From Date *</label>
                            <input type="date" name="date_from" class="form-control" required
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">To Date</label>
                            <input type="date" name="date_to" class="form-control"
                                   min="<?php echo date('Y-m-d'); ?>">
                            <p class="form-text">Leave empty for single date</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Reason</label>
                        <input type="text" name="reason" class="form-control" placeholder="e.g., Maintenance, Personal use">
                    </div>

                    <button type="submit" class="btn btn-primary">Block Dates</button>
                </form>
            </div>
        </div>

        <!-- Blocked Dates List -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Upcoming Blocked Dates</h3>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Room</th>
                            <th>Reason</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($blockedDates)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">No blocked dates</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($blockedDates as $blocked): ?>
                                <tr>
                                    <td><?php echo formatDate($blocked['blocked_date'], 'd M Y'); ?></td>
                                    <td><?php echo htmlspecialchars($blocked['room_name']); ?></td>
                                    <td><?php echo htmlspecialchars($blocked['reason'] ?: '-'); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Unblock this date?')">
                                            <input type="hidden" name="action" value="unblock">
                                            <input type="hidden" name="id" value="<?php echo $blocked['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Unblock</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
