<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Tracing;

use Carbon\Carbon;
use Cybernerdie\Glint\Aggregates\GenerationAggregateRecorder;
use Cybernerdie\Glint\Concerns\ProtectsWrites;
use Cybernerdie\Glint\Contracts\GenerationInterface;
use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Models\GlintTrace;
use Cybernerdie\Glint\Pricing\PricingRegistry;
use Cybernerdie\Glint\Support\Redactor;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

final class ActiveGeneration implements GenerationInterface
{
    use ProtectsWrites;

    public function __construct(
        private readonly string $generationId,
        private readonly PricingRegistry $pricing,
        private readonly string $provider,
        private readonly string $model,
        private readonly Carbon $startedAt,
        private readonly Redactor $redactor,
        private readonly GenerationAggregateRecorder $aggregates,
        private readonly ?string $ownedTraceId = null,
    ) {}

    /**
     * Add a key/value tag to the generation's metadata.
     *
     * Each call issues a SELECT + UPDATE against glint_generations. For
     * generations that receive many tags, pass them upfront via the metadata
     * array in Glint::generation() instead.
     */
    public function tag(string $key, string $value): static
    {
        $this->protectedWrite(function () use ($key, $value): void {
            DB::transaction(function () use ($key, $value): void {
                $generation = GlintGeneration::where('id', $this->generationId)->lockForUpdate()->first();

                if ($generation === null) {
                    return;
                }

                /** @var array<string, mixed> $metadata */
                $metadata = (array) ($generation->metadata ?? []);
                /** @var array<string, mixed> $tags */
                $tags = is_array($metadata['tags'] ?? null) ? $metadata['tags'] : [];
                $tags[$key] = $this->redactor->string($value);
                $metadata['tags'] = $tags;

                $generation->update(['metadata' => $metadata]);
            });
        });

        return $this;
    }

    /**
     * Write multiple key/value tags in a single atomic transaction.
     * Prefer this over calling tag() in a loop when you have several tags.
     *
     * @param  array<string, string>  $tags
     */
    public function tags(array $tags): static
    {
        if ($tags === []) {
            return $this;
        }

        $this->protectedWrite(function () use ($tags): void {
            DB::transaction(function () use ($tags): void {
                $generation = GlintGeneration::where('id', $this->generationId)->lockForUpdate()->first();

                if ($generation === null) {
                    return;
                }

                /** @var array<string, mixed> $metadata */
                $metadata = (array) ($generation->metadata ?? []);
                /** @var array<string, mixed> $existing */
                $existing = is_array($metadata['tags'] ?? null) ? $metadata['tags'] : [];
                /** @var array<string, string> $redactedTags */
                $redactedTags = $this->redactor->metadata($tags) ?? [];
                $metadata['tags'] = array_merge($existing, $redactedTags);

                $generation->update(['metadata' => $metadata]);
            });
        });

        return $this;
    }

    public function prompt(string $prompt): static
    {
        $this->protectedWrite(function () use ($prompt): void {
            GlintGeneration::where('id', $this->generationId)->update([
                'prompt' => Config::boolean('glint.recording.store_bodies', true) ? [
                    [
                        'role' => 'user',
                        'content' => $this->redactor->string($prompt),
                    ],
                ] : null,
            ]);
        });

        return $this;
    }

    public function options(
        ?float $temperature = null,
        ?int $maxTokens = null,
        ?float $topP = null,
        ?bool $streaming = null,
    ): static {
        $attributes = [];

        if ($temperature !== null) {
            $attributes['temperature'] = $temperature;
        }

        if ($maxTokens !== null) {
            $attributes['max_tokens'] = $maxTokens;
        }

        if ($topP !== null) {
            $attributes['top_p'] = $topP;
        }

        if ($streaming !== null) {
            $attributes['is_streaming'] = $streaming;
        }

        if ($attributes === []) {
            return $this;
        }

        $this->protectedWrite(function () use ($attributes): void {
            GlintGeneration::where('id', $this->generationId)->update($attributes);
        });

        return $this;
    }

    public function finish(string $completion, int $promptTokens, int $completionTokens, string $finishReason = 'stop'): void
    {
        $this->protectedWrite(function () use ($completion, $promptTokens, $completionTokens, $finishReason): void {
            DB::transaction(function () use ($completion, $promptTokens, $completionTokens, $finishReason): void {
                $now = now();
                $generation = GlintGeneration::where('id', $this->generationId)->lockForUpdate()->first();

                if ($generation === null || $generation->status !== RecordStatus::Pending) {
                    return;
                }

                $generation->update([
                    'completion' => Config::boolean('glint.recording.store_bodies', true)
                        ? $this->redactor->string($completion)
                        : null,
                    'prompt_tokens' => $promptTokens,
                    'completion_tokens' => $completionTokens,
                    'total_tokens' => $promptTokens + $completionTokens,
                    'finish_reason' => $finishReason,
                    'status' => RecordStatus::Success,
                    'cost_usd' => $this->pricing->costFor($this->provider, $this->model, $promptTokens, $completionTokens),
                    'ended_at' => $now,
                    'duration_ms' => (int) $this->startedAt->diffInMilliseconds($now),
                ]);

                $this->finishOwnedTrace(RecordStatus::Success, $now);
                $this->aggregates->record($generation->refresh());
            });
        });
    }

    public function fail(\Throwable $e): void
    {
        $this->protectedWrite(function () use ($e): void {
            DB::transaction(function () use ($e): void {
                $now = now();
                $generation = GlintGeneration::where('id', $this->generationId)->lockForUpdate()->first();

                if ($generation === null || $generation->status !== RecordStatus::Pending) {
                    return;
                }

                $generation->update([
                    'status' => RecordStatus::Error,
                    'error_message' => $this->redactor->string($e->getMessage()),
                    'ended_at' => $now,
                    'duration_ms' => (int) $this->startedAt->diffInMilliseconds($now),
                ]);

                $this->finishOwnedTrace(RecordStatus::Error, $now);
                $this->aggregates->record($generation->refresh());
            });
        });
    }

    public function generationId(): string
    {
        return $this->generationId;
    }

    private function finishOwnedTrace(RecordStatus $status, Carbon $endedAt): void
    {
        if ($this->ownedTraceId === null) {
            return;
        }

        GlintTrace::where('id', $this->ownedTraceId)->update([
            'status' => $status,
            'ended_at' => $endedAt,
            'duration_ms' => (int) $this->startedAt->diffInMilliseconds($endedAt),
        ]);
    }
}
