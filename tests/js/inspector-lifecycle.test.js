import assert from 'node:assert/strict';
import test from 'node:test';
import { runtime, summary, inspectorHarness } from './state-test-support.js';

const inspectors = [
  'authorization',
  'cache',
  'events',
  'http_client',
  'logs',
  'mail',
  'models',
  'notifications',
  'queries',
  'queue',
  'redis',
  'timeline',
  'views',
  'livewire',
];
const profile = { ...summary, id: 'first', inspectors: inspectors.map((key) => ({ key, label: key })) };
const encode = (payload) => Buffer.from(JSON.stringify(payload), 'utf8').toString('base64');

test('inspector mounts accept refreshed payloads and stop reading them after replacement or destruction', () => {
  for (const inspector of inspectors) {
    const { shell, state } = inspectorHarness(inspector, profile, runtime());
    let payload = null;
    let reads = 0;
    state.$root = {
      querySelector(selector) {
        reads++;
        return selector.endsWith('payload]') && payload !== null ? { textContent: encode(payload) } : null;
      },
      querySelectorAll: () => [],
    };
    state.$nextTick = (callback) => callback();
    state.init();
    payload = inspector === 'livewire' ? { components: [], activity_records: [] } : [];
    shell.refreshInspector();
    assert.equal(state.initialized, true, inspector);
    shell.refreshInspector();
    shell.summary = { ...profile, id: 'second' };
    const previousReads = reads;
    shell.refreshInspector();
    state.refresh();
    assert.equal(reads, previousReads, `${inspector} ignores a different profile`);
    shell.summary = profile;
    state.destroy();
    state.refresh();
    shell.refreshInspector();
    assert.equal(reads, previousReads, `${inspector} detaches on destruction`);
  }
});

test('a replaced inspector cannot detach its replacement through a different Alpine scope proxy', () => {
  const { shell, state: first } = inspectorHarness('mail', profile, runtime());
  const second = shell.createInspector('mail');
  let refreshed = 0;
  second.refresh = () => refreshed++;
  new Proxy(first, {}).init();
  new Proxy(second, {}).init();
  new Proxy(first, {}).destroy();
  shell.refreshInspector();
  assert.equal(refreshed, 1);
  new Proxy(second, {}).destroy();
  shell.refreshInspector();
  assert.equal(refreshed, 1);
  assert.deepEqual(shell.createInspector('request'), {});
});

test('inspector instances retain live getters and decode Unicode without sharing records or controls', () => {
  const { shell, state } = inspectorHarness('mail', profile, runtime());
  const next = shell.createInspector('mail');
  let payload = [{ execution: 1, subject: '京都 — été', has_html: true }];
  state.$root = { querySelector: () => ({ textContent: encode(payload) }) };
  state.refresh();
  assert.equal(state.selectedMailMessage.subject, '京都 — été');
  state.mailSearch = 'été';
  state.mailDetailOpen = true;
  payload = [{ ...payload[0], subject: '京都 — été 更新' }];
  state.refresh();
  assert.equal(state.selectedMailMessage.subject, '京都 — été 更新');
  assert.equal(state.mailSearch, 'été');
  assert.equal(state.mailDetailOpen, true);
  assert.deepEqual(next.mailMessages, []);
  assert.equal(next.mailSearch, '');
  assert.equal(Object.hasOwn(shell, 'mailMessages'), false);
});

test('same-profile query refresh preserves selection, scrolling, and pending or completed EXPLAIN evidence', () => {
  const { state } = inspectorHarness('queries', profile, runtime());
  const records = () => [
    {
      key: 'kyoto',
      executions: [
        { execution: 1, explain_available: true },
        { execution: 2, explain_available: true },
      ],
    },
  ];
  const row = {
    dataset: { ndbQueryKey: 'kyoto', ndbSearch: 'kyoto', ndbQueryExecutionCount: '2' },
    style: { removeProperty() {} },
  };
  state.$refs = { queryList: { querySelectorAll: () => [row] }, queryDetail: { scrollTop: 73 } };
  state.$nextTick = (callback) => callback();
  state.initializeQueries(records());
  state.selectQueryExecution(2);
  state.querySearch = 'kyoto';
  state.queryDetailOpen = true;
  assert.equal(state.beginQueryExplain(), 2);
  state.initializeQueries(records());
  assert.equal(state.queryExplainLoading, true);
  assert.equal(state.selectedQuery.explain_loading, true);
  assert.equal(state.querySelectedExecution, 2);
  assert.equal(state.queryDetailTab, 'explain');
  assert.equal(state.queryDetailOpen, true);
  assert.equal(state.querySearch, 'kyoto');
  assert.equal(state.queryExplainScrollTop, 73);
  state.receiveQueryExplain({
    profileId: profile.id,
    execution: 2,
    explain: { rows: [{ detail: 'SCAN trips' }] },
  });
  state.initializeQueries(records());
  assert.deepEqual(state.queryExplain, { rows: [{ detail: 'SCAN trips' }] });
  assert.equal(state.queryExplainLoading, false);
  const completed = records();
  completed[0].executions[1].explain = { rows: [{ detail: 'SEARCH trips' }] };
  state.initializeQueries(completed);
  assert.deepEqual(state.queryExplain, completed[0].executions[1].explain);
  state.initializeQueries([{ key: 'kyoto', executions: [{ execution: 1 }] }]);
  assert.equal(state.querySelectedExecution, 1);
  state.initializeQueries([]);
  assert.equal(state.selectedQuery, null);
  assert.equal(state.queryExplain, null);
  assert.equal(state.queryDetailOpen, false);
});

test('view refresh retains loaded render data and rejects a late response from another profile', async () => {
  const { shell, state } = inspectorHarness('views', profile, runtime());
  const groups = [{ id: 'trip', origin: 'application', items: [{ render_order: 1 }, { render_order: 2 }] }];
  state.initializeViews(groups);
  state.selectViewGroup('trip');
  state.loadSelectedViewData({ loadViewData: async () => ({ city: 'Kyoto' }) });
  await Promise.resolve();
  state.initializeViews(structuredClone(groups));
  assert.deepEqual(state.viewData, { city: 'Kyoto' });
  assert.equal(state.viewDataLoaded, true);
  state.selectViewRender(2);
  let resolve;
  state.loadSelectedViewData({
    loadViewData: () =>
      new Promise((done) => {
        resolve = done;
      }),
  });
  shell.summary = { ...profile, id: 'second' };
  resolve({ city: 'Old request' });
  await Promise.resolve();
  assert.equal(state.viewData, null);
  state.destroy();
  state.loadSelectedViewData({
    loadViewData() {
      assert.fail('destroyed controller cannot load data');
    },
  });
  const next = shell.createInspector('views');
  next.initializeViews(groups);
  next.selectViewGroup('trip');
  next.initializeViews([{ ...groups[0], items: [{ render_order: 2 }] }]);
  assert.equal(next.viewRenderOrder, 2);
  assert.equal(next.viewDataLoaded, false);
  next.initializeViews([]);
  assert.equal(next.viewSelected, null);
  assert.equal(next.viewDetailOpen, false);
});

test('closing Timeline cancels observation and prevents late pagination callbacks from reopening it', async () => {
  const browser = runtime();
  let observed = 0;
  let disconnected = 0;
  let resolve;
  browser.observeNearEnd = () => {
    observed++;
    return () => disconnected++;
  };
  const { shell, state } = inspectorHarness('timeline', profile, browser);
  const sentinel = { isConnected: true };
  state.$root = { querySelector: () => sentinel };
  state.$refs = { timelineList: {} };
  state.$nextTick = (callback) => callback();
  state.init();
  assert.equal(observed, 1);
  const pending = state.loadNextTimelinePage({
    loadMoreTimeline: () =>
      new Promise((done) => {
        resolve = done;
      }),
  });
  shell.closeInspector();
  assert.equal(disconnected, 1);
  resolve();
  assert.equal(await pending, false);
  state.observeTimelinePageEnd(sentinel);
  assert.equal(observed, 1);
  assert.equal(
    await state.loadNextTimelinePage({
      loadMoreTimeline() {
        assert.fail('hidden Timeline cannot load');
      },
    }),
    false,
  );
  shell.openInspector('timeline');
  assert.equal(observed, 2);
  state.destroy();
  state.observeTimelinePageEnd(sentinel);
  assert.equal(observed, 2);
  assert.equal(disconnected, 2);
});

test('Livewire resumes its display subscription without clearing captured page history', () => {
  const browser = runtime();
  const frames = [];
  browser.afterPaint = (callback) => frames.push(callback);
  let subscriber;
  let subscriptions = 0;
  let unsubscribed = 0;
  let snapshot = { pageSequence: 1, components: [], activity: [], dropped: { components: 0, activity: 0 } };
  const trace = {
    subscribe(callback) {
      subscriptions++;
      subscriber = callback;
      callback(snapshot);
      return () => {
        unsubscribed++;
        subscriber = null;
      };
    },
  };
  const { shell, state } = inspectorHarness('livewire', profile, browser, [], 20, trace);
  state.$nextTick = (callback) => callback();
  state.init();
  assert.equal(subscriptions, 1);
  state.focusLivewirePropertyEditor({ path: 'city' });
  shell.closeInspector();
  assert.equal(subscriber, null);
  assert.equal(unsubscribed, 1);
  assert.equal(browser.timers.size, 0);
  browser.queryAll = () => {
    assert.fail('closed property editor cannot steal focus');
  };
  while (frames.length) frames.shift()();
  snapshot = { ...snapshot, ready: true, pageSequence: 2 };
  shell.openInspector('livewire');
  assert.equal(subscriptions, 2);
  assert.equal(state.livewireTrace, snapshot);
  state.destroy();
  assert.equal(unsubscribed, 2);
  assert.equal(browser.timers.size, 0);
});
