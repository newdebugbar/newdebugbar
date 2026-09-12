import assert from 'node:assert/strict';
import test from 'node:test';

import { installAlpineHandoff } from '../../resources/js/alpine-handoff.js';

function runtime(root = { _x_ignore: true }) {
  const listeners = {};

  return {
    root,
    listeners,
    fire: (name) => (listeners[name] ?? []).forEach((callback) => callback()),
    addEventListener: (name, callback) => {
      (listeners[name] ??= []).push(callback);
    },
    document: { getElementById: (id) => (id === 'newdebugbar' ? root : null) },
  };
}

test('releases the guarded toolbar to Livewire when Livewire initializes', () => {
  const env = runtime();

  installAlpineHandoff(env);

  assert.equal(env.root._x_ignore, true);

  env.fire('livewire:init');

  assert.equal('_x_ignore' in env.root, false);
});

test('installs once per runtime', () => {
  const env = runtime();

  installAlpineHandoff(env);
  installAlpineHandoff(env);

  assert.equal(env.listeners['livewire:init'].length, 1);
});

test('tolerates a page where the toolbar was not injected', () => {
  const env = runtime();
  env.document.getElementById = () => null;

  installAlpineHandoff(env);

  assert.doesNotThrow(() => env.fire('livewire:init'));
});

test('tolerates a runtime without event or document support', () => {
  assert.doesNotThrow(() => installAlpineHandoff({}));
  assert.doesNotThrow(() => {
    const env = runtime();
    delete env.document;
    installAlpineHandoff(env);
    env.fire('livewire:init');
  });
});
