<?php

declare(strict_types=1);

namespace Cybernerdie\Glint;

use Cybernerdie\Glint\Alerts\AlertDispatcher;
use Cybernerdie\Glint\Contracts\InstrumentationDriver;
use Cybernerdie\Glint\Events\LlmCallFailed;
use Cybernerdie\Glint\Events\LlmCallFinished;
use Cybernerdie\Glint\Events\LlmCallStarted;
use Cybernerdie\Glint\Events\LlmToolCalled;
use Cybernerdie\Glint\Jobs\RecordLlmCallJob;
use Cybernerdie\Glint\Recorders\GlintRecorder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Livewire\Card;
use Livewire\Livewire;

final class GlintServiceProvider extends ServiceProvider
{
    use Concerns\RegistersBindings;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/glint.php', 'glint');

        $this->registerBindings();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->offerPublishing();
        }

        $this->registerCommands();
        $this->loadViews();

        if (! Config::boolean('glint.enabled', false)) {
            return;
        }

        $this->registerEvents();
        $this->registerDrivers();
        $this->loadRoutes();
        $this->registerSchedule();
        $this->registerMiddleware();
        $this->registerPulse();
    }

    private function offerPublishing(): void
    {
        $this->publishes(
            [__DIR__.'/../config/glint.php' => config_path('glint.php')],
            'glint-config'
        );

        $this->publishes(
            [__DIR__.'/../pricing/providers.json' => config_path('glint_pricing.json')],
            'glint-pricing'
        );

        $this->publishes(
            [__DIR__.'/../database/migrations' => database_path('migrations')],
            'glint-migrations'
        );

        $this->publishes(
            [__DIR__.'/../resources/views' => resource_path('views/vendor/glint')],
            'glint-views'
        );

        $this->publishes(
            [__DIR__.'/../stubs/GlintApplicationServiceProvider.stub' => app_path('Providers/GlintServiceProvider.php')],
            'glint-provider'
        );
    }

    private function registerEvents(): void
    {
        Config::string('glint.recording.mode', 'queue') === 'sync'
            ? $this->registerSyncListeners()
            : $this->registerQueueListeners();
    }

    private function registerSyncListeners(): void
    {
        Event::listen(LlmCallStarted::class,  [GlintRecorder::class, 'handleLlmCallStarted']);
        Event::listen(LlmCallFinished::class, [GlintRecorder::class, 'handleLlmCallFinished']);
        Event::listen(LlmToolCalled::class,   [GlintRecorder::class, 'handleLlmToolCalled']);
        Event::listen(LlmCallFailed::class,   [GlintRecorder::class, 'handleLlmCallFailed']);
    }

    private function registerQueueListeners(): void
    {
        $rawConn = Config::get('glint.queue.connection');
        $connection = is_string($rawConn) && $rawConn !== '' ? $rawConn : null;

        $rawQueue = Config::get('glint.queue.queue');
        $queue = is_string($rawQueue) && $rawQueue !== '' ? $rawQueue : null;

        $dispatch = fn (object $e) => RecordLlmCallJob::dispatch($e)->onConnection($connection)->onQueue($queue);

        foreach ([LlmCallStarted::class, LlmCallFinished::class, LlmToolCalled::class, LlmCallFailed::class] as $event) {
            Event::listen($event, $dispatch);
        }
    }

    private function registerDrivers(): void
    {
        $drivers = Config::get('glint.drivers', ['http']);
        $enabled = is_array($drivers) ? $drivers : ['http'];

        $driverMap = [
            'prism' => Instrumentation\PrismInstrumentation::class,
            'laravel-ai' => Instrumentation\LaravelAiInstrumentation::class,
            'http' => Instrumentation\HttpClientInstrumentation::class,
            'neuron-ai' => Instrumentation\NeuronAiInstrumentation::class,
        ];

        // HttpClientInstrumentation, LaravelAiInstrumentation, and
        // GlintNeuronAiObserver carry per-request mutable state ($pending,
        // $toolStartTimes). Bind them as scoped so Octane re-creates them
        // each request, preventing state from leaking across requests.
        $this->app->scoped(Instrumentation\HttpClientInstrumentation::class);
        $this->app->scoped(Instrumentation\LaravelAiInstrumentation::class);
        $this->app->scoped(Instrumentation\NeuronAi\GlintNeuronAiObserver::class);

        foreach ($enabled as $name) {
            if (! is_string($name)) {
                continue;
            }
            $class = $driverMap[trim($name)] ?? null;

            if ($class === null) {
                continue;
            }

            /** @var InstrumentationDriver $driver */
            $driver = $this->app->make($class);

            if ($driver->isAvailable()) {
                $driver->register();
            }
        }
    }

    private function loadRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }

    private function loadViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'glint');
    }

    private function registerCommands(): void
    {
        $this->commands([
            Console\Commands\InstallCommand::class,
            Console\Commands\PublishCommand::class,
            Console\Commands\PruneCommand::class,
            Console\Commands\ClearCommand::class,
            Console\Commands\PricingCommand::class,
            Console\Commands\RecalcAggregatesCommand::class,
            Console\Commands\VerifyCommand::class,
        ]);
    }

    private function registerSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->call(fn () => $this->app->make(AlertDispatcher::class)->evaluate())
                ->everyFiveMinutes()
                ->name('glint:evaluate-alerts')
                ->withoutOverlapping();
        });
    }

    private function registerPulse(): void
    {
        if (! class_exists(Card::class)) {
            return;
        }

        if (! Config::boolean('glint.pulse.enabled', false)) {
            return;
        }

        Livewire::component('cybernerdie.glint::glint-card', Pulse\GlintCard::class);
    }

    private function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('glint', Middleware\GlintMiddleware::class);

        // Create an empty 'glint-auth' middleware group so routes work even if
        // GlintApplicationServiceProvider has not been registered. When the
        // application provider IS registered, its authorization() method appends
        // the Authorize:viewGlint middleware to this group — exactly like Telescope.
        if (! $router->hasMiddlewareGroup('glint-auth')) {
            $router->middlewareGroup('glint-auth', []);
        }
    }
}
