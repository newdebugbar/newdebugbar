import { filterList } from './list.js';
import { readSectionPayload } from '../runtime.js';

/** Owns cache inspector state and interactions. */
export function createCache(context) {
  const { browser, shell } = context;
  const summary = shell.summary;
  return {
    refresh() {
      const payload = readSectionPayload(this.$root, '[data-ndb-cache-payload]');
      if (payload !== null) this.initializeCache(payload);
    },

    cacheOperations: [],
    cacheFilter: 'all',
    cacheSearch: '',
    cacheSelected: null,
    cacheDetailOpen: false,
    visibleCacheCount: summary.section_counts?.cache ?? 0,

    get selectedCacheOperation() {
      return this.cacheOperations.find((operation) => operation.execution === this.cacheSelected) ?? null;
    },

    initializeCache(operations) {
      this.cacheOperations = Array.isArray(operations) ? operations : [];
      if (this.initialized) {
        this.$nextTick?.(() => this.applyCacheView());
        return;
      }
      this.initialized = true;
      this.cacheFilter = 'all';
      this.cacheSearch = '';
      this.cacheDetailOpen = false;
      this.cacheSelected = this.cacheOperations[0]?.execution ?? null;

      if (this.cacheOperations.length === 0) {
        this.visibleCacheCount = 0;

        return;
      }

      this.$nextTick?.(() => this.applyCacheView());
    },

    setCacheFilter(filter) {
      if (!['all', 'reads', 'writes', 'deletes', 'failed'].includes(filter)) return;

      this.cacheFilter = filter;
      this.applyCacheView();
    },

    selectCacheOperation(execution) {
      if (!this.cacheOperations.some((operation) => operation.execution === execution)) return;

      this.cacheSelected = execution;
      this.cacheDetailOpen = true;
      this.resetCacheDetailScroll();
    },

    resetCacheDetailScroll() {
      this.$nextTick?.(() => {
        this.$refs?.content?.scrollTo?.({ top: 0, behavior: 'instant' });
        this.$refs?.cacheDetail?.scrollTo?.({ top: 0, behavior: 'instant' });
        browser.highlight?.();
      });
    },

    applyCacheView() {
      if (this.cacheOperations.length === 0) {
        this.cacheSelected = null;
        this.cacheDetailOpen = false;
        this.visibleCacheCount = 0;
        return;
      }
      const list = this.$refs?.cacheList;
      const search = this.cacheSearch.toLowerCase().trim();

      const { visible, firstVisible, selectedVisible } = filterList(list?.children ?? [], {
        selected: this.cacheSelected,
        key: (item) => Number(item.dataset.ndbCacheExecution),
        matches: (item) => {
          const matchesFilter =
            this.cacheFilter === 'all' ||
            (this.cacheFilter === 'reads' && item.dataset.ndbCacheCategory === 'read') ||
            (this.cacheFilter === 'writes' && item.dataset.ndbCacheCategory === 'write') ||
            (this.cacheFilter === 'deletes' && item.dataset.ndbCacheCategory === 'delete') ||
            (this.cacheFilter === 'failed' && item.dataset.ndbCacheFailed === 'true');
          return matchesFilter && (search === '' || item.dataset.ndbCacheSearchText?.includes(search));
        },
      });

      this.visibleCacheCount = visible;

      if (!selectedVisible) {
        this.cacheSelected = firstVisible;
      }
    },
  };
}
