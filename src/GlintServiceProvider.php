<?php

declare(strict_types=1);

namespace Cybernerdie\Glint;

use Cybernerdie\Glint\Alerts\AlertDispatcher;
use Cybernerdie\Glint\Contracts\InstrumentationDriver;
use Cybernerdie\Glint\Events\GlintAlertTriggered;
use Cybernerdie\Glint\Events\LlmCallFailed;
use Cybernerdie\Glint\Events\LlmCallFinished;
use Cybernerdie\Glint\Events\LlmCallStarted;
use Cybernerdie\Glint\Events\LlmToolCalled;
use Cybernerdie\Glint\Jobs\RecordLlmCallJob;
use Cybernerdie\Glint\Listeners\SendAlertNotification;
use Cybernerdie\Glint\Recorders\GlintRecorder;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View;
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
        Event::listen(LlmCallStarted::class, [GlintRecorder::class, 'handleLlmCallStarted']);
        Event::listen(LlmCallFinished::class, [GlintRecorder::class, 'handleLlmCallFinished']);
        Event::listen(LlmToolCalled::class, [GlintRecorder::class, 'handleLlmToolCalled']);
        Event::listen(LlmCallFailed::class, [GlintRecorder::class, 'handleLlmCallFailed']);
        Event::listen(GlintAlertTriggered::class, SendAlertNotification::class);
    }

    private function registerQueueListeners(): void
    {
        $rawConn = Config::get('glint.queue.connection');
        $connection = is_string($rawConn) && $rawConn !== '' ? $rawConn : null;

        $rawQueue = Config::get('glint.queue.queue');
        $queue = is_string($rawQueue) && $rawQueue !== '' ? $rawQueue : null;

        $dispatch = fn (LlmCallStarted|LlmCallFinished|LlmToolCalled|LlmCallFailed $e) => RecordLlmCallJob::dispatch($e)->onConnection($connection)->onQueue($queue);

        foreach ([LlmCallStarted::class, LlmCallFinished::class, LlmToolCalled::class, LlmCallFailed::class] as $event) {
            Event::listen($event, $dispatch);
        }

        Event::listen(GlintAlertTriggered::class, SendAlertNotification::class);
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

        $this->app->scoped(Instrumentation\HttpClientInstrumentation::class);
        $this->app->scoped(Instrumentation\LaravelAiInstrumentation::class);
        $this->app->scoped(Instrumentation\NeuronAi\GlintNeuronAiObserver::class);

        foreach ($enabled as $name) {
            if (! is_string($name)) {
                continue;
            }
            $name = trim($name);
            $class = $driverMap[$name] ?? null;

            if ($class === null && class_exists($name) && is_a($name, InstrumentationDriver::class, true)) {
                $class = $name;
            }

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

        \Illuminate\Support\Facades\View::composer('glint::*', function (View $view): void {
            if (array_key_exists('errors', $view->getData()) ||
                array_key_exists('errors', $view->getFactory()->getShared())) {
                return;
            }
            $errors = new ViewErrorBag;
            try {
                $request = request();
                if ($request->hasSession()) {
                    $bag = $request->session()->get('errors');
                    if ($bag instanceof ViewErrorBag) {
                        $errors = $bag;
                    }
                }
            } catch (\Throwable) {
            }
            $view->with('errors', $errors);
        });
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
            Console\Commands\DispatchAlertsCommand::class,
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

        Livewire::component('glint-card', Pulse\GlintCard::class);
    }

    private function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('glint', Middleware\GlintMiddleware::class);

        if (! Gate::has('viewGlint')) {
            Gate::define('viewGlint', fn ($user = null) => false);
        }

        if (! $router->hasMiddlewareGroup('glint-auth')) {
            $router->middlewareGroup('glint-auth', [Authorize::class.':viewGlint']);
        }
    }
}
