<?php
/**
 * Simple SMTP Mailer
 * Lightweight SMTP email sender without external dependencies
 */

class SMTPMailer {
    private $host;
    private $port;
    private $username;
    private $password;
    private $encryption;
    private $socket;
    private $timeout = 30;
    private $debug = false;
    private $lastError = '';

    public function __construct($host, $port, $username, $password, $encryption = 'tls') {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->encryption = $encryption;
    }

    public function setDebug($debug) {
        $this->debug = $debug;
    }

    public function getLastError() {
        return $this->lastError;
    }

    public function send($from, $fromName, $to, $subject, $body, $isHtml = false, $replyTo = null) {
        try {
            // Connect to SMTP server
            $this->connect();

            // Send EHLO
            $this->sendCommand("EHLO " . gethostname(), 250);

            // Start TLS if required
            if ($this->encryption === 'tls') {
                $this->sendCommand("STARTTLS", 220);
                stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->sendCommand("EHLO " . gethostname(), 250);
            }

            // Authenticate
            $this->sendCommand("AUTH LOGIN", 334);
            $this->sendCommand(base64_encode($this->username), 334);
            $this->sendCommand(base64_encode($this->password), 235);

            // Set sender
            $this->sendCommand("MAIL FROM:<{$from}>", 250);

            // Set recipient
            $this->sendCommand("RCPT TO:<{$to}>", 250);

            // Send data
            $this->sendCommand("DATA", 354);

            // Build message
            $headers = [
                "From: {$fromName} <{$from}>",
                "To: {$to}",
                "Subject: {$subject}",
                "MIME-Version: 1.0",
                "Content-Type: " . ($isHtml ? "text/html" : "text/plain") . "; charset=UTF-8",
                "Date: " . date("r"),
                "Message-ID: <" . uniqid() . "@" . $this->host . ">"
            ];

            // Add Reply-To header if provided
            if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $headers[] = "Reply-To: {$replyTo}";
            }

            $message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
            $this->sendCommand($message, 250);

            // Quit
            $this->sendCommand("QUIT", 221);

            fclose($this->socket);
            return true;

        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            if ($this->socket) {
                fclose($this->socket);
            }
            return false;
        }
    }

    private function connect() {
        $host = $this->encryption === 'ssl' ? "ssl://{$this->host}" : $this->host;

        $this->socket = @fsockopen($host, $this->port, $errno, $errstr, $this->timeout);

        if (!$this->socket) {
            throw new Exception("Could not connect to SMTP server: {$errstr} ({$errno})");
        }

        stream_set_timeout($this->socket, $this->timeout);

        $response = $this->getResponse();
        if (substr($response, 0, 3) !== '220') {
            throw new Exception("SMTP server not ready: {$response}");
        }
    }

    private function sendCommand($command, $expectedCode) {
        if ($this->debug) {
            echo "C: " . (strpos($command, 'AUTH') !== false ? '[AUTH DATA]' : $command) . "\n";
        }

        fwrite($this->socket, $command . "\r\n");
        $response = $this->getResponse();

        if ($this->debug) {
            echo "S: {$response}\n";
        }

        $code = (int)substr($response, 0, 3);

        if ($code !== $expectedCode) {
            throw new Exception("SMTP error: Expected {$expectedCode}, got {$code}. Response: {$response}");
        }

        return $response;
    }

    private function getResponse() {
        $response = '';
        while ($line = fgets($this->socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        return trim($response);
    }
}

/**
 * Send email using configured SMTP
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $body Email body
 * @param bool $isHtml Whether body is HTML
 * @param string|null $replyTo Reply-To email address
 */
function sendEmail($to, $subject, $body, $isHtml = false, $replyTo = null) {
    require_once __DIR__ . '/email-config.php';

    if (!SMTP_ENABLED || empty(SMTP_PASSWORD)) {
        // Fallback to PHP mail() if SMTP not configured
        $headers = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
        if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers .= "Reply-To: {$replyTo}\r\n";
        }
        $headers .= "Content-Type: " . ($isHtml ? "text/html" : "text/plain") . "; charset=UTF-8\r\n";
        return @mail($to, $subject, $body, $headers);
    }

    $mailer = new SMTPMailer(
        SMTP_HOST,
        SMTP_PORT,
        SMTP_USERNAME,
        SMTP_PASSWORD,
        SMTP_ENCRYPTION
    );

    $result = $mailer->send(
        FROM_EMAIL,
        FROM_NAME,
        $to,
        $subject,
        $body,
        $isHtml,
        $replyTo
    );

    if (!$result) {
        error_log("SMTP Error: " . $mailer->getLastError());
    }

    return $result;
}
