<?php

namespace Simp\Pindrop\Events;

use AllowDynamicProperties;

#[AllowDynamicProperties]
class EventEmitter
{
    public function __get($name)
    {
        if (property_exists($this, $name)) return $this->$name;
        return null;
    }

    public function __set($name, $value) 
    {
        $this->$name = $value;
    }
}