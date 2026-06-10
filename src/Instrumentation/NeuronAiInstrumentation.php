<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Instrumentation;

use Cybernerdie\Glint\Contracts\InstrumentationDriver;
use Cybernerdie\Glint\Instrumentation\NeuronAi\GlintNeuronAiObserver;
use NeuronAI\Agent\Agent;
use NeuronAI\Observability\EventBus;
use NeuronAI\Observability\ObserverInterface;

final class NeuronAiInstrumentation implements InstrumentationDriver
{
    public function isAvailable(): bool
    {
        return class_exists(Agent::class);
    }

    public function register(): void
    {
        // Register a lightweight proxy as the global NeuronAI observer.
        // The proxy resolves the per-request scoped GlintNeuronAiObserver from
        // the container at event time, providing Octane safety without adding
        // multiple observer registrations for long-running processes.
        EventBus::observe(new class implements ObserverInterface
        {
            public function onEvent(string $event, object $source, mixed $data = null, ?string $branchId = null): void
            {
                app(GlintNeuronAiObserver::class)->onEvent($event, $source, $data, $branchId);
            }
        });
    }
}
