{{-- Renders captured database queries. --}}
<x-newdebugbar::query-inspector
    :inspector="$inspector"
    :query-explains="$queryExplains"
    :query-explain-errors="$queryExplainErrors"
/>
