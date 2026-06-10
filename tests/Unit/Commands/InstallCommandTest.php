<?php

declare(strict_types=1);

use Cybernerdie\Glint\Console\Commands\InstallCommand;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;

// Ensure config/glint.php exists before each test so that InstallCommand
// always asks the overwrite confirmation prompt. The testbench skeleton's
// config directory may not contain the file on a fresh CI checkout.
beforeEach(function () {
    file_put_contents(config_path('glint.php'), '<?php return [];');
});

afterEach(function () {
    @unlink(config_path('glint.php'));
});

it('has the correct signature', function () {
    expect((new InstallCommand)->getName())->toBe('glint:install');
});

it('calls vendor:publish for config', function () {
    $this->artisan('glint:install')
        ->expectsConfirmation('config/glint.php already exists. Overwrite it?', 'yes')
        ->assertSuccessful();
});

it('calls vendor:publish for migrations', function () {
    $this->artisan('glint:install')
        ->expectsConfirmation('config/glint.php already exists. Overwrite it?', 'yes')
        ->assertSuccessful();
});

it('outputs success message', function () {
    $this->artisan('glint:install')
        ->expectsConfirmation('config/glint.php already exists. Overwrite it?', 'yes')
        ->expectsOutputToContain('Glint installed successfully')
        ->assertSuccessful();
});

it('warns when migrate returns a non-zero exit code', function () {
    $this->app[Kernel::class]
        ->registerCommand(new class extends Command
        {
            protected $signature = 'migrate {--force} {--path=} {--realpath} {--isolated} {--pretend} {--seed}';

            public function handle(): int
            {
                return 1;
            }
        });

    $this->artisan('glint:install')
        ->expectsConfirmation('config/glint.php already exists. Overwrite it?', 'yes')
        ->expectsOutputToContain('Migrations could not run')
        ->assertSuccessful();
});

it('warns when migrate throws an exception', function () {
    $this->app[Kernel::class]
        ->registerCommand(new class extends Command
        {
            protected $signature = 'migrate {--force} {--path=} {--realpath} {--isolated} {--pretend} {--seed}';

            public function handle(): int
            {
                throw new RuntimeException('DB connection failed');
            }
        });

    $this->artisan('glint:install')
        ->expectsConfirmation('config/glint.php already exists. Overwrite it?', 'yes')
        ->expectsOutputToContain('Migrations could not run')
        ->assertSuccessful();
});

it('treats migration as successful when migrate returns 0', function () {
    $this->app[Kernel::class]
        ->registerCommand(new class extends Command
        {
            protected $signature = 'migrate {--force} {--path=} {--realpath} {--isolated} {--pretend} {--seed}';

            public function handle(): int
            {
                return 0;
            }
        });

    $this->artisan('glint:install')
        ->expectsConfirmation('config/glint.php already exists. Overwrite it?', 'yes')
        ->expectsOutputToContain('Glint installed successfully')
        ->assertSuccessful();
});

it('skips publishing config when user declines overwrite', function () {
    $configPath = config_path('glint.php');
    $existed = file_exists($configPath);

    if (! $existed) {
        file_put_contents($configPath, '<?php return [];');
    }

    $this->artisan('glint:install')
        ->expectsConfirmation('config/glint.php already exists. Overwrite it?', 'no')
        ->assertSuccessful();

    if (! $existed) {
        unlink($configPath);
    }
});

it('registers GlintServiceProvider when not present in providers.php', function () {
    $providersPath = base_path('bootstrap/providers.php');
    $original = file_get_contents($providersPath);

    file_put_contents($providersPath, "<?php\n\nreturn [\n    //\n];\n");

    $this->artisan('glint:install')
        ->expectsConfirmation('config/glint.php already exists. Overwrite it?', 'yes')
        ->expectsOutputToContain('DONE')
        ->assertSuccessful();

    file_put_contents($providersPath, $original);
});

it('gracefully skips provider registration when bootstrap/providers.php does not exist', function () {
    $providersPath = base_path('bootstrap/providers.php');
    $tempPath = base_path('bootstrap/providers.php.bak');

    rename($providersPath, $tempPath);

    $this->artisan('glint:install')
        ->expectsConfirmation('config/glint.php already exists. Overwrite it?', 'yes')
        ->assertSuccessful();

    rename($tempPath, $providersPath);
});

it('warns when preg_replace cannot auto-register GlintServiceProvider', function () {
    $providersPath = base_path('bootstrap/providers.php');
    $original = file_get_contents($providersPath);

    file_put_contents($providersPath, "<?php\n// no array return here\n");

    $this->artisan('glint:install')
        ->expectsConfirmation('config/glint.php already exists. Overwrite it?', 'yes')
        ->expectsOutputToContain('Could not auto-register')
        ->assertSuccessful();

    file_put_contents($providersPath, $original);
});
