<?php

declare(strict_types=1);

namespace G\EventDispatcher\Tests;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\{ListenerProviderInterface, StoppableEventInterface};
use G\EventDispatcher\ListenerProvider;
use stdClass;

final class ListenerProviderTest extends TestCase
{
    private ListenerProvider $listenerProvider;

    protected function setUp(): void
    {
        $this->listenerProvider = new ListenerProvider();
    }

    /**
     * @return list<callable>
     */
    private function listenersFor(object $event): array
    {
        $listeners = $this->listenerProvider->getListenersForEvent($event);

        return is_array($listeners) ? array_values($listeners) : iterator_to_array($listeners, false);
    }

    public function testImplementsListenerProviderInterface(): void
    {
        $this->assertInstanceOf(ListenerProviderInterface::class, $this->listenerProvider);
    }

    public function testNoListeners(): void
    {
        $this->assertSame([], $this->listenersFor(new Event()));
    }

    public function testListenersOfTheEventClassInRegistrationOrder(): void
    {
        $first = fn () => null;
        $second = fn () => null;
        $this->listenerProvider->addListener(Event::class, $first);
        $this->listenerProvider->addListener(Event::class, $second);
        $this->listenerProvider->addListener(stdClass::class, fn () => null);

        $this->assertSame([$first, $second], $this->listenersFor(new Event()));
    }

    public function testLowerPriorityNumberComesFirst(): void
    {
        $late = fn () => null;
        $default = fn () => null;
        $early = fn () => null;
        $this->listenerProvider->addListener(Event::class, $late, 10);
        $this->listenerProvider->addListener(Event::class, $default);
        $this->listenerProvider->addListener(Event::class, $early, -10);

        $this->assertSame([$early, $default, $late], $this->listenersFor(new Event()));
    }

    public function testListenersOfParentClassesAndInterfacesAreIncluded(): void
    {
        $onClass = fn () => null;
        $onParent = fn () => null;
        $onInterface = fn () => null;
        $this->listenerProvider->addListener(StoppableEventInterface::class, $onInterface);
        $this->listenerProvider->addListener(Event::class, $onParent);
        $this->listenerProvider->addListener(ChildEvent::class, $onClass);

        // Same priority: own class first, then parent classes, then interfaces
        $this->assertSame([$onClass, $onParent, $onInterface], $this->listenersFor(new ChildEvent()));
        $this->assertSame([$onParent, $onInterface], $this->listenersFor(new Event()));
    }

    public function testPriorityAppliesAcrossTheHierarchy(): void
    {
        $onClass = fn () => null;
        $onInterface = fn () => null;
        $this->listenerProvider->addListener(ChildEvent::class, $onClass);
        $this->listenerProvider->addListener(StoppableEventInterface::class, $onInterface, -1);

        $this->assertSame([$onInterface, $onClass], $this->listenersFor(new ChildEvent()));
    }

    public function testRemoveListener(): void
    {
        $first = fn () => null;
        $second = fn () => null;
        $this->listenerProvider->addListener(Event::class, $first);
        $this->listenerProvider->addListener(Event::class, $second);
        $this->listenerProvider->addListener(Event::class, $first, 5);

        $this->listenerProvider->removeListener(Event::class, $first);

        $this->assertSame([$second, $first], $this->listenersFor(new Event()));
    }

    public function testRemoveUnknownListenerDoesNothing(): void
    {
        $listener = fn () => null;
        $this->listenerProvider->addListener(Event::class, $listener);

        $this->listenerProvider->removeListener(Event::class, fn () => null);
        $this->listenerProvider->removeListener(Event::class, $listener, 5);
        $this->listenerProvider->removeListener(stdClass::class, $listener);

        $this->assertSame([$listener], $this->listenersFor(new Event()));
    }
}
