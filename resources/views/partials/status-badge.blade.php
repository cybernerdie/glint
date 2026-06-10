@php $sv = $status->value; @endphp
@if($sv === 'success')
    <span class="badge badge-success"><span class="badge-dot"></span>success</span>
@elseif($sv === 'error')
    <span class="badge badge-error"><span class="badge-dot"></span>error</span>
@elseif($sv === 'running')
    <span class="badge badge-running"><span class="badge-dot"></span>running</span>
@else
    <span class="badge badge-pending"><span class="badge-dot"></span>{{ $sv }}</span>
@endif
