@php
    /** @var \App\Enums\DriverTransferStatus $status */
    $tone = $status->getColor();
@endphp
<span class="badge badge--{{ is_string($tone) ? $tone : 'gray' }}">{{ $status->getLabel() }}</span>
