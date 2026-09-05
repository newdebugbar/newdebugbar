@props([
    'label' => null,
    'language' => 'json',
    'wrap' => true,
])

<section data-ndb-inspector-evidence {{ $attributes->class('ndb:min-w-0') }}>
    @if ($label !== null || isset($aside))
        <div class="ndb:mb-2 ndb:flex ndb:items-center ndb:justify-between ndb:gap-3">
            @if ($label !== null)
                <h4 class="ndb:text-sm ndb:font-semibold">{{ $label }}</h4>
            @endif
            @isset($aside)
                <div class="ndb:shrink-0">{{ $aside }}</div>
            @endisset
        </div>
    @endif
    <x-newdebugbar::code-block
        :language="$language"
        :wrap="$wrap"
        :code-attributes="$value->attributes"
    >{{ $value }}</x-newdebugbar::code-block>
</section>
