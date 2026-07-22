<?php
/**
 * SmtpMailer — a small, dependency-free SMTP client for sending the OTP
 * email. No composer/vendor directory required: this is the whole thing.
 *
 * In MAIL_DEV_MODE (or when SMTP_HOST is blank), send() writes the message
 * to logs/otp_dev.log instead of contacting a real mail server, so the
 * login flow can be fully exercised on a local XAMPP/WAMP/MAMP install
 * without any mail provider configured.
 */
class SmtpMailer
{
    /**
     * @throws Exception on delivery failure (only relevant outside dev mode)
     */
    public static function send(string $toEmail, string $toName, string $subject, string $body): void
    {
        if (MAIL_DEV_MODE || SMTP_HOST === '') {
            self::writeDevLog($toEmail, $subject, $body);
            return;
        }

        $socket = self::connect(SMTP_HOST, SMTP_PORT, SMTP_SECURE);

        try {
            self::expect($socket, '220');
            self::command($socket, 'EHLO localhost', '250');

            if (SMTP_SECURE === 'tls') {
                self::command($socket, 'STARTTLS', '220');
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception('STARTTLS negotiation failed.');
                }
                self::command($socket, 'EHLO localhost', '250');
            }

            if (SMTP_USERNAME !== '') {
                self::command($socket, 'AUTH LOGIN', '334');
                self::command($socket, base64_encode(SMTP_USERNAME), '334');
                self::command($socket, base64_encode(SMTP_PASSWORD), '235');
            }

            self::command($socket, 'MAIL FROM:<' . MAIL_FROM_EMAIL . '>', '250');
            self::command($socket, 'RCPT TO:<' . $toEmail . '>', '250');
            self::command($socket, 'DATA', '354');

            $headers = [
                'From: ' . self::encodeHeader(MAIL_FROM_NAME) . ' <' . MAIL_FROM_EMAIL . '>',
                'To: ' . self::encodeHeader($toName) . ' <' . $toEmail . '>',
                'Subject: ' . self::encodeHeader($subject),
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Date: ' . date('r'),
            ];
            $data = implode("\r\n", $headers) . "\r\n\r\n" . self::stuffDots($body) . "\r\n.";
            self::raw($socket, $data);
            self::expect($socket, '250');

            self::command($socket, 'QUIT', '221');
        } finally {
            fclose($socket);
        }
    }

    private static function connect(string $host, int $port, string $secure)
    {
        $target = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $context = stream_context_create();
        $socket = @stream_socket_client($target, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            throw new Exception("Could not connect to SMTP server ($errstr).");
        }
        stream_set_timeout($socket, 15);
        return $socket;
    }

    private static function raw($socket, string $line): void
    {
        fwrite($socket, $line . "\r\n");
    }

    private static function command($socket, string $line, string $expectCode): string
    {
        self::raw($socket, $line);
        return self::expect($socket, $expectCode);
    }

    private static function expect($socket, string $expectCode): string
    {
        $response = '';
        while (!feof($socket)) {
            $line = fgets($socket, 515);
            if ($line === false) {
                break;
            }
            $response .= $line;
            // Multiline responses look like "250-..." with a final "250 ..."
            if (preg_match('/^\d{3} /', $line)) {
                break;
            }
        }
        if (substr($response, 0, 3) !== $expectCode) {
            throw new Exception('Unexpected SMTP response: ' . trim($response));
        }
        return $response;
    }

    /** RFC 5321: lines starting with '.' must be escaped with an extra leading '.'. */
    private static function stuffDots(string $body): string
    {
        return preg_replace('/^\./m', '..', $body);
    }

    private static function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function writeDevLog(string $toEmail, string $subject, string $body): void
    {
        $dir = __DIR__ . '/../../logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $entry = "==================== " . date('Y-m-d H:i:s') . " ====================\n"
            . "To: $toEmail\nSubject: $subject\n\n$body\n\n";
        @file_put_contents($dir . '/otp_dev.log', $entry, FILE_APPEND);
    }
}
