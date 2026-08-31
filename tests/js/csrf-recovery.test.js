import assert from 'node:assert/strict';
import test from 'node:test';

import { currentToken, installCsrfRecovery, resetCsrfRecovery } from '../../resources/js/csrf-recovery.js';

const TOOLBAR = 'newdebugbar.toolbar';
const PROFILE_ID = '12345678-1234-4567-8abc-123456789abc';
const snapshot = (name) => JSON.stringify({ memo: { name } });

const body = (names, { calls = [], token = 'stale-token' } = {}) => JSON.stringify({
  _token: token,
  components: names.map((name) => ({
    snapshot: snapshot(name),
    calls: name === TOOLBAR ? calls : [],
  })),
});

function runtime({ cookie = '', token = 'fresh-token', ok = true } = {}) {
  const calls = [];
  const retries = [];
  const cleanups = [];
  let initHook = null;

  return {
    calls,
    retries,
    cleanups,
    document: { cookie },
    boot(componentName = TOOLBAR) {
      const intercepts = [];
      const wire = {
        $interceptRequest: (callback) => {
          intercepts.push(callback);
          return () => {};
        },
        noticeProfile: (...params) => retries.push(['noticeProfile', ...params]),
        switchProfile: (...params) => retries.push(['switchProfile', ...params]),
      };

      initHook?.({
        component: { name: componentName, $wire: wire },
        cleanup: (callback) => cleanups.push(callback),
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

const interceptWith = (intercept, requestBody) => {
  const request = { options: { headers: { 'X-CSRF-TOKEN': 'page-token' }, body: requestBody } };
  let onError = null;

  intercept({ request, onError: (callback) => { onError = callback; } });

  return { request, onError };
};

const failWith = (intercept, { status, requestBody }) => {
  const { request, onError } = interceptWith(intercept, requestBody);
  let prevented = false;

  onError?.({ response: { status }, preventDefault: () => { prevented = true; } });

  return { prevented, request };
};

const settle = () => new Promise((resolve) => setTimeout(resolve, 0));

test.beforeEach(() => resetCsrfRecovery());

test('uses Laravel current XSRF cookie before a toolbar request is sent', () => {
  const host = runtime({ cookie: 'theme=dark; XSRF-TOKEN=encrypted%3Dtoken' });
  installCsrfRecovery(host);

  const { request } = interceptWith(host.boot()[0], body([TOOLBAR]));
  const payload = JSON.parse(request.options.body);

  assert.equal(payload._token, undefined);
  assert.equal(request.options.headers['X-CSRF-TOKEN'], undefined);
  assert.equal(request.options.headers['X-XSRF-TOKEN'], 'encrypted=token');
  assert.equal(host.calls.length, 0);
  assert.equal(host.cleanups.length, 1);
});

test('leaves a request shared with a host component completely untouched', async () => {
  const host = runtime({ cookie: 'XSRF-TOKEN=current-cookie' });
  installCsrfRecovery(host);
  const requestBody = body([TOOLBAR, 'app.login-form']);

  const { prevented, request } = failWith(host.boot()[0], { status: 419, requestBody });

  assert.equal(prevented, false);
  assert.equal(request.options.body, requestBody);
  assert.deepEqual(request.options.headers, { 'X-CSRF-TOKEN': 'page-token' });
  assert.equal(host.calls.length, 0);
});

test('falls back to the live token and retries profile discovery once', async () => {
  const host = runtime();
  installCsrfRecovery(host);
  const intercept = host.boot()[0];
  const calls = [{ method: 'noticeProfile', params: [PROFILE_ID, false] }];

  const { prevented } = failWith(intercept, {
    status: 419,
    requestBody: body([TOOLBAR], { calls }),
  });

  assert.equal(prevented, true);
  await settle();
  assert.equal(host.calls[0].url, '/__newdebugbar/csrf');
  assert.equal(host.calls[0].options.cache, 'no-store');
  assert.equal(currentToken(), 'fresh-token');
  assert.deepEqual(host.retries, [['noticeProfile', PROFILE_ID, false]]);

  failWith(intercept, { status: 419, requestBody: body([TOOLBAR], { calls }) });
  await settle();
  assert.deepEqual(host.retries, [['noticeProfile', PROFILE_ID, false]]);
});

test('puts the fetched raw token in the body Laravel checks first', async () => {
  const host = runtime();
  installCsrfRecovery(host);
  const intercept = host.boot()[0];

  failWith(intercept, { status: 419, requestBody: body([TOOLBAR]) });
  await settle();

  const { request } = interceptWith(intercept, body([TOOLBAR]));

  assert.equal(JSON.parse(request.options.body)._token, 'fresh-token');
  assert.equal(request.options.headers['X-CSRF-TOKEN'], undefined);
  assert.equal(request.options.headers['X-XSRF-TOKEN'], undefined);
});

test('prefers a newly rotated cookie over an older fallback token', async () => {
  const host = runtime();
  installCsrfRecovery(host);
  const intercept = host.boot()[0];

  failWith(intercept, { status: 419, requestBody: body([TOOLBAR]) });
  await settle();
  host.document.cookie = 'XSRF-TOKEN=new-cookie';

  const { request } = interceptWith(intercept, body([TOOLBAR]));

  assert.equal(JSON.parse(request.options.body)._token, undefined);
  assert.equal(request.options.headers['X-XSRF-TOKEN'], 'new-cookie');
});

test('does not retry unrelated toolbar actions', async () => {
  const host = runtime();
  installCsrfRecovery(host);

  failWith(host.boot()[0], {
    status: 419,
    requestBody: body([TOOLBAR], { calls: [{ method: 'loadSection', params: ['queries'] }] }),
  });
  await settle();

  assert.deepEqual(host.retries, []);
});

test('leaves every other failure status alone', async () => {
  const host = runtime();
  installCsrfRecovery(host);

  const { prevented } = failWith(host.boot()[0], {
    status: 500,
    requestBody: body([TOOLBAR]),
  });

  assert.equal(prevented, false);
  assert.equal(host.calls.length, 0);
});

test('never attaches itself to a host component', () => {
  const host = runtime();
  installCsrfRecovery(host);

  assert.deepEqual(host.boot('app.login-form'), []);
  assert.deepEqual(host.cleanups, []);
});

test('keeps the token unset and skips the retry when the endpoint refuses', async () => {
  const host = runtime({ ok: false });
  installCsrfRecovery(host);

  failWith(host.boot()[0], {
    status: 419,
    requestBody: body([TOOLBAR], { calls: [{ method: 'switchProfile', params: [PROFILE_ID] }] }),
  });
  await settle();

  assert.equal(currentToken(), null);
  assert.deepEqual(host.retries, []);
});
