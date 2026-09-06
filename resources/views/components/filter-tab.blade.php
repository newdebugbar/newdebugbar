@props(['variant' => 'tabs'])

@php
    $classes = match ($variant) {
        'tabs' => 'ndb:flex ndb:shrink-0 ndb:items-baseline ndb:gap-1.5 ndb:whitespace-nowrap ndb:rounded-lg ndb:border ndb:border-transparent ndb:bg-zinc-100/70 ndb:px-3 ndb:py-2 ndb:text-xs ndb:font-semibold ndb:text-zinc-500 ndb:transition-[background-color,color] ndb:hover:bg-zinc-200/70 ndb:hover:text-zinc-950 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:aria-pressed:border-indigo-200 ndb:aria-pressed:bg-indigo-50 ndb:aria-pressed:text-indigo-700 ndb:aria-pressed:hover:bg-indigo-50 ndb:aria-pressed:hover:text-indigo-700 ndb:aria-selected:border-indigo-200 ndb:aria-selected:bg-indigo-50 ndb:aria-selected:text-indigo-700 ndb:aria-selected:hover:bg-indigo-50 ndb:aria-selected:hover:text-indigo-700 ndb:dark:bg-zinc-900/70 ndb:dark:text-zinc-400 ndb:dark:hover:bg-zinc-800 ndb:dark:hover:text-white ndb:dark:aria-pressed:border-indigo-900 ndb:dark:aria-pressed:bg-indigo-950/60 ndb:dark:aria-pressed:text-indigo-300 ndb:dark:aria-pressed:hover:bg-indigo-950/60 ndb:dark:aria-pressed:hover:text-indigo-300 ndb:dark:aria-selected:border-indigo-900 ndb:dark:aria-selected:bg-indigo-950/60 ndb:dark:aria-selected:text-indigo-300 ndb:dark:aria-selected:hover:bg-indigo-950/60 ndb:dark:aria-selected:hover:text-indigo-300',
        'segmented' => 'ndb:relative ndb:flex ndb:min-w-0 ndb:items-baseline ndb:justify-center ndb:gap-1.5 ndb:whitespace-nowrap ndb:rounded-md ndb:border ndb:border-transparent ndb:bg-transparent ndb:px-3 ndb:py-1.5 ndb:text-xs ndb:font-semibold ndb:text-zinc-500 ndb:transition-[background-color,color,box-shadow] ndb:hover:bg-white/60 ndb:hover:text-zinc-950 ndb:focus-visible:z-10 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:aria-pressed:bg-white ndb:aria-pressed:text-zinc-950 ndb:aria-pressed:shadow-sm ndb:aria-pressed:hover:bg-white ndb:aria-pressed:hover:text-zinc-950 ndb:aria-selected:bg-white ndb:aria-selected:text-zinc-950 ndb:aria-selected:shadow-sm ndb:aria-selected:hover:bg-white ndb:aria-selected:hover:text-zinc-950 ndb:dark:text-zinc-400 ndb:dark:hover:bg-zinc-800/70 ndb:dark:hover:text-white ndb:dark:aria-pressed:bg-zinc-800 ndb:dark:aria-pressed:text-white ndb:dark:aria-pressed:shadow-[0_1px_2px_rgba(0,0,0,0.35)] ndb:dark:aria-pressed:hover:bg-zinc-800 ndb:dark:aria-pressed:hover:text-white ndb:dark:aria-selected:bg-zinc-800 ndb:dark:aria-selected:text-white ndb:dark:aria-selected:shadow-[0_1px_2px_rgba(0,0,0,0.35)] ndb:dark:aria-selected:hover:bg-zinc-800 ndb:dark:aria-selected:hover:text-white',
        default => throw new \InvalidArgumentException("Unknown filter tab variant [{$variant}]."),
    };
@endphp

<button
    type="button"
    data-ndb-filter-tab
    data-ndb-filter-tab-variant="{{ $variant }}"
    {{ $attributes->class($classes) }}
>
    {{ $slot }}
</button>
