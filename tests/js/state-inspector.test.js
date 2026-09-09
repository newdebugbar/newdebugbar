import assert from 'node:assert/strict';
import test from 'node:test';

import { createNewDebugBar } from '../../resources/js/state.js';
import { STORAGE_KEY } from '../../resources/js/shell/preferences.js';
import { runtime, summary } from './state-test-support.js';

test('the inspector moves focus to shrink and returns it when closed', () => {
  let openerFocused = 0;
  let shrinkFocused = 0;
  const opener = { focus: () => openerFocused++ };
  const shrink = { focus: () => shrinkFocused++ };
  const browser = runtime();
  browser.activeElement = () => opener;
  const state = createNewDebugBar(summary, browser);
  state.$root = { querySelector: () => shrink };
  state.$nextTick = (callback) => callback();

  state.openInspector();
  assert.equal(shrinkFocused, 1);
  assert.equal(browser.host.locks, 1);

  state.closeInspector();
  assert.equal(openerFocused, 1);
  assert.equal(state.inspectorReturnFocus, null);
  assert.equal(browser.host.unlocks, 1);
});

test('prevented escape events do not close the inspector', () => {
  const state = createNewDebugBar(summary, runtime());
  state.inspectorOpen = true;

  state.handleShortcut({
    defaultPrevented: true,
    metaKey: false,
    ctrlKey: false,
    shiftKey: false,
    key: 'Escape',
  });

  assert.equal(state.inspectorOpen, true);
});

test('the request action reuses an open inspector and opens a closed one', () => {
  const browser = runtime();
  const opener = {};
  const state = createNewDebugBar(
    {
      sections: [
        { key: 'request', label: 'Request' },
        { key: 'queries', label: 'Queries' },
      ],
    },
    browser,
  );
  state.$root = { querySelectorAll: () => [] };
  state.$wire = { loadSection: async () => {} };
  state.$nextTick = (callback) => callback();
  state.loadedSection = 'request';
  state.inspectorOpen = true;

  state.openRequestSection();

  assert.equal(state.selected, 'request');
  assert.equal(browser.host.locks, 0);

  state.inspectorOpen = false;
  state.selectSection('queries');
  state.openRequestSection(opener);

  assert.equal(state.inspectorOpen, true);
  assert.equal(state.selected, 'request');
  assert.equal(state.inspectorReturnFocus, opener);
  assert.equal(browser.host.locks, 1);
});

test('dismissing the bar lasts for the page lifetime without becoming a preference', () => {
  let blurred = 0;
  let prevented = 0;
  const active = { blur: () => blurred++ };
  const browser = runtime();
  browser.activeElement = () => active;
  const state = createNewDebugBar(summary, browser);
  state.$nextTick = (callback) => callback();
  state.inspectorOpen = true;
  state.paletteOpen = true;
  state.mobileToolbarMenu = 'actions';
  state.mobileToolbarReturnFocus = active;

  state.dismissBar();

  assert.equal(state.barVisible, false);
  assert.equal(state.inspectorOpen, false);
  assert.equal(state.paletteOpen, false);
  assert.equal(state.mobileToolbarMenu, null);
  assert.equal(state.mobileToolbarReturnFocus, null);
  assert.equal(browser.host.unlocks, 1);
  assert.equal(blurred, 1);
  assert.equal(browser.values.has(STORAGE_KEY), false);

  state.openInspector();
  state.openPalette();
  state.handleShortcut({
    metaKey: true,
    ctrlKey: false,
    shiftKey: true,
    key: 'P',
    preventDefault: () => prevented++,
  });

  assert.equal(state.inspectorOpen, false);
  assert.equal(state.paletteOpen, false);
  assert.equal(prevented, 0);

  const reloaded = createNewDebugBar(summary, browser);
  reloaded.init();
  assert.equal(reloaded.barVisible, true);
});

test('mobile toolbar menus manage focus and hand off to overlays', () => {
  let active = null;
  let actionsFocused = 0;
  let menuItemFocused = 0;
  let paletteFocused = 0;
  let shrinkFocused = 0;
  const actionsOpener = {
    focus() {
      active = actionsOpener;
      actionsFocused++;
    },
  };
  const metricOpener = {
    focus() {
      active = metricOpener;
    },
  };
  const menuItem = {
    querySelector: () => menuItem,
    focus() {
      active = menuItem;
      menuItemFocused++;
    },
  };
  const paletteSearch = {
    focus() {
      active = paletteSearch;
      paletteFocused++;
    },
  };
  const shrink = {
    focus() {
      active = shrink;
      shrinkFocused++;
    },
  };
  const browser = runtime();
  browser.activeElement = () => active;
  const state = createNewDebugBar(summary, browser);
  state.$wire = { loadSection: async () => {} };
  state.$refs = { paletteSearch };
  state.$root = {
    querySelector: (selector) => (selector.includes('data-ndb-mobile-toolbar-menu') ? menuItem : shrink),
    querySelectorAll: () => [],
  };
  state.$nextTick = (callback) => callback();

  state.openMobileToolbarMenu('unknown', actionsOpener);
  assert.equal(state.mobileToolbarMenu, null);

  state.inspectorOpen = true;
  state.openMobileToolbarMenu('actions', actionsOpener);
  assert.equal(state.mobileToolbarMenu, null);

  state.openMobileToolbarMenu('header-actions', actionsOpener);
  assert.equal(state.mobileToolbarMenu, 'header-actions');
  assert.equal(menuItemFocused, 1);
  state.openSectionFromToolbar('queries');
  assert.equal(state.mobileToolbarMenu, null);
  assert.equal(state.selected, 'queries');
  assert.equal(state.inspectorOpen, true);

  state.inspectorOpen = false;
  state.barVisible = false;
  state.openMobileToolbarMenu('actions', actionsOpener);
  assert.equal(state.mobileToolbarMenu, null);
  state.barVisible = true;

  active = actionsOpener;
  state.toggleMobileToolbarMenu('actions', actionsOpener);
  assert.equal(state.mobileToolbarMenu, 'actions');
  assert.equal(menuItemFocused, 2);

  state.handleShortcut({ metaKey: false, ctrlKey: false, shiftKey: false, key: 'Escape', preventDefault() {} });
  assert.equal(state.mobileToolbarMenu, null);
  assert.equal(actionsFocused, 1);

  state.toggleMobileToolbarMenu('actions', actionsOpener);
  state.toggleMobileToolbarMenu('actions', actionsOpener);
  assert.equal(state.mobileToolbarMenu, null);
  assert.equal(actionsFocused, 2);

  state.openMobileToolbarMenu('actions', actionsOpener);
  state.closeMobileToolbarMenu(false);
  assert.equal(state.mobileToolbarMenu, null);
  assert.equal(actionsFocused, 2);

  state.openMobileToolbarMenu('actions', actionsOpener);
  state.openPalette();
  assert.equal(state.mobileToolbarMenu, null);
  assert.equal(state.paletteReturnFocus, actionsOpener);
  assert.equal(paletteFocused, 1);

  state.closePalette();
  assert.equal(actionsFocused, 3);

  active = metricOpener;
  state.openInspector('queries');
  assert.equal(state.mobileToolbarMenu, null);
  assert.equal(state.inspectorOpen, true);
  assert.equal(state.selected, 'queries');
  assert.equal(state.inspectorReturnFocus, metricOpener);
  assert.equal(shrinkFocused, 1);
});

test('opening a section from the compact menu preserves the opener and focuses its heading on mobile', () => {
  const browser = runtime();
  browser.viewportWidth = () => 390;
  let focused = null;
  const opener = { focus: () => (focused = 'opener') };
  const heading = { focus: () => (focused = 'heading') };
  const state = createNewDebugBar(summary, browser);
  state.$root = { querySelector: () => heading, querySelectorAll: () => [] };
  state.$wire = { loadSection: async () => {} };
  state.$nextTick = (callback) => callback();
  state.mobileToolbarMenu = 'actions';
  state.mobileToolbarReturnFocus = opener;

  state.openSectionFromToolbar('logs');
  assert.equal(state.inspectorOpen, true);
  assert.equal(state.selected, 'logs');
  assert.equal(state.mobileToolbarMenu, null);
  assert.equal(focused, 'heading');

  state.closeInspector();
  assert.equal(focused, 'opener');
});

test('menu arrow keys skip hidden controls and wrap through the visible actions', () => {
  const browser = runtime();
  let active = null;
  const item = (visible) => {
    const button = { getClientRects: () => (visible ? [{}] : []), focus: () => (active = button) };
    return button;
  };
  const first = item(true);
  const hidden = item(false);
  const last = item(true);
  const state = createNewDebugBar(summary, browser);
  const menu = { querySelectorAll: () => [first, hidden, last] };
  browser.activeElement = () => active;

  active = first;
  state.moveMobileToolbarMenu(1, menu);
  assert.equal(active, last);
  state.moveMobileToolbarMenu(1, menu);
  assert.equal(active, first);
  state.moveMobileToolbarMenu(-1, menu);
  assert.equal(active, last);
  state.moveMobileToolbarMenu(1, { querySelectorAll: () => [] });
  assert.equal(active, last);
});

test('theme menus expose explicit choices and manage layered focus', () => {
  let active = null;
  let openerFocused = 0;
  let paletteFocused = 0;
  const opener = {
    focus() {
      active = opener;
      openerFocused++;
    },
  };
  const option = (theme) => ({
    dataset: { ndbThemeOption: theme },
    focus() {
      active = this;
    },
  });
  const options = [option('system'), option('light'), option('dark')];
  const menu = { querySelectorAll: () => options };
  const emptyMenu = { querySelectorAll: () => [] };
  const paletteSearch = {
    focus() {
      active = paletteSearch;
      paletteFocused++;
    },
  };
  const browser = runtime();
  browser.activeElement = () => active;
  const state = createNewDebugBar(summary, browser);
  state.$refs = { paletteSearch };
  state.$root = {
    querySelector: (selector) => (selector.includes('data-ndb-theme-menu') ? menu : null),
  };
  state.$nextTick = (callback) => callback();

  state.openThemeMenu('unknown', opener);
  assert.equal(state.themeMenuScope, null);

  state.inspectorOpen = true;
  state.openThemeMenu('toolbar', opener);
  assert.equal(state.themeMenuScope, null);
  state.openThemeMenu('header', opener);
  assert.equal(state.themeMenuScope, 'header');
  assert.equal(active, options[0]);
  state.closeThemeMenu(false);

  state.inspectorOpen = false;
  state.openThemeMenu('header', opener);
  assert.equal(state.themeMenuScope, null);
  state.barVisible = false;
  state.openThemeMenu('toolbar', opener);
  assert.equal(state.themeMenuScope, null);
  state.barVisible = true;

  active = opener;
  state.toggleThemeMenu('toolbar', opener);
  assert.equal(state.themeMenuScope, 'toolbar');
  assert.equal(active, options[0]);

  state.moveThemeMenu(1, menu);
  assert.equal(active, options[1]);
  state.moveThemeMenu(-1, menu);
  assert.equal(active, options[0]);

  state.theme = 'dark';
  active = null;
  state.moveThemeMenu(1, menu);
  assert.equal(active, options[0]);
  state.moveThemeMenu(1, emptyMenu);

  state.toggleThemeMenu('toolbar', opener);
  assert.equal(state.themeMenuScope, null);
  assert.equal(openerFocused, 1);

  state.openThemeMenu('toolbar', opener);
  state.handleShortcut({ metaKey: false, ctrlKey: false, shiftKey: false, key: 'Escape', preventDefault() {} });
  assert.equal(state.themeMenuScope, null);
  assert.equal(openerFocused, 2);

  state.openThemeMenu('toolbar', opener);
  state.openPalette();
  assert.equal(state.themeMenuScope, null);
  assert.equal(state.paletteReturnFocus, opener);
  assert.equal(paletteFocused, 1);
  state.closePalette();
  assert.equal(openerFocused, 3);

  state.closeThemeMenu();
});

test('modal focus wraps at both edges', () => {
  let active = null;
  let prevented = 0;
  const browser = runtime();
  browser.activeElement = () => active;
  const state = createNewDebugBar(summary, browser);
  const first = {
    hidden: false,
    getClientRects: () => [{}],
    focus() {
      active = first;
    },
  };
  const last = {
    hidden: false,
    getClientRects: () => [{}],
    focus() {
      active = last;
    },
  };
  const container = {
    contains: (element) => [first, last].includes(element),
    querySelectorAll: () => [first, last],
  };

  active = first;
  state.keepFocusWithin({ key: 'Tab', shiftKey: true, preventDefault: () => prevented++ }, container);
  assert.equal(active, last);

  state.keepFocusWithin({ key: 'Tab', shiftKey: false, preventDefault: () => prevented++ }, container);
  assert.equal(active, first);
  assert.equal(prevented, 2);
});

test('copying text reports success', async () => {
  let copied = null;
  const browser = runtime();
  browser.writeClipboard = async (value) => {
    copied = value;
    return true;
  };
  const state = createNewDebugBar(summary, browser);

  assert.equal(await state.copyText('https://api.example.test/items/42'), true);
  assert.equal(copied, 'https://api.example.test/items/42');
});

test('clipboard failures stay inside the debug bar', async () => {
  const browser = runtime();
  browser.writeClipboard = async () => {
    throw new Error('Clipboard permission denied');
  };
  const state = createNewDebugBar(summary, browser);

  assert.equal(await state.copyText('select 1'), false);

  browser.writeClipboard = () => {
    throw new Error('Clipboard is unavailable');
  };
  assert.equal(await state.copyText('select 2'), false);

  browser.writeClipboard = () => false;
  assert.equal(await state.copyText('select 3'), false);

  delete browser.writeClipboard;
  assert.equal(await state.copyText('select 4'), false);
});

test('copy feedback expires after three seconds and repeated copies replace its timer', async () => {
  const browser = runtime();
  const schedule = browser.schedule;
  const delays = [];
  const copies = [];
  browser.schedule = (callback, delay) => {
    delays.push(delay);
    return schedule(callback);
  };
  browser.writeClipboard = async (value) => copies.push(value);
  const state = createNewDebugBar(summary, browser);
  const feedback = state.copyControl();

  await feedback.copyWithFeedback('https://example.test/trips?season=autumn');
  assert.equal(feedback.copyStatus, 'Copied');
  await feedback.copyWithFeedback('https://example.test/trips?season=autumn');
  assert.equal(browser.timers.size, 1);
  assert.deepEqual(delays, [3000, 3000]);
  assert.deepEqual(copies, ['https://example.test/trips?season=autumn', 'https://example.test/trips?season=autumn']);
  browser.runTimers();
  assert.equal(feedback.copyStatus, '');

  browser.writeClipboard = async () => false;
  await feedback.copyWithFeedback('https://example.test/trips?season=autumn');
  assert.equal(feedback.copyStatus, 'Copy failed');
  feedback.destroy();
  assert.equal(browser.timers.size, 0);

  let finish;
  browser.writeClipboard = () =>
    new Promise((resolve) => {
      finish = resolve;
    });
  const pendingFeedback = state.copyControl();
  const pending = pendingFeedback.copyWithFeedback('/another-request');
  pendingFeedback.destroy();
  finish(true);
  await pending;
  assert.equal(pendingFeedback.copyStatus, '');
  assert.equal(browser.timers.size, 0);
});

test('copy feedback follows the current value and ignores replaced clipboard operations', async () => {
  const browser = runtime();
  const finishes = [];
  browser.writeClipboard = () => new Promise((resolve) => finishes.push(resolve));
  const state = createNewDebugBar(summary, browser);
  const control = state.copyControl();
  control.syncCopyValue('select 1');
  const first = control.copyWithFeedback('select 1');
  const repeated = control.copyWithFeedback('select 1');
  finishes[1](true);
  await repeated;
  assert.equal(control.copyStatus, 'Copied');
  finishes[0](false);
  await first;
  assert.equal(control.copyStatus, 'Copied');
  assert.equal(browser.timers.size, 1);
  control.syncCopyValue('select 1');
  assert.equal(control.copyStatus, 'Copied');
  control.syncCopyValue('select 2');
  assert.equal(control.copyStatus, '');
  assert.equal(browser.timers.size, 0);
  const pending = control.copyWithFeedback('select 2');
  control.syncCopyValue('select 3');
  finishes[2](true);
  await pending;
  assert.equal(control.copyStatus, '');
  assert.equal(browser.timers.size, 0);
});
