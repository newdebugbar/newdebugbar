import assert from 'node:assert/strict';
import test from 'node:test';

import { runtime, summary, sectionHarness, listRow } from './state-test-support.js';

test('HTTP client filters failures and slow requests while keeping one selected', () => {
  const browser = runtime();
  const { state, shell } = sectionHarness('http_client', summary, browser);
  let detailResets = 0;
  const element = (execution, duration, failed, slow, search) =>
    listRow({
      ndbExecution: String(execution),
      ndbDuration: String(duration),
      ndbFailed: String(failed),
      ndbSlow: String(slow),
      ndbSearch: search,
    });
  const first = element(1, 12, false, false, 'get api.example.test 200');
  const second = element(2, 319.53, false, true, 'get api.slow.test 200');
  const third = element(3, 68.44, true, false, 'delete api.error.test 503');
  const appended = [];
  state.$refs = {
    httpClientList: {
      querySelectorAll: () => [first, second, third],
      appendChild: (child) => appended.push(child),
    },
    httpClientDetail: { scrollTo: () => detailResets++ },
  };
  state.$nextTick = (callback) => callback();

  state.initializeHttpClient([
    { execution: 1, failed: false, slow: false, host: 'api.example.test' },
    { execution: 2, failed: false, slow: true, host: 'api.slow.test' },
    { execution: 3, failed: true, slow: false, host: 'api.error.test' },
  ]);
  assert.equal(state.httpClientFilter, 'all');
  assert.equal(state.httpClientSort, 'execution');
  assert.equal(state.httpClientSortDirection, 'asc');
  assert.equal(state.httpClientSelected, 1);
  assert.equal(state.httpClientDetailOpen, false);
  assert.equal(state.httpClientDetailTab, 'response');
  assert.equal(state.selectedHttpClientRequest.host, 'api.example.test');
  assert.equal(first.hidden, false);
  assert.equal(first.style.display, '');
  assert.equal(second.hidden, false);
  assert.equal(second.style.display, '');
  assert.equal(third.hidden, false);
  assert.equal(state.visibleHttpClientCount, 3);

  appended.length = 0;
  state.toggleHttpClientSort('duration');
  assert.deepEqual(appended, [second, third, first]);
  assert.equal(state.httpClientSort, 'duration');
  assert.equal(state.httpClientSortDirection, 'desc');
  assert.equal(state.httpClientSelected, 1);
  appended.length = 0;
  state.toggleHttpClientSort('duration');
  assert.deepEqual(appended, [first, third, second]);
  assert.equal(state.httpClientSortDirection, 'asc');
  appended.length = 0;
  state.toggleHttpClientSort('duration');
  assert.deepEqual(appended, [first, second, third]);
  assert.equal(state.httpClientSort, 'execution');

  const missingDuration = element(4, -1, true, false, 'post api.missing.test failed');
  state.httpClientSort = 'duration';
  state.httpClientSortDirection = 'desc';
  assert.equal(state.compareHttpClientRequests(second, missingDuration), -1);
  state.httpClientSortDirection = 'asc';
  assert.equal(state.compareHttpClientRequests(first, missingDuration), -1);
  state.httpClientSort = 'execution';
  state.httpClientSortDirection = 'asc';

  state.toggleHttpClientSort('duration');
  state.setHttpClientFilter('failed');
  assert.equal(first.hidden, true);
  assert.equal(first.style.display, 'none');
  assert.equal(second.hidden, true);
  assert.equal(second.style.display, 'none');
  assert.equal(third.hidden, false);
  assert.equal(state.visibleHttpClientCount, 1);
  assert.equal(state.httpClientSelected, 3);
  assert.equal(state.httpClientSort, 'duration');

  state.setHttpClientFilter('slow');
  assert.equal(first.hidden, true);
  assert.equal(second.hidden, false);
  assert.equal(third.hidden, true);
  assert.equal(state.visibleHttpClientCount, 1);
  assert.equal(state.httpClientSelected, 2);

  state.setHttpClientFilter('all');
  assert.equal(first.hidden, false);
  assert.equal(state.visibleHttpClientCount, 3);

  state.httpClientSearch = '503';
  state.applyHttpClientView();
  assert.equal(first.hidden, true);
  assert.equal(second.hidden, true);
  assert.equal(third.hidden, false);
  assert.equal(state.httpClientSelected, 3);
  assert.equal(state.httpClientSort, 'duration');

  state.httpClientDetailTab = 'source';
  state.selectHttpClientRequest(1);
  assert.equal(state.httpClientSelected, 1);
  assert.equal(state.httpClientDetailOpen, true);
  assert.equal(state.httpClientDetailTab, 'response');

  state.selectHttpClientRequest(2);
  assert.equal(state.httpClientSelected, 2);

  state.setHttpClientDetailTab('request');
  assert.equal(detailResets, 1);
  state.setHttpClientDetailTab('source');
  assert.equal(state.httpClientDetailTab, 'request');
  assert.equal(detailResets, 1);
  state.setHttpClientDetailTab('request');
  state.setHttpClientDetailTab('invalid');
  state.setHttpClientFilter('invalid');
  state.toggleHttpClientSort('invalid');
  state.selectHttpClientRequest(99);
  assert.equal(state.httpClientDetailTab, 'request');
  assert.equal(state.httpClientFilter, 'all');
  assert.equal(state.httpClientSelected, 2);

  assert.equal(state.formatHttpClientEvidence(null), '—');
  assert.equal(state.formatHttpClientEvidence('raw body'), 'raw body');
  assert.equal(state.formatHttpClientEvidence({ ready: true }), '{\n  "ready": true\n}');
});

test('HTTP client defaults to all when no request failed or ran slowly', () => {
  const { state, shell } = sectionHarness('http_client', summary, runtime());

  state.httpClientSort = 'duration';
  state.httpClientSortDirection = 'desc';
  state.initializeHttpClient([{ execution: 4, failed: false, slow: false }]);
  assert.equal(state.httpClientFilter, 'all');
  assert.equal(state.httpClientSort, 'execution');
  assert.equal(state.httpClientSortDirection, 'asc');
  assert.equal(state.httpClientSelected, 4);

  state.initializeHttpClient('invalid');
  assert.deepEqual(state.httpClientRequests, []);
  assert.equal(state.httpClientSelected, null);
  assert.equal(state.httpClientDetailOpen, false);

  state.$refs = {};
  state.applyHttpClientView();
  assert.equal(state.visibleHttpClientCount, 0);
});
