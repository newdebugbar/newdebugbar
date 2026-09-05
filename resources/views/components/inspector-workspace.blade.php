@props([
    'mode' => 'split',
    'frame' => 'card',
    'detailOpen' => 'false',
    'detailId' => null,
    'detailRef' => 'workspaceDetail',
    'detailLabel' => 'Selected item details',
    'backLabel' => 'Items',
    'closeAction' => '',
])

@php
    $frameClass = match ($frame) {
        'card' => 'ndb:rounded-xl ndb:border ndb:border-zinc-200/90 ndb:dark:border-zinc-800',
        'top' => 'ndb:-mx-3 ndb:border-t ndb:border-zinc-200/90 ndb:sm:mx-0 ndb:dark:border-zinc-800',
        default => throw new \InvalidArgumentException("Unknown inspector workspace frame [{$frame}]."),
    };

    $workspaceClass = match ($mode) {
        'split' => "ndb:overflow-hidden ndb:bg-white/45 ndb:lg:grid ndb:lg:min-h-0 ndb:lg:flex-1 ndb:lg:grid-cols-[minmax(18rem,0.72fr)_minmax(0,1.68fr)] ndb:dark:bg-zinc-950/35 {$frameClass}",
        'focus' => "ndb:min-h-0 ndb:min-w-0 ndb:flex-1 {$frameClass}",
        'stream' => "ndb:flex ndb:min-h-0 ndb:min-w-0 ndb:flex-1 ndb:flex-col ndb:overflow-hidden ndb:bg-white/45 ndb:dark:bg-zinc-950/35 {$frameClass}",
        default => throw new \InvalidArgumentException("Unknown inspector workspace mode [{$mode}]."),
    };

    if ($mode === 'focus' && (! is_string($detailId) || ! str_starts_with($detailId, 'newdebugbar'))) {
        throw new \InvalidArgumentException('Focused inspector workspaces require a namespaced detail ID.');
    }
@endphp

<div {{ $attributes->class($workspaceClass) }}>
    @if ($mode === 'focus')
        <div
            x-cloak
            x-show.important="! ({{ $detailOpen }})"
            data-ndb-inspector-focus-list
            {{ $list->attributes->class('ndb:min-w-0') }}
        >
            {{ $list }}
        </div>

        <section
            id="{{ $detailId }}"
            x-cloak
            x-show.important="{{ $detailOpen }}"
            x-ref="{{ $detailRef }}"
            data-ndb-inspector-focus-detail
            aria-live="polite"
            aria-label="{{ $detailLabel }}"
            tabindex="-1"
            {{ $detail->attributes->class('ndb:@container ndb:min-w-0 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500') }}
        >
            <x-newdebugbar::inspector-detail-back
                persistent
                data-ndb-inspector-focus-back
                @click="{{ $closeAction }}"
                :label="$backLabel"
            />

            {{ $detail }}
        </section>
    @elseif ($mode === 'stream')
        @isset($controls)
            <div {{ $controls->attributes->class('ndb:border-b ndb:border-zinc-200/90 ndb:p-3 ndb:dark:border-zinc-800') }}>
                {{ $controls }}
            </div>
        @endisset

        @isset($header)
            <div {{ $header->attributes->class('ndb:border-b ndb:border-zinc-200/90 ndb:dark:border-zinc-800') }}>
                {{ $header }}
            </div>
        @endisset

        @isset($body)
            <div
                data-ndb-inspector-stream-body
                {{ $body->attributes->class('ndb-scrollbar ndb:min-h-0 ndb:flex-1 ndb:overflow-y-auto') }}
            >
                {{ $body }}
            </div>
        @else
            <div data-ndb-inspector-stream-body class="ndb-scrollbar ndb:min-h-0 ndb:flex-1 ndb:overflow-y-auto">
                {{ $slot }}
            </div>
        @endisset

        @isset($empty)
            <div {{ $empty->attributes->class('ndb:p-3') }}>{{ $empty }}</div>
        @endisset
    @else
        {{ $slot }}
    @endif
</div>
