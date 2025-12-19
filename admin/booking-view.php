<?php
/**
 * Spezio Apartments Admin - View Booking
 */

require_once __DIR__ . '/includes/auth.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: bookings.php');
    exit;
}

$booking = getBookingById($id);

if (!$booking) {
    header('Location: bookings.php');
    exit;
}

$pageTitle = 'Booking #' . $booking['booking_id'];

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'update_status':
            $newStatus = $_POST['booking_status'] ?? '';
            if (in_array($newStatus, ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'])) {
                dbUpdate('bookings', ['booking_status' => $newStatus], 'id = ?', ['id' => $id]);
                logActivity($_SESSION['admin_id'], 'booking_status_updated', 'bookings', $id, ['new_status' => $newStatus]);
                $booking['booking_status'] = $newStatus;
                $success = 'Booking status updated.';
            }
            break;

        case 'add_note':
            $note = trim($_POST['admin_notes'] ?? '');
            $existingNotes = $booking['admin_notes'] ?? '';
            $newNotes = $existingNotes ? $existingNotes . "\n\n" . date('Y-m-d H:i') . ":\n" . $note : date('Y-m-d H:i') . ":\n" . $note;
            dbUpdate('bookings', ['admin_notes' => $newNotes], 'id = ?', ['id' => $id]);
            $booking['admin_notes'] = $newNotes;
            $success = 'Note added.';
            break;
    }
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Content -->
<main class="main-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">Booking #<?php echo htmlspecialchars($booking['booking_id']); ?></h1>
            <p class="page-subtitle">
                Created on <?php echo formatDate($booking['created_at'], 'd M Y, h:i A'); ?>
            </p>
        </div>
        <div class="action-buttons">
            <a href="bookings.php" class="btn btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Back to Bookings
            </a>
            <button onclick="window.print()" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                Print
            </button>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="grid-2">
        <!-- Booking Details -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Booking Details</h3>
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
            </div>
            <div class="card-body">
                <table style="width: 100%;">
                    <tr>
                        <td class="text-muted" style="padding: 0.5rem 0; width: 40%;">Room</td>
                        <td style="padding: 0.5rem 0;"><strong><?php echo htmlspecialchars($booking['room_name']); ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="padding: 0.5rem 0;">Check-in</td>
                        <td style="padding: 0.5rem 0;"><?php echo formatDate($booking['check_in'], 'l, d M Y'); ?> (<?php echo CHECK_IN_TIME; ?>)</td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="padding: 0.5rem 0;">Check-out</td>
                        <td style="padding: 0.5rem 0;"><?php echo formatDate($booking['check_out'], 'l, d M Y'); ?> (<?php echo CHECK_OUT_TIME; ?>)</td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="padding: 0.5rem 0;">Nights</td>
                        <td style="padding: 0.5rem 0;"><?php echo $booking['total_nights']; ?> night(s)</td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="padding: 0.5rem 0;">Guests</td>
                        <td style="padding: 0.5rem 0;"><?php echo $booking['num_guests']; ?> guest(s)</td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="padding: 0.5rem 0;">Rate Type</td>
                        <td style="padding: 0.5rem 0;"><?php echo ucfirst($booking['pricing_tier']); ?> Rate</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Guest Details -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Guest Information</h3>
            </div>
            <div class="card-body">
                <table style="width: 100%;">
                    <tr>
                        <td class="text-muted" style="padding: 0.5rem 0; width: 40%;">Name</td>
                        <td style="padding: 0.5rem 0;"><strong><?php echo htmlspecialchars($booking['guest_name']); ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="padding: 0.5rem 0;">Email</td>
                        <td style="padding: 0.5rem 0;">
                            <a href="mailto:<?php echo htmlspecialchars($booking['guest_email']); ?>">
                                <?php echo htmlspecialchars($booking['guest_email']); ?>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="padding: 0.5rem 0;">Phone</td>
                        <td style="padding: 0.5rem 0;">
                            <a href="tel:<?php echo htmlspecialchars($booking['guest_phone']); ?>">
                                <?php echo htmlspecialchars($booking['guest_phone']); ?>
                            </a>
                            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $booking['guest_phone']); ?>"
                               target="_blank" class="btn btn-sm btn-success" style="margin-left: 0.5rem;">
                                WhatsApp
                            </a>
                        </td>
                    </tr>
                </table>

                <?php if ($booking['special_requests']): ?>
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                        <p class="text-muted mb-1">Special Requests:</p>
                        <p style="margin: 0;"><?php echo nl2br(htmlspecialchars($booking['special_requests'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payment Details -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Payment Details</h3>
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
            </div>
            <div class="card-body">
                <table style="width: 100%;">
                    <tr>
                        <td class="text-muted" style="padding: 0.5rem 0; width: 50%;">
                            <?php echo $booking['total_nights']; ?> nights x <?php echo formatCurrency($booking['rate_per_night']); ?>
                        </td>
                        <td style="padding: 0.5rem 0; text-align: right;">
                            <?php echo formatCurrency($booking['subtotal']); ?>
                        </td>
                    </tr>
                    <?php if ($booking['discount_amount'] > 0): ?>
                        <tr class="text-success">
                            <td style="padding: 0.5rem 0;">
                                Discount (<?php echo htmlspecialchars($booking['coupon_code']); ?>)
                            </td>
                            <td style="padding: 0.5rem 0; text-align: right;">
                                -<?php echo formatCurrency($booking['discount_amount']); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <tr style="border-top: 2px solid var(--border);">
                        <td style="padding: 0.75rem 0;"><strong>Total</strong></td>
                        <td style="padding: 0.75rem 0; text-align: right;">
                            <strong style="font-size: 1.25rem;"><?php echo formatCurrency($booking['total_amount']); ?></strong>
                        </td>
                    </tr>
                </table>

                <?php if ($booking['razorpay_payment_id']): ?>
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                        <p class="text-muted mb-1">Payment ID:</p>
                        <code><?php echo htmlspecialchars($booking['razorpay_payment_id']); ?></code>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Update Status -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Update Status</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <div class="form-group">
                        <label class="form-label">Booking Status</label>
                        <select name="booking_status" class="form-control">
                            <option value="pending" <?php echo $booking['booking_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $booking['booking_status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="completed" <?php echo $booking['booking_status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $booking['booking_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="no_show" <?php echo $booking['booking_status'] === 'no_show' ? 'selected' : ''; ?>>No Show</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </form>

                <hr style="margin: 1.5rem 0;">

                <form method="POST">
                    <input type="hidden" name="action" value="add_note">
                    <div class="form-group">
                        <label class="form-label">Add Admin Note</label>
                        <textarea name="admin_notes" class="form-control" rows="3" placeholder="Add a note..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-secondary">Add Note</button>
                </form>

                <?php if ($booking['admin_notes']): ?>
                    <div style="margin-top: 1rem; padding: 1rem; background: var(--bg); border-radius: 8px;">
                        <p class="text-muted mb-1"><strong>Admin Notes:</strong></p>
                        <p style="margin: 0; white-space: pre-wrap;"><?php echo htmlspecialchars($booking['admin_notes']); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
