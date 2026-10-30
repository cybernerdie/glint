<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Listeners;

use Cybernerdie\Glint\Context\TraceContext;
use Cybernerdie\Glint\Events\LlmCallFailed;
use Cybernerdie\Glint\Events\LlmCallFinished;
use Cybernerdie\Glint\Events\LlmCallStarted;
use Cybernerdie\Glint\Events\LlmToolCalled;
use Cybernerdie\Glint\Filtering\FilterEntry;
use Cybernerdie\Glint\Filtering\GlintFilterRegistry;
use Cybernerdie\Glint\Jobs\RecordLlmCallJob;
use Illuminate\Support\Facades\Config;

final readonly class DispatchLlmCallRecording
{
    public function __construct(
        private TraceContext $context,
        private GlintFilterRegistry $filters,
    ) {}

    public function handle(LlmCallStarted|LlmCallFinished|LlmToolCalled|LlmCallFailed $event): void
    {
        $filterChecked = false;

        if ($event instanceof LlmCallStarted) {
            $traceId = $event->traceId ?? $this->context->traceId();

            if (! $this->shouldRecord($event, $traceId)) {
                return;
            }

            if ($traceId !== null) {
                $this->context->materializeTrace($traceId);

                if ($event->traceId === null) {
                    $event = $event->withTraceId($traceId);
                }
            }

            $filterChecked = true;
        }

        if ($event instanceof LlmToolCalled) {
            $this->context->materializeTrace($event->traceId);
        }

        $rawConnection = Config::get('glint.queue.connection');
        $connection = is_string($rawConnection) && filled($rawConnection) ? $rawConnection : null;

        $rawQueue = Config::get('glint.queue.queue');
        $queue = is_string($rawQueue) && filled($rawQueue) ? $rawQueue : null;

        RecordLlmCallJob::dispatch($event, $filterChecked)->onConnection($connection)->onQueue($queue);
    }

    private function shouldRecord(LlmCallStarted $event, ?string $traceId): bool
    {
        try {
            return $this->filters->shouldRecord(new FilterEntry(
                provider: $event->provider,
                model: $event->model,
                traceId: $traceId,
                metadata: $event->metadata,
            ));
        } catch (\Throwable $exception) {
            if (Config::boolean('glint.throw_on_exceptions', false)) {
                throw $exception;
            }

            logger()->warning('[Glint] LlmCallStarted recording filter failed: '.$exception->getMessage());

            return false;
        }
    }
}
