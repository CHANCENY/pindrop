<?php

declare(strict_types=1);

namespace Simp\Pindrop\Routing;

use Simp\Pindrop\Events\SystemEvents\Events;
use Simp\Pindrop\Message\Message;
use Simp\Pindrop\Plugin\PluginManager;
use Simp\Pindrop\Routing\Route;
use Simp\Pindrop\Settings\Settings;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * RouteProvider
 * 
 * Main class for registering routes with support for all HTTP methods
 * using the upgraded simp/router package with our custom Route override.
 */
class RouteProvider
{
    private Route $router;
    private array $routes = [];

    public function __construct(string|array|null $middleware_register = null)
    {
        $this->router = new Route($middleware_register);
    }

    /**
     * Register a GET route
     */
    public function get(string $path, string $route_name, string|object $controller, array $options = []): self
    {
        $this->routes[] = [
            'method' => 'GET',
            'path' => $path,
            'route_name' => $route_name,
            'controller' => $controller,
            'options' => $options
        ];
        return $this;
    }

    /**
     * Register a POST route
     */
    public function post(string $path, string $route_name, string|object $controller, array $options = []): self
    {
        $this->routes[] = [
            'method' => 'POST',
            'path' => $path,
            'route_name' => $route_name,
            'controller' => $controller,
            'options' => $options
        ];
        return $this;
    }

    /**
     * Register a PUT route
     */
    public function put(string $path, string $route_name, string|object $controller, array $options = []): self
    {
        $this->routes[] = [
            'method' => 'PUT',
            'path' => $path,
            'route_name' => $route_name,
            'controller' => $controller,
            'options' => $options
        ];
        return $this;
    }

    /**
     * Register a DELETE route
     */
    public function delete(string $path, string $route_name, string|object $controller, array $options = []): self
    {
        $this->routes[] = [
            'method' => 'DELETE',
            'path' => $path,
            'route_name' => $route_name,
            'controller' => $controller,
            'options' => $options
        ];
        return $this;
    }

    /**
     * Register a PATCH route
     */
    public function patch(string $path, string $route_name, string|object $controller, array $options = []): self
    {
        $this->routes[] = [
            'method' => 'PATCH',
            'path' => $path,
            'route_name' => $route_name,
            'controller' => $controller,
            'options' => $options
        ];
        return $this;
    }

    /**
     * Register an OPTIONS route
     */
    public function options(string $path, string $route_name, string|object $controller, array $options = []): self
    {
        $this->routes[] = [
            'method' => 'OPTIONS',
            'path' => $path,
            'route_name' => $route_name,
            'controller' => $controller,
            'options' => $options
        ];
        return $this;
    }

    /**
     * Register a route for any HTTP method
     */
    public function any(string $path, string $route_name, string|object $controller, array $options = []): self
    {
        $this->routes[] = [
            'method' => 'ANY',
            'path' => $path,
            'route_name' => $route_name,
            'controller' => $controller,
            'options' => $options
        ];
        return $this;
    }

    /**
     * Register multiple routes at once
     */
    public function group(array $routes): self
    {
        foreach ($routes as $route) {
            $method = strtolower($route['method'] ?? 'get');
            $path = $route['path'] ?? '/';
            $route_name = $route['route_name'] ?? 'default';
            $controller = $route['controller'];
            $options = $route['options'] ?? [];

            $this->$method($path, $route_name, $controller, $options);
        }
        return $this;
    }

    /**
     * Dispatch all registered routes
     */
    public function dispatch(): Response|JsonResponse|RedirectResponse|null
    {
        try{

            // csrf token generator
            $request = Request::createFromGlobals();
            \appEvents()->invokeEvents(Events::REQUEST_RECEIVED, ['request' => $request]);

            /**@var PluginManager $pluginManager **/
            $pluginManager = \getAppContainer()->get('plugin.manager');
            $pluginManager->requireModulesFile();

            // default requirement is module admin if dont exist response here
            if (!$pluginManager->isPluginEnabled('admin')) {
                $response = new RedirectResponse("/docs");
                $response->send();
                return $response;
            }

            if (\getAppContainer()->has('language.support.service')) {
                $sLanguages = \getAppContainer()->get('language.support.service')->languages;
                foreach ($this->routes as $k=>$route) {

                    foreach ($sLanguages as $lang=>$sLanguage) {
                        $cloneRoute =  $route;
                        $cloneRoute['path'] = "/{$lang}{$route['path']}";
                        $this->routes["{$k}{$lang}"] = $cloneRoute;
                    }

                }
            }

            $csrfToken = null;
            if (!empty($_ENV['CSRF_TOKEN_SECRET'])) {
                $csrfToken = $this->generateToken($request, $_ENV['CSRF_TOKEN_SECRET']);
            }

            if (!empty($csrfToken) && $request->getMethod() === 'POST' && !$request->isXmlHttpRequest()) {

                $submittedToken = $request->request->get('_csrf_token');
                if ($request->getContentTypeFormat() === 'json') {
                    $submittedToken = json_decode($request->getContent(), true)['_csrf_token'] ?? null;
                }

                if (!$submittedToken) {
                    throw new \RuntimeException('Missing CSRF token in body data with key _csrf_token');
                }

                $secret = $_ENV['CSRF_TOKEN_SECRET'];

                // Generate expected tokens (current + previous 10-min window)
                $expected = $this->generateToken($request, $secret, 0);   // current window
                $previous = $this->generateToken($request, $secret, -1);  // previous window

                if (!hash_equals($expected, $submittedToken) && !hash_equals($previous, $submittedToken)) {
                    \appEvents()->invokeEvents(Events::REQUEST_HANDLED, ['request' => $request, 'csrfValidation' => 'failed']);
                    Message::warn("Form has expired please refresh the page and try again.");
                    $redirectResponse = new RedirectResponse($request->headers->get('referer', '/'));
                    \appEvents()->invokeEvents(Events::RESPONSE_BEFORE_SEND, ['response' => &$redirectResponse]);
                    $redirectResponse->send();
                    \appEvents()->invokeEvents(Events::RESPONSE_SENT, ['response' => $redirectResponse]);
                    return $redirectResponse;
                }
            }

            // Register all routes with the router immediately
            foreach ($this->routes as $route) {
                $method = strtolower($route['method']);
                $path = $route['path'];
                $route_name = $route['route_name'];
                $controller = $route['controller'];
                $options = $route['options'];
                if ($method === 'any') {
                    $this->router->any($path, $route_name, $controller, $options);
                } else {
                    $this->router->$method($path, $route_name, $controller, $options);
                }
            }

            $response = $this->router->getResponse();

            \appEvents()->invokeEvents(Events::RESPONSE_BEFORE_SEND, ['response' => &$response ]);

            if ($response instanceof Response) {
                $response = $this->injectCsrfToken($response, $csrfToken);
            }

            $response->send();

            \appEvents()->invokeEvents(Events::RESPONSE_SENT, ['response' => $response]);

            // Return null since the response is already sent by the router
            return null;
        }catch (\Throwable $exception){
            /**@var Settings $setings **/
            $settings =  \getAppContainer()->get(Settings::class);
            $pageNotFoundTemplate = $settings->getSetting('admin.settings')?->get('page_not_error');
            if ($pageNotFoundTemplate) {
                $response = new Response(\getAppContainer()->get('twig')->render($pageNotFoundTemplate));
                \appEvents()->invokeEvents(Events::RESPONSE_BEFORE_SEND, ['response' => &$response, 'exception' => $exception]);
                $response->send();
                \appEvents()->invokeEvents(Events::RESPONSE_SENT, ['response' => &$response]);
            }
            else {
                $environment = getenv('APP_ENV') ?: 'development';
                $debug = getenv('DEBUG') ?: 'true';
                $debug = (bool)$debug;

                if ($environment !== 'production' && $debug === true){
                    $whoops = \getAppContainer()->get('whoops');
                    if ($whoops instanceof \Whoops\Run) {
                        $whoops->handleException($exception);
                    }
                }
                else {
                    die("unexpected error occurred");
                }

            }
        }
        return null;
    }

    private function generateToken(Request $request, $secret, $offset = 0): string
    {
        $ip = $request->getClientIp();
        $ua = $request->headers->get('User-Agent');

        $timeWindow = floor(time() / 600) + $offset;

        return hash_hmac('sha256', $ip . '|' . $ua . '|' . $timeWindow, $secret);
    }

    private function injectCsrfToken(Response $response, string $csrfToken): Response
    {
        $contentType = $response->headers->get('Content-Type');

        // Only process HTML responses
        $content = $response->getContent();

        if(!str_starts_with($content, "<!DOCTYPE html>")){
            return $response;
        }

        if (!$content) {
            return $response;
        }

        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();

        // Handle encoding safely
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $forms = $dom->getElementsByTagName('form');

        foreach ($forms as $form) {

            $method = strtolower($form->getAttribute('method'));

            // Only POST forms
            if ($method !== 'post') {
                continue;
            }

            // Prevent duplicate token
            $alreadyExists = false;

            foreach ($form->getElementsByTagName('input') as $input) {
                if ($input->getAttribute('name') === '_csrf_token') {
                    $alreadyExists = true;
                    break;
                }
            }

            if ($alreadyExists) {
                continue;
            }

            // Create hidden input
            $input = $dom->createElement('input');
            $input->setAttribute('type', 'hidden');
            $input->setAttribute('name', '_csrf_token');
            $input->setAttribute('value', $csrfToken);

            // Insert as first child (cleaner)
            if ($form->firstChild) {
                $form->insertBefore($input, $form->firstChild);
            } else {
                $form->appendChild($input);
            }
        }

        // Save updated HTML
        $response->setContent($dom->saveHTML());

        libxml_clear_errors();

        return $response;
    }

    /**
     * Get all registered routes
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Clear all registered routes
     */
    public function clear(): self
    {
        $this->routes = [];
        return $this;
    }

    /**
     * Get routes by method
     */
    public function getRoutesByMethod(string $method): array
    {
        return array_filter($this->routes, fn($route) => 
            strtolower($route['method']) === strtolower($method) || $route['method'] === 'ANY'
        );
    }

    /**
     * Check if route exists
     */
    public function hasRoute(string $route_name): bool
    {
        return !empty(array_filter($this->routes, fn($route) => 
            $route['route_name'] === $route_name
        ));
    }

    /**
     * Get route by name
     */
    public function getRoute(string $route_name): ?array
    {
        $found = array_filter($this->routes, fn($route) => 
            $route['route_name'] === $route_name
        );
        
        return !empty($found) ? reset($found) : null;
    }
}
