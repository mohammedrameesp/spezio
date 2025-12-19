<?php
/**
 * Spezio Apartments Booking System
 * Email Notification System
 */

require_once __DIR__ . '/config.php';

/**
 * Send email using PHP mail() or SMTP
 */
function sendEmail($to, $subject, $htmlBody, $textBody = null) {
    if (SMTP_ENABLED) {
        return sendSmtpEmail($to, $subject, $htmlBody, $textBody);
    }
    return sendPhpMail($to, $subject, $htmlBody, $textBody);
}

/**
 * Send email using PHP mail()
 */
function sendPhpMail($to, $subject, $htmlBody, $textBody = null) {
    $boundary = md5(time());

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'From: ' . SMTP_FROM_NAME . ' <' . SITE_EMAIL . '>',
        'Reply-To: ' . SITE_EMAIL,
        'X-Mailer: PHP/' . phpversion()
    ];

    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= ($textBody ?: strip_tags($htmlBody)) . "\r\n\r\n";

    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $htmlBody . "\r\n\r\n";

    $body .= "--{$boundary}--";

    return mail($to, $subject, $body, implode("\r\n", $headers));
}

/**
 * Send email using SMTP (basic implementation)
 */
function sendSmtpEmail($to, $subject, $htmlBody, $textBody = null) {
    // For production, consider using PHPMailer or similar library
    // This is a simplified SMTP implementation

    $socket = @fsockopen(
        (SMTP_PORT == 465 ? 'ssl://' : '') . SMTP_HOST,
        SMTP_PORT,
        $errno,
        $errstr,
        30
    );

    if (!$socket) {
        error_log("SMTP connection failed: {$errno} - {$errstr}");
        return false;
    }

    $response = fgets($socket, 515);

    // EHLO
    fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
    $response = fgets($socket, 515);

    // STARTTLS for port 587
    if (SMTP_PORT == 587) {
        fputs($socket, "STARTTLS\r\n");
        $response = fgets($socket, 515);
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
        $response = fgets($socket, 515);
    }

    // AUTH LOGIN
    fputs($socket, "AUTH LOGIN\r\n");
    $response = fgets($socket, 515);

    fputs($socket, base64_encode(SMTP_USER) . "\r\n");
    $response = fgets($socket, 515);

    fputs($socket, base64_encode(SMTP_PASS) . "\r\n");
    $response = fgets($socket, 515);

    // MAIL FROM
    fputs($socket, "MAIL FROM: <" . SMTP_USER . ">\r\n");
    $response = fgets($socket, 515);

    // RCPT TO
    fputs($socket, "RCPT TO: <{$to}>\r\n");
    $response = fgets($socket, 515);

    // DATA
    fputs($socket, "DATA\r\n");
    $response = fgets($socket, 515);

    // Headers and body
    $boundary = md5(time());
    $message = "From: " . SMTP_FROM_NAME . " <" . SMTP_USER . ">\r\n";
    $message .= "To: {$to}\r\n";
    $message .= "Subject: {$subject}\r\n";
    $message .= "MIME-Version: 1.0\r\n";
    $message .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n";

    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $message .= ($textBody ?: strip_tags($htmlBody)) . "\r\n\r\n";

    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $message .= $htmlBody . "\r\n\r\n";

    $message .= "--{$boundary}--\r\n";
    $message .= ".\r\n";

    fputs($socket, $message);
    $response = fgets($socket, 515);

    // QUIT
    fputs($socket, "QUIT\r\n");
    fclose($socket);

    return strpos($response, '250') !== false;
}

/**
 * Send booking confirmation email to guest
 */
function sendBookingConfirmationEmail($booking) {
    $subject = "Booking Confirmed - " . $booking['booking_id'] . " | " . SITE_NAME;

    $html = getEmailTemplate('booking_confirmation', [
        'guest_name' => $booking['guest_name'],
        'booking_id' => $booking['booking_id'],
        'room_name' => $booking['room_name'],
        'check_in' => formatDate($booking['check_in'], 'l, d M Y'),
        'check_out' => formatDate($booking['check_out'], 'l, d M Y'),
        'check_in_time' => CHECK_IN_TIME,
        'check_out_time' => CHECK_OUT_TIME,
        'num_guests' => $booking['num_guests'],
        'total_nights' => $booking['total_nights'],
        'pricing_tier' => ucfirst($booking['pricing_tier']),
        'rate_per_night' => formatCurrency($booking['rate_per_night']),
        'subtotal' => formatCurrency($booking['subtotal']),
        'discount_amount' => $booking['discount_amount'] > 0 ? formatCurrency($booking['discount_amount']) : null,
        'coupon_code' => $booking['coupon_code'],
        'total_amount' => formatCurrency($booking['total_amount']),
        'special_requests' => $booking['special_requests'],
        'site_name' => SITE_NAME,
        'site_phone' => SITE_PHONE,
        'site_email' => SITE_EMAIL,
        'whatsapp_link' => 'https://wa.me/' . WHATSAPP_NUMBER
    ]);

    return sendEmail($booking['guest_email'], $subject, $html);
}

/**
 * Send new booking notification to admin
 */
function sendAdminNotificationEmail($booking) {
    $subject = "New Booking - " . $booking['booking_id'] . " | " . SITE_NAME;

    $html = getEmailTemplate('admin_notification', [
        'booking_id' => $booking['booking_id'],
        'guest_name' => $booking['guest_name'],
        'guest_email' => $booking['guest_email'],
        'guest_phone' => $booking['guest_phone'],
        'room_name' => $booking['room_name'],
        'check_in' => formatDate($booking['check_in'], 'l, d M Y'),
        'check_out' => formatDate($booking['check_out'], 'l, d M Y'),
        'num_guests' => $booking['num_guests'],
        'total_nights' => $booking['total_nights'],
        'total_amount' => formatCurrency($booking['total_amount']),
        'coupon_code' => $booking['coupon_code'],
        'discount_amount' => $booking['discount_amount'] > 0 ? formatCurrency($booking['discount_amount']) : null,
        'special_requests' => $booking['special_requests'],
        'payment_id' => $booking['razorpay_payment_id'],
        'admin_url' => SITE_URL . '/admin/booking-view.php?id=' . $booking['id']
    ]);

    $adminEmail = getSetting('notification_email', BOOKING_EMAIL);
    return sendEmail($adminEmail, $subject, $html);
}

/**
 * Get email template
 */
function getEmailTemplate($template, $data) {
    $baseStyle = '
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #00443F; color: white; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px 20px; background: #ffffff; }
        .booking-details { background: #f9f9f9; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #666; }
        .detail-value { font-weight: bold; color: #333; }
        .total-row { background: #00443F; color: white; padding: 15px; border-radius: 5px; margin-top: 15px; }
        .total-row .detail-label, .total-row .detail-value { color: white; }
        .btn { display: inline-block; background: #CC9933; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 14px; color: #666; }
        .footer a { color: #00443F; }
        .discount { color: #27ae60; }
        .alert { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 5px; margin: 15px 0; }
    ';

    switch ($template) {
        case 'booking_confirmation':
            return '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <style>' . $baseStyle . '</style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>' . $data['site_name'] . '</h1>
                        <p style="margin: 10px 0 0;">Booking Confirmation</p>
                    </div>
                    <div class="content">
                        <p>Dear <strong>' . $data['guest_name'] . '</strong>,</p>
                        <p>Thank you for your booking! Your reservation has been confirmed.</p>

                        <div class="booking-details">
                            <h3 style="margin-top: 0; color: #00443F;">Booking Details</h3>
                            <div class="detail-row">
                                <span class="detail-label">Booking ID</span>
                                <span class="detail-value">' . $data['booking_id'] . '</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Room</span>
                                <span class="detail-value">' . $data['room_name'] . '</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Check-in</span>
                                <span class="detail-value">' . $data['check_in'] . ' (from ' . $data['check_in_time'] . ')</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Check-out</span>
                                <span class="detail-value">' . $data['check_out'] . ' (by ' . $data['check_out_time'] . ')</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Guests</span>
                                <span class="detail-value">' . $data['num_guests'] . '</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Duration</span>
                                <span class="detail-value">' . $data['total_nights'] . ' night(s) (' . $data['pricing_tier'] . ' rate)</span>
                            </div>
                        </div>

                        <div class="booking-details">
                            <h3 style="margin-top: 0; color: #00443F;">Payment Summary</h3>
                            <div class="detail-row">
                                <span class="detail-label">' . $data['total_nights'] . ' night(s) x ' . $data['rate_per_night'] . '</span>
                                <span class="detail-value">' . $data['subtotal'] . '</span>
                            </div>
                            ' . ($data['discount_amount'] ? '
                            <div class="detail-row">
                                <span class="detail-label discount">Coupon: ' . $data['coupon_code'] . '</span>
                                <span class="detail-value discount">-' . $data['discount_amount'] . '</span>
                            </div>' : '') . '
                            <div class="total-row">
                                <div class="detail-row" style="border: none; margin: 0; padding: 0;">
                                    <span class="detail-label">Total Paid</span>
                                    <span class="detail-value">' . $data['total_amount'] . '</span>
                                </div>
                            </div>
                        </div>

                        ' . ($data['special_requests'] ? '
                        <div class="alert">
                            <strong>Special Requests:</strong><br>
                            ' . nl2br(htmlspecialchars($data['special_requests'])) . '
                        </div>' : '') . '

                        <p>If you have any questions, feel free to contact us:</p>
                        <p style="text-align: center;">
                            <a href="tel:' . $data['site_phone'] . '" class="btn">Call Us</a>
                            <a href="' . $data['whatsapp_link'] . '" class="btn" style="background: #25D366;">WhatsApp</a>
                        </p>
                    </div>
                    <div class="footer">
                        <p><strong>' . $data['site_name'] . '</strong></p>
                        <p>S K Line, Bypass Jn, Perinthalmanna, Kerala - 679322</p>
                        <p>
                            <a href="mailto:' . $data['site_email'] . '">' . $data['site_email'] . '</a> |
                            <a href="tel:' . $data['site_phone'] . '">' . $data['site_phone'] . '</a>
                        </p>
                    </div>
                </div>
            </body>
            </html>';

        case 'admin_notification':
            return '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>' . $baseStyle . '</style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>New Booking Received</h1>
                    </div>
                    <div class="content">
                        <p>A new booking has been confirmed.</p>

                        <div class="booking-details">
                            <h3 style="margin-top: 0;">Booking Information</h3>
                            <div class="detail-row">
                                <span class="detail-label">Booking ID</span>
                                <span class="detail-value">' . $data['booking_id'] . '</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Room</span>
                                <span class="detail-value">' . $data['room_name'] . '</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Check-in</span>
                                <span class="detail-value">' . $data['check_in'] . '</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Check-out</span>
                                <span class="detail-value">' . $data['check_out'] . '</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Nights</span>
                                <span class="detail-value">' . $data['total_nights'] . '</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Guests</span>
                                <span class="detail-value">' . $data['num_guests'] . '</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Amount</span>
                                <span class="detail-value">' . $data['total_amount'] . '</span>
                            </div>
                            ' . ($data['discount_amount'] ? '
                            <div class="detail-row">
                                <span class="detail-label">Coupon Used</span>
                                <span class="detail-value">' . $data['coupon_code'] . ' (-' . $data['discount_amount'] . ')</span>
                            </div>' : '') . '
                            <div class="detail-row">
                                <span class="detail-label">Payment ID</span>
                                <span class="detail-value">' . $data['payment_id'] . '</span>
                            </div>
                        </div>

                        <div class="booking-details">
                            <h3 style="margin-top: 0;">Guest Details</h3>
                            <div class="detail-row">
                                <span class="detail-label">Name</span>
                                <span class="detail-value">' . $data['guest_name'] . '</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Email</span>
                                <span class="detail-value">' . $data['guest_email'] . '</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Phone</span>
                                <span class="detail-value">' . $data['guest_phone'] . '</span>
                            </div>
                        </div>

                        ' . ($data['special_requests'] ? '
                        <div class="alert">
                            <strong>Special Requests:</strong><br>
                            ' . nl2br(htmlspecialchars($data['special_requests'])) . '
                        </div>' : '') . '

                        <p style="text-align: center;">
                            <a href="' . $data['admin_url'] . '" class="btn">View Booking in Admin</a>
                        </p>
                    </div>
                </div>
            </body>
            </html>';

        default:
            return '';
    }
}
