{{-- Renders collector data that has no specialized section view. --}}
@php($items = array_values($section['payload']['items'] ?? []))

@if ($items !== [])
    <div class="ndb:flex ndb:min-h-0 ndb:flex-1 ndb:flex-col">
        <x-newdebugbar::inspector-workspace mode="stream" frame="top" data-ndb-fallback-workspace>
            <x-slot:controls>
                <x-newdebugbar::inspector-list-controls :show-search="false">
                    <x-slot:leading>
                        <p class="ndb:text-xs ndb:font-semibold ndb:text-zinc-600 ndb:dark:text-zinc-300">
                            {{ number_format(count($items)) }} {{ \Illuminate\Support\Str::plural('entry', count($items)) }}
                        </p>
                    </x-slot:leading>
                </x-newdebugbar::inspector-list-controls>
            </x-slot:controls>

            <x-slot:body class="ndb:divide-y ndb:divide-zinc-200/90 ndb:dark:divide-zinc-800">
                @foreach ($items as $index => $item)
                    <details
                        wire:key="{{ $sectionKey }}-{{ $index }}"
                        class="ndb:group ndb:m-0 ndb:bg-transparent ndb:p-0"
                    >
                        <summary class="ndb:flex ndb:cursor-pointer ndb:list-none ndb:items-center ndb:gap-3 ndb:px-3 ndb:py-3 ndb:text-xs ndb:font-semibold ndb:focus-visible:outline-2 ndb:focus-visible:outline-inset ndb:focus-visible:outline-indigo-500 ndb:sm:px-4">
                            <span class="ndb:text-xs ndb:font-bold ndb:tabular-nums ndb:text-zinc-400">{{ $index + 1 }}</span>
                            <span class="ndb:min-w-0 ndb:flex-1 ndb:truncate">
                                {{ $item['model'] ?? $item['name'] ?? $item['event'] ?? $item['level'] ?? $item['operation'] ?? $section['label'] }}
                            </span>
                            <span class="ndb:text-xs ndb:font-semibold ndb:text-zinc-400 ndb:group-open:hidden">Show</span>
                            <span class="ndb:hidden ndb:text-xs ndb:font-semibold ndb:text-zinc-400 ndb:group-open:inline">Hide</span>
                        </summary>
                        <x-newdebugbar::code-block
                            language="json"
                            class="ndb:rounded-none ndb:border-t ndb:border-zinc-200 ndb:dark:border-zinc-800"
                        >{{ json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</x-newdebugbar::code-block>
                    </details>
                @endforeach
            </x-slot:body>
        </x-newdebugbar::inspector-workspace>
    </div>
@else
    <x-newdebugbar::empty-state :label="'No '.strtolower($section['label']).' were captured.'" />
@endif
