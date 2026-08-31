const TOOLBAR = 'newdebugbar.toolbar';
const ENDPOINT = '/__newdebugbar/csrf';
const PROFILE_METHODS = new Set(['noticeProfile', 'switchProfile']);
const PROFILE_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

let freshToken = null;
let inFlight = null;
let installedLivewire = null;
const retriedProfileCalls = new Set();

const payloadFor = (request) => {
  try {
    const body = request?.options?.body;
    return typeof body === 'string' ? JSON.parse(body) : null;
  } catch {
    return null;
  }
};

/** Reads the component names a Livewire request is carrying. */
const componentNames = (payload) => {
  if (!Array.isArray(payload?.components) || payload.components.length === 0) return [];

  return payload.components.map((component) => {
    try {
      return JSON.parse(component?.snapshot ?? '{}')?.memo?.name ?? null;
    } catch {
      return null;
    }
  });
};

/**
 * A request Livewire batched with a host component is the host app's to handle.
 * Only a request carrying nothing but the toolbar is ours to change or swallow.
 */
const isToolbarOnly = (payload) => {
  const names = componentNames(payload);
  return names.length > 0 && names.every((name) => name === TOOLBAR);
};

const xsrfCookie = (runtime) => {
  try {
    const cookie = String(runtime.document?.cookie ?? '')
      .split(';')
      .map((value) => value.trim())
      .find((value) => value.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : null;
  } catch {
    return null;
  }
};

const setHeader = (headers, name, value) => {
  if (typeof headers?.set === 'function') headers.set(name, value);
  else if (headers) headers[name] = value;
};

const deleteHeader = (headers, name) => {
  if (typeof headers?.delete === 'function') {
    headers.delete(name);
    return;
  }

  if (!headers) return;
  const key = Object.keys(headers).find((candidate) => candidate.toLowerCase() === name.toLowerCase());
  if (key) delete headers[key];
};

/** Gives a toolbar-only request a token Laravel will check before sending it. */
const prepareRequest = (runtime, request) => {
  const payload = payloadFor(request);
  if (!isToolbarOnly(payload)) return false;

  const cookie = xsrfCookie(runtime);
  if (cookie) {
    // Laravel checks a body `_token` before either CSRF header. Removing
    // Livewire's stale body value lets Laravel use its newly issued encrypted
    // cookie instead.
    delete payload._token;
    request.options.body = JSON.stringify(payload);
    deleteHeader(request.options.headers, 'X-CSRF-TOKEN');
    setHeader(request.options.headers, 'X-XSRF-TOKEN', cookie);

    return true;
  }

  if (!freshToken) return false;

  payload._token = freshToken;
  request.options.body = JSON.stringify(payload);
  deleteHeader(request.options.headers, 'X-CSRF-TOKEN');
  deleteHeader(request.options.headers, 'X-XSRF-TOKEN');

  return true;
};

const profileCalls = (payload) => (payload?.components ?? [])
  .flatMap((component) => component?.calls ?? [])
  .filter((call) => PROFILE_METHODS.has(call?.method)
    && Array.isArray(call?.params)
    && PROFILE_PATTERN.test(call.params[0] ?? ''));

const retryProfileCalls = (component, calls) => {
  calls.forEach(({ method, params }) => {
    const action = component?.$wire?.[method];
    const key = `${method}:${params[0]}`;
    if (typeof action !== 'function' || retriedProfileCalls.has(key)) return;

    retriedProfileCalls.add(key);

    try {
      Promise.resolve(action.call(component.$wire, ...params)).catch(() => {});
    } catch {
      // Recovery must never become a host-page error.
    }
  });
};

const fetchToken = (runtime) => {
  if (inFlight) return inFlight;

  inFlight = Promise.resolve(runtime.fetch?.(ENDPOINT, {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
    cache: 'no-store',
  }))
    .then((response) => (response?.ok ? response.json() : null))
    .then((payload) => {
      const token = typeof payload?.token === 'string' ? payload.token : null;
      if (token) freshToken = token;

      return token;
    })
    .catch(() => null)
    .finally(() => {
      inFlight = null;
    });

  return inFlight;
};

export function currentToken() {
  return freshToken;
}

export function resetCsrfRecovery() {
  freshToken = null;
  inFlight = null;
  installedLivewire = null;
  retriedProfileCalls.clear();
}

/**
 * Keeps a rotated CSRF token from turning into the host app's problem.
 *
 * Laravel refreshes its XSRF cookie whenever a request rotates the session. The
 * toolbar uses that current cookie without changing the host page's own token.
 * If a host has disabled the cookie, a toolbar-only 419 is swallowed, the live
 * token is fetched, and only safe profile-discovery calls are retried once.
 */
export function installCsrfRecovery(runtime = window) {
  if (runtime.__newDebugBarCsrfRecovery) return;
  runtime.__newDebugBarCsrfRecovery = true;

  // The bundle runs before Livewire boots, so the hook has to wait for it.
  const install = () => attach(runtime, runtime.Livewire);

  runtime.addEventListener?.('livewire:init', install);
  install();
}

function attach(runtime, Livewire) {
  if (!Livewire || installedLivewire === Livewire) return;
  installedLivewire = Livewire;

  const guard = (component) => {
    if (component?.name !== TOOLBAR) return null;

    return component.$wire?.$interceptRequest?.(({ request, onError }) => {
      const payload = payloadFor(request);
      prepareRequest(runtime, request);

      onError?.(({ response, preventDefault }) => {
        if (response?.status !== 419 || !isToolbarOnly(payload)) return;

        preventDefault?.();
        const calls = profileCalls(payload);
        fetchToken(runtime).then((token) => {
          if (token) retryProfileCalls(component, calls);
        });
      });
    }) ?? null;
  };

  Livewire.hook?.('component.init', ({ component, cleanup }) => {
    const unregister = guard(component);
    if (unregister) cleanup?.(unregister);
  });
  (Livewire.all?.() ?? []).forEach(guard);
}
