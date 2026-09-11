import assert from 'node:assert/strict';
import test from 'node:test';

import { runtime, summary, inspectorHarness } from './state-test-support.js';

test('log controls combine severity channel and search without losing record counts', () => {
  const { state, shell } = inspectorHarness('logs', summary, runtime());
  let detailFocuses = 0;
  let detailScrolls = 0;
  let rowFocuses = 0;
  const item = (level, attention, channel, search, count = 1, sequence = 1) => ({
    dataset: {
      ndbLogLevel: level,
      ndbLogAttention: String(attention),
      ndbLogChannel: channel,
      ndbLogSearchText: search,
      ndbLogRecordCount: String(count),
      ndbLogFirstSequence: String(sequence),
    },
    hidden: false,
    focus: () => rowFocuses++,
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
  const info = item('info', false, 'audit', 'request ready actor planner', 1, 1);
  const warning = item('warning', true, 'stack', 'partner slow trip 41', 3, 2);
  const error = item('error', true, 'stack', 'database unavailable orders.php', 1, 3);
  const items = [info, warning, error];
  state.$refs = {
    logList: { children: items },
    logDetail: {
      focus: () => detailFocuses++,
      scrollTo: () => detailScrolls++,
    },
  };
  state.$root = {
    querySelector: (selector) =>
      items.find((entry) => selector.includes(entry.dataset.ndbLogFirstSequence)) ?? null,
  };
  state.$nextTick = (callback) => callback();

  state.initializeLogs();
  assert.equal(state.logLevel, 'all');
  assert.equal(state.logChannel, 'all');
  assert.equal(state.logDetailSequence, null);
  assert.equal(state.logDetailOpen, false);
  assert.equal(state.visibleLogCount, 5);
  assert.equal(state.visibleLogGroupCount, 3);

  state.selectLogEntry(1);
  assert.equal(state.logDetailSequence, 1);
  assert.equal(state.logDetailOpen, true);
  assert.equal(detailScrolls, 1);
  state.setLogLevel('attention');
  assert.equal(state.logDetailSequence, null);
  assert.equal(state.logDetailOpen, false);
  assert.equal(info.hidden, true);
  assert.equal(info.style.display, 'none');
  assert.equal(warning.hidden, false);
  assert.equal(error.hidden, false);
  assert.equal(state.visibleLogCount, 4);
  assert.equal(state.visibleLogGroupCount, 2);

  state.selectLogEntry(3);
  assert.equal(state.logDetailSequence, 3);
  assert.equal(state.logDetailOpen, true);
  state.setLogLevel('error');
  assert.equal(state.logDetailSequence, 3);
  assert.equal(state.logDetailOpen, true);
  assert.equal(info.hidden, true);
  assert.equal(warning.hidden, true);
  assert.equal(error.hidden, false);
  assert.equal(state.visibleLogCount, 1);
  assert.equal(state.visibleLogGroupCount, 1);

  state.logSearch = 'UNAVAILABLE';
  state.applyLogFilters();
  assert.equal(error.hidden, false);

  state.closeLogDetail();
  assert.equal(state.logDetailOpen, false);
  assert.equal(state.logDetailSequence, 3);
  assert.equal(rowFocuses, 1);

  state.setLogLevel('missing');
  assert.equal(state.logLevel, 'error');

  state.setLogLevel('all');
  state.logSearch = '';
  state.setLogChannel('audit');
  assert.equal(info.hidden, false);
  assert.equal(warning.hidden, true);
  assert.equal(error.hidden, true);
  assert.equal(state.visibleLogCount, 1);

  state.setLogChannel('missing');
  assert.equal(state.logChannel, 'audit');

  state.selectLogEntry(3);
  assert.equal(state.logDetailSequence, null);
  assert.equal(detailFocuses, 0);

  state.$refs = {};
  state.setLogChannel('all');
  assert.equal(state.visibleLogCount, 0);
  assert.equal(state.visibleLogGroupCount, 0);
});
