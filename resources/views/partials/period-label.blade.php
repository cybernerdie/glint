
@if($period === '7d')Last 7 days
@elseif($period === '30d')Last 30 days
@elseif($period === '90d')Last 90 days
@elseif($period === 'custom' && !empty($fromDate) && !empty($toDate)){{ $fromDate }} — {{ $toDate }}
@else Last 24 hours
@endif
