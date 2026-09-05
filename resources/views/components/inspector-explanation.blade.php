@props([
    'title' => null,
    'description' => null,
])

<header {{ $attributes->class('ndb:min-w-0') }}>
    <h4 class="ndb:text-xs ndb:font-bold">
        @isset($heading)
            <span {{ $heading->attributes }}>{{ $heading }}</span>
        @else
            {{ $title }}
        @endisset
    </h4>
    <p class="ndb:mt-0.5 ndb:max-w-3xl ndb:text-xs ndb:leading-5 ndb:text-zinc-500 ndb:dark:text-zinc-400">
        @isset($body)
            <span {{ $body->attributes }}>{{ $body }}</span>
        @else
            {{ $description }}
        @endisset
    </p>
</header>
