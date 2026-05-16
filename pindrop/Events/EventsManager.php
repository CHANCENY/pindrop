<?php

namespace Simp\Pindrop\Events;

use DI\DependencyException;
use DI\NotFoundException;
use Simp\Pindrop\Events\SystemEvents\Events;
use Simp\Pindrop\Plugin\PluginManager;

class EventsManager
{
    protected PluginManager $pluginManager;
    protected array $events;
    protected array $eventListeners;

    public function __construct(PluginManager $pluginManager){
        $this->pluginManager = $pluginManager;
        $this->events = $this->loadEvents();
        $this->eventListeners = $this->loadSubscribers();
    }

    protected function loadEvents(): array
    {
        // load all defaults events
        $defaultEvents = new \ReflectionClass(Events::class);

        // get all constants
        $events = $defaultEvents->getConstants();

        // load events defined in plugins
        $pluginEventsFiles = array_map(function ($plugin): ?string {
            $path = $plugin['path'] . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . "Plugin" . DIRECTORY_SEPARATOR . "Events" . DIRECTORY_SEPARATOR . "Events.php";
            if (file_exists($path)) {
                $namespace = null;
                $className = null;
                $lines = file($path); // read file into lines
                foreach ($lines as $line) {
                    $line = trim($line);

                    // match namespace
                    if (preg_match('/^namespace\s+(.+);$/', $line, $matches)) {
                        $namespace = $matches[1];
                    }

                    // match class
                    if (preg_match('/^class\s+([a-zA-Z0-9_]+)/', $line, $matches)) {
                        $className = $matches[1];
                        break; // found class, no need to continue
                    }
                }
                if ($namespace && $className) {
                    // fully qualified class name
                    return $namespace . '\\' . $className;
                }
            }
            return null;
        },$this->pluginManager->getEnabledPlugins());

        foreach (array_filter($pluginEventsFiles) as $file) {

           if (!empty($file) && class_exists($file)) {
               $reflection = new \ReflectionClass($file);
               $eventTemp = $reflection->getConstants();
               foreach ($eventTemp as $key=>$event) {
                   if (!array_key_exists($key, $events) && !in_array($event, $events)) {
                       $events[$key] = $event;
                   }
               }
           }
        }

        return $events;
    }

    protected function loadSubscribers(): array
    {
        $pluginEventsSubscriberFiles = array_map(function ($plugin): ?string {
            $path = $plugin['path'] . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . "Plugin" . DIRECTORY_SEPARATOR . "Events" . DIRECTORY_SEPARATOR . "EventsSubscriber.php";
            if (file_exists($path)) {
                $namespace = null;
                $className = null;
                $lines = file($path); // read file into lines
                foreach ($lines as $line) {
                    $line = trim($line);

                    // match namespace
                    if (preg_match('/^namespace\s+(.+);$/', $line, $matches)) {
                        $namespace = $matches[1];
                    }

                    // match class
                    if (preg_match('/^class\s+([a-zA-Z0-9_]+)/', $line, $matches)) {
                        $className = $matches[1];
                        break; // found class, no need to continue
                    }
                }
                if ($namespace && $className) {
                    // fully qualified class name
                    return $namespace . '\\' . $className;
                }
            }
            return null;
        }, $this->pluginManager->getEnabledPlugins());

        $subscribers = [];
        foreach (array_filter($pluginEventsSubscriberFiles) as $file) {
            if (!empty($file) && class_exists($file)) {
                $reflection = new \ReflectionClass($file);
                $object = $reflection->newInstance();
                if ($object instanceof EventsSubscriberInterface) {
                    $subs = $object->getSubscribedEvents();
                    foreach ($this->events as $key=>$event) {
                        if (!empty($subs[$event])) {
                            $subscribers[$event][] = $subs[$event];
                        }
                    }
                }
            }
        }

        return $subscribers;
    }

    public function invokeEvents(string $eventName, array $eventArguments = []): array
    {
        $this->events = $this->loadEvents();
        $this->eventListeners = $this->loadSubscribers();

        /* The line `         = new EventEmitter();` is creating a new instance of the
        `EventEmitter` class. This instance will be used to emit events and pass event data to event
        subscribers. The `EventEmitter` class likely contains methods to manage and trigger events
        within the application. */
        $eventEmitter = new EventEmitter();
        $eventEmitter->options = [];
      
        foreach ($eventArguments as $key=>$eventArgument) {
            if (is_string($key)) {
                $eventEmitter->$key = $eventArgument;
            }
            elseif (is_numeric($key)) {
                $eventEmitter->options[$key] = $eventArgument;
            }
        }

        $eventSubscribers = $this->eventListeners[$eventName] ?? [];
        foreach ($eventSubscribers as $eventSubscriber) {
            call_user_func_array($eventSubscriber, ['event' => &$eventEmitter]);
        }

        $transformedArguments = [];
        foreach ($eventArguments as $key=>$eventArgument) {
            if (is_string($key)) {
                $transformedArguments[$key] = $eventEmitter->$key;
            }
            elseif (is_numeric($key)) {
                $transformedArguments[$key] = $eventEmitter->options[$key];
            }
        }
        return $transformedArguments;
    }

}