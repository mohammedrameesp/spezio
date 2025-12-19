<?php
/**
 * Spezio Apartments Admin - Pricing Management
 * Manage room rates and duration-based discounts
 */

require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pageTitle = 'Pricing';
$currentPage = 'pricing';

$success = '';
$error = '';

// Get database connection
$db = getDB();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_room_rates') {
        // Update room base rates
        $roomId = (int) ($_POST['room_id'] ?? 0);
        $priceDaily = (float) ($_POST['price_daily'] ?? 0);

        if ($roomId > 0 && $priceDaily > 0) {
            $stmt = $db->prepare("UPDATE rooms SET price_daily = ?, price_weekly = ?, price_monthly = ? WHERE id = ?");
            // Weekly and monthly now use duration discounts, so set same as daily
            $stmt->execute([$priceDaily, $priceDaily, $priceDaily, $roomId]);
            logActivity($_SESSION['admin_id'], 'room_price_updated', 'rooms', $roomId);
            $success = 'Room rate updated successfully.';
        } else {
            $error = 'Invalid room or price.';
        }
    } elseif ($action === 'update_duration_discount') {
        // Update a duration discount
        $discountId = (int) ($_POST['discount_id'] ?? 0);
        $minNights = (int) ($_POST['min_nights'] ?? 0);
        $discountPercent = (float) ($_POST['discount_percent'] ?? 0);
        $label = sanitize($_POST['label'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($discountId > 0 && $minNights > 0) {
            $stmt = $db->prepare("UPDATE duration_discounts SET min_nights = ?, discount_percent = ?, label = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$minNights, $discountPercent, $label, $isActive, $discountId]);
            logActivity($_SESSION['admin_id'], 'duration_discount_updated', 'duration_discounts', $discountId);
            $success = 'Duration discount updated successfully.';
        } else {
            $error = 'Invalid discount settings.';
        }
    } elseif ($action === 'add_duration_discount') {
        // Add new duration discount
        $minNights = (int) ($_POST['min_nights'] ?? 0);
        $discountPercent = (float) ($_POST['discount_percent'] ?? 0);
        $label = sanitize($_POST['label'] ?? '');

        if ($minNights > 0 && $discountPercent > 0 && !empty($label)) {
            try {
                $stmt = $db->prepare("INSERT INTO duration_discounts (min_nights, discount_percent, label) VALUES (?, ?, ?)");
                $stmt->execute([$minNights, $discountPercent, $label]);
                logActivity($_SESSION['admin_id'], 'duration_discount_added', 'duration_discounts', $db->lastInsertId());
                $success = 'Duration discount added successfully.';
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = 'A discount for this number of nights already exists.';
                } else {
                    $error = 'Failed to add discount.';
                }
            }
        } else {
            $error = 'Please fill in all fields.';
        }
    } elseif ($action === 'delete_duration_discount') {
        $discountId = (int) ($_POST['discount_id'] ?? 0);
        if ($discountId > 0) {
            $stmt = $db->prepare("DELETE FROM duration_discounts WHERE id = ?");
            $stmt->execute([$discountId]);
            logActivity($_SESSION['admin_id'], 'duration_discount_deleted', 'duration_discounts', $discountId);
            $success = 'Duration discount deleted.';
        }
    }
}

// Get all rooms
$stmt = $db->query("SELECT * FROM rooms ORDER BY name");
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all duration discounts
$stmt = $db->query("SELECT * FROM duration_discounts ORDER BY min_nights ASC");
$durationDiscounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Content -->
<main class="main-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">Pricing Management</h1>
            <p class="page-subtitle">Configure room rates and duration-based discounts</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="grid-2">
        <!-- Room Base Rates -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Room Base Rates</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">Set the base rate per night for each room. Duration discounts will be applied automatically.</p>

                <?php foreach ($rooms as $room): ?>
                <form method="POST" class="room-rate-form">
                    <input type="hidden" name="action" value="update_room_rates">
                    <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">

                    <div class="form-row align-items-end">
                        <div class="form-group" style="flex: 2;">
                            <label class="form-label"><?php echo htmlspecialchars($room['name']); ?></label>
                            <div class="input-group">
                                <span class="input-group-text"><?php echo CURRENCY_SYMBOL; ?></span>
                                <input type="number" name="price_daily" class="form-control"
                                       value="<?php echo $room['price_daily']; ?>"
                                       min="0" step="100" required>
                                <span class="input-group-text">/night</span>
                            </div>
                        </div>
                        <div class="form-group" style="flex: 0;">
                            <button type="submit" class="btn btn-primary">Update</button>
                        </div>
                    </div>
                </form>
                <?php if (!end($rooms) === $room): ?>
                <hr style="margin: 1rem 0;">
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Duration Discounts -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Duration-Based Discounts</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">Discounts applied automatically based on length of stay.</p>

                <table class="table">
                    <thead>
                        <tr>
                            <th>Min. Nights</th>
                            <th>Discount</th>
                            <th>Label</th>
                            <th>Active</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($durationDiscounts as $discount): ?>
                        <tr>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_duration_discount">
                                <input type="hidden" name="discount_id" value="<?php echo $discount['id']; ?>">
                                <td>
                                    <input type="number" name="min_nights" class="form-control form-control-sm"
                                           value="<?php echo $discount['min_nights']; ?>" min="1" style="width: 70px;">
                                </td>
                                <td>
                                    <div class="input-group input-group-sm" style="width: 100px;">
                                        <input type="number" name="discount_percent" class="form-control"
                                               value="<?php echo $discount['discount_percent']; ?>"
                                               min="0" max="100" step="0.5">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" name="label" class="form-control form-control-sm"
                                           value="<?php echo htmlspecialchars($discount['label']); ?>"
                                           style="width: 100px;">
                                </td>
                                <td>
                                    <input type="checkbox" name="is_active" value="1"
                                           <?php echo $discount['is_active'] ? 'checked' : ''; ?>>
                                </td>
                                <td>
                                    <button type="submit" class="btn btn-sm btn-primary">Save</button>
                            </form>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this discount?');">
                                <input type="hidden" name="action" value="delete_duration_discount">
                                <input type="hidden" name="discount_id" value="<?php echo $discount['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                                </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <hr style="margin: 1.5rem 0;">

                <h4 style="font-size: 1rem; margin-bottom: 1rem;">Add New Discount</h4>
                <form method="POST">
                    <input type="hidden" name="action" value="add_duration_discount">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Min. Nights</label>
                            <input type="number" name="min_nights" class="form-control" min="1" required placeholder="e.g., 7">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Discount %</label>
                            <input type="number" name="discount_percent" class="form-control" min="0" max="100" step="0.5" required placeholder="e.g., 10">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Label</label>
                            <input type="text" name="label" class="form-control" required placeholder="e.g., Weekly">
                        </div>
                        <div class="form-group" style="align-self: flex-end;">
                            <button type="submit" class="btn btn-success">Add Discount</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Discount Preview -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Discount Preview</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">Preview of how discounts apply to bookings:</p>

                <?php
                $previewRoom = $rooms[0] ?? null;
                if ($previewRoom):
                    $baseRate = $previewRoom['price_daily'];
                ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Stay Duration</th>
                            <th>Base Total</th>
                            <th>Discount</th>
                            <th>Final Price</th>
                            <th>Effective Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $previewDays = [1, 3, 5, 7, 15, 30];
                        foreach ($previewDays as $days):
                            $baseTotal = $baseRate * $days;
                            $discount = null;
                            foreach ($durationDiscounts as $d) {
                                if ($d['is_active'] && $days >= $d['min_nights']) {
                                    $discount = $d;
                                }
                            }
                            $discountPercent = $discount ? $discount['discount_percent'] : 0;
                            $discountAmount = round($baseTotal * ($discountPercent / 100), 2);
                            $finalPrice = $baseTotal - $discountAmount;
                            $effectiveRate = round($finalPrice / $days, 2);
                        ?>
                        <tr>
                            <td><?php echo $days; ?> night<?php echo $days > 1 ? 's' : ''; ?></td>
                            <td><?php echo CURRENCY_SYMBOL . number_format($baseTotal); ?></td>
                            <td class="<?php echo $discountPercent > 0 ? 'text-success' : ''; ?>">
                                <?php echo $discountPercent > 0 ? '-' . $discountPercent . '%' : '-'; ?>
                            </td>
                            <td><strong><?php echo CURRENCY_SYMBOL . number_format($finalPrice); ?></strong></td>
                            <td class="text-muted"><?php echo CURRENCY_SYMBOL . number_format($effectiveRate); ?>/night</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="text-muted text-center" style="font-size: 0.85rem;">
                    Preview based on <?php echo htmlspecialchars($previewRoom['name']); ?> (<?php echo CURRENCY_SYMBOL . number_format($baseRate); ?>/night)
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<style>
.room-rate-form {
    margin-bottom: 1rem;
}
.room-rate-form:last-child {
    margin-bottom: 0;
}
.form-row {
    display: flex;
    gap: 1rem;
    align-items: flex-end;
}
.align-items-end {
    align-items: flex-end;
}
.table td, .table th {
    padding: 0.5rem;
    vertical-align: middle;
}
.input-group-sm .form-control,
.input-group-sm .input-group-text {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
.mb-3 {
    margin-bottom: 1rem;
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
