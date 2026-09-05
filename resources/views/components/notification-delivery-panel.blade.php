<div data-ndb-notification-detail-panel="delivery" class="ndb:py-3 ndb:sm:p-4">
    <div class="ndb:divide-y ndb:divide-zinc-200/90 ndb:border-y ndb:border-zinc-200/90 ndb:dark:divide-zinc-800 ndb:dark:border-zinc-800">
        <template x-for="delivery in selectedNotification.deliveries" :key="delivery.channel">
            <article
                data-ndb-notification-delivery
                :class="{ 'ndb:cursor-pointer': delivery.mail_available }"
                class="ndb:relative"
            >
                <template x-if="delivery.mail_available">
                    <button
                        type="button"
                        data-ndb-notification-view-mail
                        :aria-label="'Open email for ' + delivery.channel_label + ' delivery'"
                        @click="openNotificationMail(delivery.mail_message_id)"
                        class="ndb:absolute ndb:inset-0 ndb:z-10 ndb:cursor-pointer ndb:bg-transparent ndb:transition-colors ndb:hover:bg-zinc-950/[0.025] ndb:focus-visible:outline-2 ndb:focus-visible:outline-inset ndb:focus-visible:outline-indigo-500 ndb:dark:hover:bg-white/[0.035]"
                    ></button>
                </template>
                <div class="ndb:flex ndb:items-start ndb:justify-between ndb:gap-3 ndb:px-3 ndb:py-2.5">
                    <div class="ndb:flex ndb:min-w-0 ndb:items-start ndb:gap-2.5">
                        <span
                            :class="{
                                'ndb:bg-red-100 ndb:text-red-600 ndb:dark:bg-red-950 ndb:dark:text-red-300':
                                    delivery.status === 'failed',
                                'ndb:bg-emerald-100 ndb:text-emerald-600 ndb:dark:bg-emerald-950 ndb:dark:text-emerald-300':
                                    delivery.status === 'sent',
                                'ndb:bg-indigo-100 ndb:text-indigo-600 ndb:dark:bg-indigo-950 ndb:dark:text-indigo-300':
                                    delivery.status === 'processing',
                                'ndb:bg-amber-100 ndb:text-amber-600 ndb:dark:bg-amber-950 ndb:dark:text-amber-300': [
                                    'queued',
                                    'delayed',
                                    'waiting',
                                ].includes(delivery.status),
                            }"
                            class="ndb:flex ndb:size-7 ndb:shrink-0 ndb:items-center ndb:justify-center ndb:rounded-lg"
                        >
                            <template x-if="delivery.status === 'failed'">
                                <x-newdebugbar::icon name="warning" size="3.5" />
                            </template>
                            <template x-if="delivery.status === 'sent'">
                                <x-newdebugbar::icon name="check" size="3.5" />
                            </template>
                            <template x-if="! ['failed', 'sent'].includes(delivery.status)">
                                <x-newdebugbar::icon name="clock" size="3.5" />
                            </template>
                        </span>
                        <div class="ndb:min-w-0">
                            <h4 class="ndb:truncate ndb:text-xs ndb:font-bold" x-text="delivery.channel_label"></h4>
                            <p
                                :class="{
                                    'ndb:text-red-600 ndb:dark:text-red-300': delivery.status === 'failed',
                                    'ndb:text-zinc-500 ndb:dark:text-zinc-400': delivery.status === 'sent',
                                    'ndb:text-indigo-600 ndb:dark:text-indigo-300': delivery.status === 'processing',
                                    'ndb:text-amber-700 ndb:dark:text-amber-300': [
                                        'queued',
                                        'delayed',
                                        'waiting',
                                    ].includes(delivery.status),
                                }"
                                class="ndb:mt-0.5 ndb:text-xs ndb:font-medium"
                                x-text="delivery.status_label"
                            ></p>
                            <template x-if="delivery.destination_labels.length <= 1">
                                <span
                                    data-ndb-notification-destination
                                    :class="delivery.destination_resolved
                                        ? 'ndb:text-zinc-500 ndb:dark:text-zinc-400'
                                        : 'ndb:text-amber-700 ndb:dark:text-amber-300'"
                                    class="ndb:mt-1 ndb:block ndb:break-all ndb:bg-transparent ndb:p-0 ndb:text-xs"
                                    x-text="delivery.destination_label"
                                ></span>
                            </template>
                            <template x-if="delivery.destination_labels.length > 1">
                                <div class="ndb:mt-1.5 ndb:flex ndb:flex-wrap ndb:gap-1">
                                    <template x-for="destination in delivery.destination_labels" :key="destination">
                                        <span
                                            data-ndb-notification-destination
                                            class="ndb:max-w-full ndb:break-all ndb:rounded-md ndb:bg-zinc-100 ndb:px-1.5 ndb:py-0.5 ndb:text-xs ndb:text-zinc-600 ndb:dark:bg-zinc-800 ndb:dark:text-zinc-300"
                                            x-text="destination"
                                        ></span>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                    <span
                        class="ndb:shrink-0 ndb:text-xs ndb:font-semibold ndb:tabular-nums ndb:text-zinc-400"
                        x-text="
                            ['sent', 'failed'].includes(delivery.status) || delivery.duration_ms > 0
                                ? delivery.duration_label
                                : delivery.delay_seconds > 0
                                  ? delivery.delay_seconds + ' s delay'
                                  : delivery.status_label
                        "
                    ></span>
                </div>

                <div
                    x-show="delivery.failure_message"
                    class="ndb:border-t ndb:border-red-200/80 ndb:px-3 ndb:py-2.5 ndb:dark:border-red-950"
                >
                    <p
                        class="ndb:text-xs ndb:font-semibold ndb:leading-5 ndb:text-red-700 ndb:dark:text-red-300"
                        x-text="delivery.failure_message"
                    ></p>
                    <code
                        x-show.important="delivery.exception_class"
                        class="ndb:mt-1 ndb:block ndb:break-all ndb:text-xs ndb:text-red-500 ndb:dark:text-red-400"
                        x-text="delivery.exception_class"
                    ></code>
                    <span
                        x-show.important="delivery.exception_location"
                        class="ndb:mt-1 ndb:block ndb:break-all ndb:text-xs ndb:text-red-500 ndb:dark:text-red-400"
                        x-text="
                            delivery.exception_location
                                ? delivery.exception_location.file + ':' + delivery.exception_location.line
                                : ''
                        "
                    ></span>
                </div>

                <div
                    x-show="delivery.evidence_summary"
                    class="ndb:border-t ndb:border-zinc-200/80 ndb:px-3 ndb:py-2 ndb:text-xs ndb:text-zinc-500 ndb:dark:border-zinc-800 ndb:dark:text-zinc-400"
                    x-text="delivery.evidence_summary"
                ></div>
            </article>
        </template>
    </div>
</div>
