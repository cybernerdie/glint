# Laravel Pulse Integration

Glint ships an optional card for [Laravel Pulse](https://pulse.laravel.com) that shows today's LLM cost, request count, error rate, and a 7-day cost sparkline — all sourced from Glint's own `glint_aggregates` table.

## Requirements

- `laravel/pulse` installed and configured

## Setup

**1. Install Pulse** (if not already):

```bash
composer require laravel/pulse
php artisan vendor:publish --provider="Laravel\Pulse\PulseServiceProvider"
php artisan migrate
```

**2. Enable the card** in `.env`:

```env
GLINT_PULSE_ENABLED=true
```

**3. Add to your Pulse dashboard view** (`resources/views/vendor/pulse/dashboard.blade.php`):

```blade
<livewire:cybernerdie.glint::glint-card cols="2" />
```

## Notes

- The card reads from `glint_aggregates`. Data appears once Glint has recorded at least one generation.
- No Pulse recorder configuration is required — Glint writes its own aggregates independently.
- The card requires Glint recording to be active (`GLINT_ENABLED=true`).
