{{--
    Reusable date-range filter component.
    Must be placed inside a <form> element.

    Variables expected from the including view:
      $activePeriod  — current period slug ('' | 'today' | 'week' | 'month' | '3months' | 'custom')
      $activeFrom    — current custom "from" date string (Y-m-d)
      $activeTo      — current custom "to" date string (Y-m-d)
--}}
<div class="date-range-row"
     x-data="{
         period: @js($activePeriod ?: 'today'),
         setPreset(val) {
             this.period = val;
             if (val !== 'custom') {
                 this.$nextTick(() => this.$el.closest('form').submit());
             }
         }
     }">

    {{-- Hidden input carries the selected period on submit --}}
    <input type="hidden" name="period" :value="period">

    <div class="period-tabs" role="group" aria-label="Date range presets">
        @foreach([
            'today'    => 'Today',
            'week'     => 'This week',
            'month'    => 'This month',
            '3months'  => 'Last 3 months',
            'custom'   => 'Custom',
        ] as $value => $label)
            <button type="button"
                    class="period-tab"
                    :class="period === @js($value) ? 'is-active' : ''"
                    @click="setPreset(@js($value))"
                    aria-pressed="{{ ($activePeriod ?: 'today') === $value ? 'true' : 'false' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Custom date range inputs — visible only when period === 'custom' --}}
    <div class="custom-range" x-show="period === 'custom'" style="display:none">
        <label class="sr-only" for="glint-date-from">From</label>
        <input
            type="date"
            id="glint-date-from"
            name="from"
            value="{{ $activeFrom ?? '' }}"
            class="input"
            style="width:150px"
        >
        <span class="custom-range-sep">—</span>
        <label class="sr-only" for="glint-date-to">To</label>
        <input
            type="date"
            id="glint-date-to"
            name="to"
            value="{{ $activeTo ?? '' }}"
            class="input"
            style="width:150px"
        >
        <button type="submit" class="btn btn-primary btn-sm">Apply</button>
    </div>

</div>
