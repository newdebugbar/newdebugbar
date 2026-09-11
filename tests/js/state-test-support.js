import { createNewDebugBar } from '../../resources/js/state.js';
import { STORAGE_KEY } from '../../resources/js/shell/preferences.js';

export function runtime(saved = null) {
  const values = new Map(saved ? [[STORAGE_KEY, JSON.stringify(saved)]] : []);
  const host = { locks: 0, unlocks: 0 };
  const timers = new Set();

  return {
    values,
    host,
    timers,
    storage: {
      getItem: (key) => values.get(key) ?? null,
      setItem: (key, value) => values.set(key, value),
    },
    matchMedia: () => ({ matches: true, addEventListener() {}, removeEventListener() {} }),
    activeElement: () => null,
    highlight: () => {},
    afterPaint: (callback) => callback(),
    nextFrame: (callback) => callback(),
    schedule: (callback) => {
      timers.add(callback);

      return callback;
    },
    cancelSchedule: (timer) => timers.delete(timer),
    now: () => 13_000,
    runTimers: () => {
      const callbacks = [...timers];
      timers.clear();
      callbacks.forEach((callback) => callback());
    },
    viewportWidth: () => 1440,
    viewportHeight: () => 900,
    lockHost: () => host.locks++,
    unlockHost: () => host.unlocks++,
  };
}

export const summary = {
  inspectors: [
    { key: 'request', label: 'Requests', description: 'Request details.' },
    { key: 'queries', label: 'Queries', description: 'Query evidence.' },
    { key: 'logs', label: 'Logs', description: 'Log evidence.' },
  ],
};

export function listRow(dataset) {
  return {
    dataset,
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
  };
}

export function toolbarHarness(saved = null) {
  const browser = runtime(saved);
  browser.toolbarPlacement = (_root, preferred) => preferred;
  browser.watchHostDialogs = () => () => {};
  const state = createNewDebugBar(summary, browser);
  const capture = { pointerId: null, releases: [] };
  const toolbar = {
    setPointerCapture: (pointerId) => {
      capture.pointerId = pointerId;
    },
    hasPointerCapture: (pointerId) => capture.pointerId === pointerId,
    releasePointerCapture: (pointerId) => {
      capture.releases.push(pointerId);
      capture.pointerId = null;
    },
    getBoundingClientRect: () => {
      const corner = state.toolbarPlacement.includes('-');
      const width = corner ? 196 : 1024;
      const height = corner ? 56 : 60;
      const baseLeft = state.toolbarPlacement.endsWith('-left')
        ? 12
        : state.toolbarPlacement.endsWith('-right')
          ? 1440 - width - 12
          : (1440 - width) / 2;
      const baseTop = state.toolbarPlacement.startsWith('top') ? 12 : 900 - height - 12;
      const left = baseLeft + state.toolbarDragOffsetX;
      const top = baseTop + state.toolbarDragOffsetY;

      return { left, top, right: left + width, bottom: top + height, width, height };
    },
  };
  state.$root = {
    querySelector: (selector) => (selector === '[data-ndb-toolbar-shell]' ? toolbar : null),
    querySelectorAll: () => [],
  };
  state.$nextTick = (callback) => callback();
  state.init();

  const pointer = (overrides = {}) => ({
    pointerId: 7,
    pointerType: 'mouse',
    button: 0,
    isPrimary: true,
    clientX: 720,
    clientY: 850,
    currentTarget: toolbar,
    target: { closest: () => null },
    preventDefault() {},
    ...overrides,
  });

  return { browser, capture, pointer, state, toolbar };
}

// Supplies the parent Livewire scope used by a mounted Alpine inspector.
export function inspectorHarness(
  inspector,
  summary,
  browser,
  recentProfiles = [],
  profileLimit = 20,
  trace = null,
) {
  const shell = createNewDebugBar(summary, browser, recentProfiles, profileLimit, trace);
  shell.selected = inspector;
  shell.loadedInspector = inspector;
  shell.inspectorOpen = true;
  const state = shell.createInspector(inspector);
  for (const magic of ['$wire', '$nextTick']) {
    Object.defineProperty(state, magic, {
      configurable: true,
      get: () => shell[magic],
      set: (value) => {
        shell[magic] = value;
      },
    });
  }
  return { state, shell };
}
