<?php

declare(strict_types=1);

namespace Simp\Pindrop\Services;

use DI\Container;
use DI\ContainerBuilder;
use Simp\Pindrop\Logger\LoggerServiceProvider;
use Simp\Pindrop\Mail\MailServiceProvider;
use Simp\Pindrop\Mysql\SchemaServiceProvider;
use Simp\Pindrop\Plugin\PluginServiceProvider;
use Simp\Pindrop\Routing\RoutingServiceProvider;
use Simp\Pindrop\Theme\ThemeServiceProvider;
use Simp\Pindrop\Templating\TwigServiceProvider;
use Simp\Pindrop\Services\UserServiceProvider;
use Simp\Pindrop\Services\WhoopsServiceProvider;
use Simp\Pindrop\Services\MenuServiceProvider;
use Simp\Pindrop\Session\SessionServiceProvider;
use Throwable;

class ServiceProvider
{
    private array $providers = [];
    
    public function __construct()
    {
        $this->registerDefaultProviders();
    }
    
    /**
     * Register default service providers
     */
    private function registerDefaultProviders(): void
    {
        $envProvider = EnvServiceProvider::getInstance();
        $this->providers[] = $envProvider;
        $this->providers[] = new ConfigServiceProvider($envProvider);
        $this->providers[] = new WhoopsServiceProvider($envProvider);
        $this->providers[] = new MenuServiceProvider($envProvider);
        $this->providers[] = new LoggerServiceProvider($envProvider);
        $this->providers[] = new DatabaseServiceProvider($envProvider);
        $this->providers[] = new FileSystemServiceProvider($envProvider);
        $this->providers[] = new MailServiceProvider($envProvider);
        $this->providers[] = new SchemaServiceProvider($envProvider);
        $this->providers[] = new PluginServiceProvider($envProvider);
        $this->providers[] = new ThemeServiceProvider($envProvider);
        $this->providers[] = new TwigServiceProvider($envProvider);
        $this->providers[] = new RoutingServiceProvider($envProvider);
        $this->providers[] = new UserServiceProvider($envProvider);
        $this->providers[] = new SessionServiceProvider($envProvider);
    }
    
    /**
     * Add a custom service provider
     */
    public function addProvider(object $provider): self
    {
        $this->providers[] = $provider;
        return $this;
    }
    
    /**
     * Build DI container
     */
    public function buildContainer(): Container
    {
        $builder = new ContainerBuilder();

        // ------------------------------------------------------------------
        // DI Container compilation cache
        //
        // PHP-DI can compile all service definitions into a plain PHP class
        // (no closures, no reflection at runtime) and write it to disk.
        // On subsequent requests PHP just require()s the cached file —
        // container build time drops from ~50ms to <1ms.
        //
        // Rules:
        //  - Only enabled in production (APP_ENV=production) because the
        //    cache must be manually cleared after any service definition
        //    change.  In development it rebuilds every request so you see
        //    changes immediately.
        //  - CACHE_DIR env var overrides the default location.
        //  - The cache directory is created automatically if missing.
        //  - Call ServiceProvider::clearContainerCache() from a deploy
        //    script or CLI command to invalidate the cache after a deploy.
        // ------------------------------------------------------------------
        $env       = getenv('APP_ENV') ?: 'development';
        $cacheDir  = rtrim(getenv('CACHE_DIR') ?: (dirname(__DIR__, 2) . '/var/cache/di'), '/');

        if ($env === 'production') {
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }
            // enableCompilation() tells PHP-DI to write (or read) a compiled
            // container class in $cacheDir.  The file is regenerated only when
            // it doesn't exist — delete it to force a rebuild.
            $builder->enableCompilation($cacheDir);

            // writeProxiesToFile() avoids generating proxy classes at runtime
            // for lazy-loaded services — they're written to disk alongside the
            // compiled container.
            $builder->writeProxiesToFile(true, $cacheDir . '/proxies');
        }

        // Configure each provider
        foreach ($this->providers as $provider) {
            if ($provider instanceof EnvServiceProvider) {
                $provider->configureContainer($builder);
            } elseif ($provider instanceof ConfigServiceProvider) {
                $provider->configureContainer($builder);
            } elseif ($provider instanceof WhoopsServiceProvider) {
                $provider->configureContainer($builder);
            } elseif ($provider instanceof MenuServiceProvider) {
                $provider->configureContainer($builder);
            } elseif ($provider instanceof LoggerServiceProvider) {
                $provider->configureContainer($builder);
            } elseif ($provider instanceof DatabaseServiceProvider) {
                $provider->configureContainer($builder);
            } elseif ($provider instanceof FileSystemServiceProvider) {
                $provider->configureContainer($builder);
            } elseif ($provider instanceof MailServiceProvider) {
                $provider->configureContainer($builder);
            } elseif ($provider instanceof SchemaServiceProvider) {
                $provider->configureContainer($builder);
            } elseif ($provider instanceof PluginServiceProvider) {
                $provider->configureContainer($builder);
            } elseif ($provider instanceof ThemeServiceProvider) {
                $provider->configureContainer($builder);
            } elseif ($provider instanceof TwigServiceProvider) {
                $provider->configureContainer($builder);
            } elseif ($provider instanceof RoutingServiceProvider) {
                $provider->configureContainer($builder);
            } elseif ($provider instanceof UserServiceProvider) {
                $provider->configureContainer($builder);
            }elseif ($provider instanceof SessionServiceProvider) {
                $provider->configureContainer($builder);
            }
        }
        return $builder->build();
    }

    /**
     * Clear the compiled DI container cache.
     *
     * Call this from your deploy script after every deployment:
     *
     *   php -r "require 'vendor/autoload.php'; \Simp\Pindrop\Services\ServiceProvider::clearContainerCache();"
     *
     * Or wire it into a CLI command / admin panel button.
     * Returns the number of cache files deleted.
     */
    public static function clearContainerCache(): int
    {
        $cacheDir = rtrim(getenv('CACHE_DIR') ?: (dirname(__DIR__, 2) . '/var/cache/di'), '/');

        if (!is_dir($cacheDir)) {
            return 0;
        }

        $deleted = 0;
        $items   = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($cacheDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isFile()) {
                unlink($item->getPathname());
                $deleted++;
            } elseif ($item->isDir()) {
                rmdir($item->getPathname());
            }
        }

        return $deleted;
    }
    
    /**
     * Get environment service provider
     */
    public function getEnvProvider(): ?EnvServiceProvider
    {
        foreach ($this->providers as $provider) {
            if ($provider instanceof EnvServiceProvider) {
                return $provider;
            }
        }
        
        return null;
    }
    
    /**
     * Get logger service provider
     */
    public function getLoggerProvider(): ?LoggerServiceProvider
    {
        foreach ($this->providers as $provider) {
            if ($provider instanceof LoggerServiceProvider) {
                return $provider;
            }
        }
        
        return null;
    }
    
    /**
     * Get mail service provider
     */
    public function getMailProvider(): ?MailServiceProvider
    {
        foreach ($this->providers as $provider) {
            if ($provider instanceof MailServiceProvider) {
                return $provider;
            }
        }
        
        return null;
    }
    
    /**
     * Get schema service provider
     */
    public function getSchemaProvider(): ?SchemaServiceProvider
    {
        foreach ($this->providers as $provider) {
            if ($provider instanceof SchemaServiceProvider) {
                return $provider;
            }
        }
        
        return null;
    }
    
    /**
     * Get plugin service provider
     */
    public function getPluginProvider(): ?PluginServiceProvider
    {
        foreach ($this->providers as $provider) {
            if ($provider instanceof PluginServiceProvider) {
                return $provider;
            }
        }
        
        return null;
    }
    
    /**
     * Get config service provider
     */
    public function getConfigProvider(): ?ConfigServiceProvider
    {
        foreach ($this->providers as $provider) {
            if ($provider instanceof ConfigServiceProvider) {
                return $provider;
            }
        }
        
        return null;
    }
    
    /**
     * Get file system service provider
     */
    public function getFileSystemProvider(): ?FileSystemServiceProvider
    {
        foreach ($this->providers as $provider) {
            if ($provider instanceof FileSystemServiceProvider) {
                return $provider;
            }
        }
        
        return null;
    }
    
    /**
     * Get user service provider
     */
    public function getUserProvider(): ?UserServiceProvider
    {
        foreach ($this->providers as $provider) {
            if ($provider instanceof UserServiceProvider) {
                return $provider;
            }
        }
        
        return null;
    }
    
    /**
     * Get all registered providers
     */
    public function getProviders(): array
    {
        return $this->providers;
    }
    
    /**
     * Check if provider is registered
     */
    public function hasProvider(string $providerClass): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider instanceof $providerClass) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Remove a provider
     */
    public function removeProvider(string $providerClass): self
    {
        $this->providers = array_filter($this->providers, function($provider) use ($providerClass) {
            return !($provider instanceof $providerClass);
        });
        
        return $this;
    }
    
    /**
     * Get available services
     */
    public function getAvailableServices(): array
    {
        return [
            'env' => EnvServiceProvider::class,
            'config' => ConfigServiceProvider::class,
            'logger' => LoggerServiceProvider::class,
            'filesystem' => FileSystemServiceProvider::class,
            'mail' => MailServiceProvider::class,
            'schema' => SchemaServiceProvider::class,
            'plugin' => PluginServiceProvider::class,
            'routing' => RoutingServiceProvider::class,
            'database' => DatabaseServiceProvider::class,
            'user' => UserServiceProvider::class,
        ];
    }
    
    /**
     * Quick setup method for common use case
     */
    public static function create(): Container
    {
        return (new self())->buildContainer();
    }
}
