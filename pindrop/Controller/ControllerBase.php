<?php

declare(strict_types=1);

namespace Simp\Pindrop\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Simp\Pindrop\Templating\TwigEngine;

/**
 * ControllerBase
 * 
 * Base controller class implementing the ControllerBaseInterface.
 * Provides common functionality for all controllers.
 */
class ControllerBase implements ControllerBaseInterface
{
    protected TwigEngine $twig;
    
    public function __construct()
    {
        $this->twig = getAppContainer()->get('twig');
    }
    /**
     * Handle controller method
     * 
     * simple/router calls this method with Request and route_name parameters.
     * This method should be overridden by child controllers.
     */
    public function handle(Request $request, string $route_name): Response
    {
        // Default implementation - return a basic response
        return new Response('Controller method not implemented', 501);
    }
    
    /**
     * Render a template with data
     */
    protected function render(string $content, int $status = 200, array $headers = []): Response
    {
        return new Response($content, $status, $headers);
    }
    
    /**
     * Render Twig template with data
     */
    protected function renderTwig(string $template, array $data = [], int $status = 200, array $headers = []): Response
    {
        try {
            $content = $this->twig->render($template, $data);
            return new Response($content, $status, $headers);
        } catch (\Exception $e) {
            // Fallback to error template or simple error message
            $errorContent = $this->renderError($e);
            return new Response($errorContent, 500, $headers);
        }
    }
    
    /**
     * Render Twig template from string
     */
    protected function renderString(string $template, array $data = [], int $status = 200, array $headers = []): Response
    {
        try {
            $content = $this->twig->renderString($template, $data);
            return new Response($content, $status, $headers);
        } catch (\Exception $e) {
            $errorContent = $this->renderError($e);
            return new Response($errorContent, 500, $headers);
        }
    }
    
    /**
     * Check if template exists
     */
    protected function templateExists(string $template): bool
    {
        return $this->twig->exists($template);
    }
    
    /**
     * Render error page
     */
    private function renderError(\Exception $e): string
    {
        // Try to render error template
        if ($this->templateExists('error.twig')) {
            
            return $this->twig->render('error.twig', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        // Fallback to simple error message
        return sprintf(
            '<h1>Template Error</h1><p>%s</p><pre>%s</pre>',
            htmlspecialchars($e->getMessage()),
            htmlspecialchars($e->getTraceAsString())
        );
    }
    
    /**
     * Render JSON response
     */
    protected function json(array $data, int $status = 200, array $headers = []): Response
    {
        $headers['Content-Type'] = 'application/json';
        return new Response(json_encode($data), $status, $headers);
    }
    
    /**
     * Redirect to another URL
     */
    protected function redirect(string $url, int $status = 302): Response
    {
        return new \Symfony\Component\HttpFoundation\RedirectResponse($url, $status);
    }

    public function __toString(): string
    {
        return static::class;
    }

    public function getService(string $serviceName)
    {
        if (!getAppContainer()->has($serviceName)) {
            throw new \InvalidArgumentException("Service '$serviceName' not found in the application container.");
        }
        return getAppContainer()->get($serviceName);
    }


}