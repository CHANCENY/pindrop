<?php

declare(strict_types=1);

namespace Simp\Pindrop\Plugin\Alert;

use Simp\Pindrop\Logger\LoggerInterface;

/**
 * Alert Service
 * 
 * Main service for handling alerts and notifications.
 */
class AlertService
{
    private LoggerInterface $logger;
    
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->logger->info('AlertService initialized');
    }
    
    /**
     * Create a new alert
     */
    public function createAlert(string $level, string $title, string $message, array $context = []): array
    {
        $this->logger->info("Creating alert: {$title}", [
            'level' => $level,
            'title' => $title,
            'message' => $message,
            'context' => $context
        ]);
        
        $alert = [
            'id' => uniqid('alert_', true),
            'level' => $level,
            'title' => $title,
            'message' => $message,
            'context' => $context,
            'created_at' => date('Y-m-d H:i:s'),
            'status' => 'pending'
        ];
        
        $this->logger->info('Alert created successfully', $alert);
        
        return $alert;
    }
    
    /**
     * Send alert notification
     */
    public function sendAlert(array $alert): bool
    {
        $this->logger->info("Sending alert: {$alert['title']}", $alert);
        
        // Simulate sending alert
        $alert['status'] = 'sent';
        $alert['sent_at'] = date('Y-m-d H:i:s');
        
        $this->logger->info('Alert sent successfully', $alert);
        
        return true;
    }
    
    /**
     * Get alert statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_created' => 0,
            'total_sent' => 0,
            'pending_count' => 0,
            'service_status' => 'active'
        ];
    }
}
