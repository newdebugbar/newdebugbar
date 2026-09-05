{{-- Shows only the selected query execution and the active evidence tab. --}}
<x-newdebugbar::inspector-detail-pane
    detail-open="queryDetailOpen"
    detail-ref="queryDetail"
    detail-label="Selected query details"
    back-label="Queries"
    close-action="closeQueryDetail()"
    id="newdebugbar-query-detail"
    data-ndb-query-detail
    class="ndb:border-x-0"
>
    <x-slot:back>
        <x-newdebugbar::inspector-detail-back data-ndb-query-detail-back @click="closeQueryDetail()" label="Queries" />
    </x-slot:back>

    <template x-if="selectedQueryRecord && selectedQuery">
        <div data-ndb-query-active-detail class="ndb:flex ndb:flex-col">
            <x-newdebugbar::inspector-detail-header data-ndb-query-detail-header>
                <x-slot:title>
                    <h3
                        data-ndb-query-detail-title
                        class="ndb:min-w-0 ndb:text-sm ndb:font-bold ndb:leading-5"
                        x-text="
                            selectedQueryRecord.repeated
                                ? 'Repeated query pattern'
                                : `Query #${selectedQuery.execution}`
                        "
                    ></h3>
                </x-slot:title>
            </x-newdebugbar::inspector-detail-header>

            <x-newdebugbar::inspector-detail-tabs label="Query evidence">
                <x-newdebugbar::filter-tab
                    variant="segmented"
                    data-ndb-query-detail-tab="overview"
                    @click="setQueryDetailTab('overview')"
                    ::aria-pressed="queryDetailTab === 'overview'"
                    class="ndb:h-auto ndb:min-h-8"
                >
                    Overview
                </x-newdebugbar::filter-tab>
                <x-newdebugbar::filter-tab
                    variant="segmented"
                    data-ndb-query-detail-tab="explain"
                    @click="openQueryExplain($wire)"
                    ::aria-pressed="queryDetailTab === 'explain'"
                    class="ndb:h-auto ndb:min-h-8"
                >
                    EXPLAIN
                </x-newdebugbar::filter-tab>
            </x-newdebugbar::inspector-detail-tabs>

            <template x-if="queryDetailTab === 'overview'">
                <section data-ndb-query-detail-panel="overview">
                    <div class="ndb:space-y-3 ndb:p-3 ndb:sm:space-y-4 ndb:sm:p-4">
                        <template x-if="selectedQueryRecord.repeated">
                            <div class="ndb:max-w-sm">
                                <p
                                    class="ndb:mb-1.5 ndb:text-xs ndb:font-bold ndb:uppercase ndb:tracking-wide ndb:text-zinc-400"
                                    x-text="`Repeated runs (${selectedQueryRecord.count})`"
                                ></p>
                                <x-newdebugbar::select-field
                                    label="Choose a repeated run"
                                    data-ndb-query-execution-select
                                    x-model.number="querySelectedExecution"
                                    @change="selectQueryExecution(Number($event.target.value))"
                                >
                                    <template
                                        x-for="execution in selectedQueryRecord.executions"
                                        :key="execution.execution"
                                    >
                                        <option
                                            :value="execution.execution"
                                            x-text="`#${execution.execution} — ${execution.duration_label}`"
                                        ></option>
                                    </template>
                                </x-newdebugbar::select-field>
                            </div>
                        </template>

                        <x-newdebugbar::inspector-facts :bordered="false">
                            <x-newdebugbar::inspector-fact label="Duration">
                                <x-slot:value
                                    class="ndb:font-bold ndb:tabular-nums"
                                    x-text="selectedQuery.duration_label"
                                ></x-slot:value>
                            </x-newdebugbar::inspector-fact>
                            <x-newdebugbar::inspector-fact label="Query time">
                                <x-slot:value
                                    class="ndb:font-semibold ndb:tabular-nums"
                                    x-text="`${Number(selectedQuery.query_time_percent).toFixed(1)}%`"
                                ></x-slot:value>
                            </x-newdebugbar::inspector-fact>
                            <x-newdebugbar::inspector-fact label="Type">
                                <x-slot:value
                                    class="ndb:font-semibold"
                                    x-text="formatQueryType(selectedQuery.query_type)"
                                ></x-slot:value>
                            </x-newdebugbar::inspector-fact>
                            <x-newdebugbar::inspector-fact label="Connection">
                                <x-slot:value
                                    ::title="selectedQuery.connection"
                                    class="ndb:truncate ndb:font-semibold"
                                    x-text="selectedQuery.connection"
                                ></x-slot:value>
                            </x-newdebugbar::inspector-fact>
                        </x-newdebugbar::inspector-facts>

                        <x-newdebugbar::inspector-evidence label="Full query" language="sql">
                            <x-slot:aside>
                                <x-newdebugbar::inspector-action
                                    icon="copy"
                                    data-ndb-query-copy-sql
                                    @click="copyText(selectedQuery.display_sql)"
                                >
                                    Copy query
                                </x-newdebugbar::inspector-action>
                            </x-slot:aside>
                            <x-slot:value
                                data-ndb-query-sql
                                x-text="selectedQuery.display_sql"
                                x-effect="
                                    selectedQuery?.execution;
                                    $nextTick(() => highlightQueryCode($el));
                                "
                            ></x-slot:value>
                        </x-newdebugbar::inspector-evidence>

                        <p
                            data-ndb-query-incomplete-bindings
                            x-show.important="! selectedQuery.display_sql_complete"
                            class="ndb:text-xs ndb:leading-5 ndb:text-zinc-500 ndb:dark:text-zinc-400"
                        >
                            Some binding values were not retained, so the remaining placeholders cannot be filled in.
                        </p>

                        <x-newdebugbar::inspector-explanation
                            x-show.important="selectedQueryRecord.likely_n_plus_one"
                            title="Why this may be an N+1 query"
                            description="The same application call site ran this query at least three times with different bindings. If those loads happen inside a loop, trace the application call site and consider eager loading or batching."
                        />
                    </div>

                    <template x-if="selectedQueryHasSource">
                        <x-newdebugbar::inspector-source-panel
                            frames="selectedQuery.stack ?? []"
                            columns="1"
                            empty-label="No application stack was captured for this query."
                            class="ndb:border-t ndb:border-zinc-200/90 ndb:dark:border-zinc-800"
                        >
                            <x-newdebugbar::inspector-source-fact label="Source">
                                <x-slot:value>
                                    <x-newdebugbar::inspector-source-link
                                        ::title="selectedQuery.source_available ? 'Copy ' + selectedQuery.source_label : null"
                                        ::disabled="! selectedQuery.source_available"
                                        @click="copyText(selectedQuery.source_label)"
                                    >
                                        <x-slot:value x-text="selectedQuery.source_label"></x-slot:value>
                                    </x-newdebugbar::inspector-source-link>
                                </x-slot:value>
                            </x-newdebugbar::inspector-source-fact>
                        </x-newdebugbar::inspector-source-panel>
                    </template>
                </section>
            </template>

            <template x-if="queryDetailTab === 'explain'">
                <section
                    data-ndb-query-detail-panel="explain"
                    class="ndb:space-y-3 ndb:p-3 ndb:sm:space-y-4 ndb:sm:p-4"
                >
                    <p
                        x-show.important="! selectedQuery.explain_available"
                        class="ndb:text-xs ndb:leading-5 ndb:text-zinc-500 ndb:dark:text-zinc-400"
                        x-text="selectedQuery.explain_unavailable_reason"
                    ></p>

                    <p
                        x-show.important="queryExplainLoading"
                        data-ndb-query-explain-loading
                        role="status"
                        class="ndb:flex ndb:items-center ndb:gap-2 ndb:text-xs ndb:font-semibold ndb:text-zinc-500 ndb:dark:text-zinc-400"
                    >
                        <span class="ndb:size-1.5 ndb:shrink-0 ndb:rounded-full ndb:bg-indigo-500"></span>
                        <span>Running EXPLAIN…</span>
                    </p>

                    <template x-if="queryExplain !== null">
                        <div data-ndb-query-explain-result class="ndb:space-y-3 ndb:sm:space-y-4">
                            <x-newdebugbar::inspector-facts :columns="2" :bordered="false">
                                <x-newdebugbar::inspector-fact label="Mode">
                                    <x-slot:value class="ndb:font-semibold" x-text="queryExplain.mode"></x-slot:value>
                                </x-newdebugbar::inspector-fact>
                                <x-newdebugbar::inspector-fact label="Driver">
                                    <x-slot:value class="ndb:font-semibold" x-text="queryExplain.driver"></x-slot:value>
                                </x-newdebugbar::inspector-fact>
                            </x-newdebugbar::inspector-facts>
                            <x-newdebugbar::inspector-evidence label="Plan" language="json">
                                <x-slot:value
                                    data-ndb-query-explain-plan
                                    x-text="formatQueryEvidence(queryExplain.rows)"
                                    x-effect="
                                        queryExplain;
                                        $nextTick(() => highlightQueryCode($el));
                                    "
                                ></x-slot:value>
                            </x-newdebugbar::inspector-evidence>
                        </div>
                    </template>

                    <div
                        x-show.important="queryExplainError !== null"
                        role="alert"
                        class="ndb:rounded-lg ndb:border ndb:border-red-200 ndb:bg-red-50/60 ndb:p-3 ndb:dark:border-red-950 ndb:dark:bg-red-950/20"
                    >
                        <p class="ndb:text-xs ndb:font-semibold ndb:leading-5 ndb:text-red-700 ndb:dark:text-red-300">
                            EXPLAIN could not run
                        </p>
                        <p
                            data-ndb-query-explain-error
                            class="ndb:mt-1 ndb:text-xs ndb:leading-5 ndb:text-zinc-600 ndb:dark:text-zinc-300"
                            x-text="queryExplainError"
                        ></p>
                    </div>
                </section>
            </template>
        </div>
    </template>

    <x-newdebugbar::inspector-detail-empty
        data-ndb-query-detail-empty
        label="Choose a query to inspect its evidence."
        x-show.important="! selectedQuery"
        class="ndb:flex-1"
    />
</x-newdebugbar::inspector-detail-pane>
