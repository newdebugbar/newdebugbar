import assert from 'node:assert/strict';
import test from 'node:test';

import { runtime, summary, inspectorHarness } from './state-test-support.js';

test('event controls group, filter, and select useful event evidence', () => {
  const browser = runtime();
  const { state, shell } = inspectorHarness('events', summary, browser);
  let detailFocuses = 0;
  let detailScrolls = 0;
  let returnFocuses = 0;
  const item = (id, source, search, occurrences) => ({
    dataset: {
      ndbEventId: String(id),
      ndbEventSourceValue: source,
      ndbEventSearchValue: search,
      ndbEventOccurrenceCount: String(occurrences),
    },
    hidden: false,
    style: {
      display: '',
      removeProperty(property) {
        if (property === 'display') this.display = '';
      },
      setProperty(property, value) {
        if (property === 'display') this.display = value;
      },
    },
  });
  const framework = item(1, 'framework', 'illuminate auth login', 14);
  const application = item(2, 'application', 'clinic ready listener payload source', 2);
  const laterApplication = item(3, 'application', 'trip refreshed listener', 1);
  laterApplication.isConnected = true;
  laterApplication.focus = () => returnFocuses++;
  state.$refs = {
    eventList: {
      children: [framework, application, laterApplication],
    },
    eventDetail: {
      focus: () => detailFocuses++,
      scrollTo: () => detailScrolls++,
    },
  };
  state.$nextTick = (callback) => callback();

  state.initializeEvents([
    { id: 1, source: 'framework', name: 'Illuminate\\Auth\\Events\\Login' },
    { id: 2, source: 'application', name: 'App\\Events\\ClinicReady' },
    { id: 3, source: 'application', name: 'App\\Events\\TripRefreshed' },
  ]);
  assert.equal(state.eventSource, 'application');
  assert.equal(state.eventSelected, 2);
  assert.equal(state.selectedEvent?.id, 2);
  state.eventSelected = 99;
  assert.equal(state.selectedEvent, null);
  state.eventSelected = 2;
  assert.equal(state.eventDetailTab, 'overview');
  assert.equal(framework.hidden, true);
  assert.equal(framework.style.display, 'none');
  assert.equal(application.hidden, false);
  assert.equal(laterApplication.hidden, false);
  assert.equal(state.visibleEventCount, 3);
  assert.equal(state.visibleEventGroupCount, 2);
  assert.equal(state.visibleEventSummary, '2 events, 3 dispatches');

  state.visibleEventCount = 2;
  state.visibleEventGroupCount = 2;
  assert.equal(state.visibleEventSummary, '2 events');
  state.applyEventFilters();

  state.eventSearch = 'PAYLOAD';
  state.applyEventFilters();
  assert.equal(application.hidden, false);
  assert.equal(laterApplication.hidden, true);
  assert.equal(state.visibleEventCount, 2);
  assert.equal(state.visibleEventGroupCount, 1);
  assert.equal(state.visibleEventSummary, '1 event, 2 dispatches');

  state.eventSearch = '';
  state.setEventDetailTab('payload');
  state.setEventSource('framework');
  assert.equal(state.eventSource, 'framework');
  assert.equal(state.eventDetailTab, 'overview');
  assert.equal(framework.hidden, false);
  assert.equal(application.hidden, true);
  assert.equal(state.eventSelected, 1);
  assert.equal(state.visibleEventCount, 14);

  state.setEventSource('all');
  assert.equal(state.eventSelected, 1);
  assert.equal(state.eventDetailTab, 'overview');

  state.selectEvent(3, laterApplication);
  assert.equal(state.eventSelected, 3);
  assert.equal(state.eventDetailOpen, true);
  assert.equal(state.eventDetailTab, 'overview');
  state.setEventDetailTab('payload');
  assert.equal(state.eventDetailTab, 'payload');
  assert.equal(detailScrolls, 2);
  state.closeEventDetail();
  assert.equal(state.eventDetailOpen, false);
  assert.equal(returnFocuses, 1);

  browser.viewportWidth = () => 390;
  state.selectEvent(3, laterApplication);
  assert.equal(detailFocuses, 1);
  state.closeEventDetail();
  assert.equal(returnFocuses, 2);
  state.closeEventDetail();

  state.setEventSource('invalid');
  state.setEventDetailTab('invalid');
  state.selectEvent(99);
  assert.equal(state.eventSource, 'all');
  assert.equal(state.eventSelected, 3);
  assert.equal(state.eventDetailTab, 'overview');
  assert.equal(state.formatEventTime(null), '—');
  assert.equal(state.formatEventTime(''), '—');
  assert.equal(state.formatEventTime('missing'), '—');
  assert.equal(state.formatEventTime(12.3), '12.3 ms');

  state.$refs = {};
  state.applyEventFilters();
  assert.equal(state.visibleEventCount, 0);
  assert.equal(state.visibleEventGroupCount, 0);
  assert.equal(state.visibleEventSummary, 'No events');

  const { state: emptyState } = inspectorHarness('events', summary, runtime());
  emptyState.initializeEvents(null);
  assert.deepEqual(emptyState.eventGroups, []);
  assert.equal(emptyState.eventSource, 'all');
  assert.equal(emptyState.eventSelected, null);

  emptyState.$nextTick = (callback) => callback();
  const frameworkOnly = item(4, 'framework', 'illuminate events dispatcher', 1);
  emptyState.$refs = { eventList: { children: [frameworkOnly] } };
  emptyState.initializeEvents([{ id: 4, source: 'framework', name: 'Illuminate\\Events\\Dispatcher' }]);
  assert.equal(emptyState.eventSource, 'all');
  assert.equal(emptyState.eventSelected, 4);
  assert.equal(frameworkOnly.hidden, false);
  assert.equal(emptyState.visibleEventCount, 1);
  assert.equal(emptyState.visibleEventGroupCount, 1);
});
