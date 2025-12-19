<?php
/**
 * Contact Form Handler
 * Stores messages in database and sends email notification
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once 'db.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// If not JSON, try regular POST
if (!$data) {
    $data = $_POST;
}

// Validate required fields
$name = trim($data['name'] ?? '');
$phone = trim($data['phone'] ?? '');
$message = trim($data['message'] ?? '');
$email = trim($data['email'] ?? '');

if (empty($name) || empty($phone) || empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Name, phone, and message are required']);
    exit;
}

// Sanitize inputs
$name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$phone = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
$email = filter_var($email, FILTER_SANITIZE_EMAIL);

// Validate phone format (basic)
$phone = preg_replace('/[^0-9+\-\s]/', '', $phone);

try {
    $db = getDB();

    // Create table if not exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS contact_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            email VARCHAR(100),
            message TEXT NOT NULL,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_read TINYINT(1) DEFAULT 0
        )
    ");

    // Insert message
    $stmt = $db->prepare("
        INSERT INTO contact_messages (name, phone, email, message, ip_address)
        VALUES (:name, :phone, :email, :message, :ip)
    ");

    $stmt->execute([
        ':name' => $name,
        ':phone' => $phone,
        ':email' => $email,
        ':message' => $message,
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    $messageId = $db->lastInsertId();

    // Send email notification (optional - configure SMTP in config.php)
    $emailSent = sendEmailNotification($name, $phone, $email, $message, $messageId);

    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your message has been received. We will contact you soon.',
        'id' => $messageId
    ]);

} catch (Exception $e) {
    error_log('Contact form error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Sorry, there was an error. Please try again or call us directly.']);
}

/**
 * Send email notification to admin
 */
function sendEmailNotification($name, $phone, $email, $message, $id) {
    // Admin email - change this to your email
    $adminEmail = 'spezioapartments@gmail.com';

    $subject = "New Contact Form Message - Spezio Apartments (#$id)";

    $body = "
New message from Spezio Apartments website:

Name: $name
Phone: $phone
Email: $email

Message:
$message

---
Received: " . date('Y-m-d H:i:s') . "
IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "
    ";

    $headers = [
        'From: noreply@spezioapartments.com',
        'Reply-To: ' . ($email ?: 'noreply@spezioapartments.com'),
        'X-Mailer: PHP/' . phpversion()
    ];

    // Try to send email (may fail if mail server not configured)
    return @mail($adminEmail, $subject, $body, implode("\r\n", $headers));
}
