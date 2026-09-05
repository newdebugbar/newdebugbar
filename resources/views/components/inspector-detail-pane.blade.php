@props([
    'detailOpen',
    'detailRef',
    'detailLabel',
    'backLabel',
    'closeAction',
])

<section
    x-ref="{{ $detailRef }}"
    aria-live="polite"
    aria-label="{{ $detailLabel }}"
    tabindex="0"
    :class="{{ $detailOpen }} ? 'ndb:flex' : 'ndb:hidden ndb:lg:flex'"
    {{ $attributes->class('ndb-scrollbar ndb:@container ndb:min-h-[32rem] ndb:min-w-0 ndb:flex-col ndb:border-0 ndb:scroll-mt-20 ndb:text-sm ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:lg:min-h-0 ndb:lg:overflow-y-auto') }}
>
    @isset($back)
        {{ $back }}
    @else
        <x-newdebugbar::inspector-detail-back @click="{{ $closeAction }}" :label="$backLabel" />
    @endisset

    {{ $slot }}
</section>
