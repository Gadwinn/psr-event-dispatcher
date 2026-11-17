# PSR-14 Event Dispatcher

A lightweight, strict PSR-14 compliant implementation of `EventDispatcherInterface` and `ListenerProviderInterface`, with priority-based listener resolution across the full class and interface hierarchy.

## Tech Stack

- PHP 8.3+
- [`psr/event-dispatcher`](https://packagist.org/packages/psr/event-dispatcher) `^1.0`

## Installation

```bash
composer require gadwinn/psr-event-dispatcher
```

## Usage

### 1. Create an event

An event is any plain PHP object. To support propagation stopping, implement `Psr\EventDispatcher\StoppableEventInterface`:

```php
use Psr\EventDispatcher\StoppableEventInterface;

class OrderPlaced implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    public function __construct(public readonly int $orderId) {}

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
```

### 2. Register listeners

```php
use G\EventDispatcher\ListenerProvider;

$provider = new ListenerProvider();

// Register by exact class name
$provider->addListener(OrderPlaced::class, function (OrderPlaced $event): void {
    echo 'Order #' . $event->orderId . ' placed.';
});

// Register by interface — called for any event implementing that interface
$provider->addListener(StoppableEventInterface::class, function (StoppableEventInterface $event): void {
    // called for every stoppable event
});

// Priority: lower integer = higher priority (default: 0)
$provider->addListener(OrderPlaced::class, $criticalHandler, priority: -10);
$provider->addListener(OrderPlaced::class, $secondaryHandler, priority: 10);
```

### 3. Dispatch

```php
use G\EventDispatcher\EventDispatcher;

$dispatcher = new EventDispatcher($provider);

$event = $dispatcher->dispatch(new OrderPlaced(42));
```

The dispatcher returns the same event object after all listeners have been called. Listeners are invoked synchronously in priority order (ascending integer).

### 4. Stop propagation

When a listener calls `stopPropagation()`, the dispatcher checks `isPropagationStopped()` before each subsequent listener and halts the chain immediately:

```php
$provider->addListener(OrderPlaced::class, function (OrderPlaced $event): void {
    $event->stopPropagation(); // no further listeners will be called
}, priority: -10);

$provider->addListener(OrderPlaced::class, function (OrderPlaced $event): void {
    // never reached
}, priority: 0);

$dispatcher->dispatch(new OrderPlaced(42));
```

### 5. Remove a listener

```php
$handler = function (OrderPlaced $event): void { /* ... */ };

$provider->addListener(OrderPlaced::class, $handler, priority: 5);

// Must pass the exact same callable reference and priority
$provider->removeListener(OrderPlaced::class, $handler, priority: 5);
```

### 6. Listener resolution — class hierarchy

`ListenerProvider` resolves listeners registered against the event's own class, all parent classes, and all implemented interfaces. Given:

```php
class SpecialOrder extends OrderPlaced {}
```

Dispatching `SpecialOrder` will also invoke listeners registered for `OrderPlaced` and any shared interfaces, with the full priority ordering applied across all applicable types.

### 7. Dependency injection

`EventDispatcher` accepts any `Psr\EventDispatcher\ListenerProviderInterface` implementation, making it straightforward to inject a custom or composite provider:

```php
use Psr\EventDispatcher\ListenerProviderInterface;

$dispatcher = new EventDispatcher($yourCustomProvider);
```

## License

MIT, see [LICENSE](LICENSE).
