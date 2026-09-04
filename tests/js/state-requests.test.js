import assert from 'node:assert/strict';
import test from 'node:test';

import { createNewDebugBar } from '../../resources/js/state.js';
import { runtime, summary } from './state-test-support.js';

test('malformed startup inputs use safe request defaults', () => {
  const initial = { theme: 'dark' };
  const state = createNewDebugBar(initial, runtime(), null, 0);

  assert.equal(state.theme, 'dark');
  assert.equal(state.profileLimit, 20);
  assert.deepEqual(state.recentProfiles, []);
  assert.deepEqual(state.sectionKeys, []);
  assert.equal(state.selectedSection.key, 'request');
  assert.equal(state.currentRequestProfile, initial);

  state.currentRequestId = 'current';
  state.recentProfiles = [{ id: 'current' }, ...Array.from({ length: 10 }, (_, index) => ({ id: `later-${index}` }))];

  assert.equal(state.requestBadgeCount, '9+');
  assert.equal(state.requestPickerButtonLabel, 'Choose request, 10 later requests');
});

test('a new application profile keeps a matching section and resets stale section state', async () => {
  const state = createNewDebugBar(summary, runtime());
  let sectionsLoaded = 0;
  state.$wire = { loadSection: async () => sectionsLoaded++ };
  state.$nextTick = (callback) => callback();
  state.selected = 'logs';
  state.inspectorOpen = true;
  state.loadedSection = 'logs';
  state.viewGroups = [{ id: 'view-1' }];
  state.viewFilter = 'framework';
  state.viewSearch = 'pagination';
  state.viewSelected = 'view-1';
  state.viewDetailOpen = true;
  state.eventSource = 'framework';
  state.eventSearch = 'booted';
  state.httpClientSort = 'duration';
  state.httpClientSortDirection = 'desc';
  state.modelSort = 'retrieved';
  state.modelSortDirection = 'desc';

  state.switchProfile({
    ...summary,
    id: '550e8400-e29b-41d4-a716-446655440000',
    path: '/api/jobs',
  });
  await Promise.resolve();

  assert.equal(state.summary.path, '/api/jobs');
  assert.equal(state.selected, 'logs');
  assert.equal(state.loadedSection, 'logs');
  assert.deepEqual(state.viewGroups, []);
  assert.equal(state.viewFilter, 'application');
  assert.equal(state.viewSearch, '');
  assert.equal(state.viewSelected, null);
  assert.equal(state.viewDetailOpen, false);
  assert.equal(state.eventSource, 'all');
  assert.equal(state.eventSearch, '');
  assert.equal(state.httpClientSort, 'execution');
  assert.equal(state.httpClientSortDirection, 'asc');
  assert.equal(state.modelSort, 'capture');
  assert.equal(state.modelSortDirection, 'asc');
  assert.equal(sectionsLoaded, 1);

  state.inspectorOpen = false;
  state.selected = 'missing';
  state.switchProfile({
    ...summary,
    id: '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
  });
  assert.equal(state.selected, 'request');
});

test('foreground profiles replace the current profile', async () => {
  const activeProfileId = '6ba7b810-9dad-41d1-80b4-00c04fd430c8';
  const visitProfileId = '550e8400-e29b-41d4-a716-446655440000';
  const state = createNewDebugBar({ ...summary, id: activeProfileId }, runtime());
  let switched = null;
  state.$wire = {
    switchProfile: async (id) => {
      switched = id;
    },
  };

  state.noticeProfile(visitProfileId, true);
  await Promise.resolve();

  assert.equal(switched, visitProfileId);
  assert.equal(state.laterRequestCount, 0);
});

test('background profiles are announced once and stay counted after the picker opens', async () => {
  const activeProfileId = '6ba7b810-9dad-41d1-80b4-00c04fd430c8';
  const ajaxProfileId = '550e8400-e29b-41d4-a716-446655440000';
  const calls = [];
  const state = createNewDebugBar({ ...summary, id: activeProfileId, path: '/dashboard' }, runtime());
  state.$wire = { noticeProfile: async (id) => calls.push(['notice', id]) };
  state.$nextTick = (callback) => callback();
  state.$root = { querySelector: () => ({ querySelectorAll: () => [] }) };

  assert.equal(state.hasOtherRequests, false);
  assert.equal(state.requestPickerButtonLabel, 'No later requests yet');
  state.openRequestPicker('toolbar');
  assert.equal(state.requestPickerScope, null);

  state.noticeProfile(ajaxProfileId);
  state.noticeProfile(ajaxProfileId);
  await Promise.resolve();

  assert.deepEqual(calls, [['notice', ajaxProfileId]]);
  assert.deepEqual(state.pendingProfileIds, [ajaxProfileId]);
  assert.equal(state.laterRequestCount, 0);

  state.receiveProfile({ id: ajaxProfileId, method: 'GET', path: '/metrics' });
  assert.deepEqual(state.pendingProfileIds, []);
  assert.equal(state.recentProfiles[0].id, ajaxProfileId);
  assert.equal(state.currentRequestProfile.id, activeProfileId);
  assert.deepEqual(
    state.laterRequestProfiles.map((profile) => profile.id),
    [ajaxProfileId],
  );
  assert.equal(state.hasOtherRequests, true);
  assert.equal(state.laterRequestCount, 1);
  assert.equal(state.requestBadgeCount, '1');
  assert.equal(state.requestPickerButtonLabel, 'Choose request, 1 later request');

  state.openRequestPicker('toolbar');

  assert.equal(state.requestPickerScope, 'toolbar');
  assert.equal(state.laterRequestCount, 1);
  assert.equal(state.requestBadgeCount, '1');
  assert.equal(state.requestPickerButtonLabel, 'Choose request, 1 later request');
  assert.deepEqual(calls, [['notice', ajaxProfileId]]);
});

test('recent requests stay deduplicated, bounded, and include the selected request', () => {
  const current = {
    ...summary,
    id: '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
    path: '/current',
  };
  const first = { id: '550e8400-e29b-41d4-a716-446655440000', path: '/first' };
  const second = {
    id: 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
    path: '/second',
  };
  const state = createNewDebugBar(current, runtime(), [first, current, second], 2);

  assert.deepEqual(
    state.recentProfiles.map((profile) => profile.id),
    [current.id, first.id],
  );

  state.rememberProfile(second);

  assert.deepEqual(
    state.recentProfiles.map((profile) => profile.id),
    [second.id, current.id],
  );
});

test('request summaries format useful labels and update existing recent entries', () => {
  const current = {
    ...summary,
    id: '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
    path: '/current',
  };
  const other = { id: '550e8400-e29b-41d4-a716-446655440000', path: '/other' };
  const state = createNewDebugBar(current, runtime(), [other], 3);
  const originalNow = Date.now;

  state.rememberProfile({ ...current, method: 'POST', path: '/updated' });
  state.receiveProfile({ id: 'not-a-profile' });

  assert.equal(state.recentProfiles.find((profile) => profile.id === current.id).path, '/updated');
  assert.equal(state.requestTitle({ activity: 'Search patients', path: '/patients' }), 'Search patients');
  assert.equal(state.requestTitle({ path: '/patients' }), '/patients');
  assert.equal(state.requestTitle({}), 'Request');
  assert.deepEqual(
    ['ajax', 'artisan', 'cli', 'download', 'full_page', 'json', 'queue', 'redirect', 'stream', 'test', 'unknown'].map(
      (type) => state.requestTypeLabel(type),
    ),
    ['Ajax', 'Command', 'CLI', 'Download', 'Page', 'JSON', 'Worker', 'Redirect', 'Stream', 'Test', 'Request'],
  );
  assert.deepEqual(
    [200, 302, 422, 500, null].map((status) => state.requestStatusClass(status)),
    [
      'ndb:text-emerald-600 ndb:dark:text-emerald-300',
      'ndb:text-sky-600 ndb:dark:text-sky-300',
      'ndb:text-amber-600 ndb:dark:text-amber-300',
      'ndb:text-red-600 ndb:dark:text-red-300',
      'ndb:text-zinc-500 ndb:dark:text-zinc-400',
    ],
  );

  Date.now = () => Date.parse('2026-08-19T12:00:00Z');
  assert.equal(state.relativeRequestTime({ recorded_time: 'Earlier' }), 'Earlier');
  assert.equal(state.relativeRequestTime({ recorded_at: '2026-08-19T11:59:58Z' }), 'now');
  assert.equal(state.relativeRequestTime({ recorded_at: '2026-08-19T11:59:45Z' }), '15s ago');
  assert.equal(state.relativeRequestTime({ recorded_at: '2026-08-19T11:42:00Z' }), '18m ago');
  assert.equal(
    state.relativeRequestTime({
      recorded_at: '2026-08-19T09:00:00Z',
      recorded_time: '09:00',
    }),
    '09:00',
  );
  Date.now = originalNow;
});

test('background refresh is useful-only, bounded, and preserves related navigation', async () => {
  const origin = {
    ...summary,
    id: '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
    completion_state: 'complete',
    background_pending: true,
  };
  const worker = {
    ...summary,
    id: '550e8400-e29b-41d4-a716-446655440000',
    request_type: 'queue',
    sections: [...summary.sections, { key: 'mail', label: 'Mail' }],
  };
  const browser = runtime();
  const islandCalls = [];
  let refreshes = 0;
  let switched = null;
  const state = createNewDebugBar(origin, browser);
  state.$nextTick = (callback) => callback();
  state.$root = { querySelector: () => null, querySelectorAll: () => [] };
  const sectionWire = {
    loadSection: async () => {},
    refreshRelatedActivity: async () => refreshes++,
  };
  state.$wire = {
    $island(name) {
      islandCalls.push(name);

      return sectionWire;
    },
    switchProfile: async (id) => {
      switched = id;
    },
  };

  state.openInspector('queue');
  assert.equal(browser.timers.has(state.activityPollTimer), true);
  browser.runTimers();
  await Promise.resolve();
  assert.equal(refreshes, 1);
  assert.deepEqual(islandCalls, ['section-details', 'section-details']);

  for (let attempt = 0; attempt < 35; attempt++) {
    browser.runTimers();
    await Promise.resolve();
  }

  assert.equal(refreshes, 30);
  assert.equal(islandCalls.every((name) => name === 'section-details'), true);
  assert.equal(state.activityPollTimer, null);

  state.receiveActivityRefresh({ ...origin, background_pending: false }, [worker]);
  assert.equal(state.activityPollTimer, null);
  assert.equal(
    state.recentProfiles.some((profile) => profile.id === worker.id),
    true,
  );

  state.openRelatedProfile(worker.id, 'mail');
  await Promise.resolve();
  assert.equal(switched, worker.id);
  state.switchProfile(worker);
  assert.equal(state.selected, 'mail');
  assert.equal(state.currentRequestId, origin.id);

  state.closeInspector();
  state.summary = { ...origin, background_pending: true };
  state.scheduleActivityRefresh(true);
  assert.equal(state.activityPollTimer, null);
});

test('background refresh reloads only sections affected by related activity', async () => {
  const origin = {
    ...summary,
    id: '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
    background_pending: true,
    background_activity_count: 0,
    sections: [
      ...summary.sections,
      { key: 'timeline', label: 'Timeline' },
      { key: 'views', label: 'Views' },
      { key: 'mail', label: 'Mail' },
    ],
  };
  let loads = 0;
  const state = createNewDebugBar(origin, runtime());
  state.inspectorOpen = true;
  state.selected = 'timeline';
  state.loadedSection = 'timeline';
  state.$wire = {
    loadSection: async () => loads++,
  };

  state.receiveActivityRefresh(origin);
  await Promise.resolve();
  assert.equal(loads, 0);

  state.receiveActivityRefresh({ ...origin, background_activity_count: 1 });
  await Promise.resolve();
  assert.equal(loads, 1);
  assert.equal(state.loadedSection, 'timeline');
  state.sectionLoading = false;
  state.requestedSection = null;

  state.receiveActivityRefresh({ ...origin, background_activity_count: 1 });
  await Promise.resolve();
  assert.equal(loads, 1);

  state.selected = 'views';
  state.loadedSection = 'views';
  state.receiveActivityRefresh({ ...origin, completion_state: 'complete', background_activity_count: 2 });
  await Promise.resolve();
  assert.equal(loads, 1);

  state.selected = 'mail';
  state.loadedSection = 'mail';
  state.receiveActivityRefresh({ ...origin, completion_state: 'complete', background_activity_count: 3 });
  await Promise.resolve();
  assert.equal(loads, 2);
});

test('the request picker manages focus, keyboard movement, and profile selection', async () => {
  const requestSummary = { ...summary };
  const current = {
    ...requestSummary,
    id: '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
    path: '/current',
  };
  const other = {
    ...requestSummary,
    id: '550e8400-e29b-41d4-a716-446655440000',
    path: '/other',
  };
  const browser = runtime();
  let active = null;
  let triggerFocuses = 0;
  const switches = [];
  const option = (profileId) => ({
    dataset: { profileId },
    focus() {
      active = this;
    },
  });
  const currentOption = option(current.id);
  const laterOption = option(other.id);
  const popover = {
    getBoundingClientRect: () => ({ left: 20, width: 384 }),
  };
  const switcher = {
    getBoundingClientRect: () => ({ left: 20, width: 184 }),
    querySelectorAll: () => [currentOption, laterOption],
  };
  const trigger = {
    closest: () => switcher,
    focus: () => triggerFocuses++,
    getBoundingClientRect: () => ({ left: 160, width: 40 }),
  };
  switcher.querySelector = (selector) => (selector.includes('request-popover') ? popover : trigger);
  const listbox = { querySelectorAll: () => [currentOption, laterOption] };
  const state = createNewDebugBar(current, browser, [other]);
  browser.activeElement = () => active;
  state.$nextTick = (callback) => callback();
  state.$root = { querySelector: () => switcher };
  state.$wire = {
    loadSection: async () => {},
    switchProfile: async (id) => switches.push(id),
  };

  state.openRequestPicker('unknown', trigger);
  state.openRequestPicker('header', trigger);
  assert.equal(state.requestPickerScope, null);

  state.toggleRequestPicker('toolbar', trigger);
  assert.equal(state.requestPickerScope, 'toolbar');
  assert.equal(state.requestPickerArrowLeft, 152);
  assert.equal(active, currentOption);

  state.moveRequestPicker(-1, listbox);
  assert.equal(active, laterOption);
  state.moveRequestPicker(-1, listbox);
  assert.equal(active, currentOption);
  active = null;
  state.moveRequestPicker(1, listbox);
  assert.equal(active, laterOption);
  state.focusRequestPickerEdge('end', listbox);
  assert.equal(active, laterOption);
  state.focusRequestPickerEdge('start', listbox);
  assert.equal(active, currentOption);
  state.moveRequestPicker(1, { querySelectorAll: () => [] });
  state.focusRequestPickerEdge('start', { querySelectorAll: () => [] });

  state.toggleRequestPicker('toolbar', trigger);
  assert.equal(state.requestPickerScope, null);
  assert.equal(triggerFocuses, 1);

  state.openRequestPicker('corner', trigger);
  assert.equal(state.requestPickerScope, 'corner');
  state.closeRequestPicker(false);

  state.inspectorOpen = true;
  state.openRequestPicker('toolbar', trigger);
  assert.equal(state.requestPickerScope, null);
  state.openRequestPicker('header', trigger);
  assert.equal(state.requestPickerScope, 'header');
  state.selectRequest(current.id);
  assert.deepEqual(switches, []);
  assert.equal(state.inspectorOpen, true);
  assert.equal(state.selected, 'request');

  state.selected = 'logs';
  state.openRequestPicker('header', trigger);
  state.selectRequest(other.id);
  await Promise.resolve();
  assert.deepEqual(switches, [other.id]);
  assert.equal(state.requestSelectionPending, other.id);
  assert.equal(state.selected, 'logs');

  state.switchProfile(other);
  assert.equal(state.requestSelectionPending, null);
  assert.equal(state.inspectorOpen, true);
  assert.equal(state.selected, 'request');
  assert.equal(state.currentRequestProfile.id, current.id);
  assert.deepEqual(
    state.laterRequestProfiles.map((profile) => profile.id),
    [other.id],
  );
  state.closeRequestPicker(false);
});

test('request discovery and selection failures clear their pending state', async () => {
  const current = { ...summary, id: '6ba7b810-9dad-41d1-80b4-00c04fd430c8' };
  const otherId = '550e8400-e29b-41d4-a716-446655440000';
  const state = createNewDebugBar(current, runtime());

  state.$wire = {
    noticeProfile: async () => {
      throw new Error('unavailable');
    },
  };
  state.noticeProfile(otherId);
  await Promise.resolve();
  await Promise.resolve();
  assert.deepEqual(state.pendingProfileIds, []);
  assert.equal(state.laterRequestCount, 0);

  state.$wire = {};
  state.noticeProfile(otherId);
  state.noticeProfile('invalid');
  state.noticeProfile(current.id);
  assert.equal(state.laterRequestCount, 0);

  state.$wire = {
    switchProfile: async () => {
      throw new Error('expired');
    },
  };
  state.selectRequest(otherId);
  await Promise.resolve();
  await Promise.resolve();
  assert.equal(state.requestSelectionPending, null);

  state.requestSelectionPending = otherId;
  state.selectRequest(otherId);
  state.selectRequest('invalid');
  assert.equal(state.requestSelectionPending, otherId);
});

test('stale section responses cannot resync panels for a newer profile', async () => {
  const pending = [];
  const state = createNewDebugBar({ ...summary, id: '6ba7b810-9dad-41d1-80b4-00c04fd430c8' }, runtime());
  let synced = 0;
  state.syncSectionPanels = () => synced++;
  state.$wire = {
    loadSection: () => new Promise((resolve) => pending.push(resolve)),
  };
  state.$nextTick = (callback) => callback();
  state.inspectorOpen = true;

  state.openInspector();
  state.switchProfile({
    ...summary,
    id: '550e8400-e29b-41d4-a716-446655440000',
  });
  const syncsBeforeStaleResponse = synced;
  pending[0]();
  await Promise.resolve();

  assert.equal(synced, syncsBeforeStaleResponse);
  assert.equal(state.summary.id, '550e8400-e29b-41d4-a716-446655440000');
  assert.equal(state.selected, 'request');
});

test('section changes fade the current panel and delay loading feedback for slow responses', async () => {
  const browser = runtime();
  const state = createNewDebugBar(summary, browser);
  const panels = [
    { dataset: { ndbSectionPanel: 'request' }, hidden: false },
    { dataset: { ndbSectionPanel: 'queries' }, hidden: true },
  ];
  let resolveSection;

  state.$root = {
    querySelectorAll: (selector) => (selector === '[data-ndb-section-panel]' ? panels : []),
  };
  state.$nextTick = (callback) => callback();
  state.$wire = {
    loadSection: () =>
      new Promise((resolve) => {
        resolveSection = resolve;
      }),
  };
  state.inspectorOpen = true;
  state.loadedSection = 'request';

  state.selectSection('queries');

  assert.equal(state.sectionLoading, true);
  assert.equal(state.sectionLoadingIndicator, false);
  assert.equal(state.sectionTransitioning, true);
  assert.equal(panels[0].hidden, false);
  assert.equal(panels[1].hidden, true);

  browser.runTimers();

  assert.equal(state.sectionLoadingIndicator, true);

  resolveSection();
  await Promise.resolve();
  await Promise.resolve();

  assert.equal(state.loadedSection, 'queries');
  assert.equal(state.sectionLoading, false);
  assert.equal(state.sectionLoadingIndicator, false);
  assert.equal(state.sectionLoadingTimer, null);
  assert.equal(state.sectionTransitioning, false);
  assert.equal(panels[0].hidden, true);
  assert.equal(panels[1].hidden, false);

  state.$wire = { loadSection: async () => {} };
  state.selectSection('logs');
  await Promise.resolve();
  await Promise.resolve();

  assert.equal(state.loadedSection, 'logs');
  assert.equal(state.sectionLoadingIndicator, false);
  assert.equal(state.sectionTransitioning, false);
  assert.equal(browser.timers.size, 0);
});

test('section selection falls back safely and a failed section can retry', async () => {
  const state = createNewDebugBar(summary, runtime());
  let attempts = 0;
  state.$wire = {
    loadSection: () => {
      attempts++;
      return attempts === 1 ? Promise.reject(new Error('expired')) : Promise.resolve();
    },
  };
  state.$nextTick = (callback) => callback();

  state.selectSection('missing');
  assert.equal(state.selected, 'request');

  state.openInspector('queries');
  await new Promise((resolve) => setImmediate(resolve));
  assert.equal(state.sectionLoading, false);
  assert.equal(state.sectionError, true);

  state.openInspector('queries');
  await new Promise((resolve) => setImmediate(resolve));

  assert.equal(attempts, 2);
  assert.equal(state.sectionError, false);
  assert.equal(state.loadedSection, 'queries');
  assert.equal(state.selected, 'queries');
});
