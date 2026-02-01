<?php

declare(strict_types=1);

namespace Simp\Pindrop\Services;

use DI\Container;
use DI\ContainerBuilder;
use Simp\Pindrop\Menu\MenuManager;
use Simp\Pindrop\Menu\MenuRenderer;
use Simp\Pindrop\Plugin\PluginManager;

/**
 * Menu Service Provider
 * 
 * Provides menu services for handling plugin menus and menu rendering.
 */
class MenuServiceProvider
{
    private EnvServiceProvider $envProvider;

    public function __construct(EnvServiceProvider $envProvider)
    {
        $this->envProvider = $envProvider;
    }

    public function configureContainer(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->addDefinitions([
            'menu.manager' => function (Container $container) {
                return new MenuManager($container->get('plugin.manager'), $container);
            },
            'menu.renderer' => function (Container $container) {
                return new MenuRenderer($container->get('menu.manager'));
            },
        ]);
    }
}
