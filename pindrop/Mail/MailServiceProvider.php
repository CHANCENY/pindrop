<?php

declare(strict_types=1);

namespace Simp\Pindrop\Mail;

use DI\ContainerBuilder;
use Simp\Pindrop\Services\EnvServiceProvider;

/**
 * Mail Service Provider
 * 
 * Provides mail services for dependency injection container.
 * Supports PHPMailer integration with SMTP configuration.
 */
class MailServiceProvider
{
    private EnvServiceProvider $envProvider;
    
    public function __construct(EnvServiceProvider $envProvider)
    {
        $this->envProvider = $envProvider;
    }
    
    /**
     * Configure DI container with mail services
     */
    public function configureContainer(ContainerBuilder $builder): void
    {
        $definitions = [
            // Mail configuration
            'mail.config' => fn() => $this->getMailConfig(),
            
            // Mail manager
            'mail.manager' => fn(\DI\Container $c) => new MailManager(
                $c->get('mail.config'),
                $c->get('logger'),
                $c->get('env.services'),
                $c
            ),
            
            // Aliases for convenience
            MailManager::class => fn(\DI\Container $c) => $c->get('mail.manager'),
        ];
        
        $builder->addDefinitions($definitions);
    }
    
    /**
     * Get mail configuration from environment variables
     */
    private function getMailConfig(): array
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
     * Register mail service with container
     */
    public function register(ContainerBuilder $builder): void
    {
        $this->configureContainer($builder);
    }
    
    /**
     * Get available mail drivers
     */
    public function getAvailableDrivers(): array
    {
        return [
            'smtp' => MailManager::class,
        ];
    }
    
    /**
     * Get mail configuration template
     */
    public function getConfigTemplate(): array
    {
        return [
            'SMTP_HOST' => 'smtp.example.com',
            'SMTP_PORT' => '587',
            'SMTP_USER' => 'username@example.com',
            'SMTP_PASS' => 'password',
            'SMTP_SECURE' => 'tls|ssl',
            'SMTP_DEBUG' => 'true|false',
            'MAIL_FROM_EMAIL' => 'noreply@example.com',
            'MAIL_FROM_NAME' => 'Application Name',
        ];
    }
}
