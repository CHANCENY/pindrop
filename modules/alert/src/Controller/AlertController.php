<?php

declare(strict_types=1);

namespace Simp\Pindrop\Modules\alert\src\Controller;


use Simp\Pindrop\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alert Controller
 * 
 * Handles alert-related routes and requests.
 */
class AlertController extends ControllerBase
{
    public function settingsPanel(Request $request, string $route_name): Response
    {
        return $this->render("<h1>Hello from Alert Controller</h1>");
    }
}