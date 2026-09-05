<template x-if="selectedQueueActivity.attempts.length > 0">
    <section
        data-ndb-queue-attempts
        aria-labelledby="newdebugbar-queue-attempts-heading"
        class="ndb:border-t ndb:border-zinc-200/90 ndb:p-3 ndb:sm:p-4 ndb:dark:border-zinc-800"
    >
        <h4 id="newdebugbar-queue-attempts-heading" class="ndb:mb-3 ndb:text-xs ndb:font-bold">Attempts</h4>
        <div class="ndb:divide-y ndb:divide-zinc-200/90 ndb:border-y ndb:border-zinc-200/90 ndb:dark:divide-zinc-800 ndb:dark:border-zinc-800">
            <template x-for="attempt in selectedQueueActivity.attempts" :key="attempt.sequence">
                <article
                    data-ndb-queue-attempt
                    class="ndb:grid ndb:min-w-0 ndb:gap-2 ndb:py-2.5 ndb:sm:grid-cols-[5rem_6rem_minmax(0,1fr)_auto] ndb:sm:items-center ndb:sm:py-3"
                >
                    <span
                        class="ndb:text-xs ndb:font-bold ndb:tabular-nums"
                        x-text="attempt.attempt === null ? `Attempt ${attempt.sequence}` : `Attempt ${attempt.attempt}`"
                    ></span>
                    <span class="ndb:text-xs ndb:font-semibold" x-text="attempt.status_label"></span>
                    <span
                        class="ndb:min-w-0 ndb:break-all ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400"
                        x-text="attempt.exception_class ?? attempt.recorded_at ?? 'No exception recorded'"
                    ></span>
                    <x-newdebugbar::inspector-action
                        icon="external-link"
                        x-show.important="attempt.profile_id"
                        @click="openRelatedProfile(attempt.profile_id, 'queue')"
                        class="ndb:h-9 ndb:min-h-0 ndb:bg-transparent"
                    >Open worker</x-newdebugbar::inspector-action>
                </article>
            </template>
        </div>
    </section>
</template>
