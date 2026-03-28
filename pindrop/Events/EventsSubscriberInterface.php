<?php

namespace Simp\Pindrop\Events;

interface EventsSubscriberInterface
{
    public function getSubscribedEvents(): array;

}