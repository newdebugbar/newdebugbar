import assert from 'node:assert/strict';
import test from 'node:test';

import { runtime, summary, inspectorHarness } from './state-test-support.js';

test('query workspace filters selects and keeps explain evidence scoped to the active execution', async () => {
  const browser = runtime();
  const { state, shell } = inspectorHarness('queries', { ...summary, query_count: 4 }, browser);
  const appended = [];
  const scrolls = [];
  let detailFocused = 0;
  let rowFocused = 0;
  let highlighted = 0;
  const style = () => ({ removeProperty() {}, setProperty() {} });
  const item = (key, execution, duration, type, attention, slow, repeated, search, count = 1) => ({
    dataset: {
      ndbQueryKey: key,
      ndbExecution: String(execution),
      ndbDuration: String(duration),
      ndbQueryType: type,
      ndbAttention: String(attention),
      ndbSlow: String(slow),
      ndbRepeated: String(repeated),
      ndbSearch: search,
      ndbQueryExecutionCount: String(count),
    },
    hidden: false,
    isConnected: true,
    style: style(),
    focus: () => rowFocused++,
  });
  const group = item('group-users', 1, 10, 'read', true, false, true, 'select users 1 2', 2);
  const slowWrite = item('query-3', 3, 20, 'write', true, true, false, 'update clinics 42');
  const normalRead = item('query-4', 4, 8, 'read', false, false, false, 'select clinics 42');
  const rows = [group, slowWrite, normalRead];
  const records = [
    {
      key: 'group-users',
      executions: [
        {
          execution: 1,
          explain_available: true,
          explain: null,
          explain_error: null,
          source_available: true,
          stack: [],
        },
        {
          execution: 2,
          explain_available: true,
          explain: null,
          explain_error: null,
          source_available: false,
          stack: [],
        },
      ],
    },
    {
      key: 'query-3',
      executions: [{ execution: 3, explain_available: false }],
    },
    { key: 'query-4', executions: [{ execution: 4, explain_available: true }] },
  ];
  const queryList = {
    querySelectorAll: () => rows,
    querySelector: (selector) => (selector.includes('ndb-repeated') ? group : slowWrite),
    appendChild: (child) => appended.push(child),
  };
  const queryDetail = {
    scrollTop: 42,
    scrollTo: (options) => scrolls.push(options),
    focus: () => detailFocused++,
  };

  browser.activeElement = () => group;
  browser.highlight = () => highlighted++;
  state.$refs = { queryList, queryDetail };
  state.$nextTick = (callback) => callback();
  state.initializeQueries(records);

  assert.equal(state.querySelected, 'group-users');
  assert.equal(state.querySelectedExecution, 1);
  assert.equal(state.queryDetailOpen, false);
  assert.equal(state.queryDetailTab, 'overview');
  assert.equal(state.visibleQueryCount, 4);

  state.setQueryFilter('write');
  assert.equal(group.hidden, true);
  assert.equal(slowWrite.hidden, false);
  assert.equal(normalRead.hidden, true);
  assert.equal(state.visibleQueryCount, 1);
  assert.equal(state.querySelected, 'query-3');

  state.querySearch = 'users';
  state.setQueryFilter('read');
  assert.equal(group.hidden, false);
  assert.equal(normalRead.hidden, true);
  assert.equal(state.visibleQueryCount, 2);
  assert.equal(state.querySelected, 'group-users');

  state.querySearch = '';
  state.setQueryFilter('all');
  appended.length = 0;
  state.toggleQuerySort('duration');
  assert.deepEqual(appended, [slowWrite, group, normalRead]);
  assert.equal(state.querySort, 'duration');
  assert.equal(state.querySortDirection, 'desc');
  appended.length = 0;
  state.toggleQuerySort('duration');
  assert.deepEqual(appended, [normalRead, group, slowWrite]);
  assert.equal(state.querySortDirection, 'asc');
  appended.length = 0;
  state.toggleQuerySort('duration');
  assert.deepEqual(appended, [group, slowWrite, normalRead]);
  assert.equal(state.querySort, 'execution');
  assert.equal(state.querySortDirection, 'asc');

  state.selectQueryRecord('group-users');
  assert.equal(state.queryDetailOpen, true);
  assert.equal(state.queryDetailTab, 'overview');
  browser.viewportWidth = () => 390;
  state.selectQueryRecord('group-users');
  assert.equal(detailFocused, 1);
  state.setQueryDetailTab('bindings');
  assert.equal(state.queryDetailTab, 'overview');
  state.selectQueryExecution(2);
  assert.equal(state.querySelectedExecution, 2);
  assert.equal(state.queryDetailTab, 'overview');
  assert.equal(state.selectedQueryHasSource, false);
  state.setQueryDetailTab('source');
  assert.equal(state.queryDetailTab, 'overview');

  const explained = [];
  const wire = { explainQuery: async (execution) => explained.push(execution) };
  await state.openQueryExplain(wire);
  assert.equal(state.queryDetailTab, 'explain');
  assert.deepEqual(explained, [2]);
  assert.equal(state.queryExplainLoading, true);
  assert.equal(records[0].executions[1].explain_loading, true);
  assert.equal(state.queryExplainScrollTop, 42);
  await state.openQueryExplain(wire);
  assert.deepEqual(explained, [2]);
  state.receiveQueryExplain({
    profileId: shell.summary.id,
    execution: 1,
    error: 'Older execution failed.',
  });
  assert.equal(state.queryExplainLoading, true);
  assert.equal(records[0].executions[0].explain_error, 'Older execution failed.');
  state.receiveQueryExplain({
    profileId: shell.summary.id,
    execution: 'invalid',
  });
  state.receiveQueryExplain({
    profileId: shell.summary.id,
    execution: 2,
    explain: {
      mode: 'EXPLAIN QUERY PLAN',
      driver: 'sqlite',
      rows: [{ detail: 'SCAN users' }],
    },
    error: null,
  });
  assert.equal(state.queryExplain.mode, 'EXPLAIN QUERY PLAN');
  assert.equal(records[0].executions[1].explain.driver, 'sqlite');
  assert.equal(records[0].executions[1].explain_loading, false);
  assert.deepEqual(scrolls.at(-1), { top: 42, behavior: 'instant' });
  state.setQueryDetailTab('overview');
  await state.openQueryExplain(wire);
  assert.deepEqual(explained, [2]);

  state.failQueryExplain();
  assert.equal(state.queryExplainLoading, false);
  assert.equal(state.queryExplainError, 'EXPLAIN could not be completed.');
  assert.equal(state.formatQueryType('read'), 'Read');
  assert.equal(state.formatQueryType(''), 'Query');
  assert.equal(state.formatQueryEvidence(null), 'No evidence was captured.');
  assert.equal(state.formatQueryEvidence('SCAN users'), 'SCAN users');
  assert.equal(state.formatQueryEvidence({ ready: true }), '{\n  "ready": true\n}');

  const code = {
    textContent: 'select * from users',
    removeAttribute(attribute) {
      this.removed = attribute;
    },
  };
  state.highlightQueryCode(code);
  assert.equal(code.removed, 'data-highlighted');
  state.highlightQueryCode(null);

  state.selectQueryRecord('query-3');
  assert.equal(state.beginQueryExplain(), null);
  await state.openQueryExplain(wire);
  assert.deepEqual(explained, [2]);
  state.selectQueryRecord('group-users');
  state.selectQueryExecution(2);

  state.closeQueryDetail();
  assert.equal(state.queryDetailOpen, false);
  assert.equal(detailFocused, 3);
  assert.equal(rowFocused, 1);
  assert.ok(highlighted > 0);

  group.isConnected = false;
  state.selectQueryRecord('group-users');
  state.closeQueryDetail();
  assert.equal(detailFocused, 4);
  assert.equal(rowFocused, 2);
  state.focusQueryFinding('invalid');
  queryList.querySelector = () => null;
  state.focusQueryFinding('slow');
  assert.equal(state.queryFocusFilter, null);

  assert.equal(
    state.compareQueries(
      { dataset: { ndbDuration: '10', ndbExecution: '1' } },
      { dataset: { ndbDuration: '10', ndbExecution: '2' } },
    ),
    -1,
  );

  state.setQueryFilter('invalid');
  state.toggleQuerySort('invalid');
  state.selectQueryRecord('missing');
  state.selectQueryExecution(999);
  state.setQueryDetailTab('missing');
  assert.equal(state.queryFilter, 'all');
  assert.equal(state.querySort, 'execution');
  assert.equal(state.querySelected, 'group-users');
  assert.equal(state.querySelectedExecution, 1);
  assert.equal(state.queryDetailTab, 'overview');

  state.initializeQueries('invalid');
  assert.deepEqual(state.queryRecords, []);
  assert.equal(state.querySelected, null);
});

test('EXPLAIN ignores success and failure from a replaced profile with the same execution', async () => {
  const firstId = '6ba7b810-9dad-41d1-80b4-00c04fd430c8';
  const secondId = '550e8400-e29b-41d4-a716-446655440000';
  const { state, shell } = inspectorHarness('queries', { ...summary, id: firstId }, runtime());
  const records = () => [{ key: 'query-1', executions: [{ execution: 1, explain_available: true }] }];
  state.initializeQueries(records());
  let rejectExplain;
  const pending = state.runQueryExplain({
    explainQuery: () =>
      new Promise((resolve, reject) => {
        rejectExplain = reject;
      }),
  });

  shell.switchProfile({ ...summary, id: secondId });
  state.destroy();
  const next = shell.createInspector('queries');
  next.initializeQueries(records());
  next.beginQueryExplain();
  next.receiveQueryExplain({
    profileId: firstId,
    execution: 1,
    explain: { rows: ['old profile'] },
  });
  assert.equal(next.queryExplain, null);
  assert.equal(next.queryExplainLoading, true);
  rejectExplain(new Error('old request failed'));
  await pending;
  assert.equal(next.queryExplainError, null);
  assert.equal(next.queryExplainLoading, true);

  next.receiveQueryExplain({
    profileId: secondId,
    execution: 1,
    explain: { rows: ['selected profile'] },
  });
  assert.deepEqual(next.queryExplain.rows, ['selected profile']);
  assert.equal(next.queryExplainLoading, false);
});
