<?php

declare(strict_types=1);

namespace Simp\Pindrop\Plugin\Alert;

use Simp\Pindrop\Logger\LoggerInterface;

/**
 * Alert Validator
 * 
 * Validates alert data and configurations.
 */
class AlertValidator
{
    private LoggerInterface $logger;
    
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->logger->info('AlertValidator initialized');
    }
    
    /**
     * Validate alert data
     */
    public function validateAlert(array $alert): array
    {
        $this->logger->info('Validating alert data', $alert);
        
        $errors = [];
        
        // Validate required fields
        if (empty($alert['title'])) {
            $errors[] = 'Title is required';
        }
        
        if (empty($alert['message'])) {
            $errors[] = 'Message is required';
        }
        
        if (empty($alert['level'])) {
            $errors[] = 'Level is required';
        }
        
        // Validate level
        $validLevels = ['emergency', 'alert', 'critical', 'error', 'warning'];
        if (!empty($alert['level']) && !in_array($alert['level'], $validLevels)) {
            $errors[] = "Invalid level: {$alert['level']}";
        }
        
        // Validate title length
        if (!empty($alert['title']) && strlen($alert['title']) > 255) {
            $errors[] = 'Title must be less than 255 characters';
        }
        
        $isValid = empty($errors);
        
        $this->logger->info('Alert validation completed', [
            'valid' => $isValid,
            'errors' => $errors
        ]);
        
        return [
            'valid' => $isValid,
            'errors' => $errors
        ];
    }
    
    /**
     * Validate alert configuration
     */
    public function validateConfig(array $config): array
    {
        $this->logger->info('Validating alert configuration', $config);
        
        $errors = [];
        
        // Validate required config fields
        if (!isset($config['enabled'])) {
            $errors[] = 'Enabled setting is required';
        }
        
        if (!isset($config['log_alerts'])) {
            $errors[] = 'Log alerts setting is required';
        }
        
        $isValid = empty($errors);
        
        $this->logger->info('Configuration validation completed', [
            'valid' => $isValid,
            'errors' => $errors
        ]);
        
        return [
            'valid' => $isValid,
            'errors' => $errors
        ];
    }
    
    /**
     * Sanitize alert data
     */
    public function sanitizeAlert(array $alert): array
    {
        $this->logger->info('Sanitizing alert data');
        
        return [
            'title' => htmlspecialchars($alert['title'] ?? ''),
            'message' => htmlspecialchars($alert['message'] ?? ''),
            'level' => htmlspecialchars($alert['level'] ?? ''),
            'context' => $this->sanitizeContext($alert['context'] ?? []),
            'status' => htmlspecialchars($alert['status'] ?? 'pending')
        ];
    }
    
    /**
     * Sanitize context data
     */
    private function sanitizeContext(array $context): array
    {
        $sanitized = [];
        foreach ($context as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = htmlspecialchars($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeContext($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
}
