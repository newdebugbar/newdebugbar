import { defaultRuntime, composeState } from './runtime.js';
import { createSectionController } from './sections/index.js';
import { createInspector } from './shell/inspector.js';
import { createNavigation } from './shell/navigation.js';
import { createRequests } from './shell/requests.js';
import { createActivityRefresh } from './shell/activity-refresh.js';
import { createToolbar } from './shell/toolbar.js';
import { createPreferences } from './shell/preferences.js';
import { createPalette } from './shell/palette.js';

/** Assembles the request shell; each mounted section creates and owns its Alpine state. */
export function createNewDebugBar(
  summary = {},
  runtime = null,
  recentProfiles = [],
  profileLimit = 20,
  livewireTrace = null,
) {
  const browser = runtime ?? defaultRuntime();
  const trace = livewireTrace ?? browser.livewireTrace ?? null;
  const context = { browser, trace, summary, recentProfiles, profileLimit, shell: null, section: null };

  return composeState(
    createInspector(context),
    createNavigation(context),
    createRequests(context),
    createActivityRefresh(context),
    createToolbar(context),
    createPreferences(context),
    createPalette(context),
    {
      createSection(section, profileId = this.summary.id) {
        return createSectionController(section, { browser, trace, shell: context.shell ?? this, profileId });
      },
    },
  );
}
