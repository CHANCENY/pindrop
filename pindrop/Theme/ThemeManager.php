<?php

declare(strict_types=1);

namespace Simp\Pindrop\Theme;

use Simp\Pindrop\Services\EnvServiceProvider;
use Symfony\Component\Yaml\Yaml;

/**
 * Theme Manager
 * 
 * Handles theme discovery and management.
 * Supports YAML configuration files and theme loading.
 */
class ThemeManager
{
    private EnvServiceProvider $envProvider;
    private string $themesDir;
    private array $themes = [];
    private array $themeConfigs = [];
    
    public function __construct(
        EnvServiceProvider $envProvider,
        ?string $themesDir = null
    ) {
        $this->envProvider = $envProvider;
        $this->themesDir = $themesDir ?? $envProvider->get('THEMES_DIR', __DIR__ . '/../../themes');
        
        $this->initialize();
    }
    
    /**
     * Initialize theme system
     */
    private function initialize(): void
    {
        $this->discoverThemes();
    }
    
    /**
     * Discover available themes in themes directory
     */
    private function discoverThemes(): void
    {
        if (!is_dir($this->themesDir)) {
            return;
        }

        $directories = scandir($this->themesDir);
        foreach ($directories as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }

            $themePath = $this->themesDir . '/' . $dir;
            if (is_dir($themePath)) {
                $this->loadTheme($dir, $themePath);
            }
        }
    }
    
    /**
     * Load a single theme
     */
    private function loadTheme(string $name, string $path): void
    {
        $infoFile = $path . '/info.yml';
        if (!file_exists($infoFile)) {
            return;
        }

        try {
            $info = Yaml::parseFile($infoFile);
            if (!$info) {
                return;
            }

            // Validate required fields
            $requiredFields = ['name', 'version', 'description'];
            foreach ($requiredFields as $field) {
                if (!isset($info[$field])) {
                    return;
                }
            }

            $this->themes[$name] = [
                'name' => $name,
                'path' => $path,
                'info' => $info,
                'templates_path' => $path . '/templates',
                'assets_path' => $path . '/assets'
            ];

        } catch (\Exception $e) {
            // Skip invalid themes
        }
    }
    
    /**
     * Get all available themes
     */
    public function getThemes(): array
    {
        return $this->themes;
    }
    
    /**
     * Get theme by name
     */
    public function getTheme(string $name): ?array
    {
        return $this->themes[$name] ?? null;
    }
    
    /**
     * Get theme info
     */
    public function getThemeInfo(string $name): ?array
    {
        return $this->themes[$name]['info'] ?? null;
    }
    
    /**
     * Get theme path
     */
    public function getThemePath(string $name): ?string
    {
        return $this->themes[$name]['path'] ?? null;
    }
    
    /**
     * Get theme templates path
     */
    public function getThemeTemplatesPath(string $name): ?string
    {
        return $this->themes[$name]['templates_path'] ?? null;
    }
    
    /**
     * Get theme assets path
     */
    public function getThemeAssetsPath(string $name): ?string
    {
        return $this->themes[$name]['assets_path'] ?? null;
    }
    
    /**
     * Check if theme exists
     */
    public function themeExists(string $name): bool
    {
        return isset($this->themes[$name]);
    }
    
    /**
     * Get themes directory
     */
    public function getThemesDir(): string
    {
        return $this->themesDir;
    }
}
