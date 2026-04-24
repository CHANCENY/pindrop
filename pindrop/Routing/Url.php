<?php

namespace Simp\Pindrop\Routing;

use Symfony\Component\HttpFoundation\Request;

class Url
{
    protected RouteProvider $routeProvider;
    protected Request $request;

    public function __construct(RouteProvider $routeProvider, Request $request)
    {
        $this->routeProvider = $routeProvider;
        $this->request = $request;
    }

    public function buildFromRouteName(string $name, array $params = [], bool $absolute = false)
    {
        $route = $this->routeProvider->getRoute($name);
        
        if (!$route) {
            throw new \InvalidArgumentException("Route '{$name}' not found");
        }

        $path = $route['path'] ?? '/';
        
        // Extract placeholders from path (format: [param:type])
        $placeholders = [];
        $pathWithoutPlaceholders = $path;
        
        // Find all placeholders manually
        $pos = 0;
        while (($pos = strpos($path, '[', $pos)) !== false) {
            $endPos = strpos($path, ']', $pos);
            if ($endPos !== false) {
                $placeholderContent = substr($path, $pos + 1, $endPos - $pos - 1);
                $parts = explode(':', $placeholderContent);
                $placeholderName = $parts[0];
                $placeholders[] = $placeholderName;
                $pos = $endPos + 1;
            } else {
                break;
            }
        }
        
        // Replace placeholders in path
        $usedParams = [];
        foreach ($placeholders as $placeholder) {
            if (isset($params[$placeholder])) {
                $pathWithoutPlaceholders = str_replace(
                    '[' . $placeholder . ']',
                    (string) $params[$placeholder],
                    $pathWithoutPlaceholders
                );
                // Also replace with type suffix if present
                $pathWithoutPlaceholders = str_replace(
                    '[' . $placeholder . ':int]',
                    (string) $params[$placeholder],
                    $pathWithoutPlaceholders
                );
                $pathWithoutPlaceholders = str_replace(
                    '[' . $placeholder . ':string]',
                    (string) $params[$placeholder],
                    $pathWithoutPlaceholders
                );
                $usedParams[] = $placeholder;
            }
        }
        
        // Remove any remaining placeholders that weren't provided
        $pos = 0;
        while (($pos = strpos($pathWithoutPlaceholders, '[', $pos)) !== false) {
            $endPos = strpos($pathWithoutPlaceholders, ']', $pos);
            if ($endPos !== false) {
                $pathWithoutPlaceholders = substr_replace($pathWithoutPlaceholders, '', $pos, $endPos - $pos + 1);
            } else {
                break;
            }
        }
        
        // Add unused parameters as query string
        $queryParams = [];
        foreach ($params as $key => $value) {
            if (!in_array($key, $usedParams)) {
                $queryParams[$key] = $value;
            }
        }
        
        // Build final URL
        $finalPath = $pathWithoutPlaceholders;
        if (!empty($queryParams)) {
            $finalPath .= '?' . http_build_query($queryParams);
        }

        return $absolute ? $this->request->getSchemeAndHttpHost() . $finalPath : $finalPath;
    }

    public function buildFromRouteUri(string $uri, array $params = [], bool $absolute = false)
    {
        $path = $uri;
        
        // Extract placeholders from path (format: [param:type])
        $placeholders = [];
        $pathWithoutPlaceholders = $path;
        
        // Find all placeholders manually
        $pos = 0;
        while (($pos = strpos($path, '[', $pos)) !== false) {
            $endPos = strpos($path, ']', $pos);
            if ($endPos !== false) {
                $placeholderContent = substr($path, $pos + 1, $endPos - $pos - 1);
                $parts = explode(':', $placeholderContent);
                $placeholderName = $parts[0];
                $placeholders[] = $placeholderName;
                $pos = $endPos + 1;
            } else {
                break;
            }
        }
        
        // Replace placeholders in path
        $usedParams = [];
        foreach ($placeholders as $placeholder) {
            if (isset($params[$placeholder])) {
                $pathWithoutPlaceholders = str_replace(
                    '[' . $placeholder . ']',
                    (string) $params[$placeholder],
                    $pathWithoutPlaceholders
                );
                // Also replace with type suffix if present
                $pathWithoutPlaceholders = str_replace(
                    '[' . $placeholder . ':int]',
                    (string) $params[$placeholder],
                    $pathWithoutPlaceholders
                );
                $pathWithoutPlaceholders = str_replace(
                    '[' . $placeholder . ':string]',
                    (string) $params[$placeholder],
                    $pathWithoutPlaceholders
                );
                $usedParams[] = $placeholder;
            }
        }
        
        // Remove any remaining placeholders that weren't provided
        $pos = 0;
        while (($pos = strpos($pathWithoutPlaceholders, '[', $pos)) !== false) {
            $endPos = strpos($pathWithoutPlaceholders, ']', $pos);
            if ($endPos !== false) {
                $pathWithoutPlaceholders = substr_replace($pathWithoutPlaceholders, '', $pos, $endPos - $pos + 1);
            } else {
                break;
            }
        }
        
        // Add unused parameters as query string
        $queryParams = [];
        foreach ($params as $key => $value) {
            if (!in_array($key, $usedParams)) {
                $queryParams[$key] = $value;
            }
        }
        
        // Build final URL
        $finalPath = $pathWithoutPlaceholders;
        if (!empty($queryParams)) {
            $finalPath .= '?' . http_build_query($queryParams);
        }

        return $absolute ? $this->request->getSchemeAndHttpHost() . $finalPath : $finalPath;
    }

    public function getCurrent(array $params = [], bool $absolute = false)
    {
        $uri = $this->request->getRequestUri();
        $queryString = $this->request->getQueryString();
        
        // Parse current query string
        $currentParams = [];
        if ($queryString) {
            parse_str($queryString, $currentParams);
        }
        
        // Merge with new params
        $mergedParams = array_merge($currentParams, $params);
        
        // Build new query string
        $newQueryString = empty($mergedParams) ? '' : '?' . http_build_query($mergedParams);
        
        $path = $this->request->getPathInfo() . $newQueryString;
        
        return $absolute ? $this->request->getSchemeAndHttpHost() . $path : $path;
    }

    public static function routeByName(string $name, array $params = [], bool $absolute = false)
    {
        // Create instance with default providers and call instance method
        $routeProvider = function_exists('getRouteProvider') ? getRouteProvider() : null;
        $request = Request::createFromGlobals();
        
        if ($routeProvider) {
            $instance = new self($routeProvider, $request);
            return $instance->buildFromRouteName($name, $params, $absolute);
        }
        
        // Fallback: simple path conversion
        $path = '/' . str_replace('.', '/', $name);
        
        // Add parameters as query string if provided
        if (!empty($params)) {
            $path .= '?' . http_build_query($params);
        }
        
        return $path;
    }

    public static function routeByUri(string $uri, array $params = [], bool $absolute = false) {
        // Create instance with default providers and call instance method
        $routeProvider = function_exists('getRouteProvider') ? getRouteProvider() : null;
        $request = Request::createFromGlobals();
        
        $instance = new self($routeProvider, $request);
        return $instance->buildFromRouteUri($uri, $params, $absolute);
    }

    public static function current(array $params = [], bool $absolute = false) {
        // Create instance with default providers and call instance method
        $routeProvider = function_exists('getRouteProvider') ? getRouteProvider() : null;
        $request = Request::createFromGlobals();
        
        $instance = new self($routeProvider, $request);
        return $instance->getCurrent($params, $absolute);
    }

    /**
     * Simple static method to generate URL from route name
     */
    public static function generate(string $routeName, array $params = [], bool $absolute = false): string
    {
        return self::routeByName($routeName, $params, $absolute);
    }

    public static function generateToken(Request $request, $secret, $offset = 0): string
    {
        $ip = $request->getClientIp();
        $ua = $request->headers->get('User-Agent');

        $timeWindow = floor(time() / 600) + $offset;

        return hash_hmac('sha256', $ip . '|' . $ua . '|' . $timeWindow, $secret);
    }
}