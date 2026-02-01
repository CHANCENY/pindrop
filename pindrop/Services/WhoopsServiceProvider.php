<?php

declare(strict_types=1);

namespace Simp\Pindrop\Services;

use DI\Container;
use DI\ContainerBuilder;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Run;

/**
 * Whoops Service Provider
 * 
 * Provide Whoops error handling services for better debugging experience.
 */
class WhoopsServiceProvider
{
    private EnvServiceProvider $envProvider;

    public function __construct(EnvServiceProvider $envProvider)
    {
        $this->envProvider = $envProvider;
    }

    public function configureContainer(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->addDefinitions([
            'whoops' => function (Container $container) {
                $environment = getenv('APP_ENV') ?: 'development';
                $debug = getenv('DEBUG') ?: 'true';
                
                if ($environment === 'production' || $debug !== 'true') {
                    return null; // Don't register Whoops in production
                }
                
                $whoops = new Run();
                
                // Add pretty page handler for browser requests
                $prettyPageHandler = new PrettyPageHandler();
                $prettyPageHandler->setPageTitle('Pindrop - Something went wrong!');
                $prettyPageHandler->addDataTable('Pindrop Environment', [
                    'Environment' => $environment,
                    'Debug Mode' => $debug,
                    'Timezone' => date_default_timezone_get(),
                    'PHP Version' => PHP_VERSION,
                    'App Directory' => dirname(__DIR__, 2),
                ]);
                
                // Add JSON handler for AJAX requests
                $jsonHandler = new JsonResponseHandler();
                $jsonHandler->setJsonApi(true);
                
                $whoops->pushHandler($prettyPageHandler);
                $whoops->pushHandler($jsonHandler);
                
                return $whoops;
            },
        ]);
    }

}
