<?php

namespace Simp\Pindrop\Modules\wiki\src\Plugin\Events;

use Simp\Pindrop\Events\EventEmitter;
use Simp\Pindrop\Events\EventsSubscriberInterface;
use Simp\Pindrop\Events\SystemEvents\Events;
use Simp\Pindrop\Modules\wiki\src\Plugin\Events\Events as PluginEvents;
use Simp\Pindrop\Mysql\SchemaHandler;

class EventsSubscriber implements EventsSubscriberInterface
{
    public function getSubscribedEvents(): array
    {
        return [
            Events::PLUGIN_INSTALLED => [$this, "installedPlugin"],
            PluginEvents::WIKI_VIEW_TEMPALATE => [$this,"templateOverride"],
        ];
    }

    public function installedPlugin(EventEmitter $event): void
    {
        if ( $event->plugin_id === 'wiki') {

            /**
             * @var SchemaHandler
             */
            $schemaHandler = $event->container->get('schema.handler');
            $schemaHandler->createTableFromFile(__DIR__ ."/../../../mysql/wiki.sql","wiki_pages");
        }
    }

    public function templateOverride(EventEmitter $event)
    {
        return $event;
    }
}
