{{--
    Renders a human-readable label for the active date period.
    Variables expected:
      $period   — period slug ('today'|'week'|'month'|'3months'|'custom'|'')
      $fromDate — custom from date (Y-m-d), optional
      $toDate   — custom to date (Y-m-d), optional
--}}
@if($period === 'week')This week
@elseif($period === 'month')This month
@elseif($period === '3months')Last 3 months
@elseif($period === 'custom' && !empty($fromDate) && !empty($toDate)){{ $fromDate }} — {{ $toDate }}
@else Today
@endif
