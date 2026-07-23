@php $sv = $status->value; @endphp
@if($sv === 'success')
    <span class="badge badge-success">success</span>
@elseif($sv === 'error')
    <span class="badge badge-error">error</span>
@elseif($sv === 'running')
    <span class="badge badge-running">running</span>
@else
    <span class="badge badge-pending">{{ $sv }}</span>
@endif
