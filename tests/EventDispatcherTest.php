<?php

declare(strict_types=1);

namespace G\EventDispatcher\Tests;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\{EventDispatcherInterface, ListenerProviderInterface};
use G\EventDispatcher\{EventDispatcher, ListenerProvider};
use RuntimeException;
use stdClass;

final class EventDispatcherTest extends TestCase
{
    private ListenerProvider $listenerProvider;
    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->listenerProvider = new ListenerProvider();
        $this->eventDispatcher = new EventDispatcher($this->listenerProvider);
    }

    public function testImplementsEventDispatcherInterface(): void
    {
        $this->assertInstanceOf(EventDispatcherInterface::class, $this->eventDispatcher);
    }

    public function testDispatchReturnsTheSameEvent(): void
    {
        $event = new stdClass();

        $this->assertSame($event, $this->eventDispatcher->dispatch($event));
    }

    public function testListenersCanModifyTheEvent(): void
    {
        $this->listenerProvider->addListener(Event::class, function (Event $event): void {
            $event->setData('Modified Data');
        });

        $event = new Event('Original Data');
        $resultEvent = $this->eventDispatcher->dispatch($event);

        $this->assertSame($event, $resultEvent);
        $this->assertSame('Modified Data', $event->getData());
    }

    public function testListenersAreCalledInTheOrderGivenByTheProvider(): void
    {
        $calls = [];
        $provider = $this->createStub(ListenerProviderInterface::class);
        $provider->method('getListenersForEvent')->willReturnCallback(
            // A generator: PSR-14 only promises an iterable
            static function () use (&$calls): iterable {
                yield static function () use (&$calls): void {
                    $calls[] = 'first';
                };
                yield static function () use (&$calls): void {
                    $calls[] = 'second';
                };
            }
        );

        (new EventDispatcher($provider))->dispatch(new stdClass());

        $this->assertSame(['first', 'second'], $calls);
    }

    public function testStoppedPropagationSkipsTheRemainingListeners(): void
    {
        $calls = [];
        $this->listenerProvider->addListener(Event::class, function (Event $event) use (&$calls): void {
            $calls[] = 'first';
            $event->stopPropagation();
        });
        $this->listenerProvider->addListener(Event::class, function () use (&$calls): void {
            $calls[] = 'second';
        });

        $event = $this->eventDispatcher->dispatch(new Event());

        $this->assertInstanceOf(Event::class, $event);
        $this->assertTrue($event->isPropagationStopped());
        $this->assertSame(['first'], $calls);
    }

    public function testAlreadyStoppedEventCallsNoListener(): void
    {
        $calls = 0;
        $this->listenerProvider->addListener(Event::class, function () use (&$calls): void {
            $calls++;
        });
        $event = new Event();
        $event->stopPropagation();

        $this->assertSame($event, $this->eventDispatcher->dispatch($event));
        $this->assertSame(0, $calls);
    }

    public function testListenerExceptionIsNotCaught(): void
    {
        $calls = [];
        $this->listenerProvider->addListener(stdClass::class, function (): void {
            throw new RuntimeException('Listener failure');
        });
        $this->listenerProvider->addListener(stdClass::class, function () use (&$calls): void {
            $calls[] = 'second';
        });

        try {
            $this->eventDispatcher->dispatch(new stdClass());
            $this->fail('An exception was expected');
        } catch (RuntimeException $e) {
            $this->assertSame('Listener failure', $e->getMessage());
        }
        $this->assertSame([], $calls);
    }
}
