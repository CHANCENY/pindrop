<?php

declare(strict_types=1);

namespace Simp\Pindrop\FileSystem;

use Psr\Container\ContainerInterface;
use Simp\Pindrop\Logger\LoggerInterface;
use Simp\StreamWrapper\WrapperRegister\WrapperRegister;

/**
 * FileSystem Service
 * 
 * Service wrapper for FileSystem with DI container support.
 * Provides easy integration with pindrop's service container.
 */
class FileSystemService
{
    private FileSystem $fileSystem;
    private array $config;

    public function __construct(array $config = [], ?LoggerInterface $logger = null, ?ContainerInterface $container = null)
    {
        $this->config = $config;
        $this->fileSystem = new FileSystem($config, $logger);
    }

    /**
     * Get FileSystem instance
     */
    public function getFileSystem(): FileSystem
    {
        return $this->fileSystem;
    }

    /**
     * Create service from environment configuration
     */
    public static function createFromEnv(?LoggerInterface $logger = null): self
    {
        $config = [
            'public_stream' => $_ENV['PUBLIC_STREAM'] ?? 'public://',
        ];

        return new self($config, $logger);
    }

    /**
     * Magic method to proxy FileSystem calls
     */
    public function __call(string $method, array $arguments): mixed
    {
        if (method_exists($this->fileSystem, $method)) {
            return $this->fileSystem->$method(...$arguments);
        }
        
        throw new \BadMethodCallException("Method {$method} does not exist on FileSystem");
    }

    /**
     * Get configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Check if stream wrapper is registered
     */
    public function isStreamRegistered(string $protocol): bool
    {
        $wrapperRegister = new WrapperRegister();
        return $wrapperRegister->isWrapperRegistered($protocol);
    }

    /**
     * Get registered stream wrappers
     */
    public function getRegisteredStreams(): array
    {
        $wrapperRegister = new WrapperRegister();
        return $wrapperRegister->getStreamWrappers();
    }
}
