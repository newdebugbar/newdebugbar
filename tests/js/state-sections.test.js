import assert from 'node:assert/strict';
import test from 'node:test';

import { createNewDebugBar } from '../../resources/js/state.js';
import { STORAGE_KEY } from '../../resources/js/shell/preferences.js';
import { runtime, summary } from './state-test-support.js';

test('alphabetizes active sections while keeping selected and favorite quiet sections', () => {
  const browser = runtime();
  const state = createNewDebugBar(
    {
      sections: [
        { key: 'request', label: 'Requests', active: true },
        { key: 'queries', label: 'Queries', count: 3, active: true },
        { key: 'logs', label: 'Logs', count: 0, active: false },
        { key: 'cache', label: 'Cache', count: 0, active: false },
      ],
    },
    browser,
  );

  state.init();

  const visibleKeys = () =>
    state.orderedSections.filter((section) => state.isSectionVisible(section)).map((section) => section.key);

  assert.deepEqual(visibleKeys(), ['queries', 'request']);
  assert.equal(state.firstVisibleNonFavoriteKey, 'queries');
  assert.equal(state.isSectionVisible(state.summary.sections[2]), false);

  state.selectSection('logs');
  assert.deepEqual(visibleKeys(), ['logs', 'queries', 'request']);

  state.toggleFavorite('cache');
  assert.deepEqual(visibleKeys(), ['cache', 'logs', 'queries', 'request']);
  assert.equal(state.firstVisibleNonFavoriteKey, 'logs');
  assert.deepEqual(JSON.parse(browser.values.get(STORAGE_KEY)), {
    theme: 'system',
    toolbarAnchor: 'bottom',
    favorites: ['cache'],
  });
});

test('drops a saved Overview favorite after the UI section is removed', () => {
  const state = createNewDebugBar(summary, runtime({ favorites: ['overview', 'logs'] }));

  state.init();

  assert.deepEqual(state.favorites, ['logs']);
  assert.deepEqual(
    state.orderedSections.map((section) => section.key),
    ['logs', 'queries', 'request'],
  );
});

test('favorites can be pinned and reordered', () => {
  const browser = runtime();
  const state = createNewDebugBar(summary, browser);

  state.toggleFavorite('queries');
  state.toggleFavorite('logs');
  state.moveFavorite('logs', -1);
  const visibleKeys = () =>
    state.orderedSections.filter((section) => state.isSectionVisible(section)).map((section) => section.key);

  assert.deepEqual(state.favorites, ['logs', 'queries']);
  assert.deepEqual(visibleKeys(), ['logs', 'queries', 'request']);
  assert.equal(browser.values.has(STORAGE_KEY), true);

  state.toggleFavorite('logs');
  assert.deepEqual(state.favorites, ['queries']);
  assert.deepEqual(visibleKeys(), ['queries', 'logs', 'request']);

  state.toggleFavorite('request');
  assert.deepEqual(state.favorites, ['queries', 'request']);
  assert.deepEqual(visibleKeys(), ['queries', 'request', 'logs']);
});

test('favorites can be reordered by dragging', () => {
  const state = createNewDebugBar(summary, runtime({ favorites: ['request', 'queries', 'logs'] }));
  const favoriteRow = (key) => {
    const dropBefore = {
      hidden: false,
      toggleAttribute: (_name, hidden) => {
        dropBefore.hidden = hidden;
      },
    };
    const dropAfter = {
      hidden: false,
      toggleAttribute: (_name, hidden) => {
        dropAfter.hidden = hidden;
      },
    };
    const row = {
      dataset: { ndbSection: key },
      dragging: false,
      dropBefore,
      dropAfter,
      classList: {
        toggle: (_class, active) => {
          row.dragging = active;
        },
      },
      querySelector: (selector) => (selector.includes('before') ? dropBefore : dropAfter),
    };

    return row;
  };
  const request = favoriteRow('request');
  const logs = favoriteRow('logs');
  state.$root = { querySelectorAll: () => [request, logs] };
  const transfer = {
    effectAllowed: null,
    value: null,
    setData: (_type, value) => {
      transfer.value = value;
    },
  };

  state.init();
  state.startFavoriteDrag('request', { dataTransfer: transfer });
  state.hoverFavorite('logs', true);

  assert.equal(request.dataset.ndbDragging, 'true');
  assert.equal(request.dragging, true);
  assert.equal(logs.dropBefore.hidden, true);
  assert.equal(logs.dropAfter.hidden, false);

  state.dropFavorite('logs', true);

  assert.equal(transfer.value, 'request');
  assert.equal(transfer.effectAllowed, 'move');
  assert.deepEqual(state.favorites, ['queries', 'logs', 'request']);
  assert.equal(state.favoriteDrag, null);
  assert.equal(state.favoriteDrop, null);
  assert.equal(state.favoriteDropAfter, false);
  assert.equal(request.dragging, false);
  assert.equal(logs.dropAfter.hidden, true);
});

test('selecting a section resets content and highlights its code', async () => {
  let highlighted = 0;
  const browser = runtime();
  browser.highlight = () => highlighted++;
  const state = createNewDebugBar(summary, browser);
  const panels = [
    { dataset: { ndbSectionPanel: 'request' }, hidden: false },
    { dataset: { ndbSectionPanel: 'queries' }, hidden: true },
  ];
  state.$root = { querySelectorAll: () => panels };
  state.$refs = {
    content: { scrollTop: 60 },
    sectionHeading: { textContent: '' },
    sectionDescription: { textContent: '' },
  };
  state.$nextTick = (callback) => callback();

  state.selectSection('queries');
  state.syncSectionHeading();

  assert.equal(state.selected, 'queries');
  assert.equal(state.$refs.sectionHeading.textContent, 'Queries');
  assert.equal(state.$refs.sectionDescription.textContent, 'Query evidence.');
  assert.equal(panels[0].hidden, true);
  assert.equal(panels[1].hidden, false);
  assert.equal(state.$refs.content.scrollTop, 0);
  assert.equal(highlighted, 1);
});

test('query findings reveal and scroll to grouped slow evidence', () => {
  const state = createNewDebugBar(summary, runtime());
  const content = { scrollTop: 60 };
  let requestedSelector = '';
  let scrollOptions = null;
  const group = {
    dataset: {
      ndbQueryKey: 'group-users',
      ndbExecution: '1',
      ndbDuration: '20',
      ndbQueryType: 'read',
      ndbAttention: 'true',
      ndbSlow: 'true',
      ndbRepeated: 'true',
      ndbSearch: 'select users',
      ndbQueryExecutionCount: '3',
    },
    hidden: false,
    style: { removeProperty() {}, setProperty() {} },
    scrollIntoView: (options) => {
      scrollOptions = options;
    },
  };

  state.$root = { querySelectorAll: () => [] };
  const queries = state.createSection('queries');
  queries.queryRecords = [
    {
      key: 'group-users',
      executions: [{ execution: 1, explain_available: true }],
    },
  ];
  queries.$refs = {
    content,
    queryDetail: { scrollTo() {} },
    queryList: {
      querySelectorAll: () => [group],
      appendChild() {},
      querySelector: (selector) => {
        requestedSelector = selector;

        return group;
      },
    },
  };
  state.$refs = { content };
  queries.$nextTick = state.$nextTick = (callback) => callback();
  queries.initialized = true;
  queries.init();

  state.selectSection('queries', 'slow');

  assert.equal(queries.queryFilter, 'attention');
  assert.equal(queries.querySelected, 'group-users');
  assert.equal(queries.queryDetailOpen, true);
  assert.equal(content.scrollTop, 0);
  assert.match(requestedSelector, /data-ndb-slow/);
  assert.deepEqual(scrollOptions, { block: 'nearest' });
});

test('favorite guards and drop positions preserve a valid order', () => {
  const state = createNewDebugBar(summary, runtime({ favorites: ['request', 'queries', 'logs'] }));
  state.init();

  state.toggleFavorite('missing');
  state.moveFavorite('request', -1);
  state.startFavoriteDrag('missing');
  state.hoverFavorite('missing');
  assert.deepEqual(state.favorites, ['request', 'queries', 'logs']);
  assert.equal(state.favoriteDrag, null);

  state.startFavoriteDrag('logs');
  state.hoverFavorite('request');
  assert.equal(state.favoriteDrop, 'request');
  state.leaveFavorite('queries');
  assert.equal(state.favoriteDrop, 'request');
  state.leaveFavorite('request');
  assert.equal(state.favoriteDrop, null);
  state.hoverFavorite('request');
  state.dropFavorite('request');
  assert.deepEqual(state.favorites, ['logs', 'request', 'queries']);

  state.startFavoriteDrag('logs');
  state.dropFavorite('logs');
  assert.deepEqual(state.favorites, ['logs', 'request', 'queries']);
});
