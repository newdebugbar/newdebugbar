{{-- Shows the selected request and opens the bounded recent-request picker. --}}
@props([
    'scope',
    'direction' => 'dynamic',
])

<div
    data-ndb-request-switcher="{{ $scope }}"
    @click.outside="if (requestPickerScope === @js($scope)) closeRequestPicker(false)"
    {{ $attributes->class('ndb:relative ndb:flex ndb:min-w-0 ndb:self-stretch') }}
>
    <div
        data-ndb-request-control
        class="ndb:flex ndb:min-w-0 ndb:flex-1 ndb:overflow-visible ndb:rounded-xl ndb:border ndb:border-zinc-200/80 ndb:bg-white/35 ndb:transition-colors ndb:dark:border-white/10 ndb:dark:bg-white/5"
        :class="requestPickerScope === @js($scope) ? 'ndb:bg-zinc-100 ndb:dark:bg-white/10' : ''"
    >
        <button
            type="button"
            @if ($scope === 'toolbar') data-ndb-toolbar="request" @endif
            @if ($scope === 'corner') data-ndb-corner-request @endif
            @if ($scope === 'header-mobile') data-ndb-header-mobile-request @endif
            @if ($scope === 'header') data-ndb-header-request @endif
            @click="openRequestSection($el)"
            aria-label="Open current request in Requests"
            class="ndb:flex ndb:min-w-0 ndb:flex-1 ndb:items-center ndb:gap-1 ndb:rounded-l-xl ndb:py-1.5 ndb:pl-1.5 ndb:pr-1.5 ndb:text-left ndb:transition-colors ndb:hover:bg-zinc-100 ndb:focus-visible:z-10 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:min-[360px]:gap-2 ndb:min-[360px]:pl-2.5 ndb:min-[360px]:pr-4 ndb:dark:hover:bg-white/10"
        >
            @if ($scope === 'corner')
                <span class="ndb:min-w-0 ndb:flex-1">
                    <span class="ndb:flex ndb:min-w-0 ndb:items-baseline ndb:gap-1.5">
                        <span
                            data-ndb-request-method="corner"
                            class="ndb:shrink-0 ndb:text-xs ndb:font-bold ndb:uppercase ndb:tracking-wider ndb:text-zinc-500 ndb:dark:text-zinc-400"
                            x-text="summary.method"
                        ></span>
                        <span
                            data-ndb-corner-request-path
                            class="ndb:min-w-0 ndb:truncate ndb:text-xs ndb:font-semibold"
                            :title="summary.path"
                            x-text="summary.path"
                        ></span>
                    </span>
                    <span class="ndb:flex ndb:items-center ndb:gap-1.5 ndb:whitespace-nowrap ndb:text-xs ndb:font-medium ndb:text-zinc-400">
                        <span
                            data-ndb-corner-request-status
                            :class="requestStatusClass(summary.status)"
                            x-text="summary.status"
                        ></span>
                        <span
                            class="ndb:hidden ndb:font-semibold ndb:text-zinc-500 ndb:lg:inline ndb:dark:text-zinc-300"
                            x-show="summary.response_size"
                            x-text="summary.response_size"
                        ></span>
                    </span>
                </span>
            @else
                <span
                    data-ndb-request-method="{{ $scope }}"
                    class="ndb:flex ndb:shrink-0 ndb:items-center ndb:justify-center ndb:rounded-md ndb:bg-indigo-100/60 ndb:px-1.5 ndb:py-0.5 ndb:text-xs ndb:font-bold ndb:uppercase ndb:tracking-wide ndb:text-indigo-700 ndb:dark:bg-white/10 ndb:dark:text-white"
                    x-text="summary.method"
                ></span>
                <span class="ndb:min-w-0">
                    <span
                        @if ($scope === 'toolbar') data-ndb-toolbar-request-path @endif
                        @if ($scope === 'header-mobile') data-ndb-header-mobile-request-path @endif
                        class="ndb:block ndb:truncate ndb:text-xs ndb:font-semibold"
                        :title="summary.path"
                        x-text="summary.path"
                    ></span>
                    <span
                        class="ndb:flex ndb:items-center ndb:gap-1.5 ndb:whitespace-nowrap ndb:text-xs ndb:font-medium ndb:text-zinc-400"
                        ><span
                            @if ($scope === 'toolbar') data-ndb-toolbar-status @endif
                            @if ($scope === 'header-mobile') data-ndb-header-mobile-status @endif
                            @if ($scope === 'header') data-ndb-header-status @endif
                            :class="requestStatusClass(summary.status)"
                            x-text="summary.status"
                        ></span
                        ><span
                            @if ($scope === 'toolbar') data-ndb-toolbar-response-size @endif
                            @if ($scope === 'header') data-ndb-header-response-size @endif
                            class="ndb:hidden ndb:font-semibold ndb:text-zinc-500 ndb:lg:inline ndb:dark:text-zinc-300"
                            x-show="summary.response_size"
                            x-text="summary.response_size"
                        ></span
                    ></span>
                </span>
            @endif
        </button>

        <button
            type="button"
            data-ndb-request-picker-trigger="{{ $scope }}"
            @click.stop="toggleRequestPicker(@js($scope), $el)"
            :aria-expanded="requestPickerScope === @js($scope)"
            :disabled="! hasOtherRequests"
            aria-controls="newdebugbar-request-list-{{ $scope }}"
            aria-haspopup="listbox"
            :aria-label="requestPickerButtonLabel"
            :title="requestPickerButtonLabel"
            class="ndb:relative ndb:flex ndb:shrink-0 ndb:items-center ndb:justify-center ndb:rounded-r-xl ndb:border-l ndb:border-zinc-200/80 ndb:px-0.5 ndb:text-zinc-400 ndb:transition-colors ndb:hover:bg-zinc-100 ndb:hover:text-zinc-700 ndb:focus-visible:z-10 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:disabled:cursor-default ndb:disabled:text-zinc-300 ndb:disabled:hover:bg-transparent ndb:dark:border-white/10 ndb:dark:hover:bg-white/10 ndb:dark:hover:text-zinc-200 ndb:dark:disabled:text-zinc-700 ndb:dark:disabled:hover:bg-transparent"
        >
            <span
                class="ndb:flex ndb:transition-transform ndb:motion-reduce:transition-none"
                :class="requestPickerScope === @js($scope) ? 'ndb:rotate-180' : ''"
            >
                <x-newdebugbar::icon name="chevron-down" class="ndb:size-3.5" />
            </span>
            <span
                x-cloak
                x-show.important="hasOtherRequests"
                x-text="requestBadgeCount"
                data-ndb-request-badge="{{ $scope }}"
                aria-hidden="true"
                class="ndb:absolute ndb:-top-1.5 ndb:-right-1.5 ndb:flex ndb:h-4 ndb:min-w-4 ndb:items-center ndb:justify-center ndb:rounded-full ndb:bg-indigo-600 ndb:px-1 ndb:text-xs ndb:font-bold ndb:leading-none ndb:text-white ndb:tabular-nums ndb:dark:bg-indigo-600 ndb:dark:text-white"
            ></span>
        </button>
    </div>

    <x-newdebugbar::popover-surface
        id="newdebugbar-request-list-{{ $scope }}"
        x-cloak
        x-show.important="requestPickerScope === '{{ $scope }}'"
        x-transition:enter="ndb:transition ndb:duration-150 ndb:ease-out ndb:motion-reduce:transition-none"
        x-transition:enter-start="ndb:scale-95 ndb:opacity-0"
        x-transition:enter-end="ndb:scale-100 ndb:opacity-100"
        x-transition:leave="ndb:transition ndb:duration-100 ndb:ease-in ndb:motion-reduce:transition-none"
        x-transition:leave-start="ndb:scale-100 ndb:opacity-100"
        x-transition:leave-end="ndb:scale-95 ndb:opacity-0"
        data-ndb-request-popover="{{ $scope }}"
        ::style="{ '--ndb-request-arrow-left': requestPickerArrowLeft + 'px' }"
        :direction="$direction"
        :align="$scope === 'corner' ? 'dynamic' : 'left'"
        width-class="ndb:w-[calc(100vw-1.5rem)] ndb:max-w-sm"
        surface-class="ndb:p-0"
        arrow-class="ndb:left-[var(--ndb-request-arrow-left)]"
    >
        <div
            data-ndb-request-popover-heading
            class="ndb:border-b ndb:border-zinc-200/80 ndb:px-3 ndb:py-2.5 ndb:dark:border-zinc-800"
        >
            <span class="ndb:text-xs ndb:font-bold">Requests</span>
        </div>

        <div
            role="listbox"
            aria-label="Recent requests"
            @keydown.escape.stop.prevent="closeRequestPicker()"
            @keydown.arrow-down.prevent="moveRequestPicker(1, $event.currentTarget)"
            @keydown.arrow-up.prevent="moveRequestPicker(-1, $event.currentTarget)"
            @keydown.home.prevent="focusRequestPickerEdge('start', $event.currentTarget)"
            @keydown.end.prevent="focusRequestPickerEdge('end', $event.currentTarget)"
            class="ndb-scrollbar ndb:max-h-[min(24rem,60vh)] ndb:overflow-y-auto ndb:p-1.5"
        >
            <div
                role="group"
                aria-labelledby="newdebugbar-current-request-{{ $scope }}"
                data-ndb-request-group="current"
            >
                <p
                    id="newdebugbar-current-request-{{ $scope }}"
                    class="ndb:px-2.5 ndb:pt-1 ndb:pb-1 ndb:text-xs ndb:font-bold ndb:uppercase ndb:tracking-wider ndb:text-zinc-400"
                >
                    Current request
                </p>
                <template
                    x-for="request in currentRequestProfile ? [currentRequestProfile] : []"
                    :key="@js($scope) + '-current-' + request.id"
                >
                    <x-newdebugbar::request-option />
                </template>
            </div>

            <div
                role="group"
                aria-labelledby="newdebugbar-later-requests-{{ $scope }}"
                data-ndb-request-group="later"
                class="ndb:mt-1 ndb:pt-1"
            >
                <p
                    id="newdebugbar-later-requests-{{ $scope }}"
                    class="ndb:px-2.5 ndb:py-1 ndb:text-xs ndb:font-bold ndb:uppercase ndb:tracking-wider ndb:text-zinc-400"
                >
                    Later requests
                </p>
                <template x-for="request in laterRequestProfiles" :key="@js($scope) + '-later-' + request.id">
                    <x-newdebugbar::request-option />
                </template>
            </div>
        </div>
    </x-newdebugbar::popover-surface>
</div>
