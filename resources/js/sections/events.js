import { readSectionPayload } from '../runtime.js';
import { formatDuration } from '../duration.js';

/** Owns events inspector state and interactions. */
export function createEvents(context) {
  const { browser, shell, profileId } = context;
  const summary = shell.summary;
  const instance = Symbol();
  return {
    profileId,
    initialized: false,
    destroyed: false,
    init() {
      shell.mountSection('events', profileId, this, instance);
    },
    destroy() {
      this.destroyed = true;
      this.deactivate?.();
      shell.unmountSection(instance);
    },
    refresh() {
      if (this.destroyed || profileId !== shell.summary.id) return;
      const payload = readSectionPayload(this.$root, '[data-ndb-event-payload]');
      if (payload !== null) this.initializeEvents(payload);
    },

    eventGroups: [],
    eventSource: 'all',
    eventSearch: '',
    eventSelected: null,
    eventDetailOpen: false,
    eventDetailTab: 'overview',
    eventDetailReturnFocus: null,
    visibleEventCount: summary.section_counts?.events ?? 0,
    visibleEventGroupCount: 0,

    get selectedEvent() {
      return this.eventGroups.find((event) => event.id === this.eventSelected) ?? null;
    },

    get visibleEventSummary() {
      if (this.visibleEventGroupCount === 0) return 'No events';

      const events = `${this.visibleEventGroupCount} ${this.visibleEventGroupCount === 1 ? 'event' : 'events'}`;

      if (this.visibleEventCount === this.visibleEventGroupCount) return events;

      const dispatches = `${this.visibleEventCount} dispatches`;

      return `${events}, ${dispatches}`;
    },

    initializeEvents(groups) {
      this.eventGroups = Array.isArray(groups) ? groups : [];
      if (this.initialized) {
        this.$nextTick?.(() => this.applyEventFilters());
        return;
      }
      this.initialized = true;
      const firstApplicationEvent = this.eventGroups.find((event) => event.source === 'application');
      this.eventSource = firstApplicationEvent ? 'application' : 'all';
      this.eventSearch = '';
      this.eventDetailOpen = false;
      this.eventDetailTab = 'overview';
      this.eventDetailReturnFocus = null;
      this.eventSelected = firstApplicationEvent?.id ?? this.eventGroups[0]?.id ?? null;
      this.$nextTick?.(() => this.applyEventFilters());
    },

    setEventSource(source) {
      if (!['all', 'application', 'framework'].includes(source)) return;

      this.eventSource = source;
      this.eventDetailOpen = false;
      this.eventDetailTab = 'overview';
      this.eventDetailReturnFocus = null;
      this.eventSelected = null;
      this.applyEventFilters();
    },

    selectEvent(id, returnFocus = null) {
      if (!this.eventGroups.some((event) => event.id === id)) return;

      this.eventSelected = id;
      this.eventDetailOpen = true;
      this.eventDetailTab = 'overview';
      this.eventDetailReturnFocus = returnFocus;
      this.$nextTick?.(() => {
        if (browser.viewportWidth?.() < 1024) this.$refs?.eventDetail?.focus?.();
      });
    },

    closeEventDetail() {
      const returnFocus = this.eventDetailReturnFocus;
      this.eventDetailOpen = false;
      this.eventDetailReturnFocus = null;
      this.$nextTick?.(() => returnFocus?.isConnected && returnFocus.focus?.());
    },

    setEventDetailTab(tab) {
      if (!['overview', 'payload', 'source'].includes(tab)) return;

      this.eventDetailTab = tab;
      this.$nextTick?.(() => this.$refs?.eventDetail?.scrollTo?.({ top: 0, behavior: 'instant' }));
    },

    applyEventFilters() {
      const list = this.$refs?.eventList ?? this.$root?.querySelector?.('[x-ref="eventList"]');
      const search = this.eventSearch.toLowerCase().trim();
      let visibleEvents = 0;
      let visibleGroups = 0;
      let firstVisible = null;
      let selectedVisible = false;

      [...(list?.children ?? [])].forEach((item) => {
        const matches =
          (this.eventSource === 'all' || item.dataset.ndbEventSourceValue === this.eventSource) &&
          (search === '' || item.dataset.ndbEventSearchValue?.includes(search));
        item.hidden = !matches;

        if (matches) {
          item.style.removeProperty('display');
          const id = Number(item.dataset.ndbEventId);
          firstVisible ??= id;
          selectedVisible ||= id === this.eventSelected;
          visibleEvents += Number(item.dataset.ndbEventOccurrenceCount ?? 0);
          visibleGroups++;
        } else {
          item.style.setProperty('display', 'none', 'important');
        }
      });

      this.visibleEventCount = visibleEvents;
      this.visibleEventGroupCount = visibleGroups;

      if (!selectedVisible) {
        if (this.eventSelected !== firstVisible) this.eventDetailTab = 'overview';
        this.eventSelected = firstVisible;
      }
    },

    formatEventTime(value) {
      return formatDuration(value);
    },
  };
}
