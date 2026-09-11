import { readInspectorPayload } from '../runtime.js';

/** Owns queries inspector state and interactions. */
export function createQueries(context) {
  const { browser, shell, profileId } = context;
  const summary = shell.summary;
  return {
    refresh() {
      const payload = readInspectorPayload(this.$root, '[data-ndb-query-payload]');
      if (payload !== null) this.initializeQueries(payload);
    },
    receiveIntent(filter) {
      if (!['repeated', 'slow'].includes(filter)) return;
      this.queryFilter = 'attention';
      this.queryFocusFilter = filter;
      this.applyQueryView();
      this.$nextTick?.(() => this.focusQueryFinding(filter));
    },

    queryRecords: [],
    queryFilter: 'all',
    querySearch: '',
    querySort: 'execution',
    querySortDirection: 'asc',
    querySelected: null,
    querySelectedExecution: null,
    queryDetailOpen: false,
    queryDetailTab: 'overview',
    queryDetailReturnFocus: null,
    queryExplain: null,
    queryExplainError: null,
    queryExplainLoading: false,
    queryExplainExecution: null,
    queryExplainScrollTop: null,
    queryFocusFilter: null,
    visibleQueryCount: summary.query_count ?? 0,

    get selectedQueryRecord() {
      return this.queryRecords.find((record) => record.key === this.querySelected) ?? null;
    },

    get selectedQuery() {
      const executions = this.selectedQueryRecord?.executions ?? [];

      return (
        executions.find((query) => query.execution === this.querySelectedExecution) ?? executions[0] ?? null
      );
    },

    get selectedQueryHasSource() {
      return (
        this.selectedQuery?.source_available === true ||
        (Array.isArray(this.selectedQuery?.stack) && this.selectedQuery.stack.length > 0)
      );
    },

    initializeQueries(records) {
      const previous = new Map(
        this.queryRecords.flatMap((record) =>
          (record.executions ?? []).map((query) => [query.execution, query]),
        ),
      );
      this.queryRecords = (Array.isArray(records) ? records : []).map((record) => ({
        ...record,
        executions: (record.executions ?? []).map((query) => {
          const retained = previous.get(query.execution);
          if (!retained || query.explain != null || query.explain_error != null) return query;

          return {
            ...query,
            explain: retained.explain ?? null,
            explain_error: retained.explain_error ?? null,
            explain_loading: retained.explain_loading === true,
          };
        }),
      }));
      if (this.initialized) {
        const selectionExists = this.selectedQueryRecord?.executions?.some(
          (query) => query.execution === this.querySelectedExecution,
        );
        if (!selectionExists) {
          this.querySelectedExecution = this.selectedQueryRecord?.executions?.[0]?.execution ?? null;
          this.queryDetailTab = 'overview';
        }
        this.syncQueryExplain(!selectionExists);
        this.$nextTick?.(() => this.applyQueryView());
        return;
      }
      this.initialized = true;
      this.queryFilter = this.queryFocusFilter ? 'attention' : 'all';
      this.querySearch = '';
      this.querySort = 'execution';
      this.querySortDirection = 'asc';
      this.querySelected = this.queryRecords[0]?.key ?? null;
      this.querySelectedExecution = this.queryRecords[0]?.executions?.[0]?.execution ?? null;
      this.queryDetailOpen = false;
      this.queryDetailTab = 'overview';
      this.queryDetailReturnFocus = null;
      this.syncQueryExplain();
      this.$nextTick?.(() => {
        this.applyQueryView();
        if (this.queryFocusFilter) this.focusQueryFinding(this.queryFocusFilter);
      });
    },

    setQueryFilter(filter) {
      if (!['all', 'attention', 'read', 'write'].includes(filter)) return;

      this.queryFilter = filter;
      this.queryFocusFilter = null;
      this.applyQueryView();
    },

    toggleQuerySort(sort) {
      if (sort !== 'duration') return;

      if (this.querySort !== sort) {
        this.querySort = sort;
        this.querySortDirection = 'desc';
      } else if (this.querySortDirection === 'desc') {
        this.querySortDirection = 'asc';
      } else {
        this.querySort = 'execution';
        this.querySortDirection = 'asc';
      }

      this.applyQueryView();
    },

    selectQueryRecord(key) {
      const record = this.queryRecords.find((candidate) => candidate.key === key);
      if (!record) return;

      this.querySelected = record.key;
      this.querySelectedExecution = record.executions?.[0]?.execution ?? null;
      this.queryDetailOpen = true;
      this.queryDetailTab = 'overview';
      this.queryDetailReturnFocus = browser.activeElement?.() ?? null;
      this.syncQueryExplain();
      this.$nextTick?.(() => {
        this.$refs?.queryDetail?.scrollTo?.({ top: 0, behavior: 'instant' });
        if ((browser.viewportWidth?.() ?? 1024) < 1024)
          this.$refs?.queryDetail?.focus?.({ preventScroll: true });
        browser.highlight?.();
      });
    },

    closeQueryDetail() {
      const returnFocus = this.queryDetailReturnFocus;
      const selectedRow = [
        ...(this.$refs?.queryList?.querySelectorAll?.('[data-ndb-query-item]') ?? []),
      ].find((item) => item.dataset.ndbQueryKey === this.querySelected);
      this.queryDetailOpen = false;
      this.queryDetailReturnFocus = null;
      this.$nextTick?.(() => {
        const focus = () =>
          (returnFocus?.isConnected === false ? selectedRow : (returnFocus ?? selectedRow))?.focus?.();
        browser.afterPaint ? browser.afterPaint(focus) : focus();
      });
    },

    selectQueryExecution(execution) {
      if (!this.selectedQueryRecord?.executions?.some((query) => query.execution === execution)) return;

      this.querySelectedExecution = execution;
      this.syncQueryExplain();
      this.$nextTick?.(() => browser.highlight?.());
    },

    setQueryDetailTab(tab) {
      if (!['overview', 'explain'].includes(tab)) return;

      this.queryDetailTab = tab;
      this.$nextTick?.(() => {
        this.$refs?.queryDetail?.scrollTo?.({ top: 0, behavior: 'instant' });
        browser.highlight?.();
      });
    },

    openQueryExplain(wire) {
      this.setQueryDetailTab('explain');

      return this.runQueryExplain(wire);
    },

    async runQueryExplain(wire) {
      if (this.destroyed || this.profileId !== shell.summary.id) return;
      if (typeof wire?.explainQuery !== 'function') return;

      const execution = this.beginQueryExplain();
      if (execution === null) return;
      const profileId = shell.summary.id;

      try {
        await wire.explainQuery(execution);
      } catch {
        this.failQueryExplain(execution, profileId);
      }
    },

    syncQueryExplain(resetScroll = true) {
      this.queryExplain = this.selectedQuery?.explain ?? null;
      this.queryExplainError = this.selectedQuery?.explain_error ?? null;
      this.queryExplainLoading = this.selectedQuery?.explain_loading === true;
      this.queryExplainExecution = this.selectedQuery?.execution ?? null;
      if (resetScroll) this.queryExplainScrollTop = null;
    },

    beginQueryExplain() {
      const query = this.selectedQuery;
      if (!query?.explain_available || query.explain_loading === true) return null;
      if (query.explain != null || query.explain_error != null) return null;

      this.queryDetailTab = 'explain';
      query.explain = null;
      query.explain_error = null;
      query.explain_loading = true;
      this.queryExplain = null;
      this.queryExplainError = null;
      this.queryExplainLoading = true;
      this.queryExplainExecution = query.execution;
      this.queryExplainScrollTop = this.$refs?.queryDetail?.scrollTop ?? null;

      return query.execution;
    },

    receiveQueryExplain(detail = {}) {
      if (this.destroyed || this.profileId !== shell.summary.id || detail.profileId !== this.profileId)
        return;

      const execution = Number(detail.execution);
      if (!Number.isFinite(execution)) return;

      this.queryRecords.forEach((record) =>
        (record.executions ?? []).forEach((query) => {
          if (query.execution !== execution) return;

          query.explain = detail.explain ?? null;
          query.explain_error = detail.error ?? null;
          query.explain_loading = false;
        }),
      );

      if (execution !== this.querySelectedExecution) return;

      this.queryExplain = detail.explain ?? null;
      this.queryExplainError = detail.error ?? null;
      this.queryExplainLoading = false;
      this.$nextTick?.(() => {
        if (this.queryExplainScrollTop !== null) {
          this.$refs?.queryDetail?.scrollTo?.({
            top: this.queryExplainScrollTop,
            behavior: 'instant',
          });
        }
        browser.highlight?.();
      });
    },

    failQueryExplain(execution = this.queryExplainExecution, profileId = shell.summary.id) {
      if (this.destroyed || profileId !== this.profileId || profileId !== shell.summary.id) return;

      const normalizedExecution = Number(execution);
      if (!Number.isFinite(normalizedExecution)) return;

      this.queryRecords.forEach((record) =>
        (record.executions ?? []).forEach((query) => {
          if (query.execution !== normalizedExecution) return;

          query.explain = null;
          query.explain_error = 'EXPLAIN could not be completed.';
          query.explain_loading = false;
        }),
      );

      if (normalizedExecution !== this.querySelectedExecution) return;

      this.queryExplain = null;
      this.queryExplainLoading = false;
      this.queryExplainError = 'EXPLAIN could not be completed.';
    },

    focusQueryFinding(filter) {
      if (!['repeated', 'slow'].includes(filter)) return;

      const selector =
        filter === 'repeated'
          ? '[data-ndb-query-item][data-ndb-repeated="true"]:not([hidden])'
          : '[data-ndb-query-item][data-ndb-slow="true"]:not([hidden])';
      const item = this.$refs?.queryList?.querySelector?.(selector);
      if (!item) return;

      this.queryFocusFilter = null;
      this.selectQueryRecord(item.dataset.ndbQueryKey);
      item.scrollIntoView?.({ block: 'nearest' });
    },

    applyQueryView() {
      if (this.queryRecords.length === 0) {
        this.querySelected = null;
        this.querySelectedExecution = null;
        this.queryDetailOpen = false;
        this.visibleQueryCount = 0;
        this.syncQueryExplain();
        return;
      }
      const list = this.$refs?.queryList;
      const search = this.querySearch.toLowerCase().trim();
      const items = [...(list?.querySelectorAll?.('[data-ndb-query-item]') ?? [])];
      let visible = 0;
      let firstVisible = null;
      let selectedVisible = false;

      items
        .sort((left, right) => this.compareQueries(left, right))
        .forEach((item) => {
          const matchesFilter =
            this.queryFilter === 'all' ||
            (this.queryFilter === 'attention' && item.dataset.ndbAttention === 'true') ||
            (this.queryFilter === 'read' && item.dataset.ndbQueryType === 'read') ||
            (this.queryFilter === 'write' && item.dataset.ndbQueryType === 'write');
          const matchesSearch = search === '' || item.dataset.ndbSearch?.includes(search);
          const matches = matchesFilter && matchesSearch;
          item.hidden = !matches;

          if (matches) {
            item.style?.removeProperty?.('display');
            firstVisible ??= item.dataset.ndbQueryKey;
            selectedVisible ||= item.dataset.ndbQueryKey === this.querySelected;
            visible += Number(item.dataset.ndbQueryExecutionCount ?? 1);
          } else {
            item.style?.setProperty?.('display', 'none', 'important');
          }

          list?.appendChild?.(item);
        });

      this.visibleQueryCount = visible;

      if (!selectedVisible) {
        const record = this.queryRecords.find((candidate) => candidate.key === firstVisible) ?? null;
        this.querySelected = record?.key ?? null;
        this.querySelectedExecution = record?.executions?.[0]?.execution ?? null;
        if (record === null) this.queryDetailOpen = false;
        this.queryDetailTab = 'overview';
        this.queryDetailReturnFocus = null;
        this.syncQueryExplain();
      }
    },

    compareQueries(left, right) {
      if (this.querySort === 'duration') {
        const durationComparison =
          Number(left.dataset.ndbDuration ?? 0) - Number(right.dataset.ndbDuration ?? 0);
        const directedComparison =
          this.querySortDirection === 'asc' ? durationComparison : -durationComparison;

        return (
          directedComparison ||
          Number(left.dataset.ndbExecution ?? 0) - Number(right.dataset.ndbExecution ?? 0)
        );
      }

      return Number(left.dataset.ndbExecution ?? 0) - Number(right.dataset.ndbExecution ?? 0);
    },

    formatQueryEvidence(value) {
      if (value === null || value === undefined || value === '') return 'No evidence was captured.';
      if (typeof value === 'string') return value;

      return JSON.stringify(value, null, 2);
    },

    formatQueryType(type) {
      const value = typeof type === 'string' && type !== '' ? type : 'query';

      return value.charAt(0).toUpperCase() + value.slice(1);
    },

    highlightQueryCode(code) {
      if (typeof code?.textContent === 'string') code.textContent = code.textContent;
      code?.removeAttribute?.('data-highlighted');
      browser.highlight?.();
    },
  };
}
