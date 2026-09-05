<x-newdebugbar::inspector-detail-header data-ndb-mail-header>
    <x-slot:title>
        <h3
            data-ndb-mail-detail-subject
            class="ndb:break-words ndb:text-base ndb:font-bold ndb:leading-6"
            x-text="selectedMailMessage.subject"
        ></h3>
    </x-slot:title>

    <x-slot:aside>
        <div class="ndb:flex ndb:shrink-0 ndb:items-center ndb:justify-self-end ndb:gap-2">
            <span
                x-show.important="selectedMailMessage.lifecycle === 'after_response'"
                class="ndb:rounded-md ndb:bg-indigo-100 ndb:px-2 ndb:py-1 ndb:text-xs ndb:font-semibold ndb:text-indigo-700 ndb:dark:bg-indigo-950 ndb:dark:text-indigo-300"
            >After response</span>
            <x-newdebugbar::mail-actions />
        </div>
    </x-slot:aside>

    <x-slot:identity data-ndb-mail-recipient>
        <dl class="ndb:space-y-2">
            <div class="ndb:grid ndb:grid-cols-[4.75rem_minmax(0,1fr)] ndb:items-baseline ndb:gap-2">
                <dt class="ndb:text-xs ndb:font-semibold ndb:text-zinc-400">Recipients</dt>
                <dd
                    :title="formatMailAddresses(selectedMailMessage.to)"
                    class="ndb:flex ndb:min-w-0 ndb:items-baseline ndb:gap-2"
                >
                    <span
                        class="ndb:truncate ndb:text-xs ndb:font-semibold ndb:text-zinc-700 ndb:dark:text-zinc-200"
                        x-text="selectedMailMessage.to[0] || 'No recipient captured'"
                    ></span>
                    <span
                        x-show.important="selectedMailMessage.to.length > 1"
                        class="ndb:shrink-0 ndb:text-xs ndb:font-bold ndb:text-indigo-600 ndb:dark:text-indigo-300"
                        x-text="'+' + (selectedMailMessage.to.length - 1) + ' more'"
                    ></span>
                </dd>
            </div>

            <div class="ndb:grid ndb:grid-cols-[4.75rem_minmax(0,1fr)] ndb:items-baseline ndb:gap-2">
                <dt class="ndb:text-xs ndb:font-semibold ndb:text-zinc-400">Sender</dt>
                <dd :title="formatMailAddresses(selectedMailMessage.from)" class="ndb:min-w-0">
                    <span
                        class="ndb:block ndb:truncate ndb:text-xs ndb:font-medium ndb:text-zinc-600 ndb:dark:text-zinc-300"
                        x-text="selectedMailMessage.from[0] || 'No sender captured'"
                    ></span>
                </dd>
            </div>
        </dl>
    </x-slot:identity>

    <x-slot:metadata data-ndb-mail-metadata class="ndb:w-full">
        <x-newdebugbar::inspector-facts
            :bordered="false"
            columns="4"
            data-ndb-mail-facts
            class="ndb:w-full ndb:gap-x-3 ndb:p-0 ndb:sm:gap-x-4"
        >
            <x-newdebugbar::inspector-fact label="Attachments" data-ndb-mail-fact>
                <x-slot:value>
                    <button
                        type="button"
                        x-show.important="selectedMailMessage.attachment_count > 0"
                        @click="setMailDetailTab('message')"
                        class="ndb:max-w-full ndb:truncate ndb:text-left ndb:text-xs ndb:font-bold ndb:text-indigo-600 ndb:underline-offset-2 ndb:hover:underline ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:dark:text-indigo-300"
                        x-text="selectedMailMessage.attachment_summary_label"
                    ></button>
                    <span
                        x-show.important="selectedMailMessage.attachment_count === 0"
                        class="ndb:text-xs ndb:font-semibold ndb:text-zinc-600 ndb:dark:text-zinc-300"
                    >None</span>
                </x-slot:value>
            </x-newdebugbar::inspector-fact>
            <x-newdebugbar::inspector-fact label="Duration" data-ndb-mail-fact>
                <x-slot:value
                    class="ndb:truncate ndb:font-semibold ndb:tabular-nums"
                    x-text="
                        selectedMailMessage.status === 'sent'
                            ? selectedMailMessage.duration_label
                            : selectedMailMessage.delay_seconds > 0
                              ? selectedMailMessage.delay_seconds + ' s delay'
                              : selectedMailMessage.status_label
                    "
                ></x-slot:value>
            </x-newdebugbar::inspector-fact>
            <x-newdebugbar::inspector-fact label="Driver" data-ndb-mail-fact>
                <x-slot:value
                    ::title="selectedMailMessage.delivery_label"
                    class="ndb:truncate ndb:font-semibold"
                    x-text="selectedMailMessage.delivery_label"
                ></x-slot:value>
            </x-newdebugbar::inspector-fact>
            <x-newdebugbar::inspector-fact label="Source" data-ndb-mail-fact>
                <x-slot:value>
                    <x-newdebugbar::inspector-source-link
                        ::title="selectedMailMessage.callsite_label"
                        @click="setMailDetailTab('source')"
                    >
                        <x-slot:value x-text="selectedMailMessage.callsite_short_label"></x-slot:value>
                    </x-newdebugbar::inspector-source-link>
                </x-slot:value>
            </x-newdebugbar::inspector-fact>
        </x-newdebugbar::inspector-facts>
    </x-slot:metadata>
</x-newdebugbar::inspector-detail-header>
