@if($status->value === 'success')
    <span class="badge badge-success">success</span>
@elseif($status->value === 'error')
    <span class="badge badge-error">error</span>
@else
    <span class="badge badge-pending">{{ $status->value }}</span>
@endif
