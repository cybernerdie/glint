{{--
    The Glint spark — single source of truth for the brand mark.

    Variables expected from the including view:
      $variant — 'mark' (default): currentColor spark for inline UI use
                 'icon': standalone gradient tile (favicon / app icon)
--}}
@php
    $variant = $variant ?? 'mark';
    $spark = 'M8 0.5 Q8.9 5.2 14.5 8 Q8.9 10.8 8 15.5 Q7.1 10.8 1.5 8 Q7.1 5.2 8 0.5Z';
@endphp
@if($variant === 'icon')
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><defs><linearGradient id="glint-g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FF7E47"/><stop offset="1" stop-color="#DE4A18"/></linearGradient></defs><rect width="16" height="16" rx="3.5" fill="url(#glint-g)"/><path fill="#fff" transform="translate(2.96 2.96) scale(0.63)" d="{{ $spark }}"/></svg>
@else
<svg viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="{{ $spark }}"/></svg>
@endif
