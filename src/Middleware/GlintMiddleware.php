<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Middleware;

use Closure;
use Cybernerdie\Glint\Context\TraceContext;
use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class GlintMiddleware
{
    public function __construct(
        private readonly TraceContext $context,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Config::boolean('glint.enabled', false)) {
            return $next($request);
        }

        $glintPath = Config::string('glint.path', 'glint');
        if ($request->is($glintPath) || $request->is($glintPath.'/*')) {
            return $next($request);
        }

        $sampled = $this->shouldSample();
        $traceId = (string) Str::ulid();
        $startedAt = now();

        $this->context->openTrace($traceId, $sampled);

        if ($sampled) {
            rescue(fn () => GlintTrace::create([
                'id' => $traceId,
                'name' => substr($request->route()?->getName() ?? $request->path(), 0, 255),
                'user_id' => is_scalar($key = $request->user()?->getKey()) ? substr((string) $key, 0, 255) : null,
                'session_id' => $request->hasSession() ? substr($request->session()->getId(), 0, 255) : null,
                'metadata' => [
                    'method' => $request->method(),
                    'path' => substr($request->path(), 0, 2048),
                    'ip' => Config::boolean('glint.privacy.store_ip', true) ? $request->ip() : null,
                    'user_agent' => $this->sanitizeUserAgent($request->userAgent()),
                    'env' => app()->environment(),
                ],
                'status' => RecordStatus::Pending,
                'started_at' => $startedAt,
            ]));
        }

        try {
            $response = $next($request);

            if ($sampled) {
                $status = $response->getStatusCode() >= 500
                    ? RecordStatus::Error
                    : RecordStatus::Success;

                rescue(fn () => GlintTrace::where('id', $traceId)->update([
                    'status' => $status,
                    'ended_at' => now(),
                    'duration_ms' => (int) $startedAt->diffInMilliseconds(now()),
                    'output' => Config::boolean('glint.recording.store_bodies', false)
                        ? substr($response->getContent() ?: '', 0, 10000)
                        : null,
                ]));
            }

            return $response;
        } finally {
            $this->context->closeTrace();
        }
    }

    private function sanitizeUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null) {
            return null;
        }

        $cleaned = preg_replace('/[\x00-\x1F\x7F]/', '', $userAgent) ?? '';
        $cleaned = $this->applyRedactionPatterns($cleaned);

        return substr($cleaned, 0, 512);
    }

    private function applyRedactionPatterns(string $value): string
    {
        $patterns = (array) Config::get('glint.privacy.redact_patterns', []);

        foreach ($patterns as $pattern) {
            if (! is_string($pattern)) {
                continue;
            }

            $result = @preg_replace($pattern, '[REDACTED]', $value);

            if ($result === null || preg_last_error() !== PREG_NO_ERROR) {
                logger()->debug('[Glint] Invalid redact_pattern skipped: '.$pattern);

                continue;
            }

            $value = $result;
        }

        return $value;
    }

    private function shouldSample(): bool
    {
        $rate = Config::float('glint.recording.sampling_rate', 1.0);

        if ($rate <= 0.0) {
            return false;
        }

        if ($rate >= 1.0) {
            return true;
        }

        return (random_int(0, PHP_INT_MAX) / PHP_INT_MAX) < $rate;
    }
}
