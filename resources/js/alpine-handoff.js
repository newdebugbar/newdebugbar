/**
 * The bar injector flags the toolbar root with Alpine's shared `_x_ignore` marker
 * as soon as the markup is parsed, so a host page that starts its own Alpine
 * instance skips the toolbar instead of claiming it without Livewire's `$wire`
 * support. Livewire dispatches `livewire:init` synchronously right before starting
 * its own Alpine, which is the safe moment to release the flag: Livewire's Alpine
 * then initializes the toolbar, and a host instance that walks the page later
 * leaves it alone because every toolbar element already carries Alpine's
 * initialization marker.
 */
export function installAlpineHandoff(runtime = window) {
  if (runtime.__newDebugBarAlpineHandoff) return;
  runtime.__newDebugBarAlpineHandoff = true;

  runtime.addEventListener?.('livewire:init', () => {
    const root = runtime.document?.getElementById?.('newdebugbar');
    if (root) delete root._x_ignore;
  });
}
