import { filterList } from './list.js';
import { readSectionPayload } from '../runtime.js';

/** Owns queue inspector state and interactions. */
export function createQueue(context) {
  const { browser, shell } = context;
  const summary = shell.summary;
  return {
    refresh() {
      const payload = readSectionPayload(this.$root, '[data-ndb-queue-payload]');
      if (payload !== null) this.initializeQueue(payload);
    },

    queueActivities: [],
    queueFilter: 'all',
    queueSearch: '',
    queueSelected: null,
    queueDetailOpen: false,
    visibleQueueCount: summary.section_counts?.queue ?? 0,

    get selectedQueueActivity() {
      return this.queueActivities.find((activity) => activity.execution === this.queueSelected) ?? null;
    },

    initializeQueue(activities) {
      this.queueActivities = Array.isArray(activities) ? activities : [];
      if (
        this.queueFilter !== 'all' &&
        !this.queueActivities.some((activity) => activity.status_group === this.queueFilter)
      )
        this.queueFilter = 'all';
      if (this.initialized) {
        this.$nextTick?.(() => this.applyQueueView());
        return;
      }
      this.initialized = true;
      this.queueFilter = 'all';
      this.queueSearch = '';
      this.queueSelected = this.queueActivities[0]?.execution ?? null;
      this.queueDetailOpen = false;
      this.$nextTick?.(() => this.applyQueueView());
    },

    setQueueFilter(filter) {
      if (!['all', 'waiting', 'failed', 'completed'].includes(filter)) return;

      this.queueFilter = filter;
      this.applyQueueView();
    },

    selectQueueActivity(execution) {
      if (!this.queueActivities.some((activity) => activity.execution === execution)) return;

      this.queueSelected = execution;
      this.queueDetailOpen = true;
      this.resetQueueDetail(true);
    },

    closeQueueDetail() {
      const selected = this.queueSelected;

      if (!this.queueDetailOpen) return;

      this.queueDetailOpen = false;
      this.$nextTick?.(() => {
        const row = [...(this.$refs?.queueList?.children ?? [])].find(
          (item) => Number(item.dataset.ndbQueueExecution) === selected,
        );
        row?.focus?.({ preventScroll: true });
      });
    },

    resetQueueDetail(focus = false) {
      this.$nextTick?.(() => {
        this.$refs?.content?.scrollTo?.({ top: 0, behavior: 'instant' });
        this.$refs?.queueDetail?.scrollTo?.({ top: 0, behavior: 'instant' });
        if (focus) this.$refs?.queueDetail?.focus?.({ preventScroll: true });
        browser.highlight?.();
      });
    },

    applyQueueView() {
      const list = this.$refs?.queueList;
      const search = this.queueSearch.toLowerCase().trim();
      const activities = new Map(this.queueActivities.map((activity) => [activity.execution, activity]));

      const { visible, firstVisible, selectedVisible } = filterList(list?.children ?? [], {
        selected: this.queueSelected,
        key: (item) => Number(item.dataset.ndbQueueExecution),
        matches: (item) => {
          const activity = activities.get(Number(item.dataset.ndbQueueExecution));
          const matchesFilter = this.queueFilter === 'all' || item.dataset.ndbQueueGroup === this.queueFilter;
          return activity !== undefined && matchesFilter && (search === '' || activity.search.includes(search));
        },
      });

      this.visibleQueueCount = visible;

      if (!selectedVisible) {
        this.queueSelected = firstVisible;
        if (firstVisible === null) this.queueDetailOpen = false;
      }
    },
  };
}
