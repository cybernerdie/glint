<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Tracing;

use Carbon\Carbon;
use Cybernerdie\Glint\Concerns\ProtectsWrites;
use Cybernerdie\Glint\Context\TraceContext;
use Cybernerdie\Glint\Contracts\SpanInterface;
use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Models\GlintSpan;
use Cybernerdie\Glint\Models\GlintTrace;
use Cybernerdie\Glint\Support\Redactor;
use Illuminate\Support\Facades\DB;

final class ActiveSpan implements SpanInterface
{
    use ProtectsWrites;

    private bool $ended = false;

    public function __construct(
        private readonly string $spanId,
        private readonly Carbon $startedAt,
        private readonly Redactor $redactor,
        private readonly ?TraceContext $context = null,
        private readonly ?string $ownedTraceId = null,
    ) {
        $this->context?->openSpan($this->spanId);
    }

    /**
     * Add a key/value tag to the span's metadata.
     *
     * Each call issues a SELECT + UPDATE against glint_spans. For spans
     * that receive many tags, batch them before calling tag() or pass them
     * upfront via the metadata array.
     */
    public function tag(string $key, string $value): static
    {
        $this->protectedWrite(function () use ($key, $value): void {
            DB::transaction(function () use ($key, $value): void {
                $span = GlintSpan::where('id', $this->spanId)->lockForUpdate()->first();

                if ($span === null) {
                    return;
                }

                /** @var array<string, mixed> $metadata */
                $metadata = (array) ($span->metadata ?? []);
                /** @var array<string, mixed> $tags */
                $tags = is_array($metadata['tags'] ?? null) ? $metadata['tags'] : [];
                $tags[$key] = $this->redactor->string($value);
                $metadata['tags'] = $tags;

                $span->update(['metadata' => $metadata]);
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
                $span = GlintSpan::where('id', $this->spanId)->lockForUpdate()->first();

                if ($span === null) {
                    return;
                }

                /** @var array<string, mixed> $metadata */
                $metadata = (array) ($span->metadata ?? []);
                /** @var array<string, mixed> $existing */
                $existing = is_array($metadata['tags'] ?? null) ? $metadata['tags'] : [];
                /** @var array<string, string> $redactedTags */
                $redactedTags = $this->redactor->metadata($tags) ?? [];
                $metadata['tags'] = array_merge($existing, $redactedTags);

                $span->update(['metadata' => $metadata]);
            });
        });

        return $this;
    }

    public function end(): void
    {
        $this->complete(RecordStatus::Success);
    }

    public function fail(): void
    {
        $this->complete(RecordStatus::Error);
    }

    private function complete(RecordStatus $status): void
    {
        if ($this->ended) {
            return;
        }

        try {
            $this->protectedWrite(function () use ($status): void {
                $now = now();
                GlintSpan::where('id', $this->spanId)->update([
                    'status' => $status,
                    'ended_at' => $now,
                    'duration_ms' => (int) $this->startedAt->diffInMilliseconds($now),
                ]);

                if ($this->ownedTraceId !== null) {
                    GlintTrace::where('id', $this->ownedTraceId)->update([
                        'status' => $status,
                        'ended_at' => $now,
                        'duration_ms' => (int) $this->startedAt->diffInMilliseconds($now),
                    ]);
                }
            });
        } finally {
            $this->ended = true;
            $this->context?->closeSpan($this->spanId);

            if ($this->ownedTraceId !== null) {
                $this->context?->closeTrace($this->ownedTraceId);
            }
        }
    }

    public function spanId(): string
    {
        return $this->spanId;
    }
}
