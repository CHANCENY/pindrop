<?php

declare(strict_types=1);

namespace Simp\Pindrop\Database;

/**
 * CurrentUserResolver
 *
 * Resolves the currently authenticated user without creating a hard
 * dependency on the User entity or the session system inside the
 * database layer.
 *
 * Uses a late-binding closure stored at boot time (in bootstrap.inc)
 * so that Database → User → Database circular dependencies are avoided.
 *
 * If no resolver is registered (CLI, migrations, tests) getCurrentUser()
 * returns null, which DatabasePermissionGuard treats as "system context"
 * and allows all operations.
 */
class CurrentUserResolver
{
    /** @var callable|null */
    private static $resolver = null;

    /**
     * Register the user resolver at boot time.
     *
     * Call this in bootstrap.inc after the container is built:
     *
     *   CurrentUserResolver::register(function () use ($container) {
     *       try {
     *           return $container->get('current.user');   // returns User|null
     *       } catch (\Throwable) {
     *           return null;
     *       }
     *   });
     */
    public static function register(callable $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * Get the currently authenticated user, or null if unauthenticated
     * or in a system context (CLI, migrations).
     */
    public function getCurrentUser(): ?object
    {
        if (self::$resolver === null) {
            return null;
        }
        try {
            return (self::$resolver)();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Reset the resolver — useful in tests.
     */
    public static function reset(): void
    {
        self::$resolver = null;
    }
}
