<?php

declare(strict_types=1);

namespace Simp\Pindrop\Session;

use DI\ContainerBuilder;
use Simp\Pindrop\Services\EnvServiceProvider;

/**
 * SessionServiceProvider
 *
 * Registers session-related services into the PHP-DI container so that
 * controllers and plugins can inject them cleanly.
 *
 * Registration — add one line to ServiceProvider::registerDefaultProviders():
 *
 *   $this->providers[] = new \Simp\Pindrop\Session\SessionServiceProvider($envProvider);
 *
 * And one elseif branch to ServiceProvider::buildContainer() (follow the
 * existing pattern for the other providers):
 *
 *   } elseif ($provider instanceof \Simp\Pindrop\Session\SessionServiceProvider) {
 *       $provider->configureContainer($builder);
 *   }
 */
class SessionServiceProvider
{
    private EnvServiceProvider $envProvider;

    public function __construct(EnvServiceProvider $envProvider)
    {
        $this->envProvider = $envProvider;
    }

    public function configureContainer(ContainerBuilder $builder): void
    {
        $builder->addDefinitions([

            /**
             * The DatabaseSessionHandler singleton.
             *
             * bootstrap.inc already created one and registered it with PHP's
             * session engine.  Here we expose the same class via the DI
             * container so that admin dashboards or CLI commands can call
             * countActiveSessions() or destroyUserSessions() without
             * duplicating the PDO setup.
             */
            DatabaseSessionHandler::class => function () {
                // createFromEnv() is idempotent — env vars are already set.
                return DatabaseSessionHandler::createFromEnv();
            },

            /**
             * Convenience alias.
             */
            'session.handler' => \DI\get(DatabaseSessionHandler::class),

            /**
             * Session storage wrapper — wraps the static SessionStorage class
             * into a service so it can be injected and tested.
             */
            'session.storage' => function () {
                return new SessionStorage();
            },

            /**
             * Session configuration — useful to expose to plugins.
             */
            'session.config' => function () {
                return [
                    'lifetime'    => (int)(ini_get('session.gc_maxlifetime') ?: 1440),
                    'cookie_name' => session_name(),
                    'backend'     => 'database',
                ];
            }
            
        ]);
    }
}
