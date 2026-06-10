<?php

declare(strict_types=1);

namespace NeuronAI\Observability;

class EventBus
{
    /** @var ObserverInterface[] */
    private static array $observers = [];

    public static function observe(ObserverInterface $observer, ?string $workflowId = null): void
    {
        self::$observers[] = $observer;
    }

    public static function emit(string $event, object $source, mixed $data = null, ?string $workflowId = null, ?string $branchId = null): void
    {
        foreach (self::$observers as $observer) {
            $observer->onEvent($event, $source, $data, $branchId);
        }
    }

    public static function clear(?string $workflowId = null): void
    {
        self::$observers = [];
    }
}
