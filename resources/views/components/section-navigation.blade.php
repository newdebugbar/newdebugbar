@props(['mobile' => false])

<div
    data-ndb-section-list
    {{ $attributes->class(['ndb:flex ndb:min-h-0 ndb:flex-col ndb:gap-3', 'ndb:flex-1' => ! $mobile, 'ndb:shrink-0' => $mobile]) }}
>
    @foreach (['favorites' => true, 'sections' => false] as $group => $favorite)
        <div
            role="group"
            aria-label="{{ $favorite ? 'Favorites' : 'Sections' }}"
            x-show.important="navigationSections(@js($favorite)).length > 0"
        >
            <p
                data-ndb-{{ $group }}-heading
                @if (! $mobile) x-show.important="favorites.length > 0" @endif
                class="ndb:px-2.5 ndb:pb-1.5 ndb:pt-1 ndb:text-xs ndb:font-bold ndb:uppercase ndb:tracking-[0.14em] ndb:text-zinc-400"
            >
                {{ $favorite ? 'Favorites' : 'Sections' }}
            </p>
            <div
                x-sort="sortSection($item, $position)"
                x-sort:config="sectionSortConfig"
                data-ndb-sort-group="{{ $group }}"
                class="ndb:flex ndb:flex-col ndb:gap-0.5"
            >
                <template x-for="section in navigationSections(@js($favorite))" :key="section.key">
                    <div
                        x-sort:item="section.key"
                        :data-ndb-section="section.key"
                        data-ndb-section-visible="true"
                        :data-ndb-favorite="isFavorite(section.key) ? 'true' : 'false'"
                        class="ndb:group ndb:relative ndb:flex ndb:w-full ndb:items-center ndb:rounded-lg ndb:pr-1 ndb:transition ndb:hover:bg-zinc-200/60 ndb:dark:hover:bg-zinc-800/60"
                        :class="selected === section.key ? 'ndb-section-active' : ''"
                    >
                        <button
                            type="button"
                            :data-ndb-select-section="section.key"
                            :aria-current="selected === section.key ? 'page' : null"
                            :aria-label="section.label"
                            aria-description="Drag to reorder. On touch screens, hold before dragging. Shift and arrow keys also reorder."
                            @click="{{ $mobile ? 'inspector.openSectionFromToolbar(section.key)' : 'inspector.selectSection(section.key)' }}"
                            @if ($mobile) role="menuitem" @endif
                            @keydown.shift.arrow-up.prevent.stop="moveSection(section.key, -1)"
                            @keydown.shift.arrow-down.prevent.stop="moveSection(section.key, 1)"
                            class="{{ $mobile ? 'ndb:h-11 ndb:text-sm' : 'ndb:h-9 ndb:text-xs' }} ndb:flex ndb:min-w-0 ndb:flex-1 ndb:items-center ndb:gap-2 ndb:rounded-lg ndb:px-2.5 ndb:text-left ndb:font-semibold ndb:cursor-grab ndb:active:cursor-grabbing ndb:transition ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500"
                            :class="selected === section.key
                                ? ''
                                : 'ndb:text-zinc-600 ndb:hover:text-zinc-950 ndb:dark:text-zinc-400 ndb:dark:hover:text-white'"
                        >
                            <span class="ndb-section-label ndb:truncate" x-text="section.label"></span>
                            <span class="ndb:ml-auto ndb:flex ndb:h-7 ndb:shrink-0 ndb:items-center ndb:gap-1.5">
                                <span
                                    x-show.important="section.count !== null"
                                    class="ndb-section-count ndb:inline-flex ndb:items-center ndb:text-xs ndb:leading-none ndb:tabular-nums"
                                    :class="selected === section.key ? '' : 'ndb:text-zinc-400'"
                                    x-text="section.count"
                                ></span>
                            </span>
                        </button>
                        <button
                            type="button"
                            x-sort:ignore
                            :data-ndb-toggle-favorite="section.key"
                            @if ($mobile)
                                role="menuitemcheckbox"
                                :aria-checked="isFavorite(section.key)"
                            @else
                                :aria-pressed="isFavorite(section.key)"
                            @endif
                            :aria-label="(isFavorite(section.key) ? 'Remove ' : 'Add ') +
                            section.label +
                            (isFavorite(section.key) ? ' from favorites' : ' to favorites')"
                            :title="isFavorite(section.key) ? 'Remove from favorites' : 'Add to favorites'"
                            @click.stop="inspector.toggleFavorite(section.key)"
                            class="{{ $mobile ? 'ndb:size-11' : 'ndb:size-7 ndb:sm:opacity-0 ndb:sm:group-focus-within:opacity-100 ndb:sm:group-hover:opacity-100' }} ndb-star-button ndb:inline-flex ndb:items-center ndb:justify-center ndb:rounded-lg ndb:text-zinc-400 ndb:transition ndb:hover:scale-105 ndb:hover:text-blue-600 ndb:focus-visible:opacity-100 ndb:focus-visible:outline-2 ndb:focus-visible:outline-offset-1 ndb:focus-visible:outline-blue-500 ndb:dark:text-zinc-500 ndb:dark:hover:text-blue-300"
                            :class="isFavorite(section.key) || selected === section.key ? 'ndb:sm:opacity-100' : ''"
                        >
                            <span
                                x-show.important="! isFavorite(section.key)"
                                class="ndb-section-star-outline ndb:flex ndb:items-center ndb:justify-center ndb:leading-none"
                                ><x-newdebugbar::icon name="star" class="ndb:size-3.5"
                            /></span>
                            <span
                                x-show.important="isFavorite(section.key)"
                                class="ndb:flex ndb:items-center ndb:justify-center ndb:leading-none"
                                ><x-newdebugbar::icon name="star-filled" class="ndb-favorite-star ndb:size-3.5"
                            /></span>
                        </button>
                    </div>
                </template>
            </div>
        </div>
    @endforeach
</div>
