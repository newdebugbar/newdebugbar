const TOOLBAR = 'newdebugbar.toolbar';
const ENDPOINT = '/__newdebugbar/csrf';

let freshToken = null;
let inFlight = null;

/** Reads the component names a Livewire request is carrying. */
const componentNames = (request) => {
  try {
    const body = request?.options?.body;
    if (typeof body !== 'string') return [];

    const components = JSON.parse(body)?.components;
    if (!Array.isArray(components) || components.length === 0) return [];

    return components.map((component) => {
      const snapshot = JSON.parse(component?.snapshot ?? '{}');
      return snapshot?.memo?.name ?? null;
    });
  } catch {
    return [];
  }
};

/**
 * A request Livewire batched with a host component is the host app's to handle.
 * Only a request carrying nothing but the toolbar is ours to swallow.
 */
const isToolbarOnly = (request) => {
  const names = componentNames(request);
  return names.length > 0 && names.every((name) => name === TOOLBAR);
};

const fetchToken = (runtime) => {
  if (inFlight) return inFlight;

  inFlight = Promise.resolve(runtime.fetch?.(ENDPOINT, {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
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
}

/**
 * Keeps a rotated CSRF token from turning into the host app's problem.
 *
 * The bar notices a profile by calling the toolbar over Livewire, and that call
 * is triggered by the host request that just finished. When the host request
 * regenerated the session — a login does — the page's token is already stale by
 * the time the toolbar uses it, so Livewire answers 419 and shows its "This page
 * has expired" dialog over an app that is working perfectly well. The toolbar
 * swallows its own 419, fetches the token the session actually holds, and sends
 * it on every later request of its own. The page's own token is left alone.
 */
export function installCsrfRecovery(runtime = window) {
  if (runtime.__newDebugBarCsrfRecovery) return;
  runtime.__newDebugBarCsrfRecovery = true;

  // The bundle runs before Livewire boots, so the hook has to wait for it.
  const install = () => attach(runtime, runtime.Livewire);

  runtime.addEventListener?.('livewire:init', install);
  install();
}

let installedLivewire = null;

function attach(runtime, Livewire) {
  if (!Livewire || installedLivewire === Livewire) return;
  installedLivewire = Livewire;

  const guard = (component) => {
    if (component?.name !== TOOLBAR) return;

    component.$wire?.$interceptRequest?.(({ request, onError }) => {
      if (freshToken && request?.options?.headers) {
        request.options.headers['X-CSRF-TOKEN'] = freshToken;
      }

      onError?.(({ response, preventDefault }) => {
        if (response?.status !== 419) return;
        if (!isToolbarOnly(request)) return;

        preventDefault?.();
        fetchToken(runtime);
      });
    });
  };

  Livewire.hook?.('component.init', ({ component }) => guard(component));
  (Livewire.all?.() ?? []).forEach(guard);
}
