<div data-ndb-notification-detail-panel="payload" class="ndb:space-y-3 ndb:p-3 ndb:sm:space-y-5 ndb:sm:p-4">
    <x-newdebugbar::inspector-evidence label="Application payload" language="json">
        <x-slot:aside>
            <span
                x-show="selectedNotification.locale"
                class="ndb:text-xs ndb:font-semibold ndb:text-zinc-400"
                x-text="'Locale ' + selectedNotification.locale"
            ></span>
        </x-slot:aside>
        <x-slot:value x-text="formatNotificationEvidence(selectedNotification.notification_data)"></x-slot:value>
    </x-newdebugbar::inspector-evidence>

    <section class="ndb:border-t ndb:border-zinc-200/90 ndb:pt-3 ndb:sm:pt-4 ndb:dark:border-zinc-800">
        <div class="ndb:flex ndb:items-center ndb:justify-between ndb:gap-3">
            <h4 class="ndb:text-xs ndb:font-bold">Channel evidence</h4>
            <span
                class="ndb:text-xs ndb:font-bold ndb:text-zinc-500 ndb:dark:text-zinc-400"
                x-text="selectedNotificationDelivery?.channel_label"
            ></span>
        </div>
        <x-newdebugbar::inspector-definition-list class="ndb:mt-2">
            <x-newdebugbar::inspector-definition-row
                label="Destination"
                x-show.important="selectedNotificationDelivery?.destination_label"
            >
                <x-slot:value
                    class="ndb:break-all"
                    x-text="selectedNotificationDelivery?.destination_label"
                ></x-slot:value>
            </x-newdebugbar::inspector-definition-row>
            <x-newdebugbar::inspector-definition-row
                label="Status"
                x-show.important="selectedNotificationDelivery?.status_label"
            >
                <x-slot:value x-text="selectedNotificationDelivery?.status_label"></x-slot:value>
            </x-newdebugbar::inspector-definition-row>
            <x-newdebugbar::inspector-definition-row
                label="Response type"
                x-show.important="selectedNotificationDelivery?.response_type"
            >
                <x-slot:value class="ndb:break-all" x-text="selectedNotificationDelivery?.response_type"></x-slot:value>
            </x-newdebugbar::inspector-definition-row>
            <x-newdebugbar::inspector-definition-row
                label="Exception"
                x-show.important="selectedNotificationDelivery?.exception_class"
            >
                <x-slot:value
                    class="ndb:break-all ndb:font-mono ndb:text-xs"
                    x-text="selectedNotificationDelivery?.exception_class"
                ></x-slot:value>
            </x-newdebugbar::inspector-definition-row>
            <x-newdebugbar::inspector-definition-row
                label="Failed at"
                x-show.important="selectedNotificationDelivery?.exception_location"
            >
                <x-slot:value
                    class="ndb:break-all"
                    x-text="
                        selectedNotificationDelivery?.exception_location
                            ? selectedNotificationDelivery.exception_location.file +
                              ':' +
                              selectedNotificationDelivery.exception_location.line
                            : ''
                    "
                ></x-slot:value>
            </x-newdebugbar::inspector-definition-row>
            <x-newdebugbar::inspector-definition-row
                label="Message ID"
                x-show.important="selectedNotificationDelivery?.mail_message_id"
            >
                <x-slot:value
                    class="ndb:break-all"
                    x-text="selectedNotificationDelivery?.mail_message_id"
                ></x-slot:value>
            </x-newdebugbar::inspector-definition-row>
        </x-newdebugbar::inspector-definition-list>
        <x-newdebugbar::code-block language="json" class="ndb:mt-2">
            <x-slot:value
                x-text="
                    formatNotificationEvidence(
                        selectedNotificationDelivery?.response,
                        'No provider response was captured.',
                    )
                "
            ></x-slot:value>
        </x-newdebugbar::code-block>
        <div x-show="selectedNotificationDelivery?.status === 'failed'" class="ndb:mt-3">
            <p
                x-show="selectedNotificationDelivery?.failure_message"
                class="ndb:rounded-lg ndb:bg-red-50 ndb:px-3 ndb:py-2 ndb:text-xs ndb:font-semibold ndb:leading-5 ndb:text-red-700 ndb:dark:bg-red-950/30 ndb:dark:text-red-300"
                x-text="selectedNotificationDelivery?.failure_message"
            ></p>
            <x-newdebugbar::code-block language="json" class="ndb:mt-2">
                <x-slot:value
                    x-text="
                        formatNotificationEvidence(
                            selectedNotificationDelivery?.failure_data,
                            'No extra failure data was captured.',
                        )
                    "
                ></x-slot:value>
            </x-newdebugbar::code-block>
        </div>
    </section>

    <x-newdebugbar::inspector-evidence
        label="Anonymous routes"
        language="json"
        x-show="Object.keys(selectedNotification.routes).length > 0"
        class="ndb:border-t ndb:border-zinc-200/90 ndb:pt-3 ndb:sm:pt-4 ndb:dark:border-zinc-800"
    >
        <x-slot:value x-text="formatNotificationEvidence(selectedNotification.routes)"></x-slot:value>
    </x-newdebugbar::inspector-evidence>
</div>
