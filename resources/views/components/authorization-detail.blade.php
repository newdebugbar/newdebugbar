{{-- Presents one authorization decision as a single structured evidence flow. --}}
<x-newdebugbar::inspector-detail-pane
    detail-open="authorizationDetailOpen"
    detail-ref="authorizationDetail"
    detail-label="Selected authorization decision details"
    back-label="Decisions"
    close-action="closeAuthorizationDetail()"
    data-ndb-authorization-detail
>
    <x-slot:back>
        <x-newdebugbar::inspector-detail-back
            data-ndb-authorization-detail-back
            @click="closeAuthorizationDetail()"
            label="Decisions"
        />
    </x-slot:back>

    <template x-if="selectedAuthorizationDecision">
        <div class="ndb:flex ndb:flex-col">
            <x-newdebugbar::inspector-detail-header layout="wrap" data-ndb-authorization-header>
                <x-slot:title>
                    <h3
                        data-ndb-authorization-detail-ability
                        class="ndb:min-w-0 ndb:break-words ndb:text-base ndb:font-bold ndb:leading-6"
                        x-text="selectedAuthorizationDecision.ability"
                    ></h3>
                </x-slot:title>

                <x-slot:aside>
                    <x-newdebugbar::inspector-action
                        icon="copy"
                        data-ndb-authorization-copy-evidence
                        @click="copyText(selectedAuthorizationDecision.copy_evidence)"
                    >
                        Copy evidence
                    </x-newdebugbar::inspector-action>
                </x-slot:aside>
            </x-newdebugbar::inspector-detail-header>

            <div data-ndb-authorization-detail-panel="combined" class="ndb:p-0">
                <div class="ndb:space-y-3 ndb:p-3 ndb:sm:space-y-5 ndb:sm:p-4">
                    <x-newdebugbar::inspector-facts columns="2" :bordered="false" data-ndb-authorization-metadata>
                        <x-newdebugbar::inspector-fact label="Result">
                            <x-slot:value
                                data-ndb-authorization-detail-result
                                ::class="selectedAuthorizationDecision.result === 'allowed'
                                    ? 'ndb:text-emerald-700 ndb:dark:text-emerald-300'
                                    : 'ndb:text-red-700 ndb:dark:text-red-300'"
                                class="ndb:text-xs ndb:font-bold"
                                x-text="selectedAuthorizationDecision.result_label"
                            ></x-slot:value>
                        </x-newdebugbar::inspector-fact>

                        <x-newdebugbar::inspector-fact label="User" data-ndb-authorization-user-detail class="ndb:p-0">
                            <x-slot:value>
                                <span
                                    class="ndb:block ndb:truncate ndb:text-xs ndb:font-semibold"
                                    x-text="selectedAuthorizationDecision.user_label"
                                ></span>
                                <code
                                    x-show.important="selectedAuthorizationDecision.user_type !== null"
                                    class="ndb:mt-0.5 ndb:block ndb:truncate ndb:font-mono ndb:text-xs ndb:text-zinc-400"
                                    x-text="selectedAuthorizationDecision.user_type"
                                ></code>
                                <span
                                    x-show.important="selectedAuthorizationDecision.user_identifier !== null"
                                    class="ndb:mt-0.5 ndb:flex ndb:min-w-0 ndb:gap-1.5 ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400"
                                >
                                    <span x-text="selectedAuthorizationDecision.user_identifier_name ?? 'Identifier'"></span>
                                    <span
                                        class="ndb:min-w-0 ndb:truncate ndb:font-semibold ndb:tabular-nums"
                                        x-text="selectedAuthorizationDecision.user_identifier"
                                    ></span>
                                </span>
                            </x-slot:value>
                        </x-newdebugbar::inspector-fact>
                    </x-newdebugbar::inspector-facts>

                    <section
                        data-ndb-authorization-response
                        x-show.important="
                            selectedAuthorizationDecision.result_message !== null ||
                            selectedAuthorizationDecision.result_code !== null ||
                            selectedAuthorizationDecision.result_status !== null
                        "
                        class="ndb:p-0"
                    >
                        <h4 class="ndb:text-xs ndb:font-bold">Authorization response</h4>
                        <x-newdebugbar::inspector-definition-list class="ndb:mt-2">
                            <x-newdebugbar::inspector-definition-row
                                label="Message"
                                x-show.important="selectedAuthorizationDecision.result_message !== null"
                            >
                                <x-slot:value x-text="selectedAuthorizationDecision.result_message"></x-slot:value>
                            </x-newdebugbar::inspector-definition-row>
                            <x-newdebugbar::inspector-definition-row
                                label="Code"
                                x-show.important="selectedAuthorizationDecision.result_code !== null"
                            >
                                <x-slot:value
                                    class="ndb:font-semibold"
                                    x-text="selectedAuthorizationDecision.result_code"
                                ></x-slot:value>
                            </x-newdebugbar::inspector-definition-row>
                            <x-newdebugbar::inspector-definition-row
                                label="HTTP status"
                                x-show.important="selectedAuthorizationDecision.result_status !== null"
                            >
                                <x-slot:value
                                    class="ndb:font-semibold ndb:tabular-nums"
                                    x-text="selectedAuthorizationDecision.result_status"
                                ></x-slot:value>
                            </x-newdebugbar::inspector-definition-row>
                        </x-newdebugbar::inspector-definition-list>
                    </section>

                    <section x-show.important="selectedAuthorizationDecision.arguments.length > 0">
                        <h4 class="ndb:text-xs ndb:font-bold">Arguments</h4>
                        <x-newdebugbar::inspector-definition-list
                            data-ndb-authorization-arguments-detail
                            class="ndb:mt-2"
                        >
                            <template
                                x-for="argument in selectedAuthorizationDecision.arguments"
                                :key="argument.position"
                            >
                                <x-newdebugbar::inspector-definition-row>
                                    <x-slot:term x-text="argument.role_label"></x-slot:term>
                                    <x-slot:value class="ndb:min-w-0">
                                        <span
                                            class="ndb:block ndb:break-words ndb:text-xs ndb:font-semibold ndb:text-zinc-700 ndb:dark:text-zinc-200"
                                            x-text="argument.label"
                                        ></span>
                                        <code
                                            class="ndb:mt-0.5 ndb:block ndb:break-words ndb:font-mono ndb:text-xs ndb:text-zinc-400"
                                            x-text="argument.type"
                                        ></code>
                                        <span
                                            x-show.important="argument.identity_label !== null"
                                            class="ndb:mt-0.5 ndb:block ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400"
                                            x-text="argument.identity_label"
                                        ></span>
                                    </x-slot:value>
                                </x-newdebugbar::inspector-definition-row>
                            </template>
                        </x-newdebugbar::inspector-definition-list>
                    </section>
                </div>

                <x-newdebugbar::inspector-source-panel
                    title="Authorization logic"
                    frames="selectedAuthorizationDecision.stack"
                    columns="1"
                    empty-label="No application stack was captured for this decision."
                    class="ndb:border-t ndb:border-zinc-200/90 ndb:dark:border-zinc-800"
                >
                    <x-newdebugbar::inspector-source-fact>
                        <x-slot:term x-text="selectedAuthorizationDecision.handler_label"></x-slot:term>
                        <x-slot:value>
                            <code
                                data-ndb-language="php"
                                x-show.important="selectedAuthorizationDecision.handler_available"
                                class="ndb:block ndb:min-w-0 ndb:break-all ndb:bg-transparent ndb:font-mono ndb:text-xs ndb:font-medium ndb:leading-5 ndb:text-zinc-700 ndb:dark:text-zinc-200"
                                x-text="selectedAuthorizationDecision.handler_name"
                            ></code>
                            <span
                                x-show.important="! selectedAuthorizationDecision.handler_available"
                                class="ndb:block ndb:text-xs ndb:font-semibold"
                            >Not captured</span>
                            <template x-if="selectedAuthorizationDecision.handler_source_label">
                                <x-newdebugbar::inspector-source-link
                                    class="ndb:mt-1"
                                    data-ndb-authorization-copy-handler-source
                                    @click="copyText(selectedAuthorizationDecision.handler_source_label)"
                                    ::title="selectedAuthorizationDecision.handler_source_label"
                                >
                                    <x-slot:value x-text="selectedAuthorizationDecision.handler_source_label"></x-slot:value>
                                </x-newdebugbar::inspector-source-link>
                            </template>
                        </x-slot:value>
                    </x-newdebugbar::inspector-source-fact>

                    <template
                        x-if="
                            selectedAuthorizationDecision.stack.length === 0 &&
                            selectedAuthorizationDecision.callsite_label
                        "
                    >
                        <x-newdebugbar::inspector-source-fact label="Checked from">
                            <x-slot:value>
                                <x-newdebugbar::inspector-source-link
                                    data-ndb-authorization-copy-callsite
                                    @click="copyText(selectedAuthorizationDecision.callsite_label)"
                                    ::title="selectedAuthorizationDecision.callsite_label"
                                >
                                    <x-slot:value x-text="selectedAuthorizationDecision.callsite_label"></x-slot:value>
                                </x-newdebugbar::inspector-source-link>
                            </x-slot:value>
                        </x-newdebugbar::inspector-source-fact>
                    </template>
                </x-newdebugbar::inspector-source-panel>

                <p class="ndb:px-3 ndb:pb-3 ndb:text-xs ndb:leading-5 ndb:text-zinc-500 ndb:sm:px-4 ndb:sm:pb-4 ndb:dark:text-zinc-400">
                    Gate before or after hooks can change the final result and are not identified here.
                </p>
            </div>
        </div>
    </template>

    <x-newdebugbar::inspector-detail-empty
        x-show.important="! selectedAuthorizationDecision"
        label="Choose a decision to inspect its evidence."
    />
</x-newdebugbar::inspector-detail-pane>
