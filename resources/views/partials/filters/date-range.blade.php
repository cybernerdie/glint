{{--
    Reusable date-range filter component.
    Must be placed inside a <form> element.

    Variables expected from the including view:
      $activePeriod  — current period slug ('' | 'today' | 'week' | 'month' | '3months' | 'custom')
      $activeFrom    — current custom "from" date string (Y-m-d)
      $activeTo      — current custom "to" date string (Y-m-d)
--}}
<div class="date-range-row"
     x-data="{ period: @js($activePeriod ?: 'today') }">

    <label class="sr-only" for="glint-period-select">Date range</label>
    <select
        id="glint-period-select"
        name="period"
        class="input"
        style="width:148px"
        x-model="period"
        @change="period !== 'custom' && $nextTick(() => $el.closest('form').submit())"
    >
        <option value="today">Today</option>
        <option value="week">This week</option>
        <option value="month">This month</option>
        <option value="3months">Last 3 months</option>
        <option value="custom">Custom range</option>
    </select>

    {{-- Custom date inputs — shown only when Custom is selected --}}
    <div class="custom-range" x-show="period === 'custom'" style="display:none">
        <label class="sr-only" for="glint-date-from">From</label>
        <input
            type="date"
            id="glint-date-from"
            name="from"
            value="{{ $activeFrom ?? '' }}"
            class="input"
            style="width:140px"
        >
        <span class="custom-range-sep">—</span>
        <label class="sr-only" for="glint-date-to">To</label>
        <input
            type="date"
            id="glint-date-to"
            name="to"
            value="{{ $activeTo ?? '' }}"
            class="input"
            style="width:140px"
        >
        <button type="submit" class="btn btn-primary btn-sm">Apply</button>
    </div>

</div>
