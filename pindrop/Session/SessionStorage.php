<?php

namespace Simp\Pindrop\Session;

class SessionStorage
{
     public static function add(array|string $keyComponent, $value): void
    {
        $name = is_string($keyComponent) ? $keyComponent : implode('_',$keyComponent);
        $_SESSION['private'][$name] = $value;
    }

    public static function get(array|string $keyComponent) {
        $name = is_string($keyComponent) ? $keyComponent : implode('_',$keyComponent);
        return !empty($_SESSION['private'][$name]) ? $_SESSION['private'][$name] : null;
    }

    public static function remove(array|string $keyComponent): void {
        $name = is_string($keyComponent) ? $keyComponent : implode('_',$keyComponent);
        if (!empty($_SESSION['private'][$name])) {
            unset($_SESSION['private'][$name]);
        }
    }
}
