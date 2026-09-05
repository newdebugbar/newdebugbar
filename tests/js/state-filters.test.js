import assert from 'node:assert/strict';
import test from 'node:test';

import { createNewDebugBar } from '../../resources/js/state.js';
import { runtime, summary } from './state-test-support.js';

test('Models starts unselected and keeps a selection while opening and closing mobile detail', () => {
  const browser = runtime();
  const state = createNewDebugBar(summary, browser);
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
  const state = createNewDebugBar(summary, runtime());
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
  assert.equal(state.modelSort, 'capture');
  assert.equal(state.modelSortDirection, 'asc');
});

test('Cache filters searches and keeps a visible operation selected', () => {
  const state = createNewDebugBar(summary, runtime());
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

test('HTTP client filters failures and slow requests while keeping one selected', () => {
  const browser = runtime();
  const state = createNewDebugBar(summary, browser);
  let detailResets = 0;
  const element = (execution, duration, failed, slow, search) => ({
    dataset: {
      ndbExecution: String(execution),
      ndbDuration: String(duration),
      ndbFailed: String(failed),
      ndbSlow: String(slow),
      ndbSearch: search,
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
  const state = createNewDebugBar(summary, runtime());

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

test('mail defaults to all and preview while keeping a visible message selected', () => {
  const browser = runtime();
  const state = createNewDebugBar(summary, browser);
  let detailResets = 0;
  const element = (execution, attachments, search) => ({
    dataset: {
      ndbExecution: String(execution),
      ndbAttachments: String(attachments),
      ndbSearch: search,
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
  const first = element(1, false, 'welcome taylor');
  const second = element(2, true, 'receipt alex invoice');
  const third = element(3, false, 'plain text morgan');
  state.$refs = {
    mailList: { children: [first, second, third] },
    mailDetail: {
      scrollTo: () => detailResets++,
    },
  };
  state.$nextTick = (callback) => callback();

  state.initializeMail([
    {
      execution: 1,
      has_html: true,
      has_text: true,
      html_url: '/1/html',
      text_url: '/1/text',
    },
    {
      execution: 2,
      transport_message_id: null,
      has_html: true,
      has_text: true,
      html_url: '/2/html',
      text_url: '/2/text',
    },
    {
      execution: 3,
      has_html: false,
      has_text: true,
      html_url: null,
      text_url: '/3/text',
    },
  ]);
  assert.equal(state.mailFilter, 'all');
  assert.equal(state.mailSelected, 1);
  assert.equal(state.mailDetailOpen, false);
  assert.equal(state.mailDetailTab, 'preview');
  assert.equal(state.mailPreviewFormat, 'html');
  assert.equal(state.mailPreviewViewport, 'desktop');
  assert.equal(state.mailPreviewUrl(), '/1/html');
  assert.equal(state.visibleMailCount, 3);

  state.setMailFilter('attachments');
  assert.equal(first.hidden, true);
  assert.equal(second.hidden, false);
  assert.equal(third.hidden, true);
  assert.equal(state.mailSelected, 2);
  assert.equal(state.visibleMailCount, 1);

  state.setMailFilter('all');
  state.mailSearch = 'plain text';
  state.applyMailView();
  assert.equal(state.mailSelected, 3);
  assert.equal(state.mailPreviewFormat, 'text');
  assert.equal(state.mailPreviewUrl(), '/3/text');

  state.setMailDetailTab('message');
  assert.ok(detailResets > 0);
  state.setMailPreviewViewport('mobile');
  state.setMailPreviewFormat('html');
  assert.equal(state.mailDetailTab, 'message');
  assert.equal(state.mailPreviewViewport, 'desktop');
  assert.equal(state.mailPreviewFormat, 'text');

  state.selectMailMessage(1);
  assert.equal(state.mailDetailOpen, true);
  assert.equal(state.mailDetailTab, 'preview');
  assert.equal(state.mailPreviewFormat, 'html');
  assert.equal(state.mailPreviewViewport, 'desktop');
  assert.equal(
    state.formatMailAddresses(['one@example.test', 'two@example.test']),
    'one@example.test, two@example.test',
  );
  assert.equal(state.formatMailAddresses([]), '—');

  state.setMailFilter('invalid');
  state.setMailDetailTab('invalid');
  state.setMailPreviewFormat('invalid');
  state.setMailPreviewViewport('invalid');
  state.selectMailMessage(99);
  assert.equal(state.mailFilter, 'all');
  assert.equal(state.mailDetailTab, 'preview');
  assert.equal(state.mailSelected, 1);

  state.initializeMail('invalid');
  assert.deepEqual(state.mailMessages, []);
  assert.equal(state.mailSelected, null);
  assert.equal(state.mailDetailOpen, false);
  assert.equal(state.mailPreviewUrl(), null);
});

test('mail preview keeps desktop and mobile viewport widths inside a narrow canvas', () => {
  const OriginalFrame = globalThis.HTMLIFrameElement;

  class PreviewFrame {}

  globalThis.HTMLIFrameElement = PreviewFrame;

  try {
    const state = createNewDebugBar(summary, runtime());
    const canvas = {
      clientWidth: 320,
      style: {
        setProperty(property, value, priority = '') {
          this[property] = value;
          this[`${property}Priority`] = priority;
        },
      },
    };
    const frame = new PreviewFrame();
    frame.style = {
      height: '640px',
      setProperty(property, value) {
        this[property] = value;
      },
    };
    frame.closest = (selector) => (selector === '[data-ndb-mail-preview-canvas]' ? canvas : null);
    frame.contentDocument = null;
    frame.contentWindow = { postMessage() {} };
    Object.defineProperty(frame, 'offsetHeight', {
      get: () => (frame.style.height === '20rem' ? 320 : Number.parseFloat(frame.style.height)),
    });

    state.mailMessages = [{ execution: 1, has_html: true, has_text: true }];
    state.mailSelected = 1;
    state.$refs = { mailPreviewFrame: frame };
    state.$nextTick = (callback) => callback();

    state.layoutMailPreviewFrame(frame);
    assert.equal(frame.style.width, '1024px');
    assert.equal(frame.style.transform, 'translateX(-50%) scale(0.3125)');
    assert.equal(canvas.style.height, '200px');
    assert.equal(canvas.style.heightPriority, 'important');

    state.setMailPreviewViewport('mobile');
    assert.equal(frame.style.width, '375px');
    assert.equal(frame.style.transform, 'translateX(-50%) scale(0.8533333333333334)');
    assert.equal(canvas.style.height, '274px');

    state.setMailPreviewFormat('text');
    assert.equal(frame.style.width, '320px');
    assert.equal(frame.style.transform, 'translateX(-50%) scale(1)');
    assert.equal(canvas.style.height, '320px');

    canvas.clientWidth = 1200;
    frame.style.height = '640px';
    state.mailPreviewFormat = 'html';
    state.mailPreviewViewport = 'desktop';
    state.layoutMailPreviewFrame(frame);
    assert.equal(frame.style.transform, 'translateX(-50%) scale(1)');
    assert.equal(canvas.style.height, '640px');

    canvas.clientWidth = 0;
    frame.style.width = 'unchanged';
    state.layoutMailPreviewFrame(frame);
    assert.equal(frame.style.width, 'unchanged');

    frame.closest = () => null;
    state.layoutMailPreviewFrame(frame);
    state.layoutMailPreviewFrame({});
  } finally {
    if (OriginalFrame === undefined) delete globalThis.HTMLIFrameElement;
    else globalThis.HTMLIFrameElement = OriginalFrame;
  }
});

test('mail preview follows canvas resizes and cleans up its observer', () => {
  const OriginalFrame = globalThis.HTMLIFrameElement;
  const OriginalObserver = globalThis.ResizeObserver;
  const OriginalWindow = globalThis.window;
  const listeners = new Map();
  const observers = [];

  class PreviewFrame {}
  class PreviewResizeObserver {
    constructor(callback) {
      this.callback = callback;
      this.disconnected = false;
      observers.push(this);
    }

    observe(target) {
      this.target = target;
    }

    disconnect() {
      this.disconnected = true;
    }
  }

  globalThis.HTMLIFrameElement = PreviewFrame;
  globalThis.ResizeObserver = PreviewResizeObserver;
  globalThis.window = {
    addEventListener: (type, listener) => listeners.set(type, listener),
    removeEventListener: (type, listener) => {
      if (listeners.get(type) === listener) listeners.delete(type);
    },
  };

  try {
    const state = createNewDebugBar(summary, runtime());
    const scrolls = [];
    let detailAvailable = true;
    let previousStateCleanup = 0;
    let previousFrameCleanup = 0;
    let bodyObserverCleanup = 0;
    const canvas = {
      clientWidth: 640,
      style: {
        setProperty(property, value, priority = '') {
          this[property] = value;
          this[`${property}Priority`] = priority;
        },
      },
    };
    const detail = {
      clientHeight: 500,
      scrollBy: ({ top }) => scrolls.push(top),
    };
    const frame = new PreviewFrame();
    frame.style = {
      height: '320px',
      setProperty(property, value) {
        this[property] = value;
      },
    };
    frame.closest = (selector) => {
      if (selector === '[data-ndb-mail-preview-canvas]') return canvas;
      if (selector === '[data-ndb-mail-detail]' && detailAvailable) return detail;

      return null;
    };
    frame.contentWindow = {};
    frame.__newDebugBarMailPreviewCleanup = () => previousFrameCleanup++;
    Object.defineProperty(frame, 'offsetHeight', {
      get: () => Number.parseFloat(frame.style.height),
    });
    state.mailPreviewFrameCleanup = () => previousStateCleanup++;

    state.connectMailPreviewFrame({});
    state.connectMailPreviewFrame(frame);
    assert.equal(previousStateCleanup, 1);
    assert.equal(previousFrameCleanup, 1);
    assert.equal(frame.style.width, '1024px');
    assert.equal(frame.style.transform, 'translateX(-50%) scale(0.625)');
    assert.equal(canvas.style.heightPriority, 'important');
    assert.equal(observers[0].target, canvas);

    canvas.clientWidth = 320;
    observers[0].callback();
    assert.equal(frame.style.transform, 'translateX(-50%) scale(0.3125)');

    const handleMessage = listeners.get('message');
    handleMessage({ source: {}, data: { type: 'newdebugbar:mail-preview-height', height: 700 } });
    handleMessage({ source: frame.contentWindow, data: undefined });
    handleMessage({
      source: frame.contentWindow,
      data: { type: 'newdebugbar:mail-preview-height', height: Number.POSITIVE_INFINITY },
    });
    handleMessage({
      source: frame.contentWindow,
      data: { type: 'newdebugbar:mail-preview-scroll', deltaY: 2, deltaMode: 1 },
    });
    handleMessage({
      source: frame.contentWindow,
      data: { type: 'newdebugbar:mail-preview-scroll', deltaY: 0.5, deltaMode: 2 },
    });
    handleMessage({
      source: frame.contentWindow,
      data: { type: 'newdebugbar:mail-preview-scroll', deltaY: 3, deltaMode: 0 },
    });
    assert.deepEqual(scrolls, [32, 250, 3]);

    detailAvailable = false;
    handleMessage({
      source: frame.contentWindow,
      data: { type: 'newdebugbar:mail-preview-scroll', deltaY: 10, deltaMode: 0 },
    });
    assert.deepEqual(scrolls, [32, 250, 3]);

    handleMessage({
      source: frame.contentWindow,
      data: { type: 'newdebugbar:mail-preview-height', height: 480 },
    });
    assert.equal(frame.style.height, '480px');
    assert.equal(canvas.style.height, '150px');

    frame.__newDebugBarMailPreviewObserver = { disconnect: () => bodyObserverCleanup++ };
    state.destroy();
    observers[0].callback();
    assert.equal(bodyObserverCleanup, 1);
    assert.equal(observers[0].disconnected, true);
    assert.equal(listeners.has('message'), false);
  } finally {
    if (OriginalFrame === undefined) delete globalThis.HTMLIFrameElement;
    else globalThis.HTMLIFrameElement = OriginalFrame;
    if (OriginalObserver === undefined) delete globalThis.ResizeObserver;
    else globalThis.ResizeObserver = OriginalObserver;
    if (OriginalWindow === undefined) delete globalThis.window;
    else globalThis.window = OriginalWindow;
  }
});

test('notifications default to all and group channel delivery diagnostics', () => {
  const browser = runtime();
  const state = createNewDebugBar(
    {
      sections: [
        { key: 'request', label: 'Requests' },
        { key: 'mail', label: 'Mail' },
        { key: 'notifications', label: 'Notifications' },
      ],
    },
    browser,
  );
  let notificationScrolls = 0;
  let mailFocuses = 0;
  const element = (execution, status, search) => ({
    dataset: {
      ndbExecution: String(execution),
      ndbStatus: status,
      ndbSearch: search,
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
  const first = element(1, 'partial', 'journey ready profile sms');
  const second = element(2, 'sent', 'departure push');
  const third = element(3, 'failed', 'payment failure slack');
  state.$root = { querySelectorAll: () => [] };
  state.$refs = {
    notificationList: { children: [first, second, third] },
    notificationDetail: { scrollTo: () => notificationScrolls++ },
    mailDetail: { focus: () => mailFocuses++ },
  };
  state.$nextTick = (callback) => callback();

  state.initializeNotifications([
    {
      execution: 1,
      status: 'partial',
      deliveries: [
        { channel: 'mail', channel_label: 'Mail' },
        { channel: 'profiled-sms', channel_label: 'Profiled Sms' },
      ],
    },
    {
      execution: 2,
      status: 'sent',
      deliveries: [{ channel: 'profiled-push', channel_label: 'Profiled Push' }],
    },
    {
      execution: 3,
      status: 'failed',
      deliveries: [{ channel: 'slack', channel_label: 'Slack' }],
    },
  ]);
  assert.equal(state.notificationFilter, 'all');
  assert.equal(state.notificationSelected, 1);
  assert.equal(state.notificationDetailOpen, false);
  assert.equal(state.notificationDetailTab, 'delivery');
  assert.equal(state.notificationChannel, 'mail');
  assert.equal(state.selectedNotificationDelivery.channel, 'mail');
  assert.equal(state.visibleNotificationCount, 3);

  state.setNotificationFilter('sent');
  assert.equal(first.hidden, true);
  assert.equal(second.hidden, false);
  assert.equal(third.hidden, true);
  assert.equal(state.notificationSelected, 2);
  assert.equal(state.notificationChannel, 'profiled-push');
  assert.equal(state.visibleNotificationCount, 1);

  state.setNotificationFilter('attention');
  assert.equal(first.hidden, false);
  assert.equal(second.hidden, true);
  assert.equal(third.hidden, false);
  assert.equal(state.notificationSelected, 1);

  state.notificationSearch = 'payment';
  state.applyNotificationView();
  assert.equal(first.hidden, true);
  assert.equal(third.hidden, false);
  assert.equal(state.notificationSelected, 3);

  state.notificationSearch = '';
  state.setNotificationFilter('all');
  state.selectNotification(1);
  state.setNotificationDetailTab('payload');
  state.setNotificationChannel('profiled-sms');
  assert.equal(state.notificationDetailOpen, true);
  assert.equal(state.notificationDetailTab, 'payload');
  assert.equal(state.selectedNotificationDelivery.channel, 'profiled-sms');
  assert.ok(notificationScrolls > 0);

  state.setNotificationFilter('invalid');
  state.setNotificationDetailTab('invalid');
  state.setNotificationChannel('invalid');
  state.selectNotification(99);
  assert.equal(state.notificationFilter, 'all');
  assert.equal(state.notificationDetailTab, 'payload');
  assert.equal(state.notificationChannel, 'profiled-sms');
  assert.equal(state.notificationSelected, 1);
  assert.equal(state.formatNotificationEvidence(null), 'No data was captured.');
  assert.equal(state.formatNotificationEvidence({ ready: true }), '{\n  "ready": true\n}');

  state.mailMessages = [{ execution: 7, transport_message_id: 'mail-7', has_html: true }];
  state.openNotificationMail('mail-7');
  assert.equal(state.selected, 'mail');
  assert.equal(state.mailSelected, 7);
  assert.equal(state.mailDetailOpen, true);
  assert.equal(mailFocuses, 1);

  state.mailMessages = [];
  state.openNotificationMail('mail-8');
  assert.equal(state.selected, 'mail');
  assert.equal(state.pendingMailMessageId, 'mail-8');
  state.initializeMail([
    { execution: 8, transport_message_id: 'mail-8', has_html: true },
    { execution: 9, transport_message_id: 'mail-9', has_html: true },
  ]);
  assert.equal(state.pendingMailMessageId, null);
  assert.equal(state.mailSelected, 8);
  assert.equal(state.mailDetailOpen, true);
  assert.equal(mailFocuses, 2);

  state.initializeNotifications('invalid');
  assert.deepEqual(state.notificationGroups, []);
  assert.equal(state.notificationSelected, null);
  assert.equal(state.notificationDetailOpen, false);
});

test('query workspace filters selects and keeps explain evidence scoped to the active execution', async () => {
  const browser = runtime();
  const state = createNewDebugBar({ ...summary, query_count: 4 }, browser);
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
    { key: 'query-3', executions: [{ execution: 3, explain_available: false }] },
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
  state.receiveQueryExplain({ execution: 1, error: 'Older execution failed.' });
  assert.equal(state.queryExplainLoading, true);
  assert.equal(records[0].executions[0].explain_error, 'Older execution failed.');
  state.receiveQueryExplain({ execution: 'invalid' });
  state.receiveQueryExplain({
    execution: 2,
    explain: { mode: 'EXPLAIN QUERY PLAN', driver: 'sqlite', rows: [{ detail: 'SCAN users' }] },
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

test('authorization controls filter search selection detail and section navigation', () => {
  const browser = runtime();
  const state = createNewDebugBar({
    sections: [
      { key: 'request', label: 'Requests' },
      { key: 'authorization', label: 'Authorization' },
    ],
  }, browser);
  let headingFocused = 0;
  let selectedFocused = 0;
  let detailScrolled = 0;
  let detailFocusOptions = null;
  const style = () => ({ removeProperty() {}, setProperty() {} });
  const allowed = {
    dataset: {
      ndbAuthorizationExecution: '1',
      ndbAuthorizationResult: 'allowed',
      ndbAuthorizationSearchValue: 'inspect-profile mara trip',
    },
    hidden: false,
    style: style(),
    focus: () => selectedFocused++,
  };
  const denied = {
    dataset: {
      ndbAuthorizationExecution: '2',
      ndbAuthorizationResult: 'denied',
      ndbAuthorizationSearchValue: 'delete-profile guest model',
    },
    hidden: false,
    style: style(),
    focus: () => selectedFocused++,
  };
  state.$root = {
    querySelectorAll: () => [],
    querySelector: (selector) => (selector.includes('="2"') ? denied : allowed),
  };
  state.$refs = {
    authorizationList: { children: [allowed, denied] },
    authorizationDetail: {
      scrollTo: () => detailScrolled++,
      focus: (options) => {
        detailFocusOptions = options;
      },
    },
    content: { scrollTop: 42 },
    sectionHeading: { focus: () => headingFocused++ },
  };
  state.$nextTick = (callback) => callback();

  state.initializeAuthorization([
    { execution: 1, result: 'allowed', ability: 'inspect-profile' },
    { execution: 2, result: 'denied', ability: 'delete-profile' },
  ]);
  assert.equal(state.authorizationSelected, 1);
  assert.equal(state.selectedAuthorizationDecision.ability, 'inspect-profile');

  state.setAuthorizationFilter('allowed');
  assert.equal(allowed.hidden, false);
  assert.equal(denied.hidden, true);
  assert.equal(state.visibleAuthorizationCount, 1);

  state.authorizationSearch = 'guest';
  state.applyAuthorizationView();
  assert.equal(allowed.hidden, true);
  assert.equal(denied.hidden, true);
  assert.equal(state.visibleAuthorizationCount, 0);
  assert.equal(state.authorizationSelected, null);

  state.authorizationSearch = '';
  state.setAuthorizationFilter('all');
  state.selectAuthorizationDecision(2);
  assert.equal(state.authorizationSelected, 2);
  assert.equal(state.authorizationDetailOpen, true);
  assert.equal(state.$refs.content.scrollTop, 0);
  assert.deepEqual(detailFocusOptions, { preventScroll: true });
  assert.equal(detailScrolled > 0, true);
  state.closeAuthorizationDetail();
  assert.equal(state.authorizationDetailOpen, false);
  assert.equal(selectedFocused, 1);

  state.navigateToSection('authorization', 'denied');
  assert.equal(state.selected, 'authorization');
  assert.equal(state.authorizationFilter, 'denied');
  assert.equal(allowed.hidden, true);
  assert.equal(denied.hidden, false);
  assert.equal(headingFocused, 1);

  state.setAuthorizationFilter('invalid');
  assert.equal(state.authorizationFilter, 'denied');
});

test('Views defaults to application records and lazily loads only the selected render', async () => {
  const calls = [];
  let highlighted = 0;
  let detailFocuses = 0;
  let rowFocuses = 0;
  const browser = runtime();
  browser.highlight = () => highlighted++;
  const state = createNewDebugBar(summary, browser);
  const groups = [
    {
      id: 'view-1',
      name: 'trips.show',
      display_name: 'trips.show',
      origin: 'application',
      count: 2,
      items: [
        { render_order: 1, data_key_count: 2, composer_count: 0, composers: [], source_kind: 'template' },
        { render_order: 2, data_key_count: 1, composer_count: 0, composers: [], source_kind: 'template' },
      ],
    },
    {
      id: 'view-2',
      name: 'pagination::tailwind',
      display_name: 'pagination::tailwind',
      origin: 'framework',
      count: 1,
      items: [
        { render_order: 3, data_key_count: 3, composer_count: 0, composers: [], source_kind: 'framework' },
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
  const state = createNewDebugBar(summary, runtime());
  state.$nextTick = (callback) => callback();
  state.initializeViews([{
    id: 'view-1',
    origin: 'application',
    items: [{ render_order: 8 }],
  }]);
  state.selectViewGroup('view-1');

  state.loadSelectedViewData({ loadViewData: async () => Promise.reject(new Error('expired')) });
  await new Promise((resolve) => setImmediate(resolve));
  assert.equal(state.viewDataLoading, false);
  assert.equal(state.viewDataError, true);
});

test('timeline controls filter sections and search labels', () => {
  const browser = runtime();
  const state = createNewDebugBar({
    ...summary,
    sections: [...summary.sections, { key: 'timeline', label: 'Timeline' }, { key: 'events', label: 'Events' }],
  }, browser);
  let rowFocuses = 0;
  let detailFocuses = 0;
  const item = (id, section, search, key = false) => ({
    dataset: {
      ndbTimelineItem: id,
      ndbTimelineSection: section,
      ndbTimelineSectionLabel: section === 'queries' ? 'Queries' : 'Events',
      ndbTimelineKind: section === 'queries' ? 'Duration' : 'Event',
      ndbTimelineLabel: search,
      ndbTimelineAt: '12.5',
      ndbTimelineAtLabel: '12.5 ms',
      ndbTimelineStart: section === 'queries' ? '10' : '',
      ndbTimelineStartLabel: section === 'queries' ? '10 ms' : '',
      ndbTimelineDuration: section === 'queries' ? '2.5' : '',
      ndbTimelineDurationLabel: section === 'queries' ? '2.5 ms' : '',
      ndbTimelineSource: section === 'queries' ? 'app/Trips/LoadTrip.php:24' : '',
      ndbTimelineSearchValue: search,
      ndbTimelineKey: String(key),
    },
    hidden: false,
    focus: () => rowFocuses++,
  });
  const query = item('queries-0', 'queries', 'select users', true);
  const event = item('events-0', 'events', 'clinic ready');
  const rows = [query, event];
  state.$refs = {
    timelineList: {
      querySelectorAll(selector) {
        return selector.includes(':not([hidden])') ? rows.filter((row) => !row.hidden) : rows;
      },
    },
    timelineDetail: { focus: () => detailFocuses++ },
    content: { scrollTop: 19 },
  };
  state.$nextTick = (callback) => callback();

  state.applyTimelineFilters();
  assert.equal(state.timelineFilter, 'key');
  assert.equal(query.hidden, false);
  assert.equal(event.hidden, true);

  state.setTimelineFilter('queries');
  assert.equal(query.hidden, false);
  assert.equal(event.hidden, true);
  assert.equal(state.visibleTimelineCount, 1);

  state.selectTimelineItem('queries-0');
  assert.equal(state.timelineDetailOpen, true);
  assert.equal(state.selectedTimelineItem.label, 'select users');
  assert.equal(state.selectedTimelineItem.durationLabel, '2.5 ms');
  assert.equal(state.selectedTimelineItem.source, 'app/Trips/LoadTrip.php:24');
  assert.equal(detailFocuses, 1);
  assert.equal(state.$refs.content.scrollTop, 0);

  let restoreTimelineFocus;
  browser.afterPaint = (callback) => {
    restoreTimelineFocus = callback;
  };

  state.closeTimelineDetail();
  assert.equal(state.timelineDetailOpen, false);
  assert.equal(rowFocuses, 0);
  restoreTimelineFocus();
  assert.equal(rowFocuses, 1);

  state.timelineSearch = 'MISSING';
  state.applyTimelineFilters();
  assert.equal(query.hidden, true);
  assert.equal(state.visibleTimelineCount, 0);

  state.setTimelineFilter('unknown');
  assert.equal(state.timelineFilter, 'queries');

  state.$refs = {};
  state.applyTimelineFilters();
  assert.equal(state.visibleTimelineCount, 0);
});

test('timeline loads bounded pages near the scroll end and exposes retry state', async () => {
  const browser = runtime();
  const observers = [];
  let cleanups = 0;
  browser.observeNearEnd = (target, scrollOwner, callback) => {
    observers.push({ target, scrollOwner, callback });

    return () => cleanups++;
  };

  const state = createNewDebugBar(
    {
      ...summary,
      sections: [...summary.sections, { key: 'timeline', label: 'Timeline' }],
    },
    browser,
  );
  const scrollOwner = {};
  const firstSentinel = { isConnected: true };
  const nextSentinel = { isConnected: true };
  let resolvePage;
  let pageCalls = 0;
  const scopedWire = {
    loadMoreTimeline() {
      pageCalls++;

      return new Promise((resolve) => {
        resolvePage = resolve;
      });
    },
  };
  const wire = {
    $island(name) {
      assert.equal(name, 'section-details');

      return scopedWire;
    },
  };

  state.selected = 'timeline';
  state.$refs = { timelineList: scrollOwner };
  state.$root = {
    querySelector: (selector) => (selector === '[data-ndb-timeline-page-sentinel]' ? nextSentinel : null),
  };
  state.$nextTick = (callback) => callback();
  state.observeTimelinePageEnd(firstSentinel, wire);

  assert.equal(observers.length, 1);
  assert.equal(observers[0].target, firstSentinel);
  assert.equal(observers[0].scrollOwner, scrollOwner);

  const firstPage = observers[0].callback();
  assert.equal(state.timelineLoadingMore, true);
  assert.equal(pageCalls, 1);
  await observers[0].callback();
  assert.equal(pageCalls, 1);

  resolvePage();
  assert.equal(await firstPage, true);
  assert.equal(state.timelineLoadingMore, false);
  assert.equal(state.timelinePaginationError, false);
  assert.equal(cleanups, 1);
  assert.equal(observers.length, 2);
  assert.equal(observers[1].target, nextSentinel);

  scopedWire.loadMoreTimeline = async () => {
    pageCalls++;
    throw new Error('expired');
  };
  assert.equal(await observers[1].callback(), false);
  assert.equal(state.timelineLoadingMore, false);
  assert.equal(state.timelinePaginationError, true);
  assert.equal(observers.length, 2);
  const failedPageCalls = pageCalls;
  assert.equal(await observers[1].callback(), false);
  assert.equal(pageCalls, failedPageCalls);

  scopedWire.loadMoreTimeline = async () => {
    pageCalls++;
  };
  assert.equal(await state.retryTimelinePage(wire), true);
  assert.equal(state.timelinePaginationError, false);
  assert.equal(observers.length, 3);

  state.resetTimelinePagination();
  assert.equal(cleanups, 3);
  assert.equal(state.timelineLoadingMore, false);
});

test('event controls group, filter, and select useful event evidence', () => {
  const browser = runtime();
  const state = createNewDebugBar(summary, browser);
  let detailFocuses = 0;
  let detailScrolls = 0;
  let returnFocuses = 0;
  const item = (id, source, search, occurrences) => ({
    dataset: {
      ndbEventId: String(id),
      ndbEventSourceValue: source,
      ndbEventSearchValue: search,
      ndbEventOccurrenceCount: String(occurrences),
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
  const framework = item(1, 'framework', 'illuminate auth login', 14);
  const application = item(2, 'application', 'clinic ready listener payload source', 2);
  const laterApplication = item(3, 'application', 'trip refreshed listener', 1);
  laterApplication.isConnected = true;
  laterApplication.focus = () => returnFocuses++;
  state.$refs = {
    eventList: {
      children: [framework, application, laterApplication],
    },
    eventDetail: {
      focus: () => detailFocuses++,
      scrollTo: () => detailScrolls++,
    },
  };
  state.$nextTick = (callback) => callback();

  state.initializeEvents([
    { id: 1, source: 'framework', name: 'Illuminate\\Auth\\Events\\Login' },
    { id: 2, source: 'application', name: 'App\\Events\\ClinicReady' },
    { id: 3, source: 'application', name: 'App\\Events\\TripRefreshed' },
  ]);
  assert.equal(state.eventSource, 'application');
  assert.equal(state.eventSelected, 2);
  assert.equal(state.selectedEvent?.id, 2);
  state.eventSelected = 99;
  assert.equal(state.selectedEvent, null);
  state.eventSelected = 2;
  assert.equal(state.eventDetailTab, 'overview');
  assert.equal(framework.hidden, true);
  assert.equal(framework.style.display, 'none');
  assert.equal(application.hidden, false);
  assert.equal(laterApplication.hidden, false);
  assert.equal(state.visibleEventCount, 3);
  assert.equal(state.visibleEventGroupCount, 2);
  assert.equal(state.visibleEventSummary, '2 events, 3 dispatches');

  state.visibleEventCount = 2;
  state.visibleEventGroupCount = 2;
  assert.equal(state.visibleEventSummary, '2 events');
  state.applyEventFilters();

  state.eventSearch = 'PAYLOAD';
  state.applyEventFilters();
  assert.equal(application.hidden, false);
  assert.equal(laterApplication.hidden, true);
  assert.equal(state.visibleEventCount, 2);
  assert.equal(state.visibleEventGroupCount, 1);
  assert.equal(state.visibleEventSummary, '1 event, 2 dispatches');

  state.eventSearch = '';
  state.setEventDetailTab('payload');
  state.setEventSource('framework');
  assert.equal(state.eventSource, 'framework');
  assert.equal(state.eventDetailTab, 'overview');
  assert.equal(framework.hidden, false);
  assert.equal(application.hidden, true);
  assert.equal(state.eventSelected, 1);
  assert.equal(state.visibleEventCount, 14);

  state.setEventSource('all');
  assert.equal(state.eventSelected, 1);
  assert.equal(state.eventDetailTab, 'overview');

  state.selectEvent(3, laterApplication);
  assert.equal(state.eventSelected, 3);
  assert.equal(state.eventDetailOpen, true);
  assert.equal(state.eventDetailTab, 'overview');
  state.setEventDetailTab('payload');
  assert.equal(state.eventDetailTab, 'payload');
  assert.equal(detailScrolls, 2);
  state.closeEventDetail();
  assert.equal(state.eventDetailOpen, false);
  assert.equal(returnFocuses, 1);

  browser.viewportWidth = () => 390;
  state.selectEvent(3, laterApplication);
  assert.equal(detailFocuses, 1);
  state.closeEventDetail();
  assert.equal(returnFocuses, 2);
  state.closeEventDetail();

  state.setEventSource('invalid');
  state.setEventDetailTab('invalid');
  state.selectEvent(99);
  assert.equal(state.eventSource, 'all');
  assert.equal(state.eventSelected, 3);
  assert.equal(state.eventDetailTab, 'overview');
  assert.equal(state.formatEventTime(null), '—');
  assert.equal(state.formatEventTime(''), '—');
  assert.equal(state.formatEventTime('missing'), '—');
  assert.equal(state.formatEventTime(12.3), '12.3 ms');

  state.$refs = {};
  state.applyEventFilters();
  assert.equal(state.visibleEventCount, 0);
  assert.equal(state.visibleEventGroupCount, 0);
  assert.equal(state.visibleEventSummary, 'No events');

  const emptyState = createNewDebugBar(summary, runtime());
  emptyState.initializeEvents(null);
  assert.deepEqual(emptyState.eventGroups, []);
  assert.equal(emptyState.eventSource, 'all');
  assert.equal(emptyState.eventSelected, null);

  emptyState.$nextTick = (callback) => callback();
  const frameworkOnly = item(4, 'framework', 'illuminate events dispatcher', 1);
  emptyState.$refs = { eventList: { children: [frameworkOnly] } };
  emptyState.initializeEvents([{ id: 4, source: 'framework', name: 'Illuminate\\Events\\Dispatcher' }]);
  assert.equal(emptyState.eventSource, 'all');
  assert.equal(emptyState.eventSelected, 4);
  assert.equal(frameworkOnly.hidden, false);
  assert.equal(emptyState.visibleEventCount, 1);
  assert.equal(emptyState.visibleEventGroupCount, 1);
});

test('log controls combine severity channel and search without losing record counts', () => {
  const state = createNewDebugBar(summary, runtime());
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
    querySelector: (selector) => items.find((entry) => selector.includes(entry.dataset.ndbLogFirstSequence)) ?? null,
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
