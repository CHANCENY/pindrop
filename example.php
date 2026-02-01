<?php

use Simp\Pindrop\Services\ServiceProvider;

require 'vendor/autoload.php';

$serviceProvider = new ServiceProvider();
$container = $serviceProvider->buildContainer();

$container->get('plugin.services.register');

function getAppContainer(): \DI\Container
{
    global $container;
    return $container;
}

$factory = $container->get('content.factory');

/**@var \Simp\Pindrop\Modules\admin\src\Entity\Article $article **/
$article = $factory->storage('article');

dump($article->find(6)->getContent());
