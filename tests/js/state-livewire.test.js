import assert from 'node:assert/strict';
import test from 'node:test';
import serverActivity from './fixtures/livewire-server-activity.json' with { type: 'json' };

import { runtime, sectionHarness } from './state-test-support.js';

const livewireSummary = {
  id: '550e8400-e29b-41d4-a716-446655440000',
  sections: [
    { key: 'request', label: 'Requests' },
    { key: 'livewire', label: 'Livewire' },
  ],
};

const browserComponents = [
  {
    id: 'root-1',
    name: 'benchmark.control-panel',
    title: 'Control Panel',
    parentId: null,
    sequence: 1,
    mounted: true,
    status: 'idle',
    latestActivityId: 'activity-2',
    properties: [
      { path: 'count', type: 'Integer', value: 1 },
      {
        path: 'settings',
        type: 'Array',
        value: { enabled: true, nested: { label: 'Primary' } },
      },
      { path: 'fixedLabel', type: 'String', value: 'Fixed' },
      { path: 'optional', type: 'Null', value: null },
    ],
  },
  {
    id: 'root-2',
    name: 'benchmark.event-console',
    title: 'Event Console',
    parentId: null,
    sequence: 2,
    mounted: true,
    status: 'idle',
    latestActivityId: null,
    properties: [],
  },
  {
    id: 'child-1',
    name: 'benchmark.metric-card',
    title: 'Metric Card',
    parentId: 'root-1',
    sequence: 3,
    mounted: true,
    status: 'updating',
    latestActivityId: 'activity-3',
    properties: [{ path: 'label', type: 'String', value: 'Revenue' }],
  },
];

const activity = [
  {
    id: 'activity-1',
    sequence: 1,
    componentId: 'root-1',
    componentTitle: 'Control Panel',
    componentName: 'benchmark.control-panel',
    title: 'Control Panel mounted',
    kind: 'mount',
    status: 'complete',
    occurredAt: 0,
    durationMs: 1.2,
    profileIds: [],
    actions: [],
    changes: [],
    events: [],
    phases: [],
    error: null,
  },
  {
    id: 'activity-2',
    sequence: 2,
    componentId: 'root-1',
    componentTitle: 'Control Panel',
    componentName: 'benchmark.control-panel',
    title: 'Count changed',
    kind: 'mutation',
    status: 'complete',
    occurredAt: 1_000,
    durationMs: 8.25,
    profileIds: ['profile-1'],
    actions: [{ name: '$set' }],
    changes: [{ path: 'count', before: 0, submitted: 1, server: 1 }],
    events: [],
    phases: [],
    error: null,
  },
  {
    id: 'activity-3',
    sequence: 3,
    componentId: 'child-1',
    componentTitle: 'Metric Card',
    componentName: 'benchmark.metric-card',
    title: 'Refresh ran',
    kind: 'action',
    status: 'updating',
    occurredAt: 12_500,
    durationMs: null,
    profileIds: [],
    actions: [{ name: 'refresh' }],
    changes: [],
    events: [],
    phases: [],
    error: null,
  },
];

function traceHarness(
  data = {
    ready: true,
    components: browserComponents,
    activity,
    dropped: { components: 0, activity: 0 },
  },
) {
  const calls = [];
  let subscriber;

  return {
    calls,
    subscribe(callback) {
      subscriber = callback;
      callback(data);
      return () => calls.push(['unsubscribe']);
    },
    emit(snapshot) {
      subscriber(snapshot);
    },
    mergeServerComponents(components) {
      calls.push(['merge', components]);
    },
    async applyMutation(options) {
      calls.push(['apply', options]);
      return options.value;
    },
  };
}

function stateHarness(trace = traceHarness()) {
  const browser = runtime();
  const { state, shell } = sectionHarness('livewire', { ...livewireSummary }, browser, [], 20, trace);
  state.$root = { querySelectorAll: () => [], querySelector: () => null };
  state.$nextTick = (callback) => callback();
  state.init();

  state.mergeLivewireServer({
    components: [
      {
        id: 'root-1',
        class: 'App\\Livewire\\Benchmark\\ControlPanel',
        source: { file: 'app/Livewire/Benchmark/ControlPanel.php' },
        properties: [
          {
            path: 'count',
            type: 'Integer',
            php_type: 'int',
            server_value: 1,
            writable: true,
            array_leaf_writable: false,
            write_allowed: true,
            write_reason: null,
          },
          {
            path: 'settings',
            type: 'Array',
            php_type: 'array',
            server_value: null,
            writable: false,
            array_leaf_writable: true,
            write_allowed: true,
            write_reason: null,
          },
          {
            path: 'fixedLabel',
            type: 'String',
            php_type: 'string',
            server_value: 'Fixed',
            writable: false,
            array_leaf_writable: false,
            write_allowed: false,
            write_reason: 'locked',
          },
          {
            path: 'optional',
            type: 'Null',
            php_type: '?string',
            server_value: null,
            writable: true,
            array_leaf_writable: false,
            write_allowed: true,
            write_reason: null,
          },
        ],
      },
    ],
    activity_records: serverActivity['empty'],
  });

  return { browser, state, shell, trace };
}

test('orders several roots and nested instances while preserving stable instance identity', () => {
  const { state, shell } = stateHarness();

  assert.deepEqual(
    state.livewireComponents.map(({ id, depth }) => [id, depth]),
    [
      ['root-1', 0],
      ['root-2', 0],
      ['child-1', 1],
    ],
  );
  assert.equal(state.livewireSelectedComponentId, 'root-1');
  assert.equal(state.livewireComponentPropertyCount(state.selectedLivewireComponent), 4);
  assert.equal(state.livewireComponentPropertyCountLabel(state.selectedLivewireComponent), '4 properties');
  assert.equal(state.livewireComponentPropertyStateSummary(state.selectedLivewireComponent), '0 changed, 3 editable');
  state.livewireSearch = 'metric';
  assert.deepEqual(
    state.filteredLivewireComponents.map(({ id }) => id),
    ['root-1', 'child-1'],
  );
  assert.deepEqual(
    state.matchingLivewireComponents.map(({ id }) => id),
    ['child-1'],
  );
  assert.equal(state.livewireComponentIsSearchContext(state.filteredLivewireComponents[0]), true);
  state.livewireSearch = 'ControlPanel.php';
  assert.deepEqual(
    state.filteredLivewireComponents.map(({ id }) => id),
    ['root-1'],
  );
  assert.equal(state.livewireComponentTitle('root-2'), 'Event Console');
  assert.equal(state.livewireComponentTitle('missing'), 'missing');
});

test('collapses every component branch without hiding search matches', () => {
  const grandchild = {
    id: 'grandchild-1',
    name: 'benchmark.metric-pulse',
    title: 'Metric Pulse',
    parentId: 'child-1',
    sequence: 4,
    mounted: true,
    status: 'idle',
    latestActivityId: null,
    properties: [],
  };
  const trace = traceHarness({
    ready: true,
    components: [...browserComponents, grandchild],
    activity,
    dropped: { components: 0, activity: 0 },
  });
  const { state, shell } = stateHarness(trace);
  const root = state.livewireComponents.find(({ id }) => id === 'root-1');
  const child = state.livewireComponents.find(({ id }) => id === 'child-1');
  const pulse = state.livewireComponents.find(({ id }) => id === 'grandchild-1');

  assert.equal(root.hasChildren, true);
  assert.equal(child.hasChildren, true);
  assert.deepEqual(child.ancestorIds, ['root-1']);
  assert.deepEqual(pulse.ancestorIds, ['child-1', 'root-1']);
  assert.deepEqual(state.livewireCollapsedComponents, ['root-1', 'child-1']);
  assert.equal(state.livewireComponentCollapsed(root), true);
  assert.equal(state.livewireComponentCollapsed(child), true);
  assert.deepEqual(
    state.filteredLivewireComponents.map(({ id }) => id),
    ['root-1', 'root-2'],
  );

  state.toggleLivewireComponent(root);
  assert.equal(state.livewireComponentCollapsed(root), false);
  assert.deepEqual(
    state.filteredLivewireComponents.map(({ id }) => id),
    ['root-1', 'root-2', 'child-1'],
  );

  state.toggleLivewireComponent(child);
  assert.equal(state.livewireComponentCollapsed(child), false);
  assert.equal(state.filteredLivewireComponents.length, 4);

  state.selectLivewireComponent('grandchild-1');
  state.toggleLivewireComponent(child);

  assert.equal(state.livewireComponentCollapsed(child), true);
  assert.deepEqual(
    state.filteredLivewireComponents.map(({ id }) => id),
    ['root-1', 'root-2', 'child-1'],
  );
  assert.equal(state.livewireSelectedComponentId, 'child-1');

  state.toggleLivewireComponent(root);

  assert.equal(state.livewireComponentCollapsed(root), true);
  assert.deepEqual(
    state.filteredLivewireComponents.map(({ id }) => id),
    ['root-1', 'root-2'],
  );
  assert.equal(state.matchingLivewireComponents.length, 4);
  assert.equal(state.livewireSelectedComponentId, 'root-1');

  state.livewireSearch = 'pulse';
  assert.deepEqual(
    state.filteredLivewireComponents.map(({ id }) => id),
    ['root-1', 'child-1', 'grandchild-1'],
  );
  assert.deepEqual(
    state.matchingLivewireComponents.map(({ id }) => id),
    ['grandchild-1'],
  );

  state.livewireSearch = '';
  state.toggleLivewireComponent(root);
  assert.equal(state.livewireComponentCollapsed(root), false);
  assert.deepEqual(
    state.filteredLivewireComponents.map(({ id }) => id),
    ['root-1', 'root-2', 'child-1'],
  );

  state.toggleLivewireComponent(child);
  assert.equal(state.livewireComponentCollapsed(child), false);
  assert.equal(state.filteredLivewireComponents.length, 4);
});

test('filters activity and moves between activity and component details', () => {
  const { state, shell } = stateHarness();

  assert.equal(state.livewireSelectedActivityId, 'activity-3');
  assert.deepEqual(
    state.filteredLivewireActivity.map(({ id }) => id),
    ['activity-3', 'activity-2', 'activity-1'],
  );
  state.setLivewireActivityType('mutation');
  assert.deepEqual(
    state.filteredLivewireActivity.map(({ id }) => id),
    ['activity-2'],
  );
  assert.equal(state.livewireSelectedActivityId, 'activity-2');
  state.livewireSearch = 'count';
  assert.equal(state.filteredLivewireActivity.length, 1);
  state.setLivewireActivityType('missing');
  assert.equal(state.livewireActivityType, 'mutation');
  assert.deepEqual(state.livewireActivityTypes, ['action', 'mount', 'mutation']);
  assert.equal(state.livewireActivityStatusLabel(state.selectedLivewireActivity), 'Finished');
  assert.equal(state.livewireActivitySummary(state.selectedLivewireActivity), 'Control Panel changed count.');
  assert.equal(state.livewireActivityComponentTitle(activity[2]), 'Metric Card');
  assert.equal(state.livewireActivityShowsComponent(activity[0]), false);
  assert.equal(state.livewireActivityShowsComponent(activity[1]), true);
  assert.equal(
    state.livewireActivityShowsComponent({
      ...activity[2],
      kind: 'poll',
      title: 'Polled component',
    }),
    true,
  );
  assert.equal(state.livewireDuration(state.selectedLivewireActivity), '8.25 ms');
  assert.equal(state.livewireDuration(activity[2]), 'In progress');
  assert.equal(state.livewireDuration({ status: 'complete', durationMs: null }), '—');
  assert.equal(state.livewireActivityAge(activity[0]), 'Current request');
  assert.equal(state.livewireActivityAge(activity[1]), '12 sec ago');
  assert.equal(state.livewireActivityAge(activity[2]), 'Now');
  state.livewireClock = 73_000;
  assert.equal(state.livewireActivityAge(activity[1]), '1 min ago');
  state.livewireClock = 7_213_000;
  assert.equal(state.livewireActivityAge(activity[1]), '2 hr ago');
  state.livewireClock = 180_001_000;
  assert.equal(state.livewireActivityAge(activity[1]), '2 days ago');

  state.inspectLivewireActivityComponent();
  assert.equal(state.livewireTab, 'components');
  assert.equal(state.livewireDetailTab, 'properties');
  assert.equal(state.livewireSelectedComponentId, 'root-1');
  state.setLivewireDetailTab('source');
  assert.equal(state.livewireDetailTab, 'source');
  state.setLivewireDetailTab('invalid');
  assert.equal(state.livewireDetailTab, 'source');
  state.inspectLivewireComponentActivity();
  assert.equal(state.livewireTab, 'activity');
  assert.equal(state.livewireSelectedActivityId, 'activity-2');
  state.inspectLivewireComponent('child-1');
  assert.equal(state.livewireTab, 'components');
  assert.equal(state.livewireDetailTab, 'properties');
  assert.equal(state.livewireSelectedComponentId, 'child-1');
  state.setLivewireTab('components');
  assert.equal(state.livewireTab, 'components');
  assert.equal(state.livewireDetailOpen, false);
  state.selectLivewireComponent('child-1');
  assert.equal(state.livewireSelectedComponentId, 'child-1');
  assert.equal(state.livewireDetailTab, 'properties');
  assert.equal(state.livewireDetailOpen, true);
  state.selectLivewireComponent('missing');
  assert.equal(state.livewireSelectedComponentId, 'child-1');
  state.selectLivewireActivity('activity-1');
  assert.equal(state.livewireSelectedActivityId, 'activity-1');
  state.selectLivewireActivity('missing');
  assert.equal(state.livewireSelectedActivityId, 'activity-1');
  state.setLivewireTab('invalid');
  assert.equal(state.livewireTab, 'components');
});

test('keeps the newest visible activity selected until the developer chooses another item', () => {
  const trace = traceHarness();
  const { state, shell } = stateHarness(trace);
  const fourth = {
    ...activity[2],
    id: 'activity-4',
    sequence: 4,
    title: 'Another refresh ran',
  };

  trace.emit({
    ready: true,
    components: browserComponents,
    activity: [...activity, fourth],
    dropped: { components: 0, activity: 0 },
  });
  assert.equal(state.livewireSelectedActivityId, 'activity-4');

  state.selectLivewireActivity('activity-2');
  const fifth = { ...fourth, id: 'activity-5', sequence: 5 };
  trace.emit({
    ready: true,
    components: browserComponents,
    activity: [...activity, fourth, fifth],
    dropped: { components: 0, activity: 0 },
  });
  assert.equal(state.livewireSelectedActivityId, 'activity-2');

  state.livewireSearch = 'nothing matches this';
  state.syncLivewireSelection();
  assert.equal(state.livewireSelectedActivityId, null);
  assert.equal(state.livewireSelectedComponentId, null);

  state.livewireSearch = '';
  state.syncLivewireSelection();
  assert.equal(state.livewireSelectedActivityId, 'activity-5');
  assert.equal(state.livewireSelectedComponentId, 'root-1');
});

test('keeps shared requests and repetitive activity as separate inspectable interactions', () => {
  const poll = (id, sequence) => ({
    ...activity[2],
    id,
    sequence,
    componentId: 'root-1',
    componentTitle: 'Control Panel',
    componentName: 'benchmark.control-panel',
    title: 'Polled component',
    kind: 'poll',
    status: 'complete',
    actions: [{ name: '$refresh', metadata: { type: 'poll' } }],
  });
  const secondMount = {
    ...activity[0],
    id: 'activity-0',
    sequence: 0,
    componentId: 'root-2',
    componentTitle: 'Event Console',
  };
  const trace = traceHarness({
    ready: true,
    components: browserComponents,
    activity: [
      secondMount,
      ...activity,
      poll('poll-1', 4),
      poll('poll-2', 5),
      { ...activity[2], id: 'action-6', sequence: 6 },
      poll('poll-3', 7),
    ],
    dropped: { components: 0, activity: 0 },
  });
  const { state, shell } = stateHarness(trace);
  assert.deepEqual(
    state.filteredLivewireActivity.map(({ id }) => id),
    ['poll-3', 'action-6', 'poll-2', 'poll-1', 'activity-3', 'activity-2', 'activity-1', 'activity-0'],
  );
  state.selectLivewireActivity('poll-1');
  assert.equal(state.livewireSelectedActivityId, 'poll-1');
});

test('keeps every interaction in a bundled request separate', () => {
  const profileId = '550e8400-e29b-41d4-a716-446655440001';
  const requestActivity = [
    {
      ...activity[2],
      id: 'request-action',
      sequence: 4,
      componentId: 'root-1',
      componentTitle: 'Control Panel',
      title: 'Increment ran',
      actions: [{ name: 'increment' }],
      profileIds: [profileId],
    },
    {
      ...activity[2],
      id: 'request-child-1',
      sequence: 5,
      title: 'Component updated',
      actions: [{ name: '$commit' }],
      profileIds: [profileId],
    },
    {
      ...activity[2],
      id: 'request-child-2',
      sequence: 6,
      componentId: 'child-2',
      title: 'Component updated',
      actions: [{ name: '$commit' }],
      profileIds: [profileId],
    },
  ];
  const trace = traceHarness({
    ready: true,
    components: browserComponents,
    activity: [...activity, ...requestActivity],
    dropped: { components: 0, activity: 0 },
  });
  const { state, shell } = stateHarness(trace);
  assert.deepEqual(
    state.filteredLivewireActivity.slice(0, 3).map(({ id }) => id),
    ['request-child-2', 'request-child-1', 'request-action'],
  );
  assert.equal(state.livewireSelectedActivityId, 'request-child-2');
});

test('keeps related request and failure source evidence on the browser interaction', () => {
  const profileId = livewireSummary.id;
  const olderProfileId = '550e8400-e29b-41d4-a716-446655440001';
  const trace = traceHarness({
    ready: true,
    components: browserComponents,
    activity: [
      {
        ...activity[2],
        id: 'older-failed-action',
        componentId: 'root-1',
        componentTitle: 'Control Panel',
        title: 'Save ran',
        status: 'failed_validation',
        actions: [{ name: 'save' }],
        profileIds: [olderProfileId],
      },
      {
        ...activity[2],
        id: 'failed-action',
        componentId: 'root-1',
        componentTitle: 'Control Panel',
        title: 'Save ran',
        status: 'failed_validation',
        actions: [{ name: 'save' }],
        profileIds: [profileId, profileId, 'not-a-profile'],
      },
    ],
    dropped: { components: 0, activity: 0 },
  });
  const { state, shell } = stateHarness(trace);
  shell.summary.request_type = 'livewire';

  state.mergeLivewireServer({
    components: [],
    activity_records: serverActivity['server-action'],
  });

  const merged = state.livewireActivity.find(({ id }) => id === 'failed-action');
  assert.deepEqual(merged.serverActivityIds, ['server-action', 'server-failure']);
  assert.deepEqual(state.livewireActivityProfileIds(merged), [profileId]);
  assert.equal(state.livewireActivitySourceLabel(merged), 'app/Livewire/ControlPanel.php:42');
  assert.equal(state.livewireActivity.filter(({ kind }) => kind === 'failure').length, 0);
  assert.equal(state.livewireActivity.find(({ id }) => id === 'older-failed-action').serverActivityIds, undefined);
  state.livewireSearch = 'ControlPanel.php';
  assert.deepEqual(
    state.filteredLivewireActivity.map(({ id }) => id),
    ['failed-action'],
  );
  assert.equal(state.livewireActivitySourceLabel({ callsite: null }), null);
});

test('keeps current request evidence standalone when its browser interaction was dropped', () => {
  const olderProfileId = '550e8400-e29b-41d4-a716-446655440001';
  const trace = traceHarness({
    ready: true,
    components: browserComponents,
    activity: [
      {
        ...activity[2],
        id: 'older-failed-action',
        componentId: 'root-1',
        componentTitle: 'Control Panel',
        title: 'Save ran',
        status: 'failed_validation',
        actions: [{ name: 'save' }],
        profileIds: [olderProfileId],
      },
    ],
    dropped: { components: 0, activity: 1 },
  });
  const { state, shell } = stateHarness(trace);
  shell.summary.request_type = 'livewire';
  state.mergeLivewireServer({
    components: [],
    activity_records: serverActivity['current-server-failure'],
  });

  const older = state.livewireActivity.find(({ id }) => id === 'older-failed-action');
  const current = state.livewireActivity.find(({ id }) => id === 'current-server-failure');

  assert.equal(older.serverActivityIds, undefined);
  assert.deepEqual(state.livewireActivityProfileIds(older), [olderProfileId]);
  assert.deepEqual(state.livewireActivityProfileIds(current), [livewireSummary.id]);
  assert.equal(state.livewireActivitySourceLabel(current), 'app/Livewire/ControlPanel.php:42');
});

test('explains activity, phases, component links, and property states in plain language', () => {
  const { state, shell } = stateHarness();
  const item = {
    ...activity[1],
    status: 'failed_validation',
    changes: [{ path: 'count', serverKnown: false }],
    phases: [
      { name: 'Queued', at: 1 },
      { name: 'Responded', at: 2 },
      { name: 'Synced', at: 3 },
      { name: 'Rendered', at: 4 },
    ],
  };

  assert.equal(state.livewireActivityStatusLabel(item), 'Validation failed');
  assert.equal(
    state.livewireActivitySummary(item),
    'Control Panel tried to change count, but the update did not finish.',
  );
  assert.equal(state.livewireActivityComponent(activity[1]).id, 'root-1');
  assert.equal(state.livewireActivityComponent({ componentId: 'missing' }), null);
  assert.equal(state.livewirePropertyStateLabel({ state: 'Unknown' }), 'Not confirmed');
  assert.equal(
    state.livewirePropertyStateDescription({ state: 'Dirty' }),
    'The client value differs from the latest server value.',
  );
});

test('uses distinct plain-language explanations for every captured Livewire outcome', () => {
  const { state, shell } = stateHarness();
  const item = (values) => ({
    ...activity[2],
    status: 'complete',
    actions: [],
    changes: [],
    events: [],
    ...values,
  });

  assert.equal(state.livewireActivitySummary(item({ kind: 'mount' })), 'Metric Card was added to the page.');
  assert.equal(state.livewireActivitySummary(item({ kind: 'unmount' })), 'Metric Card was removed from the page.');
  assert.equal(state.livewireActivitySummary(item({ kind: 'poll' })), 'Metric Card asked the server for fresh state.');
  assert.equal(
    state.livewireActivitySummary(item({ kind: 'event', events: [{ name: 'saved' }] })),
    'Metric Card handled the saved event.',
  );
  assert.equal(state.livewireActivitySummary(item({ kind: 'event' })), 'Metric Card handled a Livewire event.');
  assert.equal(
    state.livewireActivitySummary(
      item({
        kind: 'mutation',
        changes: [{ path: 'count', serverKnown: true }],
      }),
    ),
    'Metric Card changed count and the server confirmed it.',
  );
  assert.equal(
    state.livewireActivitySummary(
      item({
        kind: 'mutation',
        changes: [
          { path: 'count', serverKnown: true },
          { path: 'label', serverKnown: true },
        ],
      }),
    ),
    'Metric Card changed 2 properties and the server confirmed them.',
  );
  assert.equal(
    state.livewireActivitySummary(
      item({
        kind: 'mutation',
        status: 'failed',
        changes: [{ path: 'count' }, { path: 'label' }],
      }),
    ),
    'Metric Card tried to change 2 properties, but the update did not finish.',
  );
  assert.equal(
    state.livewireActivitySummary(item({ kind: 'action', actions: [{ name: 'save' }] })),
    'Metric Card ran save on the server.',
  );
  assert.equal(
    state.livewireActivitySummary(item({ kind: 'action', status: 'failed', actions: [{ name: 'save' }] })),
    'Metric Card tried to run save, but the update did not finish.',
  );
  assert.equal(
    state.livewireActivitySummary(
      item({
        kind: 'action',
        actions: [{ name: 'save' }, { name: 'publish' }],
      }),
    ),
    'Metric Card ran 2 actions on the server.',
  );
  assert.equal(state.livewireActivitySummary(item({ kind: 'other' })), 'Metric Card completed a Livewire update.');
  assert.equal(state.livewireActivitySummary(null), '');

  assert.deepEqual(
    ['complete', 'updating', 'failed', 'failed_validation', 'cancelled', 'skipped', 'other'].map((status) =>
      state.livewireActivityStatusLabel({ status }),
    ),
    ['Finished', 'Running', 'Failed', 'Validation failed', 'Cancelled', 'Skipped', 'Recorded'],
  );
  assert.deepEqual(
    state.livewireActivityEvents(
      item({
        actions: [{ name: '__dispatch', params: ['saved', { id: 7 }] }],
      }),
    ),
    [
      {
        name: 'saved',
        params: { id: 7 },
        mode: 'received',
        declaredTarget: null,
        observedRecipientIds: [],
      },
    ],
  );
  assert.deepEqual(
    ['idle', 'updating', 'failed', 'stale', 'other'].map((status) =>
      state.livewireComponentStatusDescription({ status }),
    ),
    [
      'Mounted and waiting for the next update.',
      'A Livewire update is running.',
      'The latest Livewire update failed.',
      'Only server-captured state is available for this request.',
      'Component state was captured.',
    ],
  );
  assert.deepEqual(
    ['Synced', 'Dirty', 'Updating', 'Locked', 'Unknown', 'Other'].map((status) =>
      state.livewirePropertyStateDescription({ state: status }),
    ),
    [
      'Client and server values match.',
      'The client value differs from the latest server value.',
      'A Livewire update is in progress.',
      'Livewire prevents this property from being edited.',
      'No server-confirmed value was captured.',
      '',
    ],
  );
});

test('resets Livewire selection when browser navigation starts a new page session', () => {
  const trace = traceHarness({
    ready: true,
    pageSequence: 1,
    components: browserComponents,
    activity,
    dropped: { components: 0, activity: 0 },
  });
  const { state, shell } = stateHarness(trace);
  state.selectLivewireActivity('activity-1');

  trace.emit({
    ready: true,
    pageSequence: 2,
    components: [browserComponents[1]],
    activity: [],
    dropped: { components: 0, activity: 0 },
  });

  assert.equal(state.livewireSelectedActivityId, null);
  assert.equal(state.livewireSelectedComponentId, 'root-2');
  assert.equal(state.livewireActivitySelectionPinned, false);
});

test('builds an expandable typed property tree with proven edit eligibility', () => {
  const { state, shell } = stateHarness();
  let rows = state.livewirePropertyRows;

  assert.deepEqual(
    rows.map(({ path }) => path),
    ['count', 'settings', 'fixedLabel', 'optional'],
  );
  assert.equal(rows.find(({ path }) => path === 'count').state, 'Synced');
  assert.equal(rows.find(({ path }) => path === 'count').editable, true);
  assert.equal(rows.find(({ path }) => path === 'fixedLabel').state, 'Locked');
  assert.equal(rows.find(({ path }) => path === 'fixedLabel').editable, false);
  assert.equal(rows.find(({ path }) => path === 'optional').state, 'Synced');
  assert.equal(rows.find(({ path }) => path === 'optional').serverSummary, 'null');

  const settings = rows.find(({ path }) => path === 'settings');
  state.toggleLivewireProperty(settings);
  rows = state.livewirePropertyRows;
  assert.deepEqual(
    rows.map(({ path }) => path),
    ['count', 'settings', 'settings.enabled', 'settings.nested', 'fixedLabel', 'optional'],
  );
  assert.equal(rows.find(({ path }) => path === 'settings.enabled').editable, true);
  state.toggleLivewireProperty(rows.find(({ path }) => path === 'settings.nested'));
  assert.ok(state.livewirePropertyRows.some(({ path }) => path === 'settings.nested.label'));
  state.toggleLivewireProperty(settings);
  assert.equal(
    state.livewirePropertyRows.some(({ path }) => path === 'settings.enabled'),
    false,
  );
  state.toggleLivewireProperty({ hasChildren: false });
});

test('uses the current canonical component snapshot as the latest server value', () => {
  const components = structuredClone(browserComponents);
  components[0].properties[0].value = 8;
  components[0].serverProperties = [
    { path: 'count', type: 'Integer', value: 8 },
    {
      path: 'settings',
      type: 'Array',
      value: structuredClone(components[0].properties[1].value),
    },
  ];
  const trace = traceHarness({
    ready: true,
    components,
    activity,
    dropped: { components: 0, activity: 0 },
  });
  const { state, shell } = stateHarness(trace);

  let count = state.livewirePropertyRows.find(({ path }) => path === 'count');
  assert.equal(count.serverValue, 8);
  assert.equal(count.state, 'Synced');

  components[0].properties[0].value = 9;
  count = state.livewirePropertyRows.find(({ path }) => path === 'count');
  assert.equal(count.serverValue, 8);
  assert.equal(count.state, 'Dirty');

  const settings = state.livewirePropertyRows.find(({ path }) => path === 'settings');
  state.toggleLivewireProperty(settings);
  assert.equal(state.livewirePropertyRows.find(({ path }) => path === 'settings.enabled').state, 'Synced');
});

test('summarizes arrays, empty strings, booleans, and unknown client values', () => {
  const { state, shell } = stateHarness();
  const selected = state.livewireTrace.components.find(({ id }) => id === 'root-1');
  selected.properties.push(
    { path: 'items', type: 'Array', value: ['Only item'] },
    { path: 'empty', type: 'String', value: '' },
    { path: 'disabled', type: 'Boolean', value: false },
    { path: 'missing', type: 'Unknown', value: undefined },
  );
  const server = state.livewireServerComponents.find(({ id }) => id === 'root-1');
  server.properties.push(
    {
      path: 'items',
      type: 'Array',
      server_value: null,
      writable: false,
      array_leaf_writable: true,
      write_allowed: true,
    },
    {
      path: 'empty',
      type: 'String',
      server_value: '',
      writable: true,
      array_leaf_writable: false,
      write_allowed: true,
    },
    {
      path: 'disabled',
      type: 'Boolean',
      server_value: false,
      writable: true,
      array_leaf_writable: false,
      write_allowed: true,
    },
    {
      path: 'missing',
      type: 'Unknown',
      server_value: null,
      writable: false,
      array_leaf_writable: false,
      write_allowed: false,
    },
  );

  let rows = state.livewirePropertyRows;
  assert.equal(rows.find(({ path }) => path === 'items').valueSummary, '1 item');
  assert.equal(rows.find(({ path }) => path === 'empty').valueSummary, 'Empty string');
  assert.equal(rows.find(({ path }) => path === 'disabled').valueSummary, 'false');
  assert.equal(rows.find(({ path }) => path === 'missing').valueSummary, 'undefined');
  state.toggleLivewireProperty(rows.find(({ path }) => path === 'items'));
  rows = state.livewirePropertyRows;
  assert.equal(rows.find(({ path }) => path === 'items.0').valueSummary, 'Only item');
});

test('keeps property edits as drafts until an explicit successful apply', async () => {
  const { state, shell, trace } = stateHarness();
  const count = state.livewirePropertyRows.find(({ path }) => path === 'count');
  state.editLivewireProperty(count);
  const key = state.livewireDraftKey(count);
  state.livewireDrafts[key].value = '5';

  const optional = state.livewirePropertyRows.find(({ path }) => path === 'optional');
  state.editLivewireProperty(optional);
  assert.deepEqual(Object.keys(state.livewireDrafts), [state.livewireDraftKey(optional)]);
  state.editLivewireProperty(count);
  state.livewireDrafts[key].value = '5';
  const focused = [];
  const trigger = {
    dataset: { ndbLivewireEditKey: key },
    focus: () => focused.push('count'),
  };

  assert.equal(browserComponents[0].properties[0].value, 1);
  assert.equal(await state.applyLivewireDraft(count, trigger), true);
  assert.deepEqual(trace.calls.at(-1), [
    'apply',
    {
      componentId: 'root-1',
      path: 'count',
      baseline: 1,
      value: 5,
    },
  ]);
  assert.equal(state.livewireDrafts[key], undefined);
  assert.deepEqual(focused, ['count']);

  state.editLivewireProperty(count);
  state.livewireDrafts[key].type = 'Integer';
  state.livewireDrafts[key].value = 'five';
  assert.equal(await state.applyLivewireDraft(count), false);
  assert.match(state.livewireDrafts[key].error, /whole number/i);
  state.cancelLivewireDraft(count);
  assert.equal(state.livewireDrafts[key], undefined);
  state.editLivewireProperty({ ...count, editable: false });
  assert.equal(state.livewireDrafts[key], undefined);
});

test('clears property drafts only when leaving their component context', () => {
  const { state, shell } = stateHarness();
  const count = state.livewirePropertyRows.find(({ path }) => path === 'count');
  state.editLivewireProperty(count);
  const key = state.livewireDraftKey(count);

  assert.ok(state.livewireDrafts[key]);

  state.setLivewireTab('activity');
  assert.deepEqual(state.livewireDrafts, {});

  state.setLivewireTab('components');
  state.editLivewireProperty(count);
  state.selectLivewireComponent('child-1');
  assert.deepEqual(state.livewireDrafts, {});
});

test('keeps a closing draft alive until Alpine removes its popover', () => {
  const { state, shell } = stateHarness();
  const count = state.livewirePropertyRows.find(({ path }) => path === 'count');
  const ticks = [];
  state.$nextTick = (callback) => ticks.push(callback);
  state.editLivewireProperty(count);
  const key = state.livewireDraftKey(count);

  state.cancelLivewireDraft(count);

  assert.equal(state.livewireDrafts[key].status, 'closing');
  assert.equal(ticks.length, 1);

  ticks.shift()();
  assert.equal(state.livewireDrafts[key], undefined);
});

test('toggles a property editor without replacing its trigger', () => {
  const { state, shell } = stateHarness();
  const count = state.livewirePropertyRows.find(({ path }) => path === 'count');
  const key = state.livewireDraftKey(count);

  state.toggleLivewirePropertyEditor(count);
  assert.equal(state.livewireDrafts[key].status, 'editing');

  state.toggleLivewirePropertyEditor(count);
  assert.equal(state.livewireDrafts[key], undefined);
});

test('returns focus to the stable property editor after applying', () => {
  const { browser, state } = stateHarness();
  const count = state.livewirePropertyRows.find(({ path }) => path === 'count');
  const focused = [];
  const trigger = {
    dataset: { ndbLivewireEditKey: state.livewireDraftKey(count) },
    isConnected: true,
    focus: () => focused.push('trigger'),
  };

  state.focusLivewirePropertyEditor(count, trigger);

  assert.deepEqual(focused, ['trigger']);

  state.$root.querySelectorAll = () => [
    {
      dataset: { ndbLivewireEditKey: 'other:key' },
      focus: () => focused.push('other'),
    },
    {
      dataset: { ndbLivewireEditKey: state.livewireDraftKey(count) },
      focus: () => focused.push('count'),
    },
  ];
  browser.queryAll = state.$root.querySelectorAll;

  state.focusLivewirePropertyEditor(count, { ...trigger, isConnected: false });

  assert.deepEqual(focused, ['trigger', 'count']);

  state.$root.querySelectorAll = () => [];
  browser.queryAll = state.$root.querySelectorAll;
  state.focusLivewirePropertyEditor(count);
  assert.deepEqual(focused, ['trigger', 'count']);
});

test('supports boolean, float, string, and null replacement controls', () => {
  const { state, shell } = stateHarness();
  const optional = state.livewirePropertyRows.find(({ path }) => path === 'optional');
  state.editLivewireProperty(optional);
  const draft = state.livewireDrafts[state.livewireDraftKey(optional)];

  draft.type = 'Boolean';
  state.toggleLivewireBoolean(optional);
  assert.equal(state.livewireMutationValue(draft), true);
  draft.type = 'Float';
  draft.value = '2.5';
  assert.equal(state.livewireMutationValue(draft), 2.5);
  draft.value = '';
  assert.throws(() => state.livewireMutationValue(draft), /valid number/i);
  draft.type = 'String';
  draft.value = 42;
  assert.equal(state.livewireMutationValue(draft), '42');
});

test('falls back to stored server evidence when browser evidence is unavailable', () => {
  const trace = traceHarness({
    ready: false,
    components: [],
    activity: [],
    dropped: { components: 0, activity: 0 },
  });
  const { state, shell } = sectionHarness('livewire', livewireSummary, runtime(), [], 20, trace);
  state.$root = { querySelectorAll: () => [], querySelector: () => null };
  state.$nextTick = (callback) => callback();
  state.init();
  state.mergeLivewireServer({
    components: [
      {
        id: 'stored-1',
        name: 'benchmark.stored',
        title: 'Stored',
        parent_id: null,
        properties: [
          {
            path: 'label',
            type: 'String',
            server_value: 'Stored value',
            writable: true,
          },
        ],
      },
    ],
    activity_records: serverActivity['stored-activity'],
  });

  assert.equal(state.livewireComponents[0].status, 'stale');
  assert.equal(state.livewireComponents[0].properties[0].value, 'Stored value');
  assert.equal(state.livewireActivity[0].title, 'Save ran');
  assert.equal(state.livewireActivity[0].actions[0].name, 'save');
  assert.equal(state.livewireActivity[0].changes[0].path, 'label');
  assert.equal(state.livewireActivity[0].events[0].name, 'stored-updated');
  assert.deepEqual(state.livewireActivity[0].effects, { redirect: true });
  state.destroy();
  assert.deepEqual(trace.calls.at(-1), ['unsubscribe']);
});

test('pairs retained initial render evidence with a trace-ready browser mount', () => {
  const browserMount = {
    ...activity[0],
    durationMs: null,
    phases: [],
  };
  const trace = traceHarness({
    ready: true,
    components: [browserComponents[0]],
    activity: [browserMount],
    dropped: { components: 0, activity: 0 },
  });
  const { state, shell } = sectionHarness('livewire', livewireSummary, runtime(), [], 20, trace);
  state.$root = { querySelectorAll: () => [], querySelector: () => null };
  state.$nextTick = (callback) => callback();
  state.init();
  state.mergeLivewireServer({
    components: [],
    activity_records: serverActivity['root-1-server-1'],
  });

  assert.equal(state.livewireActivity.length, 2);
  assert.deepEqual(
    state.livewireActivity.map(({ id, sequence }) => [id, sequence]),
    [
      ['activity-1', 1],
      ['server-only-server-3', 2],
    ],
  );
  assert.equal(state.livewireActivity[0].id, 'activity-1');
  assert.equal(state.livewireActivity[0].serverMountId, 'root-1-server-1');
  assert.equal(state.livewireActivity[0].serverRenderId, 'root-1-server-2');
  assert.equal(state.livewireActivity[0].requestAtMs, 12.345);
  assert.equal(state.livewireActivity[0].initialRenderDurationMs, 2.75);
  assert.equal(state.livewireActivityTime(state.livewireActivity[0]), '+12.35 ms');
  assert.equal(state.livewireActivityDuration(state.livewireActivity[0]), 'Render 2.75 ms');
  assert.equal(state.livewireMountTime(state.livewireActivity[0]), '+12.35 ms');
  assert.equal(state.livewireInitialRenderDuration(state.livewireActivity[0]), '2.75 ms');
  assert.deepEqual(state.livewireActivity[1].serverActivityIds, ['server-only-server-3']);
  assert.deepEqual(state.livewireActivity[1].serverRenderIds, ['server-only-server-4']);
  assert.equal(state.livewireActivity[1].serverRenderDurationMs, 0.75);
});

test('reconciles retained lifecycle evidence without dropping browser-only or orphan records', () => {
  const traceActivity = [
    {
      ...activity[2],
      id: 'browser-action',
      sequence: 1,
      componentId: 'root-1',
      status: 'complete',
      actions: [{ name: 'refresh' }],
    },
    {
      ...activity[1],
      id: 'browser-change',
      sequence: 2,
      kind: 'mutation',
      actions: [],
      changes: [{ path: 'count', before: 1, submitted: 2, server: 2 }],
    },
    {
      ...activity[1],
      id: 'browser-dispatch',
      sequence: 3,
      kind: 'event',
      actions: [
        { name: '__dispatch', params: ['saved'] },
        { name: '__dispatch', params: [] },
      ],
      changes: [],
      events: [],
    },
    {
      ...activity[1],
      id: 'browser-received',
      sequence: 4,
      kind: 'event_received',
      actions: [],
      changes: [],
      events: [{ name: 'synced' }],
    },
    {
      ...activity[1],
      id: 'browser-hydrate',
      sequence: 5,
      kind: 'hydrate',
      actions: [],
      changes: [],
      events: [],
    },
    {
      ...activity[2],
      id: 'browser-only',
      sequence: 4,
      componentId: 'child-1',
      status: 'complete',
      actions: [{ name: 'browserOnly' }],
    },
  ];
  const trace = traceHarness({
    ready: true,
    components: browserComponents,
    activity: traceActivity,
    dropped: { components: 0, activity: 0 },
  });
  const { state, shell } = sectionHarness('livewire', livewireSummary, runtime(), [], 20, trace);
  state.$root = { querySelectorAll: () => [], querySelector: () => null };
  state.$nextTick = (callback) => callback();
  state.init();
  state.mergeLivewireServer({
    components: [],
    activity_records: serverActivity['orphan-render'],
  });

  const reconciled = Object.fromEntries(state.livewireActivity.map((item) => [item.id, item]));

  assert.equal(state.livewireActivity.length, 8);
  assert.deepEqual(reconciled['browser-action'].serverActivityIds, ['action-1', 'action-2']);
  assert.deepEqual(reconciled['browser-action'].serverRenderIds, ['action-render-1', 'action-render-2']);
  assert.equal(reconciled['browser-action'].requestAtMs, 8);
  assert.equal(reconciled['browser-action'].serverRenderDurationMs, 3.25);
  assert.deepEqual(reconciled['browser-change'].serverActivityIds, ['change-1']);
  assert.equal(reconciled['browser-change'].serverRenderDurationMs, null);
  assert.deepEqual(reconciled['browser-dispatch'].serverActivityIds, ['event-1']);
  assert.deepEqual(reconciled['browser-received'].serverActivityIds, ['received-1']);
  assert.deepEqual(reconciled['browser-hydrate'].serverActivityIds, ['hydrate-1']);
  assert.equal(reconciled['browser-only'].serverActivityIds, undefined);
  assert.equal(reconciled['orphan-render'].kind, 'render');
  assert.deepEqual(reconciled['server-mount'].serverRenderIds, ['server-mount-render']);
  assert.equal(reconciled['server-mount'].initialRenderDurationMs, null);
  assert.equal(reconciled['server-mount'].serverMountId, 'server-mount');
  assert.equal(reconciled['server-mount'].serverRenderId, 'server-mount-render');
  assert.deepEqual(
    state.livewireActivity.map((item) => item.sequence),
    [1, 2, 3, 4, 5, 6, 7, 8],
  );
  assert.deepEqual(
    state.livewireActivity.slice(-3).map((item) => item.id),
    ['browser-only', 'browser-received', 'server-mount'],
  );
});

test('formats sparse Livewire evidence as deliberate unavailable and zero states', () => {
  const { state, shell } = stateHarness();

  assert.equal(state.livewireInitialRenderDuration({ initialRenderDurationMs: null }), 'Not captured');
  assert.equal(state.livewireInitialRenderDuration({ initialRenderDurationMs: 'invalid' }), 'Not captured');
  assert.equal(state.livewireMountTime({ requestAtMs: null }), 'Not captured');
  assert.equal(state.livewireMountTime({ requestAtMs: 'invalid' }), 'Not captured');
  assert.equal(state.livewireActivityTime({ kind: 'action', occurredAt: 0 }), 'Current request');
  assert.equal(
    state.livewireActivityDuration({
      kind: 'mount',
      initialRenderDurationMs: null,
    }),
    'Render —',
  );
  assert.equal(
    state.livewireActivityDuration({
      kind: 'action',
      durationMs: 12,
      status: 'complete',
    }),
    '12 ms',
  );
  assert.equal(state.livewireComponentPropertyCount(null), 0);
  assert.equal(state.livewireComponentPropertyCountLabel({ properties: [{}] }), '1 property');
  assert.equal(
    state.livewireComponentPropertyStateSummary({
      id: 'not-selected',
      properties: [],
    }),
    '0 changed, 0 editable',
  );
});
