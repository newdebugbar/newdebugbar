import assert from 'node:assert/strict';
import test from 'node:test';

import { currentToken, installCsrfRecovery, resetCsrfRecovery } from '../../resources/js/csrf-recovery.js';

const snapshot = (name) => JSON.stringify({ memo: { name } });

const body = (...names) => JSON.stringify({
  components: names.map((name) => ({ snapshot: snapshot(name) })),
});

function runtime({ token = 'fresh-token', ok = true } = {}) {
  const calls = [];
  let initHook = null;

  return {
    calls,
    boot(componentName = 'newdebugbar.toolbar') {
      const intercepts = [];
      initHook?.({
        component: {
          name: componentName,
          $wire: { $interceptRequest: (callback) => intercepts.push(callback) },
        },
      });

      return intercepts;
    },
    fetch: (url, options) => {
      calls.push({ url, options });

      return Promise.resolve({ ok, json: () => Promise.resolve({ token }) });
    },
    addEventListener: () => {},
    Livewire: {
      all: () => [],
      hook: (name, callback) => {
        if (name === 'component.init') initHook = callback;
      },
    },
  };
}

const failWith = (intercept, { status, requestBody }) => {
  const request = { options: { headers: {}, body: requestBody } };
  let prevented = false;
  let onError = null;

  intercept({ request, onError: (callback) => { onError = callback; } });
  onError?.({ response: { status }, preventDefault: () => { prevented = true; } });

  return { prevented, request };
};

test.beforeEach(() => resetCsrfRecovery());

test('swallows a 419 that only the toolbar caused and fetches the live token', async () => {
  const host = runtime();
  installCsrfRecovery(host);

  const { prevented } = failWith(host.boot()[0], {
    status: 419,
    requestBody: body('newdebugbar.toolbar'),
  });

  assert.equal(prevented, true);
  await new Promise((resolve) => setTimeout(resolve, 0));
  assert.equal(host.calls[0].url, '/__newdebugbar/csrf');
  assert.equal(currentToken(), 'fresh-token');
});

test('leaves a 419 the host app shares to the host app', async () => {
  const host = runtime();
  installCsrfRecovery(host);

  const { prevented } = failWith(host.boot()[0], {
    status: 419,
    requestBody: body('newdebugbar.toolbar', 'app.login-form'),
  });

  assert.equal(prevented, false);
  assert.equal(host.calls.length, 0);
});

test('leaves every other failure status alone', async () => {
  const host = runtime();
  installCsrfRecovery(host);

  const { prevented } = failWith(host.boot()[0], {
    status: 500,
    requestBody: body('newdebugbar.toolbar'),
  });

  assert.equal(prevented, false);
  assert.equal(host.calls.length, 0);
});

test('sends the recovered token on the toolbar requests that follow', async () => {
  const host = runtime();
  installCsrfRecovery(host);
  const intercept = host.boot()[0];

  failWith(intercept, { status: 419, requestBody: body('newdebugbar.toolbar') });
  await new Promise((resolve) => setTimeout(resolve, 0));

  const { request } = failWith(intercept, { status: 200, requestBody: body('newdebugbar.toolbar') });
  assert.equal(request.options.headers['X-CSRF-TOKEN'], 'fresh-token');
});

test('never attaches itself to a host component', () => {
  const host = runtime();
  installCsrfRecovery(host);

  assert.deepEqual(host.boot('app.login-form'), []);
});

test('keeps the token unset when the endpoint refuses', async () => {
  const host = runtime({ ok: false });
  installCsrfRecovery(host);

  failWith(host.boot()[0], { status: 419, requestBody: body('newdebugbar.toolbar') });
  await new Promise((resolve) => setTimeout(resolve, 0));

  assert.equal(currentToken(), null);
});
