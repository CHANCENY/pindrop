<?php

namespace Simp\Pindrop\Routing;

interface AttributeRouteInterface
{
    public function __construct(string $path, string|array $method,  ...$options);

    public function isValidRoute(): bool;

    public function routeDefinition(): array;

    public function getName(): string;
}
