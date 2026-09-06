<ol data-ndb-livewire-activity-list aria-label="Livewire activity timeline" class="ndb:m-0 ndb:list-none ndb:p-3">
    <template x-for="(item, index) in filteredLivewireActivity" :key="item.id">
        <li data-ndb-livewire-activity-timeline-item class="ndb:flex ndb:min-w-0 ndb:gap-3">
            <span aria-hidden="true" class="ndb:relative ndb:w-4 ndb:shrink-0">
                <span
                    data-ndb-livewire-activity-connector
                    x-show.important="index < filteredLivewireActivity.length - 1"
                    class="ndb:absolute ndb:top-5 ndb:-bottom-5 ndb:left-1/2 ndb:w-px ndb:-translate-x-1/2 ndb:bg-zinc-200 ndb:dark:bg-zinc-800"
                ></span>
                <span
                    data-ndb-livewire-activity-dot
                    class="ndb:absolute ndb:top-5 ndb:left-1/2 ndb:z-10 ndb:size-2.5 ndb:-translate-x-1/2 ndb:-translate-y-1/2 ndb:rounded-full ndb:ring-4 ndb:ring-white ndb:dark:ring-zinc-950"
                    :class="item.status === 'failed' || item.status === 'failed_validation'
                        ? 'ndb:bg-red-500'
                        : item.status === 'updating' || livewireSelectedActivityId === item.id
                          ? 'ndb:bg-indigo-500'
                          : 'ndb:bg-zinc-300 ndb:dark:bg-zinc-700'"
                ></span>
            </span>

            <div class="ndb:min-w-0 ndb:flex-1 ndb:pb-2">
                @include('newdebugbar::livewire.livewire.activity-item')
            </div>
        </li>
    </template>
</ol>

<div x-show.important="filteredLivewireActivity.length === 0" class="ndb:p-3">
    <x-newdebugbar::empty-state label="No Livewire activity matches this view." />
</div>
