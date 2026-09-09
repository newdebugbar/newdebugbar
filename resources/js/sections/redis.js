import { filterList } from './list.js';
import { readSectionPayload } from '../runtime.js';

/** Owns redis inspector state and interactions. */
export function createRedis(context) {
  const { browser, shell } = context;
  const summary = shell.summary;
  return {
    refresh() {
      const payload = readSectionPayload(this.$root, '[data-ndb-redis-payload]');
      if (payload !== null) this.initializeRedis(payload);
    },

    redisCommands: [],
    redisFilter: 'all',
    redisSearch: '',
    redisSelected: null,
    redisDetailOpen: false,
    visibleRedisCount: summary.section_counts?.redis ?? 0,

    get selectedRedisCommand() {
      return this.redisCommands.find((command) => command.execution === this.redisSelected) ?? null;
    },

    initializeRedis(commands) {
      this.redisCommands = Array.isArray(commands) ? commands : [];
      if (this.initialized) {
        this.$nextTick?.(() => this.applyRedisView());
        return;
      }
      this.initialized = true;
      this.redisFilter = 'all';
      this.redisSearch = '';
      this.redisSelected = this.redisCommands[0]?.execution ?? null;
      this.redisDetailOpen = false;
      this.$nextTick?.(() => this.applyRedisView());
    },

    setRedisFilter(filter) {
      if (!['all', 'failed'].includes(filter)) return;

      this.redisFilter = filter;
      this.applyRedisView();
    },

    selectRedisCommand(execution) {
      if (!this.redisCommands.some((command) => command.execution === execution)) return;

      this.redisSelected = execution;
      this.redisDetailOpen = true;
      this.resetRedisDetail(true);
    },

    closeRedisDetail() {
      const selected = this.redisSelected;

      if (!this.redisDetailOpen) return;

      this.redisDetailOpen = false;
      this.$nextTick?.(() => {
        const row = [...(this.$refs?.redisList?.children ?? [])].find(
          (item) => Number(item.dataset.ndbRedisExecution) === selected,
        );
        row?.focus?.({ preventScroll: true });
      });
    },

    resetRedisDetail(focus = false) {
      this.$nextTick?.(() => {
        this.$refs?.content?.scrollTo?.({ top: 0, behavior: 'instant' });
        this.$refs?.redisDetail?.scrollTo?.({ top: 0, behavior: 'instant' });
        if (focus) this.$refs?.redisDetail?.focus?.({ preventScroll: true });
        browser.highlight?.();
      });
    },

    applyRedisView() {
      const list = this.$refs?.redisList;
      const search = this.redisSearch.toLowerCase().trim();
      const commands = new Map(this.redisCommands.map((command) => [command.execution, command]));

      const { visible, firstVisible, selectedVisible } = filterList(list?.children ?? [], {
        selected: this.redisSelected,
        key: (item) => Number(item.dataset.ndbRedisExecution),
        matches: (item) => {
          const command = commands.get(Number(item.dataset.ndbRedisExecution));
          const matchesFilter =
            this.redisFilter === 'all' || (this.redisFilter === 'failed' && item.dataset.ndbRedisFailed === 'true');
          return command !== undefined && matchesFilter && (search === '' || command.search.includes(search));
        },
      });

      this.visibleRedisCount = visible;

      if (!selectedVisible) {
        this.redisSelected = firstVisible;
        if (firstVisible === null) this.redisDetailOpen = false;
      }
    },
  };
}
