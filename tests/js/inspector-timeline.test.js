import assert from 'node:assert/strict';
import test from 'node:test';

import { runtime, summary, inspectorHarness } from './state-test-support.js';

test('timeline filters request the full capture and preserve detail navigation', async () => {
  const browser = runtime();
  const { state, shell } = inspectorHarness(
    'timeline',
    {
      ...summary,
      inspectors: [
        ...summary.inspectors,
        { key: 'timeline', label: 'Timeline' },
        { key: 'events', label: 'Events' },
      ],
    },
    browser,
  );
  let rowFocuses = 0;
  let detailFocuses = 0;
  const item = (id, inspector, search, key = false) => ({
    dataset: {
      ndbTimelineItem: id,
      ndbTimelineInspector: inspector,
      ndbTimelineInspectorLabel: inspector === 'queries' ? 'Queries' : 'Events',
      ndbTimelineKind: inspector === 'queries' ? 'Duration' : 'Event',
      ndbTimelineLabel: search,
      ndbTimelineAt: '12.5',
      ndbTimelineAtLabel: '12.5 ms',
      ndbTimelineStart: inspector === 'queries' ? '10' : '',
      ndbTimelineStartLabel: inspector === 'queries' ? '10 ms' : '',
      ndbTimelineDuration: inspector === 'queries' ? '2.5' : '',
      ndbTimelineDurationLabel: inspector === 'queries' ? '2.5 ms' : '',
      ndbTimelineSource: inspector === 'queries' ? 'app/Trips/LoadTrip.php:24' : '',
      ndbTimelineSearchValue: search,
      ndbTimelineKey: String(key),
    },
    hidden: false,
    focus: () => rowFocuses++,
  });
  const query = item('queries-0', 'queries', 'select users', true);
  const event = item('events-0', 'events', 'clinic ready');
  let rows = [query, event];
  const requests = [];
  state.$wire = { filterTimeline: async (...args) => requests.push(args) };
  state.$refs = {
    timelineList: {
      querySelectorAll(selector) {
        return selector.includes(':not([hidden])') ? rows.filter((row) => !row.hidden) : rows;
      },
    },
    timelineDetail: { focus: () => detailFocuses++ },
    content: { scrollTop: 19 },
  };
  state.$nextTick = (callback) => callback();

  await state.setTimelineFilter('queries');
  assert.deepEqual(requests, [['queries', '']]);
  // The response morph supplies only the matching page.
  rows = [query];
  state.syncTimelineSelection();

  state.selectTimelineItem('queries-0');
  assert.equal(state.timelineDetailOpen, true);
  assert.equal(state.selectedTimelineItem.label, 'select users');
  assert.equal(state.selectedTimelineItem.durationLabel, '2.5 ms');
  assert.equal(state.selectedTimelineItem.source, 'app/Trips/LoadTrip.php:24');
  assert.equal(detailFocuses, 1);
  assert.equal(state.$refs.content.scrollTop, 0);

  let restoreTimelineFocus;
  browser.afterPaint = (callback) => {
    restoreTimelineFocus = callback;
  };

  state.closeTimelineDetail();
  assert.equal(state.timelineDetailOpen, false);
  assert.equal(rowFocuses, 0);
  restoreTimelineFocus();
  assert.equal(rowFocuses, 1);

  state.timelineSearch = 'MISSING';
  await state.applyTimelineFilters();
  assert.deepEqual(requests.at(-1), ['queries', 'MISSING']);
  rows = [];
  state.syncTimelineSelection();
  assert.equal(state.timelineSelected, null);
  assert.equal(state.timelineDetailOpen, false);

  state.setTimelineFilter('unknown');
  assert.equal(state.timelineFilter, 'queries');
  state.$wire.filterTimeline = async () => {
    throw new Error('unavailable');
  };
  await state.applyTimelineFilters();
  assert.equal(state.timelineFiltering, false);
  assert.equal(state.timelineFilterError, true);
});

test('timeline loads bounded pages near the scroll end and exposes retry state', async () => {
  const browser = runtime();
  const observers = [];
  let cleanups = 0;
  browser.observeNearEnd = (target, scrollOwner, callback) => {
    observers.push({ target, scrollOwner, callback });

    return () => cleanups++;
  };

  const { state, shell } = inspectorHarness(
    'timeline',
    {
      ...summary,
      inspectors: [...summary.inspectors, { key: 'timeline', label: 'Timeline' }],
    },
    browser,
  );
  const scrollOwner = {};
  const firstSentinel = { isConnected: true };
  const nextSentinel = { isConnected: true };
  let resolvePage;
  let pageCalls = 0;
  const scopedWire = {
    loadMoreTimeline() {
      pageCalls++;

      return new Promise((resolve) => {
        resolvePage = resolve;
      });
    },
  };
  const wire = {
    $island(name) {
      assert.equal(name, 'inspector-details');

      return scopedWire;
    },
  };

  shell.selected = 'timeline';
  state.$refs = { timelineList: scrollOwner };
  state.$root = {
    querySelector: (selector) => (selector === '[data-ndb-timeline-page-sentinel]' ? nextSentinel : null),
  };
  state.$nextTick = (callback) => callback();
  state.observeTimelinePageEnd(firstSentinel, wire);

  assert.equal(observers.length, 1);
  assert.equal(observers[0].target, firstSentinel);
  assert.equal(observers[0].scrollOwner, scrollOwner);

  const firstPage = observers[0].callback();
  assert.equal(state.timelineLoadingMore, true);
  assert.equal(pageCalls, 1);
  await observers[0].callback();
  assert.equal(pageCalls, 1);

  resolvePage();
  assert.equal(await firstPage, true);
  assert.equal(state.timelineLoadingMore, false);
  assert.equal(state.timelinePaginationError, false);
  assert.equal(cleanups, 1);
  assert.equal(observers.length, 2);
  assert.equal(observers[1].target, nextSentinel);

  scopedWire.loadMoreTimeline = async () => {
    pageCalls++;
    throw new Error('expired');
  };
  assert.equal(await observers[1].callback(), false);
  assert.equal(state.timelineLoadingMore, false);
  assert.equal(state.timelinePaginationError, true);
  assert.equal(observers.length, 2);
  const failedPageCalls = pageCalls;
  assert.equal(await observers[1].callback(), false);
  assert.equal(pageCalls, failedPageCalls);

  scopedWire.loadMoreTimeline = async () => {
    pageCalls++;
  };
  assert.equal(await state.retryTimelinePage(wire), true);
  assert.equal(state.timelinePaginationError, false);
  assert.equal(observers.length, 3);

  state.resetTimelinePagination();
  assert.equal(cleanups, 3);
  assert.equal(state.timelineLoadingMore, false);
});
