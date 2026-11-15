<?php

declare(strict_types=1);

namespace G\EventDispatcher;

use Psr\EventDispatcher\{
    EventDispatcherInterface,
    ListenerProviderInterface,
    StoppableEventInterface
};

class EventDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private ListenerProviderInterface $listenerProvider
    ) { //
    }

    /**
     * @param object $event Event object
     * @return object Event modified by listeners
     */
    public function dispatch(object $event): object
    {
        $stoppable = $event instanceof StoppableEventInterface;
        /** @var callable $listener */
        foreach ($this->listenerProvider->getListenersForEvent($event) as $listener) {
            if ($stoppable && $event->isPropagationStopped()) {
                break;
            }
            $listener($event);
        }
        return $event;
    }
}
