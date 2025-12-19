<?php
/**
 * Spezio Apartments Admin - Rooms Management
 */

require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pageTitle = 'Rooms';

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'update' && $id) {
        $data = [
            'name' => sanitize($_POST['name'] ?? ''),
            'description' => sanitize($_POST['description'] ?? ''),
            'price_daily' => (float)($_POST['price_daily'] ?? 0),
            'price_weekly' => (float)($_POST['price_weekly'] ?? 0),
            'price_monthly' => (float)($_POST['price_monthly'] ?? 0),
            'max_guests' => (int)($_POST['max_guests'] ?? 2),
            'bedrooms' => (int)($_POST['bedrooms'] ?? 1),
            'bathrooms' => (int)($_POST['bathrooms'] ?? 1),
            'status' => ($_POST['status'] ?? 'active') === 'active' ? 'active' : 'inactive'
        ];

        // Handle amenities
        if (isset($_POST['amenities'])) {
            $amenities = array_filter(array_map('trim', explode(',', $_POST['amenities'])));
            $data['amenities'] = json_encode($amenities);
        }

        dbUpdate('rooms', $data, 'id = ?', ['id' => $id]);
        logActivity($_SESSION['admin_id'], 'room_updated', 'rooms', $id);
        $success = 'Room updated successfully.';
    }
}

// Get rooms
$rooms = dbFetchAll("SELECT * FROM rooms ORDER BY display_order ASC");

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Content -->
<main class="main-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">Rooms</h1>
            <p class="page-subtitle">Manage room details and pricing</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Rooms List -->
    <?php foreach ($rooms as $room): ?>
        <?php $amenities = json_decode($room['amenities'], true) ?: []; ?>
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title"><?php echo htmlspecialchars($room['name']); ?></h3>
                <span class="badge badge-<?php echo $room['status'] === 'active' ? 'success' : 'secondary'; ?>">
                    <?php echo ucfirst($room['status']); ?>
                </span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?php echo $room['id']; ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Room Name</label>
                            <input type="text" name="name" class="form-control"
                                   value="<?php echo htmlspecialchars($room['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="active" <?php echo $room['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $room['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($room['description']); ?></textarea>
                    </div>

                    <div style="background: var(--bg); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <h4 style="margin-bottom: 1rem; font-size: 0.9rem; color: var(--primary);">Tiered Pricing (Per Night)</h4>
                        <div class="form-row" style="grid-template-columns: repeat(3, 1fr);">
                            <div class="form-group mb-0">
                                <label class="form-label">Daily Rate (1-6 nights)</label>
                                <div style="display: flex; align-items: center;">
                                    <span style="padding: 0.625rem; background: var(--border); border-radius: 8px 0 0 8px;"><?php echo CURRENCY_SYMBOL; ?></span>
                                    <input type="number" name="price_daily" class="form-control" style="border-radius: 0 8px 8px 0;"
                                           value="<?php echo $room['price_daily']; ?>" step="0.01" required>
                                </div>
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label">Weekly Rate (7-29 nights)</label>
                                <div style="display: flex; align-items: center;">
                                    <span style="padding: 0.625rem; background: var(--border); border-radius: 8px 0 0 8px;"><?php echo CURRENCY_SYMBOL; ?></span>
                                    <input type="number" name="price_weekly" class="form-control" style="border-radius: 0 8px 8px 0;"
                                           value="<?php echo $room['price_weekly']; ?>" step="0.01" required>
                                </div>
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label">Monthly Rate (30+ nights)</label>
                                <div style="display: flex; align-items: center;">
                                    <span style="padding: 0.625rem; background: var(--border); border-radius: 8px 0 0 8px;"><?php echo CURRENCY_SYMBOL; ?></span>
                                    <input type="number" name="price_monthly" class="form-control" style="border-radius: 0 8px 8px 0;"
                                           value="<?php echo $room['price_monthly']; ?>" step="0.01" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row" style="grid-template-columns: repeat(3, 1fr);">
                        <div class="form-group">
                            <label class="form-label">Max Guests</label>
                            <input type="number" name="max_guests" class="form-control"
                                   value="<?php echo $room['max_guests']; ?>" min="1" max="10" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Bedrooms</label>
                            <input type="number" name="bedrooms" class="form-control"
                                   value="<?php echo $room['bedrooms']; ?>" min="1" max="10">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Bathrooms</label>
                            <input type="number" name="bathrooms" class="form-control"
                                   value="<?php echo $room['bathrooms']; ?>" min="1" max="10">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Amenities (comma-separated)</label>
                        <input type="text" name="amenities" class="form-control"
                               value="<?php echo htmlspecialchars(implode(', ', $amenities)); ?>"
                               placeholder="Air Conditioning, Free WiFi, Smart TV, Kitchen...">
                    </div>

                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
