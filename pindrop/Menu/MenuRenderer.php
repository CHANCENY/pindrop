<?php

namespace Simp\Pindrop\Menu;

use DI\Container;
use Simp\Pindrop\Plugin\PluginManager;



/**
 * Menu Renderer
 *
 * Handles menu rendering for templates.
 */
class MenuRenderer
{
    private MenuManager $menuManager;

    public function __construct(MenuManager $menuManager)
    {
        $this->menuManager = $menuManager;
    }

    /**
     * Render menu group as HTML
     */
    public function renderGroup(string $group, ?string $userRole = null): string
    {
        $menus = $this->menuManager->getMenusByGroup($group);
        $html = '';

        foreach ($menus as $menu) {
            // Generate URL from route name
            $url = $this->generateUrl($menu);
            
            $class = $menu['attributes']['class'] ?? '';
            $title = $menu['attributes']['title'] ?? $menu['title'];
            $weight = $menu['attributes']['weight'] ?? 999;

            $html .= sprintf(
                '<li class="menu-item" data-weight="%d" data-menu-id="%s">
                    <a href="%s" class="%s" title="%s">%s</a>
                </li>',
                $weight,
                htmlspecialchars($menu['id']),
                htmlspecialchars($url),
                htmlspecialchars($class),
                htmlspecialchars($title),
                htmlspecialchars($menu['title'])
            );
        }

        return $html;
    }

    /**
     * Generate URL from menu item
     */
    private function generateUrl(array $menu): string
    {
        // If explicit URL is provided, use it
        if (!empty($menu['url'])) {
            return $menu['url'];
        }

        // Try to resolve route name to URL
        $routeName = $menu['route_name'] ?? null;
        if ($routeName) {
            try {
                // Try to get URL from route provider
                if (function_exists('getRouteProvider')) {
                    $routeProvider = getRouteProvider();
                    $url = $this->generateUrlFromRouteProvider($routeProvider, $routeName);
                    if ($url) {
                        return $url;
                    }
                }
                
                // Fallback: try to get route from container
                $container = $this->menuManager->getContainer();
                if ($container && $container->has('route.provider')) {
                    $routeProvider = $container->get('route.provider');
                    $url = $this->generateUrlFromRouteProvider($routeProvider, $routeName);
                    if ($url) {
                        return $url;
                    }
                }
                
                // Final fallback: convert route name to path
                return '/' . str_replace('.', '/', $routeName);
                
            } catch (\Exception $e) {
                // If route resolution fails, use fallback
                return '/' . str_replace('.', '/', $routeName);
            }
        }

        // Final fallback
        return '#';
    }

    /**
     * Generate URL from RouteProvider
     */
    private function generateUrlFromRouteProvider($routeProvider, string $routeName): ?string
    {
        // Get route by name
        $route = $routeProvider->getRoute($routeName);
        if ($route && isset($route['path'])) {
            return $route['path'];
        }
        
        return null;
    }

    /**
     * Render all menus grouped
     */
    public function renderAll(?string $userRole = null): array
    {
        $grouped = $this->menuManager->getAccessibleMenus($userRole);
        $rendered = [];

        foreach ($grouped as $group => $menus) {
            $rendered[$group] = [];

            foreach ($menus as $menu) {
                $rendered[$group][] = [
                    'html' => $this->renderMenuItem($menu),
                    'menu' => $menu
                ];
            }
        }

        return $rendered;
    }

    /**
     * Render single menu item
     */
    private function renderMenuItem(array $menu): string
    {
        // Generate URL from route name
        $url = $this->generateUrl($menu);

        $class = $menu['attributes']['class'] ?? '';
        $title = $menu['attributes']['title'] ?? $menu['title'];
        $weight = $menu['attributes']['weight'] ?? 999;

        return sprintf(
            '<li class="menu-item" data-weight="%d" data-menu-id="%s">
                <a href="%s" class="%s" title="%s">%s</a>
            </li>',
            $weight,
            htmlspecialchars($menu['id']),
            htmlspecialchars($url),
            htmlspecialchars($class),
            htmlspecialchars($title),
            htmlspecialchars($menu['title'])
        );
    }

    /**
     * Get menu data for template rendering
     */
    public function getMenuData(?string $userRole = null): array
    {
        return $this->menuManager->getAccessibleMenus($userRole);
    }
}
