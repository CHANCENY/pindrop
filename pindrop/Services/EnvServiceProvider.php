<?php

declare(strict_types=1);

namespace Simp\Pindrop\Services;

use DI\Container;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

class EnvServiceProvider
{
    private array $envVars = [];

    /**
     * Singleton instance — the .env file is parsed exactly once per process.
     *
     * Every call to new EnvServiceProvider() previously re-read and re-parsed
     * the .env file from disk.  With 6+ instantiations per request that was
     * 6 file_reads + 6 regex loops + 6 putenv() batches for nothing.
     *
     * getInstance() returns the same object every time.  new EnvServiceProvider()
     * still works (all call sites are unchanged) because __construct() delegates
     * to getInstance() and copies the already-resolved vars.
     */
    private static ?self $instance = null;

    public function __construct()
    {
        if (self::$instance === null) {
            // First instantiation — do the real work.
            $this->loadEnvFile();
            $this->resolveEnvVars();
            $this->populateGlobalEnv();
            self::$instance = $this;
        } else {
            // Subsequent instantiations — copy vars from the singleton.
            // No file I/O, no regex, no putenv() calls.
            $this->envVars = self::$instance->envVars;
        }
    }

    /**
     * Get the shared singleton instance.
     * Prefer this over new EnvServiceProvider() in new code.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            new self();
        }
        return self::$instance;
    }

    /**
     * Reset the singleton — used in tests or after .env changes.
     * Never call this in production request code.
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
    
    /**
     * Load and parse .env file
     */
    private function loadEnvFile(): void
    {
        $envFile = dirname(__DIR__, 2) . '/.env';
        
        if (!file_exists($envFile)) {
            return;
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (str_starts_with($line, '#')) {
                continue;
            }
            
            // Parse KEY=VALUE format
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $this->envVars[trim($key)] = trim($value);
            }
        }
    }
    
    /**
     * Resolve environment variables with dependency resolution
     */
    private function resolveEnvVars(): void
    {
        $resolved = [];
        $maxIterations = 10; // Prevent infinite loops
        $iteration = 0;
        
        while (!empty($this->envVars) && $iteration < $maxIterations) {
            $iteration++;
            $remaining = [];
            $hasChanges = false;
            
            foreach ($this->envVars as $key => $value) {
                $resolvedValue = $this->resolveValue($value, array_merge($resolved, $this->envVars));
                
                if ($resolvedValue !== $value) {
                    // Value was resolved, add to resolved
                    $resolved[$key] = $resolvedValue;
                    $hasChanges = true;
                } else {
                    // Value still has unresolved dependencies, keep for next iteration
                    $remaining[$key] = $value;
                }
            }
            
            $this->envVars = $remaining;
            
            // If no changes in this iteration, break to prevent infinite loop
            if (!$hasChanges) {
                break;
            }
        }
        
        // Add any remaining unresolved vars
        $this->envVars = array_merge($this->envVars, $resolved);
    }
    
    /**
     * Resolve a single value, replacing [VAR] with resolved values
     */
    private function resolveValue(string $value, array $resolved): string
    {
        // Find all [VAR] patterns
        if (preg_match_all('/\[([A-Z_]+)\]/', $value, $matches)) {
            foreach ($matches[1] as $varName) {
                if (isset($resolved[$varName])) {
                    $resolvedValue = $resolved[$varName];
                    // Remove quotes from the resolved value if they exist
                    if ((str_starts_with($resolvedValue, '"') && str_ends_with($resolvedValue, '"')) ||
                        (str_starts_with($resolvedValue, "'") && str_ends_with($resolvedValue, "'"))) {
                        $resolvedValue = substr($resolvedValue, 1, -1);
                    }
                    $value = str_replace("[$varName]", $resolvedValue, $value);
                }
            }
        }
        
        return trim($value, '"');
    }
    
    /**
     * Populate $_ENV superglobal with resolved values
     */
    private function populateGlobalEnv(): void
    {
        foreach ($this->envVars as $key => $value) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
    
    /**
     * Configure DI container with environment definitions
     */
    public function configureContainer(ContainerBuilder $builder): void
    {
        $definitions = [];
        
        foreach ($this->envVars as $key => $value) {
            $definitions[$key] = $value;
        }
        
        // Add common environment-related services
        $definitions['env.root'] = fn() => $_ENV['ROOT'] ?? null;
        $definitions['env.plugin_root'] = fn() => $_ENV['PLUGIN_ROOT'] ?? null;
        $definitions['env.all'] = fn() => $_ENV;
        $definitions['env.services'] = \DI\value($this); // pre-built instance, \DIalue() bypasses compilation

        $builder->addDefinitions($definitions);
    }
    
    /**
     * Get resolved environment variable
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->envVars[$key] ?? $default;
    }
    
    /**
     * Get all resolved environment variables
     */
    public function all(): array
    {
        return $this->envVars;
    }
}
