import assert from 'node:assert/strict';
import test from 'node:test';

import { createNewDebugBar } from '../../resources/js/state.js';
import { STORAGE_KEY } from '../../resources/js/shell/preferences.js';
import { runtime, summary, toolbarHarness } from './state-test-support.js';

test('moves the compact toolbar to the edge with less host dialog overlap', () => {
  const browser = runtime();
  let placement = 'top';
  let watcher = null;
  let stopped = 0;
  browser.toolbarPlacement = () => placement;
  browser.watchHostDialogs = (_root, callback) => {
    watcher = callback;

    return () => stopped++;
  };
  const state = createNewDebugBar(summary, browser);
  state.$root = {};
  state.$nextTick = (callback) => callback();

  state.init();
  assert.equal(state.toolbarPlacement, 'top');

  placement = 'bottom';
  watcher();
  assert.equal(state.toolbarPlacement, 'bottom');

  placement = 'invalid';
  watcher();
  assert.equal(state.toolbarPlacement, 'bottom');

  state.destroy();
  assert.equal(stopped, 1);
});

test('restores and commands every supported toolbar anchor', () => {
  const placements = ['top-left', 'top', 'top-right', 'bottom-left', 'bottom', 'bottom-right'];

  placements.forEach((placement) => {
    const { state } = toolbarHarness({ toolbarAnchor: placement });

    assert.equal(state.toolbarPlacement, placement);
    assert.equal(state.toolbarPreferredPlacement, placement);
    assert.equal(state.toolbarVerticalPlacement, placement.startsWith('top') ? 'top' : 'bottom');
    assert.equal(state.toolbarIsCorner, placement.includes('-'));
    assert.equal(state.toolbarIsLeft, placement.endsWith('-left'));
    assert.equal(state.toolbarIsRight, placement.endsWith('-right'));
  });

  const state = createNewDebugBar(summary, runtime());
  const commandPlacements = ['top', 'bottom', 'top-left', 'top-right', 'bottom-left', 'bottom-right'];

  assert.deepEqual(
    state.allCommands.filter((command) => command.id.startsWith('toolbar:')).map((command) => command.id),
    commandPlacements.map((placement) => `toolbar:${placement}`),
  );
});

test('targets all four compact corners and uses their destination dimensions', () => {
  const targets = [
    ['top-left', 40, 40],
    ['top-right', 1400, 40],
    ['bottom-left', 40, 860],
    ['bottom-right', 1400, 860],
  ];

  targets.forEach(([placement, clientX, clientY]) => {
    const { browser, pointer, state } = toolbarHarness();

    state.startToolbarDrag(pointer());
    state.moveToolbarDrag(pointer({ clientX, clientY }));

    assert.equal(state.toolbarDragging, true);
    assert.equal(state.toolbarDragTarget, placement);
    assert.equal(state.toolbarPreviewWidth(placement), 196);
    assert.equal(state.toolbarPreviewHeight(placement), 56);

    state.endToolbarDrag(pointer({ clientX, clientY }));
    assert.equal(state.toolbarPlacement, placement);
    assert.equal(state.toolbarPreferredPlacement, placement);
    assert.equal(JSON.parse(browser.values.get(STORAGE_KEY)).toolbarAnchor, placement);

    browser.runTimers();
    assert.equal(state.toolbarSnapping, false);
  });
});

test('the compact toolbar follows a pointer and pins to the nearest anchor', () => {
  const { browser, capture, pointer, state } = toolbarHarness();
  const paintCallbacks = [];
  let prevented = 0;
  let clickPrevented = 0;
  let clickStopped = 0;
  browser.afterPaint = (callback) => paintCallbacks.push(callback);
  browser.nextFrame = (callback) => paintCallbacks.push(callback);

  state.startToolbarDrag(pointer());
  state.moveToolbarDrag(pointer({ clientY: 92, preventDefault: () => prevented++ }));

  assert.equal(state.toolbarDragging, true);
  assert.equal(state.toolbarDragTarget, 'top');
  assert.equal(state.toolbarDragWidth, 1024);
  assert.equal(state.toolbarDragOffsetX, 0);
  assert.equal(state.toolbarDragOffsetY, -758);
  assert.equal(capture.pointerId, 7);
  assert.equal(prevented, 1);

  state.endToolbarDrag(pointer({ clientY: 92, preventDefault: () => prevented++ }));

  assert.equal(state.toolbarDragging, false);
  assert.equal(state.toolbarPlacement, 'top');
  assert.equal(state.toolbarPreferredPlacement, 'top');
  assert.equal(state.toolbarRebasing, true);
  assert.equal(state.toolbarSnapping, false);
  assert.equal(state.toolbarSuppressClick, true);
  assert.deepEqual(capture.releases, [7]);
  assert.equal(prevented, 2);
  assert.equal(JSON.parse(browser.values.get(STORAGE_KEY)).toolbarAnchor, 'top');

  paintCallbacks.shift()();
  assert.equal(state.toolbarRebasing, false);
  assert.equal(state.toolbarSnapping, true);
  assert.equal(state.toolbarDragOffsetY, 58);

  paintCallbacks.shift()();
  assert.equal(state.toolbarDragOffsetY, 0);

  state.consumeToolbarClick({
    preventDefault: () => clickPrevented++,
    stopPropagation: () => clickStopped++,
  });
  browser.runTimers();

  assert.equal(clickPrevented, 1);
  assert.equal(clickStopped, 1);
  assert.equal(state.toolbarSuppressClick, false);
  assert.equal(state.toolbarSnapping, false);
  assert.equal(state.toolbarDragOffsetY, 0);
});

test('toolbar snapping works without animation frame helpers', () => {
  const { browser, pointer, state } = toolbarHarness();
  browser.afterPaint = null;
  browser.nextFrame = null;

  state.startToolbarDrag(pointer());
  state.moveToolbarDrag(pointer({ clientY: 92 }));
  state.endToolbarDrag(pointer({ clientY: 92 }));

  assert.equal(state.toolbarPlacement, 'top');
  assert.equal(state.toolbarRebasing, false);
  assert.equal(state.toolbarSnapping, true);
  assert.equal(state.toolbarDragOffsetY, 0);
  assert.equal(browser.timers.has(state.toolbarSnapTimer), true);

  browser.runTimers();

  assert.equal(state.toolbarSnapping, false);
});

test('a cancelled toolbar drag returns to its original anchor', () => {
  const { browser, pointer, state } = toolbarHarness({ toolbarAnchor: 'top' });

  state.startToolbarDrag(pointer({ clientY: 36 }));
  state.moveToolbarDrag(pointer({ clientY: 850 }));

  assert.equal(state.toolbarDragging, true);
  assert.equal(state.toolbarDragTarget, 'bottom');

  state.cancelToolbarDrag(pointer({ clientY: 850 }));
  browser.runTimers();

  assert.equal(state.toolbarDragging, false);
  assert.equal(state.toolbarPlacement, 'top');
  assert.equal(state.toolbarPreferredPlacement, 'top');
  assert.equal(state.toolbarSnapping, false);
});

test('toolbar drag guards preserve ordinary clicks and reject invalid anchors', () => {
  const { browser, pointer, state } = toolbarHarness();

  state.startToolbarDrag(pointer({ button: 2 }));
  state.startToolbarDrag(pointer({ isPrimary: false }));
  state.startToolbarDrag(pointer({ target: { closest: () => ({}) } }));
  assert.equal(state.toolbarDragPointerId, null);

  state.startToolbarDrag(pointer());
  state.moveToolbarDrag(pointer({ pointerId: 9, clientY: 100 }));
  state.moveToolbarDrag(pointer({ clientX: 722, clientY: 852 }));
  state.endToolbarDrag(pointer({ pointerId: 9 }));
  assert.equal(state.toolbarDragging, false);
  assert.equal(state.toolbarDragPointerId, 7);

  state.endToolbarDrag(pointer());
  assert.equal(state.toolbarDragPointerId, null);
  assert.equal(state.toolbarSuppressClick, false);

  state.startToolbarDrag(pointer());
  state.cancelToolbarDrag(pointer());
  assert.equal(state.toolbarDragPointerId, null);
  assert.equal(state.toolbarSuppressClick, false);

  state.suppressToolbarClick();
  browser.runTimers();
  assert.equal(state.toolbarSuppressClick, false);
  assert.equal(state.toolbarClickTimer, null);

  state.mobileToolbarMenu = 'actions';
  state.mobileToolbarReturnFocus = {};
  state.pinToolbar('top');
  browser.runTimers();
  assert.equal(state.toolbarPreferredPlacement, 'top');
  assert.equal(state.mobileToolbarMenu, null);

  state.pinToolbar('middle');
  state.moveToolbarTo('middle', true);
  state.consumeToolbarClick({});
  assert.equal(state.toolbarPreferredPlacement, 'top');

  state.inspectorOpen = true;
  state.startToolbarDrag(pointer());
  assert.equal(state.toolbarDragPointerId, null);
});
