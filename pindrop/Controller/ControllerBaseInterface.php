<?php

declare(strict_types=1);

namespace Simp\Pindrop\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface ControllerBaseInterface
{
    /**
     * Handle controller method
     * 
     * simple/router calls this method with Request and route_name parameters
     */
    public function handle(Request $request, string $route_name): Response;
}