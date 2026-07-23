
@php $glintPeriod = $activePeriod ?: '24h'; @endphp

<div class="seg-anchor" x-data="{ customOpen: false, period: @js($glintPeriod) }">
    <input type="hidden" name="period" :value="period" value="{{ $glintPeriod }}">

    <div class="seg" role="group" aria-label="Date range">
        @foreach(['24h' => '24H', '7d' => '7D', '30d' => '30D', '90d' => '90D'] as $value => $label)
            <button type="button"
                    class="seg-btn {{ $glintPeriod === $value ? 'is-active' : '' }}"
                    @click="period = '{{ $value }}'; $nextTick(() => $el.closest('form').requestSubmit())">
                {{ $label }}
            </button>
        @endforeach

        <button type="button"
                class="seg-btn {{ $glintPeriod === 'custom' ? 'is-active' : '' }}"
                aria-label="Custom date range"
                @click="customOpen = !customOpen">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
            </svg>
            @if($glintPeriod === 'custom' && !empty($activeFrom) && !empty($activeTo))
                <span style="font-size:11px;font-family:var(--font-mono)">{{ $activeFrom }} – {{ $activeTo }}</span>
            @endif
        </button>
    </div>
    <div class="seg-pop" x-show="customOpen" style="display:none" @click.outside="customOpen = false">
        <label class="sr-only" for="glint-date-from">From</label>
        <input
            type="date"
            id="glint-date-from"
            name="from"
            value="{{ $activeFrom ?? '' }}"
            class="input"
            style="width:140px"
            :disabled="period !== 'custom' && !customOpen"
            {{ $glintPeriod !== 'custom' ? 'disabled' : '' }}
        >
        <span class="custom-range-sep">–</span>
        <label class="sr-only" for="glint-date-to">To</label>
        <input
            type="date"
            id="glint-date-to"
            name="to"
            value="{{ $activeTo ?? '' }}"
            class="input"
            style="width:140px"
            :disabled="period !== 'custom' && !customOpen"
            {{ $glintPeriod !== 'custom' ? 'disabled' : '' }}
        >
        <button type="button" class="btn btn-primary btn-sm"
                @click="period = 'custom'; $nextTick(() => $el.closest('form').requestSubmit())">
            Apply
        </button>
    </div>
</div>
