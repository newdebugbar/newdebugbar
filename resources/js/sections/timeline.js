/** Owns timeline inspector state and interactions. */
export function createTimeline(context) {
  const { browser, shell, profileId } = context;
  return {
    refresh() {
      this.initialized = true;
      this.syncTimelineSelection();
    },
    activate() {
      this.$nextTick?.(() => {
        const sentinel = this.$root?.querySelector?.('[data-ndb-timeline-page-sentinel]');
        if (sentinel) this.observeTimelinePageEnd(sentinel);
      });
    },
    deactivate() {
      this.resetTimelinePagination();
    },

    timelineFilter: 'key',
    timelineSearch: '',
    timelineSelected: null,
    timelineDetailOpen: false,
    timelineLoadingMore: false,
    timelineFiltering: false,
    timelineFilterError: false,
    timelinePaginationError: false,
    timelinePaginationCleanup: null,
    timelinePaginationRequest: 0,

    get selectedTimelineItem() {
      return this.timelineSelected;
    },

    stopTimelinePagination() {
      this.timelinePaginationCleanup?.();
      this.timelinePaginationCleanup = null;
    },

    resetTimelinePagination() {
      this.stopTimelinePagination();
      this.timelinePaginationRequest += 1;
      this.timelineLoadingMore = false;
      this.timelineFiltering = false;
      this.timelineFilterError = false;
      this.timelinePaginationError = false;
    },

    observeTimelinePageEnd(element, wire = this.$wire) {
      this.stopTimelinePagination();
      if (
        this.destroyed ||
        profileId !== shell.summary.id ||
        !shell.inspectorOpen ||
        !shell.barVisible ||
        shell.selected !== 'timeline'
      )
        return;

      const scrollOwner = this.$refs?.timelineList;
      if (!element?.isConnected || !scrollOwner) return;

      this.timelinePaginationCleanup =
        browser.observeNearEnd?.(element, scrollOwner, () => this.loadNextTimelinePage(wire)) ?? null;
    },

    loadNextTimelinePage(wire = this.$wire) {
      if (
        this.timelineLoadingMore ||
        this.timelinePaginationError ||
        this.timelineFiltering ||
        this.destroyed ||
        profileId !== shell.summary.id ||
        !shell.inspectorOpen ||
        !shell.barVisible ||
        shell.selected !== 'timeline'
      ) {
        return Promise.resolve(false);
      }

      const action = wire?.loadMoreTimeline;

      if (typeof action !== 'function') {
        this.timelinePaginationError = true;

        return Promise.resolve(false);
      }

      const request = ++this.timelinePaginationRequest;
      let loaded = false;
      this.timelineLoadingMore = true;
      this.timelinePaginationError = false;

      return Promise.resolve(action.call(wire))
        .then(() => {
          if (request !== this.timelinePaginationRequest || profileId !== shell.summary.id) return false;

          loaded = true;

          return true;
        })
        .catch(() => {
          if (request === this.timelinePaginationRequest && profileId === shell.summary.id) {
            this.timelinePaginationError = true;
          }

          return false;
        })
        .finally(() => {
          if (request !== this.timelinePaginationRequest || profileId !== shell.summary.id) return;

          this.timelineLoadingMore = false;
          if (!loaded) return;

          this.stopTimelinePagination();
          this.$nextTick?.(() => {
            const sentinel = this.$root?.querySelector?.('[data-ndb-timeline-page-sentinel]');
            if (sentinel) this.observeTimelinePageEnd(sentinel, wire);
          });
        });
    },

    retryTimelinePage(wire = this.$wire) {
      this.timelinePaginationError = false;

      return this.loadNextTimelinePage(wire);
    },

    setTimelineFilter(filter) {
      if (!shell.sectionKeys.includes(filter) && !['all', 'key'].includes(filter)) return;

      this.timelineFilter = filter;
      return this.applyTimelineFilters();
    },

    async applyTimelineFilters(wire = this.$wire) {
      if (
        this.destroyed ||
        profileId !== shell.summary.id ||
        !shell.inspectorOpen ||
        shell.selected !== 'timeline'
      )
        return;
      if (typeof wire?.filterTimeline !== 'function') return;

      this.resetTimelinePagination();
      const request = this.timelinePaginationRequest;
      this.timelineFiltering = true;
      this.timelineFilterError = false;
      try {
        await wire.filterTimeline(this.timelineFilter, this.timelineSearch);
      } catch {
        if (request === this.timelinePaginationRequest && profileId === shell.summary.id)
          this.timelineFilterError = true;
      } finally {
        if (request === this.timelinePaginationRequest && profileId === shell.summary.id) {
          this.timelineFiltering = false;
          this.$nextTick?.(() => {
            this.syncTimelineSelection();
            this.$refs?.timelineList?.scrollTo?.({
              top: 0,
              behavior: 'instant',
            });
            const sentinel = this.$root?.querySelector?.('[data-ndb-timeline-page-sentinel]');
            if (sentinel) this.observeTimelinePageEnd(sentinel, wire);
          });
        }
      }
    },

    syncTimelineSelection() {
      const list = this.$refs?.timelineList;
      if (!list?.querySelectorAll) return;

      if (
        this.timelineSelected &&
        ![...list.querySelectorAll('[data-ndb-timeline-item]:not([hidden])')].some(
          (item) => item.dataset.ndbTimelineItem === this.timelineSelected.id,
        )
      ) {
        this.timelineSelected = null;
        this.timelineDetailOpen = false;
      }
    },

    selectTimelineItem(id) {
      const items = this.$refs?.timelineList?.querySelectorAll?.('[data-ndb-timeline-item]') ?? [];
      const item = [...items].find((candidate) => candidate.dataset.ndbTimelineItem === id);

      if (!item || item.hidden) return;

      this.timelineSelected = {
        id,
        section: item.dataset.ndbTimelineSection,
        sectionLabel: item.dataset.ndbTimelineSectionLabel,
        kind: item.dataset.ndbTimelineKind,
        label: item.dataset.ndbTimelineLabel,
        atMs: Number(item.dataset.ndbTimelineAt),
        atLabel: item.dataset.ndbTimelineAtLabel,
        startMs: item.dataset.ndbTimelineStart === '' ? null : Number(item.dataset.ndbTimelineStart),
        startLabel: item.dataset.ndbTimelineStartLabel || null,
        durationMs: item.dataset.ndbTimelineDuration === '' ? null : Number(item.dataset.ndbTimelineDuration),
        durationLabel: item.dataset.ndbTimelineDurationLabel || null,
        source: item.dataset.ndbTimelineSource || null,
      };
      this.timelineDetailOpen = true;
      this.$nextTick?.(() => {
        if (this.$refs?.content) this.$refs.content.scrollTop = 0;
        this.$refs?.timelineDetail?.focus?.({ preventScroll: true });
      });
    },

    closeTimelineDetail() {
      const id = this.timelineSelected?.id;
      this.timelineDetailOpen = false;
      this.$nextTick?.(() => {
        const focus = () => {
          const items = this.$refs?.timelineList?.querySelectorAll?.('[data-ndb-timeline-item]') ?? [];
          [...items].find((item) => item.dataset.ndbTimelineItem === id)?.focus?.({ preventScroll: true });
        };

        browser.afterPaint ? browser.afterPaint(focus) : focus();
      });
    },
  };
}
