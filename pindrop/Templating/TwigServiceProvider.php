<?php

declare(strict_types=1);

namespace Simp\Pindrop\Templating;

use DI\Container;
use DI\ContainerBuilder;
use Simp\Pindrop\Services\EnvServiceProvider;

/**
 * Twig Service Provider
 * 
 * Provides Twig templating services for dependency injection container.
 */
class TwigServiceProvider
{
    private EnvServiceProvider $envProvider;
    private ?\DI\Container $container = null;
    
    public function __construct(EnvServiceProvider $envProvider)
    {
        $this->envProvider = $envProvider;
    }
    
    /**
     * Configure DI container with Twig services
     */
    public function configureContainer(ContainerBuilder $builder): void
    {
        $definitions = [
            // Twig configuration
            'twig.config' => fn() => $this->getTwigConfig(),
            
            // Twig engine
            'twig.engine' => fn(\DI\Container $c) => $this->createTwigEngine($c),
            
            // Aliases for convenience
            TwigEngine::class => fn(\DI\Container $c) => $c->get('twig.engine'),
            'twig' => fn(\DI\Container $c) => $c->get('twig.engine'),
            'library' => fn(Container $c) => new LibraryAssets($c->get('database')),
        ];

        $builder->addDefinitions($definitions);
    }
    
    /**
     * Create Twig engine instance
     */
    private function createTwigEngine(\DI\Container $container): TwigEngine
    {
        return new TwigEngine(
            $container->get('theme.manager'),
            $this->envProvider,
            $container
        );
    }
    
    /**
     * Get Twig configuration from environment variables
     */
    private function getTwigConfig(): array
    {
        return [
            'debug' => $this->envProvider->get('APP_DEBUG', false),
            'cache' => $this->envProvider->get('TWIG_CACHE', false),
            'cache_path' => $this->envProvider->get('TWIG_CACHE_PATH', __DIR__ . '/../../var/cache/twig'),
            'auto_reload' => $this->envProvider->get('TWIG_AUTO_RELOAD', true),
            'strict_variables' => $this->envProvider->get('TWIG_STRICT_VARIABLES', false),
            'autoescape' => $this->envProvider->get('TWIG_AUTOESCAPE', 'html'),
        ];
    }
    
    /**
     * Register Twig service with container
     */
    public function register(ContainerBuilder $builder): void
    {
        $this->configureContainer($builder);
    }
    
    /**
     * Get available Twig services
     */
    public function getAvailableServices(): array
    {
        return [
            'twig' => TwigEngine::class,
        ];
    }
    
    /**
     * Get Twig configuration template
     */
    public function getConfigTemplate(): array
    {
        return [
            'TWIG_CACHE' => 'false',
            'TWIG_CACHE_PATH' => '/path/to/cache/directory',
            'TWIG_AUTO_RELOAD' => 'true',
            'TWIG_STRICT_VARIABLES' => 'false',
            'TWIG_AUTOESCAPE' => 'html',
        ];
    }
}
