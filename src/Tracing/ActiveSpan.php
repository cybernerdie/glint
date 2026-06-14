<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Tracing;

use Carbon\Carbon;
use Cybernerdie\Glint\Concerns\ProtectsWrites;
use Cybernerdie\Glint\Contracts\SpanInterface;
use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Models\GlintSpan;
use Illuminate\Support\Facades\DB;

final class ActiveSpan implements SpanInterface
{
    use ProtectsWrites;

    public function __construct(
        private readonly string $spanId,
        private readonly Carbon $startedAt,
    ) {}

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
                $tags[$key] = $value;
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
                $metadata['tags'] = array_merge($existing, $tags);

                $span->update(['metadata' => $metadata]);
            });
        });

        return $this;
    }

    public function end(): void
    {
        $this->protectedWrite(function (): void {
            $now = now();
            GlintSpan::where('id', $this->spanId)->update([
                'status' => RecordStatus::Success,
                'ended_at' => $now,
                'duration_ms' => (int) $this->startedAt->diffInMilliseconds($now),
            ]);
        });
    }

    public function spanId(): string
    {
        return $this->spanId;
    }
}
