import { readSectionPayload } from '../runtime.js';

/** Owns http-client inspector state and interactions. */
export function createHttpClient(context) {
  const { browser, shell, profileId } = context;
  const summary = shell.summary;
  const instance = Symbol();
  return {
    profileId,
    initialized: false,
    destroyed: false,
    init() {
      shell.mountSection('http_client', profileId, this, instance);
    },
    destroy() {
      this.destroyed = true;
      this.deactivate?.();
      shell.unmountSection(instance);
    },
    refresh() {
      if (this.destroyed || profileId !== shell.summary.id) return;
      const payload = readSectionPayload(this.$root, '[data-ndb-http-client-payload]');
      if (payload !== null) this.initializeHttpClient(payload);
    },

    httpClientRequests: [],
    httpClientFilter: 'all',
    httpClientSearch: '',
    httpClientSort: 'execution',
    httpClientSortDirection: 'asc',
    httpClientSelected: null,
    httpClientDetailOpen: false,
    httpClientDetailTab: 'response',
    visibleHttpClientCount: summary.section_counts?.http_client ?? 0,

    get selectedHttpClientRequest() {
      return this.httpClientRequests.find((request) => request.execution === this.httpClientSelected) ?? null;
    },

    initializeHttpClient(requests) {
      this.httpClientRequests = Array.isArray(requests) ? requests : [];
      if (this.httpClientRequests.length === 0) {
        this.httpClientSelected = null;
        this.httpClientDetailOpen = false;
        this.visibleHttpClientCount = 0;
      }
      if (this.initialized) {
        this.$nextTick?.(() => this.applyHttpClientView());
        return;
      }
      this.initialized = true;
      this.httpClientFilter = 'all';
      this.httpClientSearch = '';
      this.httpClientSort = 'execution';
      this.httpClientSortDirection = 'asc';
      this.httpClientDetailOpen = false;
      this.httpClientDetailTab = 'response';
      this.httpClientSelected = this.httpClientRequests[0]?.execution ?? null;
      this.$nextTick?.(() => this.applyHttpClientView());
    },

    setHttpClientFilter(filter) {
      if (!['all', 'failed', 'slow'].includes(filter)) return;

      this.httpClientFilter = filter;
      this.applyHttpClientView();
    },

    toggleHttpClientSort(sort) {
      if (sort !== 'duration') return;

      if (this.httpClientSort !== sort) {
        this.httpClientSort = sort;
        this.httpClientSortDirection = 'desc';
      } else if (this.httpClientSortDirection === 'desc') {
        this.httpClientSortDirection = 'asc';
      } else {
        this.httpClientSort = 'execution';
        this.httpClientSortDirection = 'asc';
      }

      this.applyHttpClientView();
    },

    selectHttpClientRequest(execution) {
      if (!this.httpClientRequests.some((request) => request.execution === execution)) return;

      this.httpClientSelected = execution;
      this.httpClientDetailOpen = true;
      this.httpClientDetailTab = 'response';
      this.$nextTick?.(() => browser.highlight?.());
    },

    setHttpClientDetailTab(tab) {
      if (!['response', 'request'].includes(tab)) return;

      this.httpClientDetailTab = tab;
      this.$nextTick?.(() => {
        this.$refs?.httpClientDetail?.scrollTo?.({
          top: 0,
          behavior: 'instant',
        });
        browser.highlight?.();
      });
    },

    applyHttpClientView() {
      if (this.httpClientRequests.length === 0) {
        this.httpClientSelected = null;
        this.httpClientDetailOpen = false;
        this.visibleHttpClientCount = 0;
        return;
      }
      const list = this.$refs?.httpClientList;
      const search = this.httpClientSearch.toLowerCase().trim();
      const items = [...(list?.querySelectorAll?.('[data-ndb-http-client-item]') ?? [])];
      let visible = 0;
      let firstVisible = null;
      let selectedVisible = false;

      items
        .sort((left, right) => this.compareHttpClientRequests(left, right))
        .forEach((item) => {
          const matchesFilter =
            this.httpClientFilter === 'all' ||
            (this.httpClientFilter === 'failed' && item.dataset.ndbFailed === 'true') ||
            (this.httpClientFilter === 'slow' && item.dataset.ndbSlow === 'true');
          const matches = matchesFilter && (search === '' || item.dataset.ndbSearch?.includes(search));
          item.hidden = !matches;
          if (matches) {
            item.style.removeProperty('display');
          } else {
            item.style.setProperty('display', 'none', 'important');
          }

          if (matches) {
            const execution = Number(item.dataset.ndbExecution);
            firstVisible ??= execution;
            selectedVisible ||= execution === this.httpClientSelected;
            visible++;
          }

          list?.appendChild?.(item);
        });

      this.visibleHttpClientCount = visible;

      if (!selectedVisible) {
        this.httpClientSelected = firstVisible;
        this.httpClientDetailTab = 'response';
      }
    },

    compareHttpClientRequests(left, right) {
      const executionComparison = Number(left.dataset.ndbExecution ?? 0) - Number(right.dataset.ndbExecution ?? 0);
      let comparison = 0;

      if (this.httpClientSort === 'duration') {
        const leftDuration = Number(left.dataset.ndbDuration ?? -1);
        const rightDuration = Number(right.dataset.ndbDuration ?? -1);

        if (leftDuration < 0 || rightDuration < 0) {
          if (leftDuration < 0 && rightDuration >= 0) return 1;
          if (rightDuration < 0 && leftDuration >= 0) return -1;
        }

        comparison = leftDuration - rightDuration;
      } else {
        return executionComparison;
      }

      const directedComparison = this.httpClientSortDirection === 'asc' ? comparison : -comparison;

      return directedComparison || executionComparison;
    },

    formatHttpClientEvidence(value) {
      if (value === null || value === undefined || value === '') return '—';
      if (typeof value === 'string') return value;

      return JSON.stringify(value, null, 2);
    },
  };
}
