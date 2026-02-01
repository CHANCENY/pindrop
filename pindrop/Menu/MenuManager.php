<?php

namespace Simp\Pindrop\Menu;

use DI\Container;
use Simp\Pindrop\Plugin\PluginManager;

/**
 * Menu Manager
 *
 * Handles menu collection, sorting, and filtering.
 */
class MenuManager
{
    private PluginManager $pluginManager;
    private Container $container;
    private array $menus = [];
    private array $sortedMenus = [];

    public function __construct(PluginManager $pluginManager, Container $container)
    {
        $this->pluginManager = $pluginManager;
        $this->container = $container;
        $this->initializeMenus();
    }

    /**
     * Initialize menus from all enabled plugins
     */
    private function initializeMenus(): void
    {
        $pluginMenus = $this->pluginManager->getPluginMenus();
        $currentUserRole = $this->getCurrentUserRole();

        foreach ($pluginMenus as $pluginId => $menus) {
            // Only include menus from enabled plugins
            if ($this->pluginManager->isPluginEnabled($pluginId)) {
                // Handle new group-based structure
                if (isset($menus['groups'])) {
                    foreach ($menus['groups'] as $groupName => $groupMenus) {
                        foreach ($groupMenus as $menuId => $menuConfig) {
                            // Check if current user has access to this menu
                            if ($this->hasAccessToMenu($menuConfig, $currentUserRole)) {
                                $this->menus[$menuId] = [
                                    'id' => $menuId,
                                    'title' => $menuConfig['title'] ?? $menuId,
                                    'route_name' => $menuConfig['route'] ?? null,
                                    'url' => $menuConfig['url'] ?? null,
                                    'attributes' => [
                                        'class' => $menuConfig['icon'] ?? '',
                                        'title' => $menuConfig['title'] ?? '',
                                        'weight' => $menuConfig['weight'] ?? 999,
                                        'group' => $groupName,
                                        'roles' => $menuConfig['roles'] ?? []
                                    ],
                                    'plugin' => $pluginId,
                                ];
                            }
                        }
                    }
                } else {
                    // Handle legacy flat structure for backward compatibility
                    foreach ($menus as $menuId => $menuConfig) {
                        // Check if current user has access to this menu
                        if ($this->hasAccessToMenu($menuConfig, $currentUserRole)) {
                            $this->menus[$menuId] = [
                                'id' => $menuId,
                                'title' => $menuConfig['title'] ?? $menuId,
                                'route_name' => $menuConfig['route_name'] ?? null,
                                'url' => $menuConfig['url'] ?? null,
                                'attributes' => $menuConfig['attributes'] ?? [],
                                'plugin' => $pluginId,
                            ];
                        }
                    }
                }
            }
        }

        $this->sortMenus();
    }

    /**
     * Check if user has access to menu based on role configuration
     */
    private function hasAccessToMenu(array $menuConfig, ?string $userRole): bool
    {
        // Handle new structure
        $requiredRoles = $menuConfig['roles'] ?? null;
        
        // Handle legacy structure
        if ($requiredRoles === null) {
            $requiredRoles = $menuConfig['attributes']['role'] ?? null;
        }

        // If no role required, everyone has access
        if (!$requiredRoles) {
            return true;
        }

        // If no user role, deny access
        if (!$userRole) {
            return false;
        }

        // Support multiple roles (array or comma-separated string)
        $allowedRoles = $requiredRoles;
        if (is_string($requiredRoles)) {
            $allowedRoles = array_map('trim', explode(',', $requiredRoles));
        }

        // Allow access if user role is in allowed roles
        // Admin users can access all menus (fallback for admin-level access)
        return in_array($userRole, (array) $allowedRoles) || $userRole === 'admin';
    }

    /**
     * Sort menus by weight and title
     */
    private function sortMenus(): void
    {
        // Group menus by group
        $grouped = [];
        foreach ($this->menus as $menuId => $menu) {
            $group = $menu['attributes']['group'] ?? 'default';
            $weight = $menu['attributes']['weight'] ?? 999;
            $title = $menu['title'];

            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }

            $grouped[$group][$menuId] = [
                'weight' => $weight,
                'title' => $title,
                'menu' => $menu
            ];
        }

        // Sort each group by weight, then by title
        foreach ($grouped as $group => $menus) {
            uasort($menus, function ($a, $b) {
                if ($a['weight'] !== $b['weight']) {
                    return $a['weight'] <=> $b['weight'];
                }
                return strcasecmp($a['title'], $b['title']);
            });

            // Extract just the menu data
            $grouped[$group] = array_map(function ($item) {
                return $item['menu'];
            }, $menus);
        }

        $this->sortedMenus = $grouped;
    }

    /**
     * Get all menus grouped by group
     */
    public function getGroupedMenus(): array
    {
        return $this->sortedMenus;
    }

    /**
     * Get menus by group
     */
    public function getMenusByGroup(string $group): array
    {
        return $this->sortedMenus[$group] ?? [];
    }

    /**
     * Get all menus (flat)
     */
    public function getAllMenus(): array
    {
        return $this->menus;
    }

    /**
     * Get a specific menu by ID
     */
    public function getMenu(string $menuId): ?array
    {
        return $this->menus[$menuId] ?? null;
    }

    /**
     * Get current user role
     */
    private function getCurrentUserRole(): ?string
    {
        try {
            if ($this->container->has('current_user')) {
                $user = $this->container->get('current_user');
                return $user?->getUser()?->getRole() ?? null;
            }
        } catch (\Exception $e) {
            // User not available
        }

        return null;
    }

    /**
     * Get DI container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Get filtered menus that user has access to
     */
    public function getAccessibleMenus(?string $userRole = null): array
    {
        // Menus are already filtered by user role during initialization
        // Just return the grouped menus
        return $this->sortedMenus;
    }
}