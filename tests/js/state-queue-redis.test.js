import assert from 'node:assert/strict';
import test from 'node:test';

import { runtime, summary, inspectorHarness, listRow } from './state-test-support.js';

function row(dataset, focused) {
  return { ...listRow(dataset), focus: (options) => focused.push([dataset, options]) };
}

test('Queue searches, filters, selects, and restores mobile list focus', () => {
  const browser = runtime();
  const { state, shell } = inspectorHarness('queue', summary, browser);
  const focused = [];
  const detailScrolls = [];
  const contentScrolls = [];
  const rows = [
    row({ ndbQueueExecution: '1', ndbQueueGroup: 'waiting' }, focused),
    row({ ndbQueueExecution: '2', ndbQueueGroup: 'failed' }, focused),
    row({ ndbQueueExecution: '3', ndbQueueGroup: 'completed' }, focused),
  ];
  state.$refs = {
    queueList: { children: rows },
    queueDetail: {
      scrollTo: (options) => detailScrolls.push(options),
      focus: (options) => focused.push(['detail', options]),
    },
    content: { scrollTo: (options) => contentScrolls.push(options) },
  };
  state.$nextTick = (callback) => callback();

  state.initializeQueue([
    {
      search: 'sendreceipt redis mail',
      execution: 1,
      status_group: 'waiting',
      job: 'SendReceipt',
      connection: 'redis',
      queue: 'mail',
    },
    {
      search: 'syncinvoice runtimeexception',
      execution: 2,
      status_group: 'failed',
      job: 'SyncInvoice',
      exception_class: 'RuntimeException',
    },
    {
      search: 'reindexsearch completed',
      execution: 3,
      status_group: 'completed',
      job: 'ReindexSearch',
      status: 'completed',
    },
  ]);

  assert.equal(state.queueFilter, 'all');
  assert.equal(state.queueSelected, 1);
  assert.equal(state.queueDetailOpen, false);
  assert.equal(state.visibleQueueCount, 3);
  assert.equal(state.selectedQueueActivity.execution, 1);

  state.setQueueFilter('failed');
  assert.equal(rows[0].hidden, true);
  assert.equal(rows[1].hidden, false);
  assert.equal(rows[2].hidden, true);
  assert.equal(state.visibleQueueCount, 1);
  assert.equal(state.queueSelected, 2);

  state.setQueueFilter('all');
  state.queueSearch = 'redis';
  state.applyQueueView();
  assert.equal(rows[0].hidden, false);
  assert.equal(rows[1].hidden, true);
  assert.equal(state.queueSelected, 1);

  state.queueSearch = '';
  state.applyQueueView();
  state.selectQueueActivity(2);
  assert.equal(state.queueSelected, 2);
  assert.equal(state.queueDetailOpen, true);
  assert.equal(detailScrolls.length, 1);
  assert.equal(contentScrolls.length, 1);
  assert.deepEqual(focused[0], ['detail', { preventScroll: true }]);

  state.setQueueFilter('invalid');
  state.selectQueueActivity(99);
  assert.equal(state.queueFilter, 'all');
  assert.equal(state.queueSelected, 2);

  state.closeQueueDetail();
  assert.equal(state.queueDetailOpen, false);
  assert.deepEqual(focused.at(-1), [rows[1].dataset, { preventScroll: true }]);

  state.queueSearch = 'missing';
  state.applyQueueView();
  assert.equal(state.visibleQueueCount, 0);
  assert.equal(state.queueSelected, null);

  state.initializeQueue('invalid');
  assert.deepEqual(state.queueActivities, []);
  assert.equal(state.queueSelected, null);
});

test('Queue preserves retained attempts while filtering and changing the selection', () => {
  const { state, shell } = inspectorHarness('queue', summary, runtime());
  const rows = [
    row({ ndbQueueExecution: '1', ndbQueueGroup: 'completed' }, []),
    row({ ndbQueueExecution: '2', ndbQueueGroup: 'failed' }, []),
  ];
  state.$refs = {
    queueList: { children: rows },
    queueDetail: { scrollTo() {}, focus() {} },
    content: { scrollTo() {} },
  };
  state.$nextTick = (callback) => callback();

  const linked = {
    search: 'sendreceipt',
    execution: 1,
    status_group: 'completed',
    job: 'SendReceipt',
    attempts: [{ sequence: 1, profile_id: 'linked-worker' }],
  };
  const unlinked = {
    search: 'syncinvoice',
    execution: 2,
    status_group: 'failed',
    job: 'SyncInvoice',
    attempts: [],
  };

  state.initializeQueue([linked, unlinked]);
  assert.equal(state.selectedQueueActivity.attempts.length, 1);

  state.selectQueueActivity(2);
  assert.deepEqual(state.selectedQueueActivity.attempts, []);

  state.selectQueueActivity(1);
  state.setQueueFilter('failed');
  assert.equal(state.queueSelected, 2);

  state.initializeQueue([{ ...linked, attempts: [] }]);
  assert.deepEqual(state.selectedQueueActivity.attempts, []);
});

test('Redis builds bounded search state and keeps failed filtering truthful', () => {
  const browser = runtime();
  const { state, shell } = inspectorHarness('redis', summary, browser);
  const focused = [];
  const rows = [
    row({ ndbRedisExecution: '1', ndbRedisFailed: 'false' }, focused),
    row({ ndbRedisExecution: '2', ndbRedisFailed: 'true' }, focused),
    row({ ndbRedisExecution: '3', ndbRedisFailed: 'false' }, focused),
  ];
  state.$refs = {
    redisList: { children: rows },
    redisDetail: {
      scrollTo() {},
      focus: (options) => focused.push(['detail', options]),
    },
    content: { scrollTo() {} },
  };
  state.$nextTick = (callback) => callback();

  state.initializeRedis([
    {
      search: 'get default trip:kyoto',
      execution: 1,
      command: 'GET',
      connection: 'default',
      keys: ['trip:kyoto'],
      key_hashes: [],
    },
    {
      search: 'hget sessions 18b0b12c34d56e78 runtimeexception',
      execution: 2,
      command: 'HGET',
      connection: 'sessions',
      keys: [],
      key_hashes: ['18b0b12c34d56e78'],
      exception_class: 'RuntimeException',
    },
    {
      search: 'flushdb maintenance',
      execution: 3,
      command: 'FLUSHDB',
      connection: 'maintenance',
      keys: [],
      key_hashes: [],
    },
  ]);

  assert.equal(state.redisFilter, 'all');
  assert.equal(state.redisSelected, 1);
  assert.equal(state.redisDetailOpen, false);
  assert.equal(state.visibleRedisCount, 3);
  assert.equal(state.selectedRedisCommand.command, 'GET');
  assert.match(state.redisCommands[0].search, /trip:kyoto/);
  assert.match(state.redisCommands[1].search, /runtimeexception/);

  state.setRedisFilter('failed');
  assert.equal(rows[0].hidden, true);
  assert.equal(rows[1].hidden, false);
  assert.equal(rows[2].hidden, true);
  assert.equal(state.redisSelected, 2);
  assert.equal(state.visibleRedisCount, 1);

  state.setRedisFilter('all');
  state.redisSearch = 'maintenance';
  state.applyRedisView();
  assert.equal(rows[2].hidden, false);
  assert.equal(state.redisSelected, 3);

  state.redisSearch = '18b0b12c';
  state.applyRedisView();
  assert.equal(rows[1].hidden, false);
  assert.equal(state.redisSelected, 2);

  state.redisSearch = '';
  state.applyRedisView();
  state.selectRedisCommand(1);
  assert.equal(state.redisDetailOpen, true);
  assert.deepEqual(focused.at(-1), ['detail', { preventScroll: true }]);

  state.setRedisFilter('succeeded');
  state.selectRedisCommand(99);
  assert.equal(state.redisFilter, 'all');
  assert.equal(state.redisSelected, 1);

  state.closeRedisDetail();
  assert.equal(state.redisDetailOpen, false);
  assert.deepEqual(focused.at(-1), [rows[0].dataset, { preventScroll: true }]);

  state.initializeRedis(null);
  assert.deepEqual(state.redisCommands, []);
  assert.equal(state.redisSelected, null);
});
