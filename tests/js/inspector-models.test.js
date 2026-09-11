import assert from 'node:assert/strict';
import test from 'node:test';

import { runtime, summary, inspectorHarness } from './state-test-support.js';

test('Models starts unselected and keeps a selection while opening and closing mobile detail', () => {
  const browser = runtime();
  const { state, shell } = inspectorHarness('models', summary, browser);
  const contentScrolls = [];
  const listScrolls = [];
  const detailScrolls = [];
  const detailFocus = [];
  const rowFocus = [];
  let contentScrollTop = 0;
  let listScrollTop = 184;
  const modelRow = (index, search) => ({
    dataset: {
      ndbModelIndex: String(index),
      ndbModelSearchValue: search,
    },
    hidden: false,
    style: {
      removeProperty(property) {
        if (property === 'display') this.display = '';
      },
      setProperty(property, value) {
        if (property === 'display') this.display = value;
      },
    },
    focus: (options) => rowFocus.push([index, options]),
  });
  const rows = [
    modelRow(0, 'studiojob testing studio_jobs'),
    modelRow(1, 'proofversion testing proof_versions'),
  ];

  state.$refs = {
    content: {
      get scrollTop() {
        return contentScrollTop;
      },
      scrollTo(options) {
        contentScrolls.push(options);
        contentScrollTop = options.top;
      },
    },
    modelList: {
      get scrollTop() {
        return listScrollTop;
      },
      scrollTo(options) {
        listScrolls.push(options);
        listScrollTop = options.top;
      },
      querySelectorAll: (selector) => (selector === '[data-ndb-model-group]' ? rows : []),
    },
    modelDetail: {
      focus: (options) => detailFocus.push(options),
      scrollTo: (options) => detailScrolls.push(options),
    },
  };
  state.$root = {
    querySelectorAll: (selector) => (selector === '[data-ndb-model-group]' ? rows : []),
  };
  state.$nextTick = (callback) => callback();

  state.initializeModels(2);
  assert.equal(state.modelGroupCount, 2);
  assert.equal(state.visibleModelCount, 2);
  assert.equal(state.modelSelected, null);
  assert.equal(state.modelDetailOpen, false);
  assert.equal(state.modelDetailTab, 'records');

  state.modelSearch = 'proof';
  state.applyModelView();
  assert.equal(rows[0].hidden, true);
  assert.equal(rows[1].hidden, false);
  assert.equal(state.visibleModelCount, 1);

  state.modelSearch = '';
  state.applyModelView();
  assert.equal(state.visibleModelCount, 2);

  state.selectModelGroup(1);
  assert.equal(state.modelSelected, 1);
  assert.equal(state.modelDetailOpen, true);
  assert.equal(state.modelListScrollTop, 184);
  assert.deepEqual(contentScrolls, [{ top: 0, behavior: 'instant' }]);
  assert.deepEqual(detailScrolls, [{ top: 0, behavior: 'instant' }]);
  assert.deepEqual(detailFocus, [{ preventScroll: true }]);

  state.setModelDetailTab('source');
  assert.equal(state.modelDetailTab, 'source');
  assert.deepEqual(detailScrolls, [
    { top: 0, behavior: 'instant' },
    { top: 0, behavior: 'instant' },
  ]);

  state.setModelDetailTab('overview');
  assert.equal(state.modelDetailTab, 'source');

  state.selectModelGroup(-1);
  state.selectModelGroup(0.5);
  state.selectModelGroup(2);
  assert.equal(state.modelSelected, 1);

  browser.afterPaint = null;
  state.closeModelDetail();
  assert.equal(state.modelSelected, 1);
  assert.equal(state.modelDetailOpen, false);
  assert.deepEqual(listScrolls, [{ top: 184, behavior: 'instant' }]);
  assert.deepEqual(contentScrolls, [
    { top: 0, behavior: 'instant' },
    { top: 184, behavior: 'instant' },
  ]);
  assert.deepEqual(rowFocus, [[1, { preventScroll: true }]]);
  state.closeModelDetail();
  assert.deepEqual(rowFocus, [[1, { preventScroll: true }]]);

  state.modelSearch = 'studio';
  state.applyModelView();
  assert.equal(state.modelSelected, null);
  assert.equal(state.modelDetailTab, 'records');

  state.initializeModels(0);
  assert.equal(state.modelGroupCount, 0);
  state.initializeModels('invalid');
  assert.equal(state.modelGroupCount, 0);
  assert.equal(state.modelSelected, null);
  state.selectModelGroup(0);
  assert.equal(state.modelDetailOpen, false);
});

test('Models sorts comparable headings without losing stable capture identity', () => {
  const { state, shell } = inspectorHarness('models', summary, runtime());
  const appended = [];
  const modelRow = (index, name, retrieved, writes, reloads) => ({
    dataset: {
      ndbModelIndex: String(index),
      ndbModelSearchValue: name.toLowerCase(),
      ndbModelSortName: name.toLowerCase(),
      ndbModelSortRetrieved: String(retrieved),
      ndbModelSortWrites: String(writes),
      ndbModelSortReloads: String(reloads),
    },
    hidden: false,
    style: {
      removeProperty(property) {
        if (property === 'display') this.display = '';
      },
      setProperty(property, value) {
        if (property === 'display') this.display = value;
      },
    },
  });
  const rows = [
    modelRow(0, 'Zebra', 5, 0, 1),
    modelRow(1, 'Alpha', 5, 2, 0),
    modelRow(2, 'Beta', 9, 2, 4),
    modelRow(3, 'Alpha', 1, 0, 4),
  ];

  state.$refs = {
    modelList: {
      querySelectorAll: () => rows,
      appendChild: (row) => appended.push(Number(row.dataset.ndbModelIndex)),
    },
  };
  state.initializeModels(rows.length);
  state.modelSelected = 2;

  state.toggleModelSort('model');
  assert.equal(state.modelSort, 'model');
  assert.equal(state.modelSortDirection, 'asc');
  assert.deepEqual(appended.splice(0), [1, 3, 2, 0]);
  assert.equal(state.modelSelected, 2);

  state.toggleModelSort('model');
  assert.equal(state.modelSortDirection, 'desc');
  assert.deepEqual(appended.splice(0), [0, 2, 1, 3]);

  state.toggleModelSort('model');
  assert.equal(state.modelSort, 'capture');
  assert.equal(state.modelSortDirection, 'asc');
  assert.deepEqual(appended.splice(0), [0, 1, 2, 3]);

  state.toggleModelSort('retrieved');
  assert.equal(state.modelSortDirection, 'desc');
  assert.deepEqual(appended.splice(0), [2, 0, 1, 3]);
  state.toggleModelSort('retrieved');
  assert.deepEqual(appended.splice(0), [3, 0, 1, 2]);
  state.toggleModelSort('retrieved');
  assert.deepEqual(appended.splice(0), [0, 1, 2, 3]);

  state.toggleModelSort('writes');
  assert.deepEqual(appended.splice(0), [1, 2, 0, 3]);
  state.toggleModelSort('reloads');
  assert.deepEqual(appended.splice(0), [2, 3, 0, 1]);

  state.toggleModelSort('missing');
  assert.equal(state.modelSort, 'reloads');

  state.modelSearch = 'alpha';
  state.applyModelView();
  assert.equal(state.visibleModelCount, 2);
  assert.deepEqual(appended.splice(0), [2, 3, 0, 1]);
  assert.equal(state.modelSelected, null);

  state.modelSort = 'writes';
  state.modelSortDirection = 'asc';
  state.initializeModels(rows.length);
  assert.equal(state.modelSort, 'writes');
  assert.equal(state.modelSortDirection, 'asc');
});
