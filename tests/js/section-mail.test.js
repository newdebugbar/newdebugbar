import assert from 'node:assert/strict';
import test from 'node:test';

import { runtime, summary, sectionHarness } from './state-test-support.js';

test('mail defaults to all and preview while keeping a visible message selected', () => {
  const browser = runtime();
  const { state, shell } = sectionHarness('mail', summary, browser);
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
    const { state, shell } = sectionHarness('mail', summary, runtime());
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
    const browser = runtime();
    const { state, shell } = sectionHarness(
      'mail',
      { ...summary, sections: [...summary.sections, { key: 'mail', label: 'Mail' }] },
      browser,
    );
    const scrolls = [];
    let detailAvailable = true;
    let previousStateCleanup = 0;
    let previousFrameCleanup = 0;
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
    frame.contentWindow = { postMessage() {} };
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
    handleMessage({
      source: {},
      data: { type: 'newdebugbar:mail-preview-height', height: 700 },
    });
    handleMessage({ source: frame.contentWindow, data: undefined });
    handleMessage({
      source: frame.contentWindow,
      data: {
        type: 'newdebugbar:mail-preview-height',
        height: Number.POSITIVE_INFINITY,
      },
    });
    handleMessage({
      source: frame.contentWindow,
      data: {
        type: 'newdebugbar:mail-preview-scroll',
        deltaY: 2,
        deltaMode: 1,
      },
    });
    handleMessage({
      source: frame.contentWindow,
      data: {
        type: 'newdebugbar:mail-preview-scroll',
        deltaY: 0.5,
        deltaMode: 2,
      },
    });
    handleMessage({
      source: frame.contentWindow,
      data: {
        type: 'newdebugbar:mail-preview-scroll',
        deltaY: 3,
        deltaMode: 0,
      },
    });
    assert.deepEqual(scrolls, [32, 250, 3]);

    detailAvailable = false;
    handleMessage({
      source: frame.contentWindow,
      data: {
        type: 'newdebugbar:mail-preview-scroll',
        deltaY: 10,
        deltaMode: 0,
      },
    });
    assert.deepEqual(scrolls, [32, 250, 3]);

    handleMessage({
      source: frame.contentWindow,
      data: { type: 'newdebugbar:mail-preview-height', height: 480 },
    });
    assert.equal(frame.style.height, '480px');
    assert.equal(canvas.style.height, '150px');

    const pendingFrames = [];
    browser.nextFrame = (callback) => pendingFrames.push(callback);
    frame.contentDocument = {
      body: { scrollHeight: 900, offsetHeight: 900 },
      documentElement: { scrollHeight: 900, offsetHeight: 900 },
    };
    state.init();
    state.resizeMailPreviewFrame(frame);
    assert.equal(observers[1].target, frame.contentDocument.body);
    shell.closeInspector();
    while (pendingFrames.length) pendingFrames.shift()();
    assert.equal(frame.style.height, '480px');
    assert.equal(observers[1].disconnected, true);
    state.resizeMailPreviewFrame(frame);
    state.connectMailPreviewFrame(frame);
    assert.equal(observers.length, 2, 'late iframe loads cannot recreate observers while hidden');
    shell.openInspector('mail');
    state.connectMailPreviewFrame(frame);
    state.resizeMailPreviewFrame(frame);
    while (pendingFrames.length) pendingFrames.shift()();
    assert.equal(frame.style.height, '900px');
    state.destroy();
    state.resizeMailPreviewFrame(frame);
    observers[0].callback();
    assert.equal(observers.length, 4);
    assert.ok(observers.every((observer) => observer.disconnected));
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
