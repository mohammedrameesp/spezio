<?php
/**
 * Email Configuration - SAMPLE FILE
 *
 * Copy this file to email-config.php and update with your credentials
 *
 * SETUP INSTRUCTIONS FOR GMAIL:
 *
 * 1. Go to https://myaccount.google.com/security
 * 2. Enable 2-Factor Authentication (required)
 * 3. Go to https://myaccount.google.com/apppasswords
 * 4. Select "Mail" and your device
 * 5. Click "Generate" and copy the 16-character password
 * 6. Paste it below (without spaces)
 */

// Email Settings
define('SMTP_ENABLED', true);  // Set to true to enable SMTP

// Gmail SMTP Settings
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');  // Your Gmail address
define('SMTP_PASSWORD', 'xxxx xxxx xxxx xxxx');   // Your Gmail App Password
define('SMTP_ENCRYPTION', 'tls');

// Email addresses
define('ADMIN_EMAIL', 'your-email@gmail.com');    // Where to receive notifications
define('FROM_EMAIL', 'your-email@gmail.com');     // Sender email
define('FROM_NAME', 'Spezio Apartments');         // Sender name
