{{-- Renders one exception as focused evidence and multiple exceptions as a list-detail inspector. --}}
@php
    $exceptions = array_values($inspector['payload']['items'] ?? []);
    $profileActionLabel = match ($profile['profile_type'] ?? 'http') {
        'http' => 'Open request',
        'queue' => 'Open worker',
        'artisan' => 'Open command',
        'test' => 'Open test run',
        default => 'Open runtime',
    };
@endphp

@if (count($exceptions) === 1)
    <div
        data-ndb-exceptions
        data-ndb-exception-layout="focused"
        class="ndb:flex ndb:min-h-0 ndb:flex-1 ndb:flex-col ndb:overflow-hidden ndb:border-0 ndb:bg-transparent ndb:p-0"
    >
        <x-newdebugbar::inspector-workspace
            mode="stream"
            frame="top"
            data-ndb-exception-workspace
            data-ndb-exception-focused-workspace
            class="ndb:border-l-0 ndb:p-0"
        >
            <x-slot:body data-ndb-exception-focused-detail class="ndb:border-0 ndb:bg-transparent ndb:p-0">
                <x-newdebugbar::exception-detail
                    :exception="$exceptions[0]"
                    :index="0"
                    :profile-action-label="$profileActionLabel"
                />
            </x-slot:body>
        </x-newdebugbar::inspector-workspace>
    </div>
@elseif ($exceptions !== [])
    <div
        data-ndb-exceptions
        data-ndb-exception-layout="split"
        x-data="{
            selectedException: 0,
            exceptionDetailOpen: false,
            selectException(index) {
                this.selectedException = index;
                this.exceptionDetailOpen = true;
                this.$nextTick(() => this.$refs.exceptionDetail?.focus());
            },
            closeException() {
                const selected = this.selectedException;
                this.exceptionDetailOpen = false;
                this.$nextTick(() => this.$root.querySelector(`[data-ndb-exception-item='${selected}']`)?.focus());
            },
        }"
        class="ndb:flex ndb:min-h-0 ndb:flex-1 ndb:flex-col ndb:overflow-hidden ndb:border-0 ndb:bg-transparent ndb:p-0"
    >
        <x-newdebugbar::inspector-workspace frame="top" data-ndb-exception-workspace class="ndb:border-l-0 ndb:p-0">
            <x-newdebugbar::inspector-list-panel
                detail-open="exceptionDetailOpen"
                list-ref="exceptionList"
                data-ndb-exception-list-panel
                class="ndb:border-l-0 ndb:bg-transparent ndb:p-0"
            >
                <x-slot:controls>
                    <x-newdebugbar::inspector-list-controls :show-search="false">
                        <x-slot:leading>
                            <p class="ndb:text-xs ndb:font-semibold ndb:text-zinc-600 ndb:dark:text-zinc-300">
                                {{ number_format(count($exceptions)) }} {{ \Illuminate\Support\Str::plural('exception', count($exceptions)) }}
                            </p>
                        </x-slot:leading>
                    </x-newdebugbar::inspector-list-controls>
                </x-slot:controls>

                <x-slot:list data-ndb-exception-list>
                    @foreach ($exceptions as $index => $exception)
                        <x-newdebugbar::exception-list-item :exception="$exception" :index="$index" />
                    @endforeach
                </x-slot:list>
            </x-newdebugbar::inspector-list-panel>

            <x-newdebugbar::inspector-detail-pane
                detail-open="exceptionDetailOpen"
                detail-ref="exceptionDetail"
                detail-label="Selected exception details"
                back-label="Exceptions"
                close-action="closeException()"
                data-ndb-exception-split-detail
                class="ndb:bg-transparent ndb:p-0"
            >
                <x-slot:back>
                    <x-newdebugbar::inspector-detail-back
                        data-ndb-exception-detail-back
                        @click="closeException()"
                        label="Exceptions"
                        class="ndb:bg-transparent"
                    />
                </x-slot:back>

                @foreach ($exceptions as $index => $exception)
                    <template x-if="selectedException === {{ $index }}">
                        <x-newdebugbar::exception-detail
                            :exception="$exception"
                            :index="$index"
                            :profile-action-label="$profileActionLabel"
                        />
                    </template>
                @endforeach
            </x-newdebugbar::inspector-detail-pane>
        </x-newdebugbar::inspector-workspace>
    </div>
@else
    <x-newdebugbar::empty-state label="No exceptions were reported." success />
@endif
