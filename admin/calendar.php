<?php
/**
 * Spezio Apartments Admin - Calendar View
 */

require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pageTitle = 'Calendar';

// Get rooms
$rooms = getRooms();

// Get selected month
$month = $_GET['month'] ?? date('Y-m');
$monthStart = $month . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));

// Get bookings for this month
$bookings = dbFetchAll(
    "SELECT b.*, r.name as room_name, r.slug as room_slug FROM bookings b
     JOIN rooms r ON b.room_id = r.id
     WHERE b.booking_status IN ('confirmed', 'pending')
     AND ((b.check_in BETWEEN ? AND ?) OR (b.check_out BETWEEN ? AND ?)
     OR (b.check_in <= ? AND b.check_out >= ?))",
    [$monthStart, $monthEnd, $monthStart, $monthEnd, $monthStart, $monthEnd]
);

// Get blocked dates
$blockedDates = dbFetchAll(
    "SELECT bd.*, r.name as room_name FROM blocked_dates bd
     JOIN rooms r ON bd.room_id = r.id
     WHERE bd.blocked_date BETWEEN ? AND ?",
    [$monthStart, $monthEnd]
);

// Organize bookings by room and date
$calendar = [];
foreach ($rooms as $room) {
    $calendar[$room['id']] = [
        'name' => $room['name'],
        'dates' => []
    ];
}

foreach ($bookings as $booking) {
    $start = new DateTime($booking['check_in']);
    $end = new DateTime($booking['check_out']);
    while ($start < $end) {
        $date = $start->format('Y-m-d');
        if ($date >= $monthStart && $date <= $monthEnd) {
            $calendar[$booking['room_id']]['dates'][$date] = [
                'type' => 'booking',
                'booking_id' => $booking['id'],
                'guest' => $booking['guest_name'],
                'status' => $booking['booking_status']
            ];
        }
        $start->modify('+1 day');
    }
}

foreach ($blockedDates as $blocked) {
    $calendar[$blocked['room_id']]['dates'][$blocked['blocked_date']] = [
        'type' => 'blocked',
        'reason' => $blocked['reason']
    ];
}

// Generate calendar days
$daysInMonth = date('t', strtotime($monthStart));
$firstDayOfWeek = date('w', strtotime($monthStart));

// Navigation
$prevMonth = date('Y-m', strtotime($monthStart . ' -1 month'));
$nextMonth = date('Y-m', strtotime($monthStart . ' +1 month'));

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<style>
.calendar-wrapper { overflow-x: auto; }
.calendar-table { width: 100%; border-collapse: collapse; min-width: 800px; }
.calendar-table th, .calendar-table td { border: 1px solid var(--border); padding: 0.5rem; text-align: center; font-size: 0.8rem; }
.calendar-table th { background: var(--bg); font-weight: 600; }
.calendar-table .room-name { text-align: left; font-weight: 500; min-width: 150px; }
.calendar-table .day-header { width: 40px; }
.calendar-table .day-cell { height: 40px; vertical-align: middle; }
.calendar-table .booked { background: #e3f2fd; color: #1565c0; }
.calendar-table .blocked { background: #ffebee; color: #c62828; }
.calendar-table .today { border: 2px solid var(--primary); }
.calendar-table .past { opacity: 0.5; }
.calendar-nav { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
.month-display { font-size: 1.25rem; font-weight: 600; }
</style>

<!-- Main Content -->
<main class="main-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">Calendar</h1>
            <p class="page-subtitle">Room availability overview</p>
        </div>
        <a href="blocked-dates.php" class="btn btn-secondary">Manage Blocked Dates</a>
    </div>

    <!-- Month Navigation -->
    <div class="calendar-nav">
        <a href="?month=<?php echo $prevMonth; ?>" class="btn btn-secondary btn-sm">&larr; Previous</a>
        <span class="month-display"><?php echo date('F Y', strtotime($monthStart)); ?></span>
        <a href="?month=<?php echo $nextMonth; ?>" class="btn btn-secondary btn-sm">Next &rarr;</a>
        <a href="?month=<?php echo date('Y-m'); ?>" class="btn btn-sm btn-primary">Today</a>
    </div>

    <!-- Legend -->
    <div class="card mb-3">
        <div class="card-body" style="padding: 0.75rem; display: flex; gap: 2rem; font-size: 0.8rem;">
            <span><span style="display: inline-block; width: 16px; height: 16px; background: #e3f2fd; border-radius: 3px; vertical-align: middle;"></span> Booked</span>
            <span><span style="display: inline-block; width: 16px; height: 16px; background: #ffebee; border-radius: 3px; vertical-align: middle;"></span> Blocked</span>
            <span><span style="display: inline-block; width: 16px; height: 16px; border: 2px solid var(--primary); border-radius: 3px; vertical-align: middle;"></span> Today</span>
        </div>
    </div>

    <!-- Calendar -->
    <div class="card">
        <div class="calendar-wrapper">
            <table class="calendar-table">
                <thead>
                    <tr>
                        <th class="room-name">Room</th>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                            <?php $dateStr = $month . '-' . str_pad($d, 2, '0', STR_PAD_LEFT); ?>
                            <th class="day-header <?php echo $dateStr === date('Y-m-d') ? 'today' : ''; ?>">
                                <?php echo $d; ?><br>
                                <small><?php echo date('D', strtotime($dateStr)); ?></small>
                            </th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($calendar as $roomId => $roomData): ?>
                        <tr>
                            <td class="room-name"><?php echo htmlspecialchars($roomData['name']); ?></td>
                            <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                                <?php
                                $dateStr = $month . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
                                $isPast = $dateStr < date('Y-m-d');
                                $isToday = $dateStr === date('Y-m-d');
                                $cellData = $roomData['dates'][$dateStr] ?? null;
                                $cellClass = 'day-cell';
                                if ($isToday) $cellClass .= ' today';
                                if ($isPast) $cellClass .= ' past';
                                if ($cellData) {
                                    $cellClass .= $cellData['type'] === 'booking' ? ' booked' : ' blocked';
                                }
                                ?>
                                <td class="<?php echo $cellClass; ?>"
                                    title="<?php
                                        if ($cellData) {
                                            if ($cellData['type'] === 'booking') {
                                                echo htmlspecialchars($cellData['guest']);
                                            } else {
                                                echo 'Blocked: ' . htmlspecialchars($cellData['reason'] ?? 'No reason');
                                            }
                                        }
                                    ?>">
                                    <?php if ($cellData): ?>
                                        <?php if ($cellData['type'] === 'booking'): ?>
                                            <a href="booking-view.php?id=<?php echo $cellData['booking_id']; ?>" style="color: inherit; text-decoration: none;">
                                                &#9679;
                                            </a>
                                        <?php else: ?>
                                            &#10005;
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            <?php endfor; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
