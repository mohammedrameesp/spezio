<?php
/**
 * Spezio Apartments Admin - Coupons Management
 */

require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pageTitle = 'Coupons';

$success = '';
$error = '';
$editCoupon = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $data = [
            'code' => strtoupper(trim($_POST['code'] ?? '')),
            'description' => sanitize($_POST['description'] ?? ''),
            'discount_type' => $_POST['discount_type'] ?? 'percentage',
            'discount_value' => (float)($_POST['discount_value'] ?? 0),
            'min_nights' => (int)($_POST['min_nights'] ?? 1),
            'min_amount' => (float)($_POST['min_amount'] ?? 0),
            'max_discount' => $_POST['max_discount'] ? (float)$_POST['max_discount'] : null,
            'valid_from' => $_POST['valid_from'] ?? date('Y-m-d'),
            'valid_until' => $_POST['valid_until'] ?? date('Y-m-d', strtotime('+1 year')),
            'usage_limit' => $_POST['usage_limit'] ? (int)$_POST['usage_limit'] : null,
            'status' => ($_POST['status'] ?? 'active') === 'active' ? 'active' : 'inactive'
        ];

        if (empty($data['code'])) {
            $error = 'Coupon code is required.';
        } elseif ($data['discount_value'] <= 0) {
            $error = 'Discount value must be greater than 0.';
        } else {
            if ($action === 'create') {
                // Check if code exists
                if (dbExists('coupons', 'code = ?', [$data['code']])) {
                    $error = 'Coupon code already exists.';
                } else {
                    $id = dbInsert('coupons', $data);
                    logActivity($_SESSION['admin_id'], 'coupon_created', 'coupons', $id);
                    $success = 'Coupon created successfully.';
                }
            } else {
                $id = (int)($_POST['id'] ?? 0);
                if ($id) {
                    // Check if code exists for other coupons
                    $existing = dbFetchOne("SELECT id FROM coupons WHERE code = ? AND id != ?", [$data['code'], $id]);
                    if ($existing) {
                        $error = 'Coupon code already exists.';
                    } else {
                        dbUpdate('coupons', $data, 'id = ?', ['id' => $id]);
                        logActivity($_SESSION['admin_id'], 'coupon_updated', 'coupons', $id);
                        $success = 'Coupon updated successfully.';
                    }
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            dbDelete('coupons', 'id = ?', [$id]);
            logActivity($_SESSION['admin_id'], 'coupon_deleted', 'coupons', $id);
            $success = 'Coupon deleted.';
        }
    }
}

// Check for edit
if (isset($_GET['edit'])) {
    $editCoupon = dbFetchOne("SELECT * FROM coupons WHERE id = ?", [(int)$_GET['edit']]);
}

// Get coupons
$coupons = dbFetchAll("SELECT * FROM coupons ORDER BY created_at DESC");

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Content -->
<main class="main-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">Coupons</h1>
            <p class="page-subtitle">Manage discount codes</p>
        </div>
        <button onclick="openModal('couponModal')" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Add Coupon
        </button>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Coupons Table -->
    <div class="card">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Discount</th>
                        <th>Conditions</th>
                        <th>Validity</th>
                        <th>Usage</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($coupons)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">No coupons yet</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($coupons as $coupon): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($coupon['code']); ?></strong>
                                    <?php if ($coupon['description']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($coupon['description']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($coupon['discount_type'] === 'percentage'): ?>
                                        <?php echo $coupon['discount_value']; ?>%
                                        <?php if ($coupon['max_discount']): ?>
                                            <br><small class="text-muted">Max: <?php echo formatCurrency($coupon['max_discount']); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php echo formatCurrency($coupon['discount_value']); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($coupon['min_nights'] > 1): ?>
                                        Min <?php echo $coupon['min_nights']; ?> nights<br>
                                    <?php endif; ?>
                                    <?php if ($coupon['min_amount'] > 0): ?>
                                        Min <?php echo formatCurrency($coupon['min_amount']); ?>
                                    <?php endif; ?>
                                    <?php if ($coupon['min_nights'] <= 1 && $coupon['min_amount'] <= 0): ?>
                                        <span class="text-muted">No conditions</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo formatDate($coupon['valid_from'], 'd M Y'); ?><br>
                                    to <?php echo formatDate($coupon['valid_until'], 'd M Y'); ?>
                                </td>
                                <td>
                                    <?php echo $coupon['used_count']; ?>
                                    <?php if ($coupon['usage_limit']): ?>
                                        / <?php echo $coupon['usage_limit']; ?>
                                    <?php else: ?>
                                        <span class="text-muted">/ unlimited</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $coupon['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($coupon['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="?edit=<?php echo $coupon['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this coupon?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $coupon['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Coupon Modal -->
<div class="modal <?php echo $editCoupon ? 'show' : ''; ?>" id="couponModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title"><?php echo $editCoupon ? 'Edit Coupon' : 'Add New Coupon'; ?></h3>
            <button class="modal-close" onclick="closeModal('couponModal'); location.href='coupons.php';">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="<?php echo $editCoupon ? 'update' : 'create'; ?>">
                <?php if ($editCoupon): ?>
                    <input type="hidden" name="id" value="<?php echo $editCoupon['id']; ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Coupon Code *</label>
                        <input type="text" name="code" class="form-control" required
                               style="text-transform: uppercase;"
                               value="<?php echo htmlspecialchars($editCoupon['code'] ?? ''); ?>"
                               placeholder="e.g., WELCOME20">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="active" <?php echo ($editCoupon['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($editCoupon['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control"
                           value="<?php echo htmlspecialchars($editCoupon['description'] ?? ''); ?>"
                           placeholder="e.g., Welcome discount for new customers">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Discount Type *</label>
                        <select name="discount_type" class="form-control" id="discountType">
                            <option value="percentage" <?php echo ($editCoupon['discount_type'] ?? 'percentage') === 'percentage' ? 'selected' : ''; ?>>Percentage (%)</option>
                            <option value="fixed" <?php echo ($editCoupon['discount_type'] ?? '') === 'fixed' ? 'selected' : ''; ?>>Fixed Amount (<?php echo CURRENCY_SYMBOL; ?>)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Discount Value *</label>
                        <input type="number" name="discount_value" class="form-control" required
                               value="<?php echo $editCoupon['discount_value'] ?? ''; ?>" step="0.01" min="0">
                    </div>
                </div>

                <div class="form-group" id="maxDiscountGroup">
                    <label class="form-label">Max Discount (for percentage)</label>
                    <input type="number" name="max_discount" class="form-control"
                           value="<?php echo $editCoupon['max_discount'] ?? ''; ?>" step="0.01" min="0"
                           placeholder="Leave empty for no cap">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Minimum Nights</label>
                        <input type="number" name="min_nights" class="form-control"
                               value="<?php echo $editCoupon['min_nights'] ?? 1; ?>" min="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Minimum Amount</label>
                        <input type="number" name="min_amount" class="form-control"
                               value="<?php echo $editCoupon['min_amount'] ?? 0; ?>" step="0.01" min="0">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Valid From *</label>
                        <input type="date" name="valid_from" class="form-control" required
                               value="<?php echo $editCoupon['valid_from'] ?? date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Valid Until *</label>
                        <input type="date" name="valid_until" class="form-control" required
                               value="<?php echo $editCoupon['valid_until'] ?? date('Y-m-d', strtotime('+1 year')); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Usage Limit</label>
                    <input type="number" name="usage_limit" class="form-control"
                           value="<?php echo $editCoupon['usage_limit'] ?? ''; ?>" min="1"
                           placeholder="Leave empty for unlimited">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('couponModal'); location.href='coupons.php';">Cancel</button>
                <button type="submit" class="btn btn-primary"><?php echo $editCoupon ? 'Update' : 'Create'; ?> Coupon</button>
            </div>
        </form>
    </div>
</div>

<script>
// Toggle max discount field based on discount type
document.getElementById('discountType')?.addEventListener('change', function() {
    document.getElementById('maxDiscountGroup').style.display = this.value === 'percentage' ? 'block' : 'none';
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
