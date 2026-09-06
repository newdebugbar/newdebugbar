import assert from 'node:assert/strict';
import test from 'node:test';

import { runtime, summary, sectionHarness } from './state-test-support.js';

test('Cache filters searches and keeps a visible operation selected', () => {
  const { state, shell } = sectionHarness('cache', summary, runtime());
  let detailResets = 0;
  let contentResets = 0;
  const element = (execution, category, failed, search) => ({
    dataset: {
      ndbCacheExecution: String(execution),
      ndbCacheCategory: category,
      ndbCacheFailed: String(failed),
      ndbCacheSearchText: search,
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
  const first = element(1, 'read', false, 'get hit trip alpha array');
  const second = element(2, 'write', false, 'put stored trip beta redis');
  const third = element(3, 'delete', true, 'forget failed trip stale database');
  state.$refs = {
    cacheList: {
      children: [first, second, third],
    },
    cacheDetail: { scrollTo: () => detailResets++ },
    content: { scrollTo: () => contentResets++ },
  };
  state.$nextTick = (callback) => callback();

  state.initializeCache([
    { execution: 1, key: 'trip:alpha' },
    { execution: 2, key: 'trip:beta' },
    { execution: 3, key: 'trip:stale', failed: true },
  ]);
  assert.equal(state.cacheFilter, 'all');
  assert.equal(state.cacheSelected, 1);
  assert.equal(state.cacheDetailOpen, false);
  assert.equal(state.selectedCacheOperation.key, 'trip:alpha');
  assert.equal(state.visibleCacheCount, 3);

  state.setCacheFilter('failed');
  assert.equal(first.hidden, true);
  assert.equal(second.hidden, true);
  assert.equal(third.hidden, false);
  assert.equal(state.cacheSelected, 3);
  assert.equal(state.visibleCacheCount, 1);

  state.setCacheFilter('writes');
  assert.equal(second.hidden, false);
  assert.equal(state.cacheSelected, 2);

  state.setCacheFilter('all');
  state.cacheSearch = 'alpha';
  state.applyCacheView();
  assert.equal(first.hidden, false);
  assert.equal(second.hidden, true);
  assert.equal(third.hidden, true);
  assert.equal(state.cacheSelected, 1);

  state.cacheSearch = '';
  state.selectCacheOperation(3);
  assert.equal(state.cacheSelected, 3);
  assert.equal(state.cacheDetailOpen, true);
  assert.equal(detailResets, 1);
  assert.equal(contentResets, 1);

  state.setCacheFilter('invalid');
  state.selectCacheOperation(99);
  assert.equal(state.cacheFilter, 'all');
  assert.equal(state.cacheSelected, 3);

  state.initializeCache('invalid');
  assert.deepEqual(state.cacheOperations, []);
  assert.equal(state.cacheSelected, null);
  assert.equal(state.cacheDetailOpen, false);
});
