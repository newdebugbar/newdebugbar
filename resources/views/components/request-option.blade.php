{{-- One fixed-column request option shared by the current and later request groups. --}}
<button
    type="button"
    role="option"
    data-ndb-request-option
    :data-ndb-profile-id="request.id"
    :aria-selected="request.id === summary.id"
    :aria-busy="requestSelectionPending === request.id"
    @click="selectRequest(request.id)"
    class="ndb:flex ndb:w-full ndb:min-w-0 ndb:items-center ndb:gap-2.5 ndb:rounded-xl ndb:px-2.5 ndb:py-2 ndb:text-left ndb:transition-colors ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500"
    :class="request.id === summary.id
        ? 'ndb:bg-indigo-100/60 ndb:text-indigo-950 ndb:dark:bg-indigo-950/70 ndb:dark:text-indigo-100'
        : requestSelectionPending === request.id
          ? 'ndb:opacity-60'
          : 'ndb:hover:bg-zinc-100/70 ndb:dark:hover:bg-white/10'"
>
    <span
        data-ndb-request-method
        class="ndb:flex ndb:w-12 ndb:shrink-0 ndb:items-center ndb:justify-center ndb:rounded-md ndb:bg-zinc-100/70 ndb:py-0.5 ndb:text-xs ndb:font-bold ndb:uppercase ndb:tracking-wide ndb:text-zinc-600 ndb:dark:bg-white/10 ndb:dark:text-white"
        x-text="request.method"
    ></span>
    <span class="ndb:min-w-0 ndb:flex-1">
        <span
            class="ndb:block ndb:truncate ndb:text-xs ndb:font-semibold"
            :title="requestTitle(request)"
            x-text="requestTitle(request)"
        ></span>
        <span class="ndb:mt-0.5 ndb:flex ndb:flex-wrap ndb:items-center ndb:gap-x-2 ndb:gap-y-0.5 ndb:text-xs ndb:font-medium ndb:text-zinc-400">
            <span x-text="requestTypeLabel(request.request_type)"></span>
            <span class="ndb:tabular-nums" x-text="request.duration_label"></span>
            <span
                class="ndb:tabular-nums"
                x-text="request.query_count + (request.query_count === 1 ? ' query' : ' queries')"
            ></span>
            <time :datetime="request.recorded_at" x-text="relativeRequestTime(request)"></time>
        </span>
    </span>
    <span
        data-ndb-request-status
        class="ndb:w-8 ndb:shrink-0 ndb:self-center ndb:text-center ndb:text-xs ndb:font-bold ndb:tabular-nums"
        :class="requestStatusClass(request.status)"
        x-text="request.status"
    ></span>
    <span
        data-ndb-request-current
        aria-hidden="true"
        class="ndb:flex ndb:size-4 ndb:shrink-0 ndb:self-center ndb:items-center ndb:justify-center ndb:text-indigo-600 ndb:transition-opacity ndb:dark:text-indigo-300"
        :class="request.id === summary.id ? 'ndb:opacity-100' : 'ndb:opacity-0'"
        ><x-newdebugbar::icon name="check" class="ndb:size-3.5"
    /></span>
</button>
