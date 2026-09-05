<button
    type="button"
    data-ndb-livewire-activity-item
    :data-ndb-livewire-activity-kind="item.kind"
    @click="selectLivewireActivity(item.id)"
    :aria-pressed="livewireSelectedActivityId === item.id"
    :class="livewireSelectedActivityId === item.id
        ? 'ndb:bg-indigo-50/65 ndb:dark:bg-indigo-950/20'
        : 'ndb:hover:bg-zinc-50/80 ndb:dark:hover:bg-zinc-900/60'"
    class="ndb:grid ndb:h-auto ndb:w-full ndb:min-w-0 ndb:grid-cols-[minmax(0,1fr)_4.75rem] ndb:items-center ndb:gap-3 ndb:px-3 ndb:py-2.5 ndb:text-left ndb:transition-colors ndb:focus-visible:relative ndb:focus-visible:z-10 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500"
>
    <span class="ndb:min-w-0">
        <span class="ndb:flex ndb:min-w-0 ndb:items-center ndb:gap-2">
            <span
                data-ndb-livewire-activity-title
                class="ndb:min-w-0 ndb:truncate ndb:text-xs ndb:font-bold"
                x-text="item.title"
            ></span>
            <span
                x-show.important="item.status === 'failed' || item.status === 'failed_validation'"
                class="ndb:shrink-0 ndb:text-xs ndb:font-bold ndb:text-red-600 ndb:dark:text-red-300"
                x-text="livewireActivityStatusLabel(item)"
            ></span>
            <span
                x-show.important="item.status === 'updating'"
                class="ndb:shrink-0 ndb:text-xs ndb:font-bold ndb:text-indigo-600 ndb:dark:text-indigo-300"
            >Running</span>
        </span>
        <span
            x-show.important="livewireActivityShowsComponent(item)"
            data-ndb-livewire-activity-component
            class="ndb:mt-0.5 ndb:block ndb:min-w-0 ndb:truncate ndb:text-xs ndb:font-medium ndb:text-zinc-500 ndb:dark:text-zinc-400"
            x-text="livewireActivityComponentTitle(item)"
        ></span>
    </span>

    <span class="ndb:flex ndb:min-w-0 ndb:flex-col ndb:items-end ndb:gap-0.5 ndb:text-xs ndb:tabular-nums">
        <span
            data-ndb-livewire-activity-time
            class="ndb:max-w-full ndb:truncate ndb:font-medium ndb:text-zinc-400"
            x-text="livewireActivityTime(item)"
        ></span>
        <span
            data-ndb-livewire-activity-duration
            class="ndb:whitespace-nowrap ndb:font-semibold ndb:text-zinc-600 ndb:dark:text-zinc-300"
            x-text="livewireActivityDuration(item)"
        ></span>
    </span>
</button>
