<?php

declare(strict_types=1);

namespace Simp\Pindrop\Theme;

use DI\ContainerBuilder;
use Simp\Pindrop\Services\EnvServiceProvider;

/**
 * Theme Service Provider
 * 
 * Provides theme services for dependency injection container.
 * Supports theme management and configuration.
 */
class ThemeServiceProvider
{
    private EnvServiceProvider $envProvider;
    private ?\DI\Container $container = null;
    
    public function __construct(EnvServiceProvider $envProvider)
    {
        $this->envProvider = $envProvider;
    }
    
    /**
     * Configure DI container with theme services
     */
    public function configureContainer(ContainerBuilder $builder): void
    {
        $definitions = [
            // Theme configuration
            'theme.config' => fn() => $this->getThemeConfig(),
            
            // Theme manager
            'theme.manager' => fn(\DI\Container $c) => $this->createThemeManager($c),
            
            // Aliases for convenience
            ThemeManager::class => fn(\DI\Container $c) => $c->get('theme.manager'),
        ];

        $builder->addDefinitions($definitions);
    }
    
    /**
     * Create theme manager instance
     */
    private function createThemeManager(\DI\Container $container): ThemeManager
    {
        $themeManager = new ThemeManager(
            $this->envProvider,
            $this->getThemeConfig()['themes_dir']
        );
        
        return $themeManager;
    }
    
    /**
     * Get theme configuration from environment variables
     */
    private function getThemeConfig(): array
    {
        return [
            'themes_dir' => $this->envProvider->get('THEMES_DIR', __DIR__ . '/../../themes'),
        ];
    }
    
    /**
     * Register theme service with container
     */
    public function register(ContainerBuilder $builder): void
    {
        $this->configureContainer($builder);
    }
    
    /**
     * Get available theme services
     */
    public function getAvailableServices(): array
    {
        return [
            'theme' => ThemeManager::class,
        ];
    }
    
    /**
     * Get theme configuration template
     */
    public function getConfigTemplate(): array
    {
        return [
            'THEMES_DIR' => '/path/to/themes/directory',
        ];
    }
}
