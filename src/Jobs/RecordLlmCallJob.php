<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Jobs;

use Cybernerdie\Glint\Events\LlmCallFailed;
use Cybernerdie\Glint\Events\LlmCallFinished;
use Cybernerdie\Glint\Events\LlmCallStarted;
use Cybernerdie\Glint\Events\LlmToolCalled;
use Cybernerdie\Glint\Recorders\GlintRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RecordLlmCallJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [5, 15, 30];

    public function __construct(
        private readonly LlmCallStarted|LlmCallFinished|LlmToolCalled|LlmCallFailed $event,
    ) {}

    public function handle(GlintRecorder $recorder): void
    {
        if ($this->event instanceof LlmCallStarted) {
            $recorder->handleLlmCallStarted($this->event);
        } elseif ($this->event instanceof LlmCallFinished) {
            $recorder->handleLlmCallFinished($this->event);
        } elseif ($this->event instanceof LlmToolCalled) {
            $recorder->handleLlmToolCalled($this->event);
        } else {
            $recorder->handleLlmCallFailed($this->event);
        }
    }

    public function failed(\Throwable $e): void
    {
        logger()->warning('[Glint] RecordLlmCallJob failed: '.$e->getMessage());
    }
}
