/** Owns logs inspector state and interactions. */
export function createLogs(context) {
  const { browser, shell } = context;
  const summary = shell.summary;
  return {
    refresh() {
      this.initializeLogs();
    },

    logLevel: 'all',
    logChannel: 'all',
    logSearch: '',
    logDetailSequence: null,
    logDetailOpen: false,
    visibleLogCount: summary.inspector_counts?.logs ?? 0,
    visibleLogGroupCount: summary.inspector_counts?.logs ?? 0,

    initializeLogs() {
      if (this.initialized) {
        this.$nextTick?.(() => this.applyLogFilters());
        return;
      }
      this.initialized = true;
      this.logLevel = 'all';
      this.logChannel = 'all';
      this.logSearch = '';
      this.logDetailSequence = null;
      this.logDetailOpen = false;
      this.$nextTick?.(() => this.applyLogFilters());
    },

    selectLogEntry(sequence) {
      const list = this.$refs?.logList ?? this.$root?.querySelector?.('[x-ref="logList"]');
      const entry = [...(list?.children ?? [])].find(
        (item) => Number(item.dataset.ndbLogFirstSequence) === Number(sequence),
      );
      if (!entry || entry.hidden) return;

      this.logDetailSequence = Number(sequence);
      this.logDetailOpen = true;
      this.$nextTick?.(() => {
        this.$refs?.logDetail?.scrollTo?.({ top: 0, behavior: 'instant' });
        if ((browser.viewportWidth?.() ?? 1024) < 1024)
          this.$refs?.logDetail?.focus?.({ preventScroll: true });
        browser.highlight?.();
      });
    },

    closeLogDetail() {
      const sequence = this.logDetailSequence;
      this.logDetailOpen = false;
      this.$nextTick?.(() => {
        this.$root
          ?.querySelector?.(`[data-ndb-log-entry][data-ndb-log-first-sequence="${sequence}"]`)
          ?.focus?.();
      });
    },

    setLogLevel(level) {
      const list = this.$refs?.logList ?? this.$root?.querySelector?.('[x-ref="logList"]');
      const available = [...(list?.children ?? [])].map((item) => item.dataset.ndbLogLevel);
      if (!['all', 'attention'].includes(level) && !available.includes(level)) return;

      this.logLevel = level;
      this.applyLogFilters();
    },

    setLogChannel(channel) {
      const list = this.$refs?.logList ?? this.$root?.querySelector?.('[x-ref="logList"]');
      const available = [...(list?.children ?? [])].map((item) => item.dataset.ndbLogChannel);
      if (channel !== 'all' && !available.includes(channel)) return;

      this.logChannel = channel;
      this.applyLogFilters();
    },

    applyLogFilters() {
      const list = this.$refs?.logList ?? this.$root?.querySelector?.('[x-ref="logList"]');
      const search = this.logSearch.toLowerCase().trim();
      let visibleRecords = 0;
      let visibleGroups = 0;
      let selectedVisible = false;

      [...(list?.children ?? [])].forEach((item) => {
        const matchesLevel =
          this.logLevel === 'all' ||
          item.dataset.ndbLogLevel === this.logLevel ||
          (this.logLevel === 'attention' && item.dataset.ndbLogAttention === 'true');
        const matches =
          matchesLevel &&
          (this.logChannel === 'all' || item.dataset.ndbLogChannel === this.logChannel) &&
          (search === '' || item.dataset.ndbLogSearchText?.includes(search));
        item.hidden = !matches;
        if (matches) {
          item.style?.removeProperty?.('display');
          visibleRecords += Math.max(1, Number(item.dataset.ndbLogRecordCount) || 1);
          visibleGroups++;
          selectedVisible ||= Number(item.dataset.ndbLogFirstSequence) === this.logDetailSequence;
        } else {
          item.style?.setProperty?.('display', 'none', 'important');
        }
      });

      this.visibleLogCount = visibleRecords;
      this.visibleLogGroupCount = visibleGroups;

      if (this.logDetailSequence !== null && !selectedVisible) {
        this.logDetailSequence = null;
        this.logDetailOpen = false;
      }
    },
  };
}
