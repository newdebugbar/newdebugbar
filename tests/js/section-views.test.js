import assert from 'node:assert/strict';
import test from 'node:test';

import { runtime, summary, sectionHarness } from './state-test-support.js';

test('Views defaults to application records and lazily loads only the selected render', async () => {
  const calls = [];
  let highlighted = 0;
  let detailFocuses = 0;
  let rowFocuses = 0;
  const browser = runtime();
  browser.highlight = () => highlighted++;
  const { state, shell } = sectionHarness('views', summary, browser);
  const groups = [
    {
      id: 'view-1',
      name: 'trips.show',
      display_name: 'trips.show',
      origin: 'application',
      count: 2,
      items: [
        {
          render_order: 1,
          data_key_count: 2,
          composer_count: 0,
          composers: [],
          source_kind: 'template',
        },
        {
          render_order: 2,
          data_key_count: 1,
          composer_count: 0,
          composers: [],
          source_kind: 'template',
        },
      ],
    },
    {
      id: 'view-2',
      name: 'pagination::tailwind',
      display_name: 'pagination::tailwind',
      origin: 'framework',
      count: 1,
      items: [
        {
          render_order: 3,
          data_key_count: 3,
          composer_count: 0,
          composers: [],
          source_kind: 'framework',
        },
      ],
    },
  ];
  const rows = [
    {
      dataset: {
        ndbViewGroup: 'view-1',
        ndbViewOrigin: 'application',
        ndbViewSearchValue: 'trips.show resources/views/trips/show.blade.php',
        ndbViewCount: '2',
      },
      hidden: false,
      focus: () => rowFocuses++,
    },
    {
      dataset: {
        ndbViewGroup: 'view-2',
        ndbViewOrigin: 'framework',
        ndbViewSearchValue: 'pagination::tailwind vendor/laravel/framework',
        ndbViewCount: '1',
      },
      hidden: false,
      focus: () => rowFocuses++,
    },
  ];
  state.$refs = {
    viewGroups: {
      querySelectorAll(selector) {
        return selector.includes(':not([hidden])') ? rows.filter((row) => !row.hidden) : rows;
      },
    },
    viewDetail: { focus: () => detailFocuses++ },
    content: { scrollTop: 20 },
  };
  state.$nextTick = (callback) => callback();

  state.initializeViews(groups);
  assert.equal(state.viewFilter, 'application');
  assert.equal(rows[0].hidden, false);
  assert.equal(rows[1].hidden, true);
  assert.equal(state.visibleViewCount, 1);
  assert.equal(state.visibleViewRenderCount, 2);

  state.selectViewGroup('view-1');
  assert.equal(state.viewDetailOpen, true);
  assert.equal(state.selectedViewGroup.name, 'trips.show');
  assert.equal(state.selectedViewRender.render_order, 1);
  assert.equal(detailFocuses, 1);
  assert.equal(state.$refs.content.scrollTop, 0);

  const wire = {
    loadViewData: async (renderOrder) => {
      calls.push(renderOrder);

      return { label: `Render ${renderOrder}` };
    },
  };

  state.loadSelectedViewData(wire);
  assert.equal(state.viewDataLoading, true);
  await Promise.resolve();
  await Promise.resolve();

  assert.deepEqual(calls, [1]);
  assert.equal(state.viewDataLoaded, true);
  assert.equal(state.viewDataIsEmpty, false);
  assert.match(state.formattedViewData, /Render 1/);
  assert.equal(highlighted, 1);

  state.loadSelectedViewData(wire);
  assert.deepEqual(calls, [1]);

  state.selectViewRender(2);
  assert.equal(state.viewDataLoaded, false);
  state.loadSelectedViewData(wire);
  await Promise.resolve();
  await Promise.resolve();
  assert.deepEqual(calls, [1, 2]);

  state.closeViewDetail();
  assert.equal(state.viewDetailOpen, false);
  assert.equal(rowFocuses, 1);

  state.setViewFilter('framework');
  assert.equal(rows[0].hidden, true);
  assert.equal(rows[1].hidden, false);
  assert.equal(state.viewSelected, null);
  assert.equal(state.visibleViewRenderCount, 1);
});

test('Views reports retryable lazy-data failures', async () => {
  const { state, shell } = sectionHarness('views', summary, runtime());
  state.$nextTick = (callback) => callback();
  state.initializeViews([
    {
      id: 'view-1',
      origin: 'application',
      items: [{ render_order: 8 }],
    },
  ]);
  state.selectViewGroup('view-1');

  state.loadSelectedViewData({
    loadViewData: async () => Promise.reject(new Error('expired')),
  });
  await new Promise((resolve) => setImmediate(resolve));
  assert.equal(state.viewDataLoading, false);
  assert.equal(state.viewDataError, true);
});
