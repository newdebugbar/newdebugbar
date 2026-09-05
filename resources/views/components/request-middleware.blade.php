{{-- Shows retained middleware in the shared anchored popover. --}}
@props(['middleware'])

<div
    data-ndb-request-middleware
    x-data="{ middlewareOpen: false }"
    x-id="['newdebugbar-request-middleware-trigger', 'newdebugbar-request-middleware-popover']"
    x-effect="
        if (middlewareOpen && paletteOpen) {
            paletteReturnFocus = $refs.middlewareTrigger;
            middlewareOpen = false;
        } else if (selected !== 'request' || ! inspectorOpen || ! barVisible) {
            middlewareOpen = false;
        }
    "
    @keydown.escape="
        if (middlewareOpen) {
            $event.preventDefault();
            $event.stopPropagation();
            middlewareOpen = false;
            $refs.middlewareTrigger.focus();
        }
    "
>
    <button
        type="button"
        data-ndb-request-middleware-trigger
        x-ref="middlewareTrigger"
        :id="$id('newdebugbar-request-middleware-trigger')"
        :aria-controls="$id('newdebugbar-request-middleware-popover')"
        :aria-expanded="middlewareOpen"
        @click.stop="middlewareOpen = ! middlewareOpen"
        class="ndb:flex ndb:w-fit ndb:items-center ndb:gap-2 ndb:rounded-sm ndb:text-left ndb:focus-visible:outline-2 ndb:focus-visible:outline-offset-4 ndb:focus-visible:outline-indigo-500"
    >
        <span>{{ count($middleware) }} middleware</span>
        <x-newdebugbar::icon
            name="chevron-down"
            ::class="middlewareOpen ? 'ndb:rotate-180' : ''"
            class="ndb:size-3.5 ndb:text-zinc-400 ndb:transition"
        />
    </button>

    <template x-if="middlewareOpen">
        <template x-teleport="#newdebugbar">
            <x-newdebugbar::popover-surface
                :anchored="true"
                x-anchor.bottom-start.offset.12.fixed="
                    document.getElementById($id('newdebugbar-request-middleware-trigger'))
                "
                x-init="$nextTick(() => $el.querySelector('ol').focus({ preventScroll: true }))"
                @click.outside="middlewareOpen = false"
                @keydown.escape.stop.prevent="
                    middlewareOpen = false;
                    $refs.middlewareTrigger.focus();
                "
                @keydown.tab.stop.prevent="
                    middlewareOpen = false;
                    $refs.middlewareTrigger.focus();
                "
                data-ndb-request-middleware-popover
                ::id="$id('newdebugbar-request-middleware-popover')"
                ::style="{ visibility: $anchor.x !== 0 || $anchor.y !== 0 ? 'visible' : 'hidden' }"
                role="region"
                aria-label="Middleware"
                tabindex="-1"
                width-class="ndb:w-[min(32rem,calc(100vw-2rem))]"
                surface-class="ndb:p-0"
                arrow-class="ndb:hidden"
                class="ndb:pointer-events-auto"
            >
                <ol
                    tabindex="0"
                    aria-label="Middleware list"
                    class="ndb:max-h-[min(24rem,60dvh)] ndb:space-y-3 ndb:overflow-y-auto ndb:overscroll-contain ndb:p-4 ndb:text-xs ndb:leading-5 ndb:focus-visible:outline-2 ndb:focus-visible:-outline-offset-2 ndb:focus-visible:outline-indigo-500"
                >
                    @foreach ($middleware as $name)
                        <li>
                            <code data-ndb-language="php" class="ndb:[overflow-wrap:anywhere]">{{ $name }}</code>
                        </li>
                    @endforeach
                </ol>
            </x-newdebugbar::popover-surface>
        </template>
    </template>
</div>
