import assert from 'node:assert/strict';
import test from 'node:test';

import { createNewDebugBar } from '../../resources/js/state.js';
import { STORAGE_KEY } from '../../resources/js/shell/preferences.js';
import { runtime, summary } from './state-test-support.js';

test('restores safe local preferences', () => {
  const browser = runtime({
    theme: 'dark',
    toolbarAnchor: 'top',
    favorites: ['logs', 'unknown', 'logs'],
  });
  const state = createNewDebugBar(summary, browser);

  state.init();

  assert.equal(state.resolvedTheme, 'dark');
  assert.equal(state.toolbarPlacement, 'top');
  assert.equal(state.toolbarPreferredPlacement, 'top');
  assert.deepEqual(state.favorites, ['logs']);

  state.setTheme('light');
  assert.deepEqual(JSON.parse(browser.values.get(STORAGE_KEY)), {
    theme: 'light',
    toolbarAnchor: 'top',
    favorites: ['logs'],
    inspectorOrder: [],
  });
});

test('broken browser preferences never break initialization or persistence', () => {
  const browser = runtime();
  browser.storage = {
    getItem: () => {
      throw new Error('blocked');
    },
    setItem: () => {
      throw new Error('blocked');
    },
  };
  const state = createNewDebugBar(summary, browser);

  assert.doesNotThrow(() => state.init());
  assert.doesNotThrow(() => state.toggleFavorite('queries'));
  assert.deepEqual(state.favorites, ['queries']);
});

test('system theme changes only update a system preference', () => {
  let dark = false;
  let listener = null;
  let removed = null;
  const browser = runtime();
  browser.matchMedia = () => ({
    get matches() {
      return dark;
    },
    addEventListener: (_name, callback) => {
      listener = callback;
    },
    removeEventListener: (_name, callback) => {
      removed = callback;
    },
  });
  const state = createNewDebugBar(summary, browser);

  state.init();
  assert.equal(state.resolvedTheme, 'light');

  dark = true;
  listener();
  assert.equal(state.resolvedTheme, 'dark');

  state.setTheme('light');
  dark = false;
  listener();
  assert.equal(state.resolvedTheme, 'light');

  state.setTheme('invalid');
  assert.equal(state.theme, 'light');

  state.destroy();
  assert.equal(removed, listener);
  assert.equal(state.colorScheme, null);
  assert.equal(state.colorSchemeListener, null);
});
