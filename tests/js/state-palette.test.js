import assert from 'node:assert/strict';
import test from 'node:test';

import { createNewDebugBar } from '../../resources/js/state.js';
import { runtime, summary } from './state-test-support.js';

test('the command palette jumps to inspectors and changes settings', async () => {
  let highlighted = 0;
  const browser = runtime();
  browser.highlight = () => highlighted++;
  const state = createNewDebugBar(summary, browser);
  let inspectorsLoaded = 0;
  state.$wire = { loadInspector: async () => inspectorsLoaded++ };
  state.$nextTick = (callback) => callback();
  const opener = { focus() {} };
  state.paletteOpen = true;
  state.paletteReturnFocus = opener;

  state.runCommand('inspector:queries');
  await Promise.resolve();
  assert.equal(state.inspectorOpen, true);
  assert.equal(state.selected, 'queries');
  assert.equal(inspectorsLoaded, 1);
  assert.equal(highlighted, 2);
  assert.equal(state.inspectorReturnFocus, opener);
  assert.equal(state.paletteReturnFocus, null);

  state.paletteSearch = 'light theme';
  assert.equal(state.commandIndex('theme:light'), 0);
  state.paletteIndex = 0;
  state.runActiveCommand();
  assert.equal(state.resolvedTheme, 'light');

  state.paletteSearch = 'missing command';
  state.runActiveCommand();
  assert.equal(state.resolvedTheme, 'light');
});

test('the command palette keeps quiet collectors behind one reveal action', () => {
  const state = createNewDebugBar(
    {
      inspectors: [
        { key: 'request', label: 'Requests', active: true },
        { key: 'queries', label: 'Queries', active: true },
        { key: 'redis', label: 'Redis', active: false },
        { key: 'mail', label: 'Mail', active: false },
      ],
    },
    runtime(),
  );

  assert.deepEqual(
    state.filteredCommands
      .filter((command) => command.id.startsWith('inspector:'))
      .map((command) => command.id),
    ['inspector:queries', 'inspector:request'],
  );
  assert.equal(state.filteredCommands.at(-1).id, 'collectors:show');

  state.runCommand('collectors:show');
  assert.deepEqual(
    state.filteredCommands
      .filter((command) => command.id.startsWith('inspector:'))
      .map((command) => command.id),
    ['inspector:queries', 'inspector:request', 'inspector:mail', 'inspector:redis'],
  );

  state.paletteSearch = 'redis';
  state.paletteShowQuiet = false;
  assert.deepEqual(
    state.filteredCommands.map((command) => command.id),
    ['inspector:redis'],
  );
});

test('pinning from the command palette shrinks the inspector and keeps its selected inspector', () => {
  for (const placement of ['top', 'bottom', 'top-left', 'top-right', 'bottom-left', 'bottom-right']) {
    const state = createNewDebugBar(summary, runtime());
    state.inspectorOpen = true;
    state.selected = 'queries';
    state.paletteOpen = true;
    state.runCommand(`toolbar:${placement}`);

    assert.equal(state.toolbarPlacement, placement);
    assert.equal(state.inspectorOpen, false);
    assert.equal(state.paletteOpen, false);
    assert.equal(state.barVisible, true);
    assert.equal(state.selected, 'queries');
  }
});

test('the palette filters, wraps, restores focus, and handles layered shortcuts', () => {
  let focused = 0;
  const returnTarget = { focus: () => focused++ };
  const browser = runtime();
  browser.activeElement = () => returnTarget;
  const state = createNewDebugBar(summary, browser);
  state.$refs = { paletteSearch: { focus: () => focused++ } };
  state.$nextTick = (callback) => callback();

  state.openPalette();
  assert.equal(state.paletteOpen, true);
  assert.equal(focused, 1);

  state.paletteSearch = 'dark theme';
  assert.deepEqual(
    state.filteredCommands.map((command) => command.id),
    ['theme:dark'],
  );
  state.paletteIndex = 0;
  state.movePalette(-1);
  assert.equal(state.paletteIndex, 0);

  state.paletteSearch = '';
  state.paletteIndex = 0;
  state.movePalette(-1);
  assert.equal(state.paletteIndex, state.allCommands.length - 1);

  let prevented = 0;
  state.handleShortcut({
    metaKey: true,
    ctrlKey: false,
    shiftKey: true,
    key: 'P',
    preventDefault: () => prevented++,
  });
  assert.equal(state.paletteOpen, false);
  assert.equal(prevented, 1);
  assert.equal(focused, 2);

  state.openInspector();
  state.handleShortcut({
    metaKey: false,
    ctrlKey: false,
    shiftKey: false,
    key: 'Escape',
    preventDefault() {},
  });
  assert.equal(state.inspectorOpen, false);
});
