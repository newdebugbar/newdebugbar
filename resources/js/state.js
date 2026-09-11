import { defaultRuntime, composeState } from './runtime.js';
import { createInspectorController } from './inspectors/index.js';
import { createInspectorShell } from './shell/inspector.js';
import { createNavigation } from './shell/navigation.js';
import { createRequests } from './shell/requests.js';
import { createActivityRefresh } from './shell/activity-refresh.js';
import { createToolbar } from './shell/toolbar.js';
import { createPreferences } from './shell/preferences.js';
import { createPalette } from './shell/palette.js';

/** Assembles the request shell; each mounted inspector creates and owns its Alpine state. */
export function createNewDebugBar(
  summary = {},
  runtime = null,
  recentProfiles = [],
  profileLimit = 20,
  livewireTrace = null,
) {
  const browser = runtime ?? defaultRuntime();
  const trace = livewireTrace ?? browser.livewireTrace ?? null;
  const context = { browser, trace, summary, recentProfiles, profileLimit, shell: null, inspector: null };

  return composeState(
    createInspectorShell(context),
    createNavigation(context),
    createRequests(context),
    createActivityRefresh(context),
    createToolbar(context),
    createPreferences(context),
    createPalette(context),
    {
      createInspector(inspector, profileId = this.summary.id) {
        return createInspectorController(inspector, {
          browser,
          trace,
          shell: context.shell ?? this,
          profileId,
        });
      },
    },
  );
}
