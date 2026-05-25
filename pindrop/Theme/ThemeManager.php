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
    private ?string $manifestCacheFile = null;
    private bool    $cacheEnabled      = false;
    
    public function __construct(
        EnvServiceProvider $envProvider,
        ?string $themesDir = null
    ) {
        $this->envProvider = $envProvider;
        $this->themesDir = $themesDir ?? $envProvider->get('THEMES_DIR', __DIR__ . '/../../themes');

        $env = getenv('APP_ENV') ?: 'development';
        if ($env === 'production') {
            $cacheDir = rtrim(getenv('CACHE_DIR') ?: (dirname(__DIR__, 2) . '/var/cache'), '/');
            $themeCacheDir = $cacheDir . '/themes';
            if (!is_dir($themeCacheDir)) {
                mkdir($themeCacheDir, 0755, true);
            }
            $this->manifestCacheFile = $themeCacheDir . '/manifest.php';
            $this->cacheEnabled      = true;
        }

        $this->initialize();
    }
    
    /**
     * Initialize theme system
     */
    private function initialize(): void
    {
        if ($this->cacheEnabled && $this->loadFromManifestCache()) {
            return;
        }

        $this->discoverThemes();

        if ($this->cacheEnabled) {
            $this->writeManifestCache();
        }
    }

    private function loadFromManifestCache(): bool
    {
        if (!file_exists($this->manifestCacheFile)) {
            return false;
        }
        try {
            $manifest = require $this->manifestCacheFile;
        } catch (\Throwable $e) {
            @unlink($this->manifestCacheFile);
            return false;
        }
        if (!is_array($manifest) || empty($manifest['_version'] ?? null)) {
            @unlink($this->manifestCacheFile);
            return false;
        }
        $this->themes       = $manifest['themes']       ?? [];
        $this->themeConfigs = $manifest['themeConfigs'] ?? [];
        return true;
    }

    private function writeManifestCache(): void
    {
        $manifest = [
            '_version'    => '1',
            '_written_at' => time(),
            'themes'      => $this->themes,
            'themeConfigs'=> $this->themeConfigs,
        ];
        $php = '<?php return ' . var_export($manifest, true) . ';' . PHP_EOL;
        $tmp = $this->manifestCacheFile . '.tmp.' . getmypid();
        file_put_contents($tmp, $php, LOCK_EX);
        rename($tmp, $this->manifestCacheFile);
    }

    public function clearManifestCache(): void
    {
        if ($this->manifestCacheFile && file_exists($this->manifestCacheFile)) {
            @unlink($this->manifestCacheFile);
        }
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
