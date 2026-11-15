<?php

declare(strict_types=1);

namespace G\EventDispatcher;

use Psr\EventDispatcher\ListenerProviderInterface;

class ListenerProvider implements ListenerProviderInterface
{
    /**
     * @var array<string,array<int,array<int,callable>>>
     */
    protected array $listeners = [];

    /**
     * @return iterable<callable>
     */
    public function getListenersForEvent(object $event): iterable
    {
        $eventTypes = [
            $event::class,
            ...array_values(class_parents($event)),
            ...array_values(class_implements($event)),
        ];

        $collected = [];

        foreach ($eventTypes as $type) {
            if (!isset($this->listeners[$type])) {
                continue;
            }

            foreach ($this->listeners[$type] as $priority => $listeners) {
                foreach ($listeners as $listener) {
                    $collected[$priority][] = $listener;
                }
            }
        }

        ksort($collected);

        $finalListeners = [];

        foreach ($collected as $listeners) {
            foreach ($listeners as $listener) {
                $finalListeners[] = $listener;
            }
        }

        return $finalListeners;
    }

    /**
     * @param string $eventName Event name
     * @param callable $listener Listener
     * @param int $priority Priority, lower number is higher priority
     *
     * @return void
     */
    public function addListener(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->listeners[$eventName][$priority][] = $listener;
    }

    /**
     * @param string $eventName Event name
     * @param callable $listener Listener
     * @param int $priority Priority
     *
     * @return void
     */
    public function removeListener(string $eventName, callable $listener, int $priority = 0): void
    {
        if (
            !isset($this->listeners[$eventName]) ||
            !isset($this->listeners[$eventName][$priority])
        ) {
            return;
        }

        foreach ($this->listeners[$eventName][$priority] as $index => $value) {
            if ($value === $listener) {
                unset($this->listeners[$eventName][$priority][$index]);
            }
        }
    }
}
