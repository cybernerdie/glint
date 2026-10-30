<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Instrumentation;

use Cybernerdie\Glint\Contracts\InstrumentationDriver;
use Cybernerdie\Glint\Instrumentation\NeuronAi\GlintNeuronAiObserver;
use Illuminate\Contracts\Container\Container;
use NeuronAI\Agent\Agent;
use NeuronAI\Observability\EventBus;
use NeuronAI\Observability\ObserverInterface;

final class NeuronAiInstrumentation implements InstrumentationDriver
{
    public function __construct(private readonly Container $container) {}

    public function isAvailable(): bool
    {
        return class_exists(Agent::class);
    }

    public function register(): void
    {
        EventBus::observe(new class($this->container) implements ObserverInterface
        {
            public function __construct(private readonly Container $container) {}

            public function onEvent(string $event, object $source, mixed $data = null, ?string $branchId = null): void
            {
                $this->container->make(GlintNeuronAiObserver::class)->onEvent($event, $source, $data, $branchId);
            }
        });
    }
}
