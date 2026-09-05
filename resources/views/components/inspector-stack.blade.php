@props([
    'frames',
    'emptyLabel' => 'No application stack was captured.',
    'showHeading' => true,
    'title' => 'Application stack',
])

<section
    data-ndb-inspector-stack
    {{ $attributes->class('ndb:mt-4 ndb:border-0 ndb:bg-transparent ndb:p-0 ndb:text-inherit ndb:sm:mt-5') }}
>
    @if ($showHeading)
        <div class="ndb:flex ndb:items-center ndb:justify-between ndb:gap-3 ndb:border-b ndb:border-zinc-200/90 ndb:pb-2 ndb:dark:border-zinc-800">
            <h4 class="ndb:text-xs ndb:font-bold ndb:text-zinc-800 ndb:dark:text-zinc-100">{{ $title }}</h4>
            <span
                x-show.important="({{ $frames }}).length > 0"
                class="ndb:text-xs ndb:font-medium ndb:text-zinc-400"
                x-text="({{ $frames }}).length + (({{ $frames }}).length === 1 ? ' frame' : ' frames')"
            ></span>
        </div>
    @endif
    <template x-if="({{ $frames }}).length === 0">
        <p class="ndb:pt-3 ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400">{{ $emptyLabel }}</p>
    </template>
    <ol class="ndb:m-0 ndb:list-none ndb:divide-y ndb:divide-zinc-200/90 ndb:p-0 ndb:dark:divide-zinc-800">
        <template x-for="(frame, index) in {{ $frames }}" :key="frame.file + ':' + frame.line + ':' + index">
            <li class="ndb:flex ndb:min-w-0 ndb:items-start ndb:gap-3 ndb:py-2.5 ndb:sm:py-3">
                <span
                    class="ndb:flex ndb:size-6 ndb:shrink-0 ndb:items-center ndb:justify-center ndb:rounded-md ndb:bg-zinc-100 ndb:text-xs ndb:font-semibold ndb:tabular-nums ndb:text-zinc-500 ndb:dark:bg-zinc-800 ndb:dark:text-zinc-400"
                    x-text="index + 1"
                ></span>
                <span class="ndb:min-w-0 ndb:flex-1">
                    <code
                        class="ndb:block ndb:truncate ndb:font-mono ndb:text-xs ndb:font-semibold ndb:leading-5 ndb:text-zinc-700 ndb:dark:text-zinc-200"
                        x-text="frame.function || 'Application call'"
                    ></code>
                    <x-newdebugbar::inspector-source-link
                        class="ndb:mt-0.5"
                        @click="copyText(frame.file + ':' + frame.line)"
                        ::aria-label="'Copy source ' + frame.file + ':' + frame.line"
                    >
                        <x-slot:value x-text="frame.file + ':' + frame.line"></x-slot:value>
                    </x-newdebugbar::inspector-source-link>
                </span>
            </li>
        </template>
    </ol>
</section>
