<div
    x-data="{}"
    x-id="['newdebugbar-livewire-edit-trigger', 'newdebugbar-livewire-edit-popover', 'newdebugbar-livewire-edit-title']"
    class="ndb:relative ndb:w-full ndb:sm:w-auto ndb:sm:justify-self-end"
    @keydown.escape.stop="
        if (livewireDrafts[livewireDraftKey(row)]) {
            cancelLivewireDraft(row, true);
        }
    "
>
    <button
        x-show.important="row.editable"
        type="button"
        :id="$id('newdebugbar-livewire-edit-trigger')"
        :data-ndb-livewire-edit-key="livewireDraftKey(row)"
        :aria-controls="$id('newdebugbar-livewire-edit-popover')"
        :aria-expanded="Boolean(
            livewireDrafts[livewireDraftKey(row)] && livewireDrafts[livewireDraftKey(row)]?.status !== 'closing',
        )"
        @click.stop="toggleLivewirePropertyEditor(row)"
        :class="livewireDrafts[livewireDraftKey(row)] && livewireDrafts[livewireDraftKey(row)]?.status !== 'closing'
            ? 'ndb:bg-indigo-50 ndb:dark:bg-indigo-950/60'
            : 'ndb:hover:bg-zinc-100 ndb:dark:hover:bg-zinc-800'"
        class="ndb:inline-flex ndb:h-7 ndb:items-center ndb:rounded-md ndb:px-2 ndb:text-xs ndb:font-bold ndb:text-indigo-600 ndb:transition ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:dark:text-indigo-300"
    >
        Edit
    </button>

    <template x-if="livewireDrafts[livewireDraftKey(row)] && livewireDrafts[livewireDraftKey(row)]?.status !== 'closing'">
        <template x-teleport="#newdebugbar">
            {{-- Keep the anchor gap inside the measured popover height. --}}
            <x-newdebugbar::popover-surface
                :anchored="true"
                x-anchor.bottom-end.fixed="document.getElementById($id('newdebugbar-livewire-edit-trigger'))"
                x-init="
                    $nextTick(() => {
                        $el.querySelector('[data-ndb-livewire-edit-control]')?.focus();
                    })
                "
                @keydown.escape.stop.prevent="cancelLivewireDraft(row, true)"
                @click.outside="
                    if (livewireDrafts[livewireDraftKey(row)]?.status !== 'updating') {
                        cancelLivewireDraft(row);
                    }
                "
                data-ndb-livewire-property-popover
                ::id="$id('newdebugbar-livewire-edit-popover')"
                ::aria-labelledby="$id('newdebugbar-livewire-edit-title')"
                ::style="{
          visibility:
            $anchor.x !== 0 || $anchor.y !== 0 ? 'visible' : 'hidden',
        }"
                role="dialog"
                direction="below"
                align="left"
                width-class="ndb:w-[min(21rem,calc(100vw-3rem))]"
                surface-class="ndb:p-0"
                arrow-class="ndb:hidden"
                class="ndb:pointer-events-auto ndb:py-3"
            >
                <div class="ndb:border-b ndb:border-zinc-200/80 ndb:px-4 ndb:py-3 ndb:dark:border-zinc-700/80">
                    <p
                        :id="$id('newdebugbar-livewire-edit-title')"
                        class="ndb:truncate ndb:text-xs ndb:font-bold"
                        :title="row.path"
                    >
                        Edit <span x-text="row.path"></span>
                    </p>
                </div>

                <div class="ndb:space-y-3 ndb:px-4 ndb:py-3">
                    <template x-if="row.value === null">
                        <select
                            data-ndb-livewire-edit-control
                            x-model="livewireDrafts[livewireDraftKey(row)].type"
                            :aria-label="`Value type for ${row.path}`"
                            class="ndb:h-9 ndb:w-full ndb:rounded-lg ndb:border ndb:border-zinc-200 ndb:bg-white ndb:px-3 ndb:text-xs ndb:outline-none ndb:focus:border-indigo-400 ndb:focus:ring-2 ndb:focus:ring-indigo-500/15 ndb:dark:border-zinc-700 ndb:dark:bg-zinc-900"
                        >
                            <option>String</option>
                            <option>Integer</option>
                            <option>Float</option>
                            <option>Boolean</option>
                        </select>
                    </template>

                    <template x-if="livewireDrafts[livewireDraftKey(row)]?.type === 'Boolean'">
                        <button
                            type="button"
                            role="switch"
                            data-ndb-livewire-edit-control
                            :aria-checked="livewireDrafts[livewireDraftKey(row)].value"
                            @click="toggleLivewireBoolean(row)"
                            class="ndb:group ndb:flex ndb:w-full ndb:items-center ndb:justify-center ndb:gap-3 ndb:focus-visible:outline-none"
                        >
                            <span
                                data-ndb-livewire-boolean-label="false"
                                class="ndb:text-right ndb:text-xs ndb:leading-none ndb:font-semibold ndb:transition-colors"
                                :class="livewireDrafts[livewireDraftKey(row)].value
                                    ? 'ndb:text-zinc-400'
                                    : 'ndb:font-bold ndb:text-zinc-900 ndb:dark:text-zinc-100'"
                            >False</span>
                            <span
                                aria-hidden="true"
                                class="ndb:relative ndb:h-6 ndb:w-11 ndb:shrink-0 ndb:rounded-full ndb:bg-zinc-300 ndb:shadow-inner ndb:transition-colors ndb:group-aria-checked:bg-indigo-600 ndb:group-focus-visible:ring-2 ndb:group-focus-visible:ring-indigo-500 ndb:group-focus-visible:ring-offset-2 ndb:dark:bg-zinc-700 ndb:dark:group-aria-checked:bg-indigo-500 ndb:dark:group-focus-visible:ring-indigo-400 ndb:dark:group-focus-visible:ring-offset-zinc-950"
                            >
                                <span class="ndb:absolute ndb:top-0.5 ndb:left-0.5 ndb:size-5 ndb:rounded-full ndb:bg-white ndb:shadow-sm ndb:transition-transform ndb:group-aria-checked:translate-x-5"></span>
                            </span>
                            <span
                                data-ndb-livewire-boolean-label="true"
                                class="ndb:text-left ndb:text-xs ndb:leading-none ndb:font-semibold ndb:transition-colors"
                                :class="livewireDrafts[livewireDraftKey(row)].value
                                    ? 'ndb:font-bold ndb:text-zinc-900 ndb:dark:text-zinc-100'
                                    : 'ndb:text-zinc-400'"
                            >True</span>
                        </button>
                    </template>

                    <template x-if="['Integer', 'Float'].includes(livewireDrafts[livewireDraftKey(row)]?.type)">
                        <input
                            data-ndb-livewire-edit-control
                            x-model="livewireDrafts[livewireDraftKey(row)].value"
                            type="number"
                            :step="livewireDrafts[livewireDraftKey(row)]?.type === 'Float' ? 'any' : '1'"
                            :aria-label="`New value for ${row.path}`"
                            class="ndb:h-9 ndb:w-full ndb:rounded-lg ndb:border ndb:border-zinc-200 ndb:bg-white ndb:px-3 ndb:text-xs ndb:outline-none ndb:focus:border-indigo-400 ndb:focus:ring-2 ndb:focus:ring-indigo-500/15 ndb:dark:border-zinc-700 ndb:dark:bg-zinc-900"
                        />
                    </template>

                    <template x-if="livewireDrafts[livewireDraftKey(row)]?.type === 'String'">
                        <div class="ndb:space-y-2">
                            <textarea
                                data-ndb-livewire-edit-control
                                x-model="livewireDrafts[livewireDraftKey(row)].value"
                                rows="3"
                                :aria-label="`New value for ${row.path}`"
                                aria-keyshortcuts="Meta+Enter Control+Enter"
                                @keydown.meta.enter.stop.prevent="
                                    applyLivewireDraft(
                                        row,
                                        document.getElementById($id('newdebugbar-livewire-edit-trigger')),
                                    )
                                "
                                @keydown.ctrl.enter.stop.prevent="
                                    applyLivewireDraft(
                                        row,
                                        document.getElementById($id('newdebugbar-livewire-edit-trigger')),
                                    )
                                "
                                class="ndb:field-sizing-content ndb:max-h-[min(20rem,50vh)] ndb:min-h-20 ndb:w-full ndb:resize-y ndb:overflow-y-auto ndb:rounded-lg ndb:border ndb:border-zinc-200 ndb:bg-white ndb:px-3 ndb:py-2.5 ndb:text-xs ndb:leading-5 ndb:outline-none ndb:focus:border-indigo-400 ndb:focus:ring-2 ndb:focus:ring-indigo-500/15 ndb:dark:border-zinc-700 ndb:dark:bg-zinc-900"
                            ></textarea>
                            <kbd aria-hidden="true" class="ndb:block ndb:text-xs ndb:font-semibold ndb:text-zinc-400"
                                >⌘/Ctrl + Enter</kbd>
                        </div>
                    </template>

                    <p
                        x-show.important="livewireDrafts[livewireDraftKey(row)]?.error"
                        role="alert"
                        class="ndb:text-xs ndb:font-semibold ndb:text-red-700 ndb:dark:text-red-300"
                        x-text="livewireDrafts[livewireDraftKey(row)]?.error"
                    ></p>
                </div>

                <div class="ndb:flex ndb:items-center ndb:justify-end ndb:gap-3 ndb:border-t ndb:border-zinc-200/80 ndb:bg-zinc-50/70 ndb:px-4 ndb:py-3 ndb:dark:border-zinc-700/80 ndb:dark:bg-zinc-950/30">
                    <div class="ndb:flex ndb:shrink-0 ndb:gap-2">
                        <button
                            data-ndb-livewire-edit-cancel
                            type="button"
                            @click="cancelLivewireDraft(row, true)"
                            class="ndb:h-9 ndb:rounded-lg ndb:px-3 ndb:text-xs ndb:font-bold ndb:text-zinc-500 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:dark:text-zinc-400"
                        >
                            Cancel
                        </button>
                        <button
                            data-ndb-livewire-edit-apply
                            type="button"
                            @click="
                                applyLivewireDraft(
                                    row,
                                    document.getElementById($id('newdebugbar-livewire-edit-trigger')),
                                )
                            "
                            :disabled="livewireDrafts[livewireDraftKey(row)]?.status === 'updating'"
                            class="ndb:h-9 ndb:rounded-lg ndb:bg-indigo-600 ndb:px-3 ndb:text-xs ndb:font-bold ndb:text-white ndb:focus-visible:outline-2 ndb:focus-visible:outline-offset-2 ndb:focus-visible:outline-indigo-500 ndb:disabled:opacity-50 ndb:dark:bg-indigo-500"
                        >
                            <span
                                x-text="
                                    livewireDrafts[livewireDraftKey(row)]?.status === 'updating' ? 'Applying…' : 'Apply'
                                "
                            ></span>
                        </button>
                    </div>
                </div>
            </x-newdebugbar::popover-surface>
        </template>
    </template>
</div>
