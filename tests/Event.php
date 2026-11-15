<?php

declare(strict_types=1);

namespace G\EventDispatcher\Tests;

use Psr\EventDispatcher\StoppableEventInterface;

class Event implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    public function __construct(private mixed $data = null) {}

    public function getData(): mixed
    {
        return $this->data;
    }

    public function setData(mixed $data): void
    {
        $this->data = $data;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
