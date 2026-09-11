<header data-ndb-inspector-header {{ $attributes->class('ndb:px-3 ndb:pt-3 ndb:sm:px-6 ndb:sm:pt-6') }}>
    <h2 {{
        $heading->attributes->class(
            'ndb:text-base ndb:font-bold ndb:leading-5 ndb:focus:outline-none',
        )
    }}>
        {{ $heading }}
    </h2>

    <p {{
        $description->attributes->class(
            'ndb:mt-0.5 ndb:max-w-3xl ndb:text-xs ndb:leading-4 ndb:text-zinc-500 ndb:dark:text-zinc-400',
        )
    }}>
        {{ $description }}
    </p>
</header>
