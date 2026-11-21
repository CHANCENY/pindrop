<?php

declare(strict_types=1);

namespace Simp\Pindrop\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Psr\Container\ContainerInterface;
use Simp\Pindrop\Logger\LoggerInterface;
use Simp\Pindrop\Services\EnvServiceProvider;

/**
 * Mail Manager
 *
 * Service for sending emails using PHPMailer with SMTP configuration.
 * Supports HTML emails, attachments, logging and an optional local copy
 * of every mail (see LocalMailServer) for development.
 *
 * Env flags:
 *  - EMAIL_KEEP_COPY  : save a copy of every outgoing mail locally
 *  - EMAIL_ONLY_LOCAL : save locally and do NOT contact SMTP (implies keep copy)
 *
 * Optional config key 'attachment_base_dirs' (string[]): when non-empty,
 * attachments must live inside one of these directories.
 */
class MailManager
{
    private PHPMailer $mailer;
    private ?LoggerInterface $logger;
    private EnvServiceProvider $envProvider;
    private ?LocalMailServer $localMailServer;
    private array $config;
    private bool $connected = false;
    private string $lastError = '';

    public function __construct(
        array $config = [],
        ?LoggerInterface $logger = null,
        ?EnvServiceProvider $envProvider = null,
        ?ContainerInterface $container = null,
        ?LocalMailServer $localMailServer = null
    ) {
        $this->logger = $logger;
        $this->envProvider = $envProvider ?? EnvServiceProvider::getInstance();
        $this->localMailServer = $localMailServer;
        $this->config = array_merge($this->getDefaultConfig(), $config);

        // If a container is provided, get missing services from it
        if ($container !== null) {
            if ($this->logger === null && $container->has('logger')) {
                $this->logger = $container->get('logger');
            }
            if ($this->localMailServer === null && $container->has(LocalMailServer::class)) {
                $this->localMailServer = $container->get(LocalMailServer::class);
            }
        }

        $this->initializeMailer();
    }

    /**
     * Get default configuration from environment
     */
    private function getDefaultConfig(): array
    {
        return [
            'host' => $this->envProvider->get('SMTP_HOST', 'localhost'),
            'port' => (int) $this->envProvider->get('SMTP_PORT', '587'),
            'username' => $this->envProvider->get('SMTP_USER', ''),
            'password' => $this->envProvider->get('SMTP_PASS', ''),
            'secure' => $this->envProvider->get('SMTP_SECURE', 'tls'),
            'from_email' => $this->envProvider->get('MAIL_FROM_EMAIL', ''),
            'from_name' => $this->envProvider->get('MAIL_FROM_NAME', 'Application'),
            'debug' => $this->toBool($this->envProvider->get('SMTP_DEBUG', 'false')),
            'keep_copy' => $this->toBool($this->envProvider->get('EMAIL_KEEP_COPY', 'false')),
            'only_local' => $this->toBool($this->envProvider->get('EMAIL_ONLY_LOCAL', 'false')),
            'attachment_base_dirs' => [],
            'charset' => 'UTF-8',
            'encoding' => 'base64',
            'word_wrap' => 78,
        ];
    }

    /**
     * Parse env-style booleans. Unlike boolval(), the string "false" is false.
     */
    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Initialize PHPMailer
     */
    private function initializeMailer(): void
    {
        $this->mailer = new PHPMailer(true);

        // SMTP configuration
        $this->mailer->isSMTP();
        $this->mailer->Host = $this->config['host'];
        $this->mailer->Port = $this->config['port'];

        // Only authenticate when credentials exist (local dev servers usually have none)
        $this->mailer->SMTPAuth = $this->config['username'] !== '';
        if ($this->mailer->SMTPAuth) {
            $this->mailer->Username = $this->config['username'];
            $this->mailer->Password = $this->config['password'];
        }

        // Security
        $secure = strtolower((string) $this->config['secure']);
        if ($secure === 'ssl') {
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($secure === 'tls') {
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        // Email settings
        $this->mailer->CharSet = $this->config['charset'];
        $this->mailer->Encoding = $this->config['encoding'];
        $this->mailer->WordWrap = $this->config['word_wrap'];

        // From address
        if (!empty($this->config['from_email'])) {
            $this->mailer->setFrom(
                $this->config['from_email'],
                $this->config['from_name']
            );
        }

        $this->applyDebug((bool) $this->config['debug']);

        $this->log('info', 'Mail manager initialized', [
            'host' => $this->config['host'],
            'port' => $this->config['port'],
            'secure' => $this->config['secure'],
            'from_email' => $this->config['from_email'],
            'keep_copy' => $this->config['keep_copy'],
            'only_local' => $this->config['only_local'],
        ]);
    }

    /**
     * Route SMTP debug output to the logger instead of echoing it
     * (echo would corrupt HTTP/JSON responses).
     */
    private function applyDebug(bool $debug): void
    {
        if ($debug) {
            $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
            $this->mailer->Debugoutput = function ($str, $level): void {
                $this->log('debug', 'SMTP debug', ['level' => $level, 'message' => trim((string) $str)]);
            };
        } else {
            $this->mailer->SMTPDebug = SMTP::DEBUG_OFF;
        }
    }

    /**
     * Null-safe logging helper (the logger is optional).
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $this->logger?->{$level}($message, $context);
    }

    /**
     * Send email
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body Email body (HTML or plain text)
     * @param array $options Email options:
     *                       html (bool), text (string, plain-text alternative), cc, bcc,
     *                       reply_to, attachments, headers, skip_local_copy (bool)
     * @return bool Success status
     */
    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        $this->lastError = '';

        try {
            $this->log('info', 'Preparing to send email', [
                'to' => $to,
                'subject' => $subject,
                'is_html' => (bool) ($options['html'] ?? false),
                'cc_count' => count((array) ($options['cc'] ?? [])),
                'bcc_count' => count((array) ($options['bcc'] ?? [])),
                'attachments_count' => count((array) ($options['attachments'] ?? [])),
            ]);

            if (!$this->validateEmail($to)) {
                $this->lastError = 'Invalid recipient address';
                $this->log('error', 'Failed to send email', ['to' => $to, 'error' => $this->lastError]);
                return false;
            }

            // Development mode: keep a local copy and optionally skip SMTP entirely
            $onlyLocal = (bool) $this->config['only_local'];
            $keepCopy = $onlyLocal || (bool) $this->config['keep_copy'];
            $skipLocalCopy = (bool) ($options['skip_local_copy'] ?? false);

            if ($keepCopy && !$skipLocalCopy) {
                $saved = $this->saveLocalCopy($to, $subject, $body, $options);

                if ($onlyLocal) {
                    // Never report success if nothing was actually saved or sent
                    return $saved;
                }
            } elseif ($onlyLocal) {
                // Called from LocalMail::send() while SMTP is disabled: nothing to do
                $this->log('info', 'Email delivery skipped (EMAIL_ONLY_LOCAL)', ['to' => $to]);
                return true;
            }

            // Set recipient
            $this->mailer->addAddress($to);

            // Set subject
            $this->mailer->Subject = $subject;

            // Set body
            $isHtml = (bool) ($options['html'] ?? false);
            if ($isHtml) {
                // Set the body directly instead of msgHTML(): msgHTML() embeds any
                // local file referenced by <img src="...">, which would let
                // untrusted HTML pull server files into the email.
                $this->mailer->isHTML(true);
                $this->mailer->Body = $body;
                $this->mailer->AltBody = (string) ($options['text'] ?? '') !== ''
                    ? (string) $options['text']
                    : strip_tags($body);
            } else {
                $this->mailer->isHTML(false);
                $this->mailer->Body = $body;
            }

            // CC recipients
            if (!empty($options['cc'])) {
                foreach ((array) $options['cc'] as $cc) {
                    $this->mailer->addCC($cc);
                }
            }

            // BCC recipients
            if (!empty($options['bcc'])) {
                foreach ((array) $options['bcc'] as $bcc) {
                    $this->mailer->addBCC($bcc);
                }
            }

            // Reply to
            if (!empty($options['reply_to'])) {
                $this->mailer->addReplyTo($options['reply_to']);
            }

            // Attachments
            if (!empty($options['attachments'])) {
                foreach ((array) $options['attachments'] as $attachment) {
                    if (is_string($attachment)) {
                        $this->mailer->addAttachment($this->resolveAttachmentPath($attachment));
                    } elseif (is_array($attachment) && isset($attachment['path'])) {
                        $path = $this->resolveAttachmentPath((string) $attachment['path']);
                        $this->mailer->addAttachment(
                            $path,
                            $attachment['name'] ?? basename($path),
                            $attachment['encoding'] ?? 'base64',
                            $attachment['type'] ?? '',
                            $attachment['disposition'] ?? 'attachment'
                        );
                    }
                }
            }

            // Custom headers
            if (!empty($options['headers'])) {
                foreach ((array) $options['headers'] as $name => $value) {
                    $name = (string) $name;
                    $value = (string) $value;
                    if (!preg_match('/^[A-Za-z0-9-]+$/', $name) || preg_match('/[\r\n]/', $value)) {
                        throw new \InvalidArgumentException("Invalid custom header: {$name}");
                    }
                    $this->mailer->addCustomHeader($name, $value);
                }
            }

            // Send email
            $sent = $this->mailer->send();

            if ($sent) {
                $this->log('info', 'Email sent successfully', [
                    'to' => $to,
                    'subject' => $subject,
                    'is_html' => $isHtml,
                    'attachments_count' => count((array) ($options['attachments'] ?? [])),
                ]);
            } else {
                $this->lastError = $this->mailer->ErrorInfo;
                $this->log('error', 'Failed to send email', [
                    'to' => $to,
                    'subject' => $subject,
                    'error' => $this->mailer->ErrorInfo,
                ]);
            }

            return $sent;

        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            $this->log('error', 'Email sending failed', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        } finally {
            // Reset ALL per-message state so nothing leaks into the next email
            $this->mailer->clearAllRecipients(); // to, cc, bcc
            $this->mailer->clearReplyTos();
            $this->mailer->clearAttachments();
            $this->mailer->clearCustomHeaders();
            $this->mailer->Subject = '';
            $this->mailer->Body = '';
            $this->mailer->AltBody = '';
        }
    }

    /**
     * Save a copy of the outgoing mail through LocalMailServer.
     */
    private function saveLocalCopy(string $to, string $subject, string $body, array $options): bool
    {
        if ($this->localMailServer === null) {
            $this->lastError = 'Local mail requested but no LocalMailServer is configured';
            $this->log('warning', $this->lastError);
            return false;
        }

        $mail = LocalMail::fromSendArguments($to, $subject, $body, $options);

        if ($this->localMailServer->save($mail)) {
            $this->log('info', 'Local copy of email saved', ['id' => $mail->id, 'to' => $to]);
            return true;
        }

        $this->lastError = 'Failed to save local copy: ' . $this->localMailServer->getLastError();
        $this->log('error', 'Failed to save local copy of email', [
            'to' => $to,
            'subject' => $subject,
            'error' => $this->localMailServer->getLastError(),
        ]);

        return false;
    }

    /**
     * Resolve an attachment path and, if base dirs are configured,
     * make sure it stays inside one of them.
     */
    private function resolveAttachmentPath(string $path): string
    {
        $baseDirs = (array) ($this->config['attachment_base_dirs'] ?? []);
        if ($baseDirs === []) {
            return $path;
        }

        $real = realpath($path);
        if ($real === false) {
            throw new \InvalidArgumentException("Attachment not found: {$path}");
        }

        foreach ($baseDirs as $dir) {
            $base = realpath((string) $dir);
            if ($base !== false && str_starts_with($real, rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
                return $real;
            }
        }

        throw new \InvalidArgumentException('Attachment path is outside the allowed directories');
    }

    /**
     * Send multiple emails
     *
     * @param array $recipients Array of recipient arrays with 'to', 'subject', 'body', 'options'
     * @return array Results for each email
     */
    public function sendMultiple(array $recipients): array
    {
        $results = [];

        // Reuse one SMTP connection for the whole batch
        $this->mailer->SMTPKeepAlive = true;

        try {
            foreach ($recipients as $index => $recipient) {
                $success = $this->send(
                    $recipient['to'] ?? '',
                    $recipient['subject'] ?? '',
                    $recipient['body'] ?? '',
                    $recipient['options'] ?? []
                );

                $results[$index] = [
                    'to' => $recipient['to'] ?? '',
                    'subject' => $recipient['subject'] ?? '',
                    'success' => $success,
                ];
            }
        } finally {
            $this->mailer->SMTPKeepAlive = false;
            $this->mailer->smtpClose();
        }

        $this->log('info', 'Batch email sending completed', [
            'total_recipients' => count($recipients),
            'successful_sends' => count(array_filter($results, fn($r) => $r['success'])),
        ]);

        return $results;
    }

    /**
     * Test SMTP connection
     *
     * @return bool Connection test result
     */
    public function testConnection(): bool
    {
        try {
            $this->log('info', 'Testing SMTP connection', [
                'host' => $this->config['host'],
                'port' => $this->config['port'],
            ]);

            $connected = $this->mailer->smtpConnect();

            if ($connected) {
                $this->mailer->smtpClose();
                $this->connected = true;
                $this->log('info', 'SMTP connection test successful');
                return true;
            }

            $this->connected = false;
            $this->lastError = $this->mailer->ErrorInfo;
            $this->log('error', 'SMTP connection test failed', [
                'error' => $this->mailer->ErrorInfo,
            ]);
            return false;

        } catch (\Throwable $e) {
            $this->connected = false;
            $this->lastError = $e->getMessage();
            $this->log('error', 'SMTP connection test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Get mailer configuration (secrets redacted)
     */
    public function getConfig(): array
    {
        $config = $this->config;
        if (($config['password'] ?? '') !== '') {
            $config['password'] = '********';
        }

        return $config;
    }

    /**
     * Whether the last testConnection() succeeded
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Get last error
     */
    public function getLastError(): string
    {
        return $this->lastError !== '' ? $this->lastError : $this->mailer->ErrorInfo;
    }

    /**
     * Set from address
     */
    public function setFrom(string $email, string $name = ''): void
    {
        $this->config['from_email'] = $email;
        $this->config['from_name'] = $name;
        $this->mailer->setFrom($email, $name);
    }

    /**
     * Enable/disable debug mode
     */
    public function setDebug(bool $debug): void
    {
        $this->config['debug'] = $debug;
        $this->applyDebug($debug);
    }

    /**
     * Get the local mail server (null when local copies are not configured)
     */
    public function getLocalMailServer(): ?LocalMailServer
    {
        return $this->localMailServer;
    }

    /**
     * Get mailer instance for advanced usage
     */
    public function getMailer(): PHPMailer
    {
        return $this->mailer;
    }

    /**
     * Validate email address
     *
     * @param string $email Email address to validate
     * @return bool Valid email address
     */
    public function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Create simple text email
     *
     * @param string $to Recipient
     * @param string $subject Subject
     * @param string $message Message
     * @return bool Success status
     */
    public function sendText(string $to, string $subject, string $message): bool
    {
        return $this->send($to, $subject, $message, ['html' => false]);
    }

    /**
     * Create HTML email
     *
     * @param string $to Recipient
     * @param string $subject Subject
     * @param string $html HTML message
     * @param string $text Plain text fallback (defaults to the stripped HTML)
     * @return bool Success status
     */
    public function sendHtml(string $to, string $subject, string $html, string $text = ''): bool
    {
        return $this->send($to, $subject, $html, [
            'html' => true,
            'text' => $text,
        ]);
    }

    /**
     * Send email with attachment
     *
     * @param string $to Recipient
     * @param string $subject Subject
     * @param string $body Message body
     * @param string $attachmentPath Path to attachment file
     * @param string $attachmentName Custom attachment name
     * @return bool Success status
     */
    public function sendWithAttachment(
        string $to,
        string $subject,
        string $body,
        string $attachmentPath,
        string $attachmentName = ''
    ): bool {
        return $this->send($to, $subject, $body, [
            'attachments' => [
                [
                    'path' => $attachmentPath,
                    'name' => $attachmentName ?: basename($attachmentPath),
                ]
            ]
        ]);
    }

    /**
     * Get mailer statistics (secrets redacted)
     */
    public function getStatistics(): array
    {
        return [
            'config' => $this->getConfig(),
            'connected' => $this->connected,
            'last_error' => $this->getLastError(),
            'phpmailer_version' => PHPMailer::VERSION,
            'smtp_host' => $this->config['host'],
            'smtp_port' => $this->config['port'],
            'smtp_secure' => $this->config['secure'],
            'from_email' => $this->config['from_email'],
            'from_name' => $this->config['from_name'],
            'keep_copy' => $this->config['keep_copy'],
            'only_local' => $this->config['only_local'],
        ];
    }
}
