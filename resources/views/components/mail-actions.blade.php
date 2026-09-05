<details
    data-ndb-mail-actions
    x-show="selectedMailMessage.has_html || selectedMailMessage.has_text || selectedMailMessage.related_profile_id"
    x-data="{ mailActionsOpen: false }"
    @toggle="mailActionsOpen = $el.open"
    @click.outside="$el.open = false"
    @keydown.escape.stop.prevent="
        $el.open = false;
        $refs.mailActionsTrigger.focus();
    "
    class="ndb:relative ndb:m-0 ndb:shrink-0 ndb:border-0 ndb:bg-transparent ndb:p-0"
>
    <summary
        x-ref="mailActionsTrigger"
        data-ndb-mail-actions-trigger
        :aria-expanded="mailActionsOpen"
        aria-controls="newdebugbar-mail-actions-menu"
        aria-haspopup="menu"
        aria-label="Mail actions"
        title="Mail actions"
        :class="mailActionsOpen
            ? 'ndb:border-indigo-300 ndb:bg-indigo-50 ndb:text-indigo-700 ndb:dark:border-indigo-700 ndb:dark:bg-indigo-950/50 ndb:dark:text-indigo-300'
            : 'ndb:border-zinc-200 ndb:bg-white/75 ndb:text-zinc-600 ndb:hover:bg-zinc-100 ndb:hover:text-zinc-950 ndb:dark:border-zinc-700 ndb:dark:bg-zinc-900/75 ndb:dark:text-zinc-300 ndb:dark:hover:bg-zinc-800 ndb:dark:hover:text-white'"
        class="ndb:flex ndb:size-8 ndb:cursor-pointer ndb:list-none ndb:items-center ndb:justify-center ndb:rounded-lg ndb:border ndb:transition-colors ndb:focus-visible:outline-2 ndb:focus-visible:outline-offset-2 ndb:focus-visible:outline-indigo-500"
    >
        <x-newdebugbar::icon name="ellipsis" size="4" />
    </summary>

    <x-newdebugbar::popover-surface
        id="newdebugbar-mail-actions-menu"
        direction="below"
        width-class="ndb:w-64"
        arrow-class="ndb:right-[8px]"
        data-ndb-mail-actions-menu
        role="menu"
        aria-label="Mail actions"
    >
        <button
            data-ndb-mail-open-related
            x-show.important="selectedMailMessage.related_profile_id"
            @click="
                $el.closest('details').open = false;
                openRelatedProfile(selectedMailMessage.related_profile_id, selectedMailMessage.related_section);
            "
            type="button"
            role="menuitem"
            class="ndb:flex ndb:h-auto ndb:min-h-11 ndb:w-full ndb:items-center ndb:gap-3 ndb:rounded-lg ndb:bg-transparent ndb:px-3 ndb:py-2 ndb:text-left ndb:text-zinc-700 ndb:transition-colors ndb:hover:bg-zinc-100 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:dark:text-zinc-200 ndb:dark:hover:bg-white/10"
        >
            <span class="ndb:flex ndb:size-8 ndb:shrink-0 ndb:items-center ndb:justify-center ndb:rounded-lg ndb:bg-indigo-50 ndb:text-indigo-600 ndb:dark:bg-indigo-950/60 ndb:dark:text-indigo-300">
                <x-newdebugbar::icon name="external-link" size="4" />
            </span>
            <span class="ndb:min-w-0">
                <span class="ndb:block ndb:text-xs ndb:font-bold" x-text="selectedMailMessage.related_label"></span>
                <span class="ndb:mt-0.5 ndb:block ndb:text-xs ndb:text-zinc-400">Follow the linked queue activity</span>
            </span>
        </button>
        <a
            data-ndb-mail-open-preview
            :href="mailPreviewUrl()"
            x-show.important="selectedMailMessage.has_html || selectedMailMessage.has_text"
            @click="$el.closest('details').open = false"
            target="_blank"
            rel="noopener noreferrer"
            role="menuitem"
            class="ndb:flex ndb:h-auto ndb:min-h-11 ndb:w-full ndb:items-center ndb:gap-3 ndb:rounded-lg ndb:bg-transparent ndb:px-3 ndb:py-2 ndb:text-left ndb:text-zinc-700 ndb:no-underline ndb:transition-colors ndb:hover:bg-zinc-100 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:dark:text-zinc-200 ndb:dark:hover:bg-white/10"
        >
            <span class="ndb:flex ndb:size-8 ndb:shrink-0 ndb:items-center ndb:justify-center ndb:rounded-lg ndb:bg-indigo-50 ndb:text-indigo-600 ndb:dark:bg-indigo-950/60 ndb:dark:text-indigo-300">
                <x-newdebugbar::icon name="external-link" size="4" />
            </span>
            <span class="ndb:min-w-0">
                <span class="ndb:block ndb:text-xs ndb:font-bold">Open preview</span>
                <span class="ndb:mt-0.5 ndb:block ndb:text-xs ndb:text-zinc-400">Open the rendered message in a new tab</span>
            </span>
        </a>
        <a
            data-ndb-mail-download
            :href="selectedMailMessage.eml_url"
            x-show.important="selectedMailMessage.eml_url"
            @click="$el.closest('details').open = false"
            role="menuitem"
            class="ndb:flex ndb:h-auto ndb:min-h-11 ndb:w-full ndb:items-center ndb:gap-3 ndb:rounded-lg ndb:bg-transparent ndb:px-3 ndb:py-2 ndb:text-left ndb:text-zinc-700 ndb:no-underline ndb:transition-colors ndb:hover:bg-zinc-100 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:dark:text-zinc-200 ndb:dark:hover:bg-white/10"
        >
            <span class="ndb:flex ndb:size-8 ndb:shrink-0 ndb:items-center ndb:justify-center ndb:rounded-lg ndb:bg-emerald-50 ndb:text-emerald-600 ndb:dark:bg-emerald-950/60 ndb:dark:text-emerald-300">
                <x-newdebugbar::icon name="download" size="4" />
            </span>
            <span class="ndb:min-w-0">
                <span class="ndb:block ndb:text-xs ndb:font-bold">Download .EML</span>
                <span class="ndb:mt-0.5 ndb:block ndb:text-xs ndb:text-zinc-400">Save the raw captured message</span>
            </span>
        </a>
    </x-newdebugbar::popover-surface>
</details>
