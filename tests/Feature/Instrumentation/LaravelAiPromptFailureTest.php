<?php

declare(strict_types=1);

use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Instrumentation\LaravelAi\GlintPromptMiddleware;
use Cybernerdie\Glint\Instrumentation\LaravelAiInstrumentation;
use Cybernerdie\Glint\Models\GlintGeneration;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Prompts\AgentPrompt;

it('records the original Laravel AI exception on the matching generation', function () {
    app(LaravelAiInstrumentation::class)->register();

    $prompt = new AgentPrompt(prompt: 'Hello', model: 'gpt-4o');
    $exception = new RuntimeException('Provider rejected request');

    expect(fn () => app(GlintPromptMiddleware::class)->handle(
        $prompt,
        function () use ($prompt, $exception): never {
            event(new PromptingAgent('failed-invocation', $prompt));

            throw $exception;
        },
    ))->toThrow($exception);

    $generation = GlintGeneration::first();

    expect($generation->status)->toBe(RecordStatus::Error)
        ->and($generation->ended_at)->not->toBeNull()
        ->and($generation->error_message)->toBe('Provider rejected request');
});
