<?php

declare(strict_types=1);

namespace Simp\Pindrop\Mail;

use Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Simp\Pindrop\Logger\LoggerInterface;
use Simp\Pindrop\Services\EnvServiceProvider;

/**
 * Mail Manager
 * 
 * Service for sending emails using PHPMailer with SMTP configuration.
 * Supports HTML emails, attachments, and logging.
 */
class MailManager
{
    private PHPMailer $mailer;
    private ?LoggerInterface $logger;
    private EnvServiceProvider $envProvider;
    private array $config;
    private bool $connected = false;
    
    public function __construct(
        array $config = [],
        ?LoggerInterface $logger = null,
        ?EnvServiceProvider $envProvider = null,
        ?\DI\Container $container = null
    ) {
        $this->logger = $logger;
        $this->envProvider = $envProvider ?? new EnvServiceProvider();
        $this->config = array_merge($this->getDefaultConfig(), $config);

        // If a container is provided, get logger from it
        if ($container && $this->logger === null) {
            $this->logger = $container->has('logger') ? $container->get('logger') : null;
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
            'debug' => $this->envProvider->get('SMTP_DEBUG', 'false') === 'true',
            'charset' => 'UTF-8',
            'encoding' => 'base64',
            'word_wrap' => 78,
        ];
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
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $this->config['username'];
        $this->mailer->Password = $this->config['password'];
        
        // Security
        $secure = strtolower($this->config['secure']);
        if ($secure === 'ssl') {
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($secure === 'tls') {
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        
        // Email settings
        $this->mailer->CharSet = $this->config['charset'];
        $this->mailer->Encoding = $this->config['encoding'];
        $this->mailer->WordWrap = $this->config['word_wrap'];
        $this->mailer->isHTML(true);
        
        // From address
        if (!empty($this->config['from_email'])) {
            $this->mailer->setFrom(
                $this->config['from_email'],
                $this->config['from_name']
            );
        }
        
        // Debug mode
        if ($this->config['debug']) {
            $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
            $this->mailer->Debugoutput = function($str, $level) {
                echo "SMTP Debug [{$level}]: {$str}\n";
            };
        }
        
        $this->logger->info('Mail manager initialized', [
            'host' => $this->config['host'],
            'port' => $this->config['port'],
            'username' => $this->config['username'],
            'secure' => $this->config['secure'],
            'from_email' => $this->config['from_email'],
        ]);
    }
    
    /**
     * Send email
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body Email body (HTML or plain text)
     * @param array $options Email options
     * @return bool Success status
     */
    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        try {
            $this->logger->info('Preparing to send email', [
                'to' => $to,
                'subject' => $subject,
                'options' => $options
            ]);
            
            // Set recipient
            $this->mailer->addAddress($to);
            
            // Set subject
            $this->mailer->Subject = $subject;
            
            // Set body
            $isHtml = $options['html'] ?? false;
            if ($isHtml) {
                $this->mailer->msgHTML($body);
                $this->mailer->AltBody = strip_tags($body);
            } else {
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
                        $this->mailer->addAttachment($attachment);
                    } elseif (is_array($attachment)) {
                        $this->mailer->addAttachment(
                            $attachment['path'],
                            $attachment['name'] ?? basename($attachment['path']),
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
                    $this->mailer->addCustomHeader($name, $value);
                }
            }
            
            // Send email
            $sent = $this->mailer->send();
            
            if ($sent) {
                $this->logger->info('Email sent successfully', [
                    'to' => $to,
                    'subject' => $subject,
                    'is_html' => $isHtml,
                    'attachments_count' => count($options['attachments'] ?? []),
                ]);
            } else {
                $this->logger->error('Failed to send email', [
                    'to' => $to,
                    'subject' => $subject,
                    'error' => $this->mailer->ErrorInfo,
                ]);
            }
            
            return $sent;
            
        } catch (Exception $e) {
            $this->logger->error('Email sending failed', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return false;
        } finally {
            // Clear recipients and attachments for next email
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            $this->mailer->clearCustomHeaders();
        }
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
        
        $this->logger->info('Batch email sending completed', [
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
            $this->logger->info('Testing SMTP connection', [
                'host' => $this->config['host'],
                'port' => $this->config['port'],
                'username' => $this->config['username'],
            ]);
            
            $connected = $this->mailer->smtpConnect();
            
            if ($connected) {
                $this->connected = true;
                $this->logger->info('SMTP connection test successful');
                return true;
            } else {
                $this->logger->error('SMTP connection test failed', [
                    'error' => $this->mailer->ErrorInfo,
                ]);
                return false;
            }
            
        } catch (Exception $e) {
            $this->logger->error('SMTP connection test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return false;
        }
    }
    
    /**
     * Get mailer configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }
    
    /**
     * Get connection status
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
        return $this->mailer->ErrorInfo ?? '';
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
        $this->mailer->SMTPDebug = $debug ? SMTP::DEBUG_SERVER : 0;
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
     * @param string $text Plain text fallback
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
     * Get mailer statistics
     */
    public function getStatistics(): array
    {
        return [
            'config' => $this->config,
            'connected' => $this->connected,
            'last_error' => $this->getLastError(),
            'phpmailer_version' => PHPMailer::VERSION,
            'smtp_host' => $this->config['host'],
            'smtp_port' => $this->config['port'],
            'smtp_secure' => $this->config['secure'],
            'from_email' => $this->config['from_email'],
            'from_name' => $this->config['from_name'],
        ];
    }
}
