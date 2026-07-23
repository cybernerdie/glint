{{--
    Renders a human-readable label for the active rolling-window period.
    Variables expected:
      $period   — period slug ('24h'|'7d'|'30d'|'90d'|'custom'|'')
      $fromDate — custom from date (Y-m-d), optional
      $toDate   — custom to date (Y-m-d), optional
--}}
@if($period === '7d')Last 7 days
@elseif($period === '30d')Last 30 days
@elseif($period === '90d')Last 90 days
@elseif($period === 'custom' && !empty($fromDate) && !empty($toDate)){{ $fromDate }} — {{ $toDate }}
@else Last 24 hours
@endif
