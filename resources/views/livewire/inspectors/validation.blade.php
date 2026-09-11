{{-- Renders validation failures with their messages, rules, and application source. --}}
@php($validationItems = array_values($inspector['payload']['items'] ?? []))

@if ($validationItems !== [])
    <div class="ndb:flex ndb:min-h-0 ndb:flex-1 ndb:flex-col">
        <x-newdebugbar::inspector-workspace mode="stream" frame="top" data-ndb-validation-workspace>
            <x-slot:controls>
                <x-newdebugbar::inspector-list-controls :show-search="false">
                    <x-slot:leading>
                        <p class="ndb:text-xs ndb:font-semibold ndb:text-zinc-600 ndb:dark:text-zinc-300">
                            {{ number_format(count($validationItems)) }} {{ \Illuminate\Support\Str::plural('validation attempt', count($validationItems)) }}
                        </p>
                    </x-slot:leading>
                </x-newdebugbar::inspector-list-controls>
            </x-slot:controls>

            <x-slot:body data-ndb-validation-list class="ndb:divide-y ndb:divide-zinc-200/90 ndb:dark:divide-zinc-800">
                @foreach ($validationItems as $index => $item)
                    <x-newdebugbar::validation-entry :item="$item" :index="$index" />
                @endforeach
            </x-slot:body>
        </x-newdebugbar::inspector-workspace>
    </div>
@else
    <x-newdebugbar::empty-state label="No validation failures were captured. This does not prove validation ran." />
@endif
