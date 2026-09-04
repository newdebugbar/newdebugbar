<template x-if="mailDetailTab === 'message'">
    <div data-ndb-mail-detail-panel="message" class="ndb:p-3 ndb:sm:p-4">
        <dl class="ndb:divide-y ndb:divide-zinc-200/90 ndb:dark:divide-zinc-800">
            <template
                x-for="
                    field in
                    [
                        ['From', selectedMailMessage.from],
                        ['To', selectedMailMessage.to],
                        ['CC', selectedMailMessage.cc],
                        ['BCC', selectedMailMessage.bcc],
                        ['Reply to', selectedMailMessage.reply_to],
                    ].filter((field) => field[1].length > 0 || ['From', 'To'].includes(field[0]))
                "
                :key="field[0]"
            >
                <x-newdebugbar::inspector-definition-row>
                    <x-slot:term x-text="field[0]"></x-slot:term>
                    <x-slot:value
                        class="ndb:break-all ndb:text-xs ndb:leading-5 ndb:text-zinc-600 ndb:dark:text-zinc-300"
                        x-text="formatMailAddresses(field[1])"
                    ></x-slot:value>
                </x-newdebugbar::inspector-definition-row>
            </template>
        </dl>

        <section
            x-show="selectedMailMessage.attachments.length > 0"
            class="ndb:mt-4 ndb:border-t ndb:border-zinc-200/90 ndb:pt-3 ndb:sm:mt-5 ndb:sm:pt-4 ndb:dark:border-zinc-800"
        >
            <h4 class="ndb:text-xs ndb:font-bold">Attachments</h4>
            <div class="ndb:mt-2 ndb:divide-y ndb:divide-zinc-200/90 ndb:overflow-hidden ndb:rounded-xl ndb:border ndb:border-zinc-200/90 ndb:dark:divide-zinc-800 ndb:dark:border-zinc-800">
                <template x-for="(attachment, index) in selectedMailMessage.attachments" :key="index">
                    <div>
                        <template x-if="attachment.download_url">
                            <a
                                data-ndb-mail-attachment-download
                                :href="attachment.download_url"
                                :download="attachment.name"
                                class="ndb:flex ndb:h-auto ndb:min-w-0 ndb:items-center ndb:gap-3 ndb:bg-transparent ndb:px-3 ndb:py-2.5 ndb:no-underline ndb:transition-colors ndb:hover:bg-zinc-50/80 ndb:focus-visible:outline-2 ndb:focus-visible:outline-offset-[-2px] ndb:focus-visible:outline-indigo-500 ndb:dark:hover:bg-zinc-900/60"
                            >
                                <span class="ndb:min-w-0 ndb:flex-1">
                                    <span
                                        class="ndb:block ndb:truncate ndb:text-xs ndb:font-semibold"
                                        x-text="attachment.name"
                                    ></span>
                                    <span class="ndb:mt-0.5 ndb:flex ndb:min-w-0 ndb:flex-wrap ndb:gap-x-2 ndb:text-[11px] ndb:text-zinc-400">
                                        <span class="ndb:truncate" x-text="attachment.content_type"></span>
                                        <span class="ndb:tabular-nums" x-text="attachment.size_label"></span>
                                    </span>
                                </span>
                                <span class="ndb:flex ndb:shrink-0 ndb:items-center ndb:gap-1.5 ndb:text-[11px] ndb:font-bold ndb:text-indigo-600 ndb:dark:text-indigo-300">
                                    Download
                                    <x-newdebugbar::icon name="download" size="3.5" />
                                </span>
                            </a>
                        </template>
                        <template x-if="! attachment.download_url">
                            <div class="ndb:flex ndb:min-w-0 ndb:items-center ndb:gap-3 ndb:px-3 ndb:py-2.5">
                                <span class="ndb:min-w-0 ndb:flex-1">
                                    <span
                                        class="ndb:block ndb:truncate ndb:text-xs ndb:font-semibold"
                                        x-text="attachment.name"
                                    ></span>
                                    <span class="ndb:mt-0.5 ndb:flex ndb:min-w-0 ndb:flex-wrap ndb:gap-x-2 ndb:text-[11px] ndb:text-zinc-400">
                                        <span class="ndb:truncate" x-text="attachment.content_type"></span>
                                        <span class="ndb:tabular-nums" x-text="attachment.size_label"></span>
                                    </span>
                                </span>
                                <span class="ndb:shrink-0 ndb:text-[11px] ndb:font-semibold ndb:text-zinc-400">
                                    Not retained
                                </span>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
            <p
                x-show="selectedMailMessage.attachment_bodies_omitted > 0"
                class="ndb:mt-2 ndb:text-[11px] ndb:leading-5 ndb:text-zinc-500 ndb:dark:text-zinc-400"
            >
                <span x-text="selectedMailMessage.attachment_bodies_omitted"></span>
                <span x-text="selectedMailMessage.attachment_bodies_omitted === 1 ? 'attachment was' : 'attachments were'"></span>
                not retained because of capture settings, size limits, redaction, or unreadable files.
            </p>
        </section>

        <section class="ndb:mt-4 ndb:border-t ndb:border-zinc-200/90 ndb:pt-3 ndb:sm:mt-5 ndb:sm:pt-4 ndb:dark:border-zinc-800">
            <h4 class="ndb:text-xs ndb:font-bold">Delivery details</h4>
            <dl class="ndb:mt-2 ndb:divide-y ndb:divide-zinc-200/90 ndb:dark:divide-zinc-800">
                <template
                    x-for="
                        field in
                        [
                            ['Sender', selectedMailMessage.sender],
                            ['Return path', selectedMailMessage.return_path],
                            ['Date', selectedMailMessage.date],
                            ['Message ID', selectedMailMessage.transport_message_id],
                            ['Default mailer', selectedMailMessage.mailer],
                            ['Transport', selectedMailMessage.transport],
                            ['Connection', selectedMailMessage.connection],
                            ['Queue', selectedMailMessage.queue],
                            ['Job ID', selectedMailMessage.job_id],
                            ['Delay seconds', selectedMailMessage.delay_seconds],
                        ].filter((field) => field[1])
                    "
                    :key="field[0]"
                >
                    <x-newdebugbar::inspector-definition-row>
                        <x-slot:term x-text="field[0]"></x-slot:term>
                        <x-slot:value
                            class="ndb:break-all ndb:text-xs ndb:leading-5 ndb:text-zinc-600 ndb:dark:text-zinc-300"
                            x-text="field[1]"
                        ></x-slot:value>
                    </x-newdebugbar::inspector-definition-row>
                </template>
            </dl>
        </section>

        <p
            x-show="
                selectedMailMessage.truncated ||
                selectedMailMessage.addresses_omitted > 0 ||
                selectedMailMessage.attachment_bodies_omitted > 0 ||
                selectedMailMessage.attachment_metadata_omitted > 0
            "
            class="ndb:mt-3 ndb:rounded-lg ndb:bg-amber-50 ndb:px-3 ndb:py-2 ndb:text-[11px] ndb:font-semibold ndb:leading-5 ndb:text-amber-700 ndb:sm:mt-4 ndb:dark:bg-amber-950/35 ndb:dark:text-amber-300"
        >
            Some message data was bounded to keep this profile responsive.
        </p>
    </div>
</template>

<template x-if="mailDetailTab === 'source'">
    <div data-ndb-mail-detail-panel="source">
        <x-newdebugbar::inspector-source-panel frames="selectedMailMessage.stack">
            <x-newdebugbar::inspector-source-fact label="Mailable or notification" :code="true">
                <x-slot:value x-text="selectedMailMessage.source || 'No source class was captured.'"></x-slot:value>
            </x-newdebugbar::inspector-source-fact>
            <x-newdebugbar::inspector-source-fact label="Triggered at">
                <x-slot:value x-text="selectedMailMessage.callsite_label"></x-slot:value>
            </x-newdebugbar::inspector-source-fact>
        </x-newdebugbar::inspector-source-panel>
    </div>
</template>
