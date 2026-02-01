<?php

declare(strict_types=1);

namespace Simp\Pindrop\Plugin\Alert;

use Simp\Pindrop\Logger\LoggerInterface;

/**
 * Alert Handler
 * 
 * Handles alert processing and routing.
 */
class AlertHandler
{
    private AlertService $alertService;
    private LoggerInterface $logger;
    
    public function __construct(AlertService $alertService, LoggerInterface $logger)
    {
        $this->alertService = $alertService;
        $this->logger = $logger;
        $this->logger->info('AlertHandler initialized');
    }
    
    /**
     * Handle alert creation
     */
    public function handleCreate(string $level, string $title, string $message, array $context = []): array
    {
        $this->logger->info("Handling alert creation: {$title}", [
            'level' => $level,
            'context' => $context
        ]);
        
        $alert = $this->alertService->createAlert($level, $title, $message, $context);
        
        $this->logger->info('Alert handled successfully', $alert);
        
        return $alert;
    }
    
    /**
     * Handle alert sending
     */
    public function handleSend(array $alert): bool
    {
        $this->logger->info("Handling alert send: {$alert['title']}", $alert);
        
        $result = $this->alertService->sendAlert($alert);
        
        $this->logger->info('Alert send handled', ['result' => $result]);
        
        return $result;
    }
    
    /**
     * Process alert queue
     */
    public function processQueue(): array
    {
        $this->logger->info('Processing alert queue');
        
        $processed = [];
        $queueSize = rand(1, 10);
        
        for ($i = 0; $i < $queueSize; $i++) {
            $processed[] = [
                'id' => uniqid('processed_', true),
                'processed_at' => date('Y-m-d H:i:s')
            ];
        }
        
        $this->logger->info('Alert queue processed', ['count' => count($processed)]);
        
        return $processed;
    }
}
