<?php
/**
 * Spezio Apartments Admin - Settings
 */

require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pageTitle = 'Settings';

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_settings') {
        $settings = [
            'site_name' => sanitize($_POST['site_name'] ?? ''),
            'site_email' => sanitize($_POST['site_email'] ?? ''),
            'site_phone' => sanitize($_POST['site_phone'] ?? ''),
            'site_address' => sanitize($_POST['site_address'] ?? ''),
            'whatsapp_number' => sanitize($_POST['whatsapp_number'] ?? ''),
            'check_in_time' => sanitize($_POST['check_in_time'] ?? '14:00'),
            'check_out_time' => sanitize($_POST['check_out_time'] ?? '12:00'),
            'notification_email' => sanitize($_POST['notification_email'] ?? '')
        ];

        foreach ($settings as $key => $value) {
            updateSetting($key, $value);
        }

        logActivity($_SESSION['admin_id'], 'settings_updated', 'settings', null);
        $success = 'Settings updated successfully.';
    } elseif ($action === 'update_booking_settings') {
        $settings = [
            'extra_bed_price' => (float) ($_POST['extra_bed_price'] ?? 600),
            'min_booking_days' => (int) ($_POST['min_booking_days'] ?? 1),
            'max_booking_days' => (int) ($_POST['max_booking_days'] ?? 90)
        ];

        foreach ($settings as $key => $value) {
            updateSetting($key, $value);
        }

        logActivity($_SESSION['admin_id'], 'booking_settings_updated', 'settings', null);
        $success = 'Booking settings updated successfully.';
    } elseif ($action === 'update_razorpay') {
        $settings = [
            'razorpay_key_id' => sanitize($_POST['razorpay_key_id'] ?? ''),
            'razorpay_key_secret' => sanitize($_POST['razorpay_key_secret'] ?? ''),
            'razorpay_webhook_secret' => sanitize($_POST['razorpay_webhook_secret'] ?? '')
        ];

        foreach ($settings as $key => $value) {
            if (!empty($value)) {
                updateSetting($key, $value);
            }
        }

        logActivity($_SESSION['admin_id'], 'razorpay_settings_updated', 'settings', null);
        $success = 'Razorpay settings updated.';
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword)) {
            $error = 'Please fill in all password fields.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            $result = changePassword($_SESSION['admin_id'], $currentPassword, $newPassword);
            if ($result['success']) {
                $success = 'Password changed successfully.';
            } else {
                $error = $result['error'];
            }
        }
    }
}

// Get current settings
$siteName = getSetting('site_name', 'Spezio Apartments');
$siteEmail = getSetting('site_email', '');
$sitePhone = getSetting('site_phone', '');
$siteAddress = getSetting('site_address', '');
$whatsappNumber = getSetting('whatsapp_number', '');
$checkInTime = getSetting('check_in_time', '14:00');
$checkOutTime = getSetting('check_out_time', '12:00');
$notificationEmail = getSetting('notification_email', '');
$razorpayKeyId = getSetting('razorpay_key_id', '');
$extraBedPrice = getSetting('extra_bed_price', '600');
$minBookingDays = getSetting('min_booking_days', '1');
$maxBookingDays = getSetting('max_booking_days', '90');

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<!-- Main Content -->
<main class="main-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">Settings</h1>
            <p class="page-subtitle">Configure your booking system</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="grid-2">
        <!-- General Settings -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">General Settings</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_settings">

                    <div class="form-group">
                        <label class="form-label">Site Name</label>
                        <input type="text" name="site_name" class="form-control"
                               value="<?php echo htmlspecialchars($siteName); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contact Email</label>
                        <input type="email" name="site_email" class="form-control"
                               value="<?php echo htmlspecialchars($siteEmail); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contact Phone</label>
                        <input type="text" name="site_phone" class="form-control"
                               value="<?php echo htmlspecialchars($sitePhone); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">WhatsApp Number</label>
                        <input type="text" name="whatsapp_number" class="form-control"
                               value="<?php echo htmlspecialchars($whatsappNumber); ?>"
                               placeholder="e.g., 919876543210">
                        <p class="form-text">Include country code without + sign</p>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <textarea name="site_address" class="form-control" rows="2"><?php echo htmlspecialchars($siteAddress); ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Check-in Time</label>
                            <input type="time" name="check_in_time" class="form-control"
                                   value="<?php echo htmlspecialchars($checkInTime); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Check-out Time</label>
                            <input type="time" name="check_out_time" class="form-control"
                                   value="<?php echo htmlspecialchars($checkOutTime); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Notification Email</label>
                        <input type="email" name="notification_email" class="form-control"
                               value="<?php echo htmlspecialchars($notificationEmail); ?>"
                               placeholder="Email for new booking alerts">
                    </div>

                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </form>
            </div>
        </div>

        <!-- Booking Settings -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Booking Settings</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_booking_settings">

                    <div class="form-group">
                        <label class="form-label">Extra Bed Price (per night)</label>
                        <div class="input-group">
                            <span class="input-group-text"><?php echo CURRENCY_SYMBOL; ?></span>
                            <input type="number" name="extra_bed_price" class="form-control"
                                   value="<?php echo htmlspecialchars($extraBedPrice); ?>"
                                   min="0" step="100">
                        </div>
                        <p class="form-text">Additional charge per night when guest requests an extra bed</p>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Minimum Booking Days</label>
                            <input type="number" name="min_booking_days" class="form-control"
                                   value="<?php echo htmlspecialchars($minBookingDays); ?>"
                                   min="1" max="30">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Maximum Booking Days</label>
                            <input type="number" name="max_booking_days" class="form-control"
                                   value="<?php echo htmlspecialchars($maxBookingDays); ?>"
                                   min="1" max="365">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Booking Settings</button>
                </form>
            </div>
        </div>

        <!-- Razorpay Settings -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Razorpay Settings</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_razorpay">

                    <div class="alert alert-info">
                        Get your API keys from <a href="https://dashboard.razorpay.com/app/keys" target="_blank">Razorpay Dashboard</a>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Key ID</label>
                        <input type="text" name="razorpay_key_id" class="form-control"
                               value="<?php echo htmlspecialchars($razorpayKeyId); ?>"
                               placeholder="rzp_test_xxxxx or rzp_live_xxxxx">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Key Secret</label>
                        <input type="password" name="razorpay_key_secret" class="form-control"
                               placeholder="Enter new secret to update">
                        <p class="form-text">Leave empty to keep existing secret</p>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Webhook Secret</label>
                        <input type="password" name="razorpay_webhook_secret" class="form-control"
                               placeholder="Enter new webhook secret to update">
                        <p class="form-text">Configure webhook URL: <?php echo SITE_URL; ?>/api/webhook.php</p>
                    </div>

                    <button type="submit" class="btn btn-primary">Update Razorpay Settings</button>
                </form>
            </div>
        </div>

        <!-- Change Password -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Change Password</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required
                               minlength="8">
                        <p class="form-text">Minimum 8 characters</p>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Change Password</button>
                </form>
            </div>
        </div>

        <!-- System Info -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">System Information</h3>
            </div>
            <div class="card-body">
                <table style="width: 100%;">
                    <tr>
                        <td class="text-muted" style="padding: 0.5rem 0;">PHP Version</td>
                        <td style="padding: 0.5rem 0;"><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="padding: 0.5rem 0;">Server</td>
                        <td style="padding: 0.5rem 0;"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="padding: 0.5rem 0;">Database</td>
                        <td style="padding: 0.5rem 0;">MySQL</td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="padding: 0.5rem 0;">Timezone</td>
                        <td style="padding: 0.5rem 0;"><?php echo date_default_timezone_get(); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="padding: 0.5rem 0;">Current Time</td>
                        <td style="padding: 0.5rem 0;"><?php echo date('Y-m-d H:i:s'); ?></td>
                    </tr>
                </table>

                <hr style="margin: 1rem 0;">

                <p class="text-muted text-center" style="font-size: 0.8rem;">
                    Spezio Booking System v1.0<br>
                    &copy; <?php echo date('Y'); ?> Spezio Apartments
                </p>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
