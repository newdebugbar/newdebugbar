import { PROFILE_PATTERN } from '../../shell/requests.js';
import { formatDuration } from '../../duration.js';

/** Owns livewire activity inspector state and interactions. */
export function createActivity(context) {
  const { browser, shell } = context;
  const summary = shell.summary;
  return {
    livewireActivityType: 'all',
    livewireSelectedActivityId: null,
    livewireActivitySelectionPinned: false,
    livewireServerActivity: [],
    livewireClock: browser.now?.() ?? Date.now(),
    livewireClockTimer: null,
    livewireClockRunning: false,

    get livewireActivity() {
      const currentProfileId = PROFILE_PATTERN.test(shell.summary?.id ?? '') ? shell.summary.id : null;
      const isCurrentLivewireRequest = shell.summary?.request_type === 'livewire' && currentProfileId !== null;
      const serverActivity = this.livewireServerActivity;
      if (!this.livewireTrace.ready) return serverActivity;

      const byId = new Map(serverActivity.map((item) => [item.id, item]));
      const renderEvidence = new Map(
        serverActivity.map((item) => [item.id, (item.serverRenderIds ?? []).map((id) => byId.get(id)).filter(Boolean)]),
      );

      const consumedServerIds = new Set();
      const hasCurrentProfileActivity =
        currentProfileId !== null &&
        this.livewireTrace.activity.some((item) =>
          (Array.isArray(item?.profileIds) ? item.profileIds : []).includes(currentProfileId),
        );
      const matchingServerEvidence = (item) => {
        if (
          (isCurrentLivewireRequest || hasCurrentProfileActivity) &&
          !(Array.isArray(item?.profileIds) ? item.profileIds : []).includes(currentProfileId)
        ) {
          return [];
        }

        const actionNames = new Set((item.actions ?? []).map((action) => action.name));
        const changePaths = new Set((item.changes ?? []).map((change) => change.path));
        const eventNames = new Set([
          ...(item.events ?? []).map((event) => event.name),
          ...(item.actions ?? [])
            .filter((action) => action.name === '__dispatch')
            .map((action) => action.params?.[0])
            .filter(Boolean),
        ]);
        const candidates = serverActivity.filter(
          (serverItem) =>
            serverItem.kind !== 'render' &&
            !consumedServerIds.has(serverItem.id) &&
            String(serverItem.componentId) === String(item.componentId),
        );

        if (item.kind === 'mount') return candidates.filter((serverItem) => serverItem.kind === 'mount').slice(0, 1);

        const exact = candidates.filter((serverItem) => {
          if (serverItem.kind === 'action') return actionNames.has(serverItem.actions?.[0]?.name);
          if (serverItem.kind === 'change') return changePaths.has(serverItem.changes?.[0]?.path);
          if (serverItem.kind === 'failure') {
            return ['failed', 'failed_validation'].includes(item.status);
          }
          if (['event', 'event_received'].includes(serverItem.kind)) {
            return eventNames.has(serverItem.events?.[0]?.name);
          }

          return serverItem.kind === item.kind;
        });

        return exact;
      };
      const browserActivity = this.livewireTrace.activity.map((item) => {
        const evidence = matchingServerEvidence(item);
        if (evidence.length === 0) return item;

        const renders = evidence.flatMap((serverItem) => renderEvidence.get(serverItem.id) ?? []);
        evidence.forEach((serverItem) => consumedServerIds.add(serverItem.id));
        renders.forEach((render) => consumedServerIds.add(render.id));
        const requestTimes = evidence
          .map((serverItem) => serverItem.requestAtMs)
          .filter((at) => at !== null && at !== undefined)
          .map(Number)
          .filter(Number.isFinite);
        const renderDurations = evidence
          .map((item) => item.serverRenderDurationMs)
          .filter((duration) => duration !== null && duration !== undefined)
          .map(Number)
          .filter(Number.isFinite);
        const mount = evidence.find((serverItem) => serverItem.kind === 'mount') ?? null;
        const callsite = evidence.find((serverItem) => serverItem.callsite)?.callsite ?? item.callsite ?? null;

        return {
          ...item,
          callsite,
          requestAtMs: requestTimes.length > 0 ? Math.min(...requestTimes) : null,
          initialRenderDurationMs: item.kind === 'mount' ? (mount?.initialRenderDurationMs ?? null) : null,
          serverRenderDurationMs:
            renderDurations.length > 0 ? renderDurations.reduce((total, duration) => total + duration, 0) : null,
          serverActivityIds: evidence.map((serverItem) => serverItem.id),
          serverRenderIds: renders.map((render) => render.id),
          serverMountId: mount?.id ?? null,
          serverRenderId: item.kind === 'mount' ? (renders[0]?.id ?? null) : null,
        };
      });

      const unmatchedServerActivity = serverActivity.flatMap((item) => {
        if (consumedServerIds.has(item.id)) return [];
        if (item.kind === 'render') return [item];

        const renders = (renderEvidence.get(item.id) ?? []).filter((render) => !consumedServerIds.has(render.id));
        renders.forEach((render) => consumedServerIds.add(render.id));
        return [
          {
            ...item,
            serverActivityIds: [item.id],
            serverRenderIds: renders.map((render) => render.id),
            serverMountId: item.kind === 'mount' ? item.id : null,
            serverRenderId: item.kind === 'mount' ? (renders[0]?.id ?? null) : null,
          },
        ];
      });

      return [...browserActivity, ...unmatchedServerActivity]
        .sort((left, right) => {
          const leftAt = Number(left.requestAtMs);
          const rightAt = Number(right.requestAtMs);
          const leftHasTime = left.requestAtMs !== null && left.requestAtMs !== undefined && Number.isFinite(leftAt);
          const rightHasTime =
            right.requestAtMs !== null && right.requestAtMs !== undefined && Number.isFinite(rightAt);

          if (leftHasTime && rightHasTime && leftAt !== rightAt) return leftAt - rightAt;
          if (leftHasTime !== rightHasTime) return leftHasTime ? -1 : 1;

          const sequence = Number(left.sequence ?? 0) - Number(right.sequence ?? 0);
          return sequence !== 0 ? sequence : String(left.id).localeCompare(String(right.id));
        })
        .map((item, index) => ({ ...item, sequence: index + 1 }));
    },

    get filteredLivewireActivity() {
      const search = this.livewireSearch.toLowerCase().trim();

      const items = this.livewireActivity.filter(
        (item) =>
          (this.livewireActivityType === 'all' || item.kind === this.livewireActivityType) &&
          [
            item.title,
            item.kind,
            item.status,
            item.componentTitle,
            item.componentName,
            this.livewireActivitySourceLabel(item),
            ...this.livewireMeaningfulActions(item).map((action) => action.name),
            ...item.changes.map((change) => change.path),
            ...this.livewireActivityEvents(item).map((event) => event.name),
          ].some((value) =>
            String(value ?? '')
              .toLowerCase()
              .includes(search),
          ),
      );

      return [...items].sort((left, right) => Number(right.sequence ?? 0) - Number(left.sequence ?? 0));
    },

    get livewireActivityTypes() {
      return [...new Set(this.livewireActivity.map((item) => item.kind))].sort();
    },

    get selectedLivewireActivity() {
      return this.livewireActivity.find((item) => item.id === this.livewireSelectedActivityId) ?? null;
    },

    setLivewireActivityType(type) {
      if (type !== 'all' && !this.livewireActivityTypes.includes(type)) return;
      this.livewireActivityType = type;
      this.syncLivewireSelection();
    },

    selectLivewireActivity(id) {
      if (!this.livewireActivity.some((item) => item.id === id)) return;
      this.livewireSelectedActivityId = id;
      this.livewireActivitySelectionPinned = true;
      this.livewireDetailTab = 'overview';
      this.livewireDetailOpen = true;
      this.$nextTick?.(() => browser.highlight?.());
    },

    livewireActivityComponent(item) {
      return this.livewireComponentById(item?.componentId);
    },

    livewireActivityComponentTitle(item) {
      return this.livewireActivityComponent(item)?.title ?? item?.componentTitle ?? 'Livewire component';
    },

    livewireActivityParentTitle(item) {
      const component = this.livewireActivityComponent(item);

      return component?.parentId ? this.livewireComponentTitle(component.parentId) : 'Top level';
    },

    livewireActivityShowsComponent(item) {
      const componentTitle = this.livewireActivityComponentTitle(item).trim();
      const activityTitle = String(item?.title ?? '').trim();

      if (!componentTitle || componentTitle === 'Livewire component') return false;

      return !activityTitle.toLowerCase().includes(componentTitle.toLowerCase());
    },

    livewireMeaningfulActions(item) {
      return (item?.actions ?? []).filter((action) => !['$commit', '$set', '__dispatch'].includes(action.name));
    },

    livewireActivityEvents(item) {
      const events = [...(item?.events ?? [])];

      (item?.actions ?? [])
        .filter((action) => action.name === '__dispatch' && action.params?.[0])
        .forEach((action) => {
          const name = action.params[0];
          if (events.some((event) => event.name === name)) return;

          events.push({
            name,
            params: action.params[1] ?? {},
            mode: 'received',
            declaredTarget: null,
            observedRecipientIds: [],
          });
        });

      return events;
    },

    livewireActivityProfileIds(item) {
      return [
        ...new Set(
          (Array.isArray(item?.profileIds) ? item.profileIds : []).filter((id) => PROFILE_PATTERN.test(id ?? '')),
        ),
      ];
    },

    livewireActivitySourceLabel(item) {
      const callsite = item?.callsite;
      const file = typeof callsite?.file === 'string' ? callsite.file.trim() : '';
      if (file === '') return null;

      const line = Number(callsite?.line);

      return Number.isInteger(line) && line > 0 ? `${file}:${line}` : file;
    },

    livewireActivityStatusLabel(item) {
      return (
        {
          complete: 'Finished',
          updating: 'Running',
          failed: 'Failed',
          failed_validation: 'Validation failed',
          cancelled: 'Cancelled',
          skipped: 'Skipped',
        }[item?.status] ?? 'Recorded'
      );
    },

    livewireActivitySummary(item) {
      if (!item) return '';
      const component = item.componentTitle || 'This component';
      const failed = ['failed', 'failed_validation', 'cancelled'].includes(item.status);

      if (item.kind === 'mount') return `${component} was added to the page.`;
      if (item.kind === 'unmount') return `${component} was removed from the page.`;
      if (item.kind === 'poll') return `${component} asked the server for fresh state.`;
      if (item.kind === 'event') {
        const event = this.livewireActivityEvents(item)[0]?.name;
        return event ? `${component} handled the ${event} event.` : `${component} handled a Livewire event.`;
      }
      if (item.kind === 'mutation') {
        const paths = item.changes?.map((change) => change.path) ?? [];
        const confirmed = item.changes?.some((change) => change.serverKnown === true);
        if (paths.length === 1) {
          if (failed) return `${component} tried to change ${paths[0]}, but the update did not finish.`;
          return `${component} changed ${paths[0]}${confirmed ? ' and the server confirmed it' : ''}.`;
        }
        if (paths.length > 1) {
          if (failed) return `${component} tried to change ${paths.length} properties, but the update did not finish.`;
          return `${component} changed ${paths.length} properties${confirmed ? ' and the server confirmed them' : ''}.`;
        }
        return `${component} sent a state change to the server.`;
      }
      if (item.kind === 'action') {
        const actions = this.livewireMeaningfulActions(item);
        if (actions.length === 1) {
          return failed
            ? `${component} tried to run ${actions[0].name}, but the update did not finish.`
            : `${component} ran ${actions[0].name} on the server.`;
        }
        if (actions.length > 1) return `${component} ran ${actions.length} actions on the server.`;
      }

      return `${component} completed a Livewire update.`;
    },

    livewireActivityPhaseGroups(item) {
      const phases = item?.phases ?? [];
      const requestNames = new Set(['Queued', 'Sent', 'Responded', 'Streamed']);
      const request = phases.filter((phase) => requestNames.has(phase.name));
      const browserPhases = phases.filter((phase) => !requestNames.has(phase.name));

      return [
        { name: 'Request', phases: request },
        { name: 'Browser', phases: browserPhases },
      ].filter((group) => group.phases.length > 0);
    },

    livewirePhaseDescription(name) {
      return (
        {
          Queued: 'Livewire prepared the update.',
          Sent: 'The browser sent the request.',
          Responded: 'The server response arrived.',
          Streamed: 'A streamed response chunk arrived.',
          Synced: 'Server state replaced the browser baseline.',
          Effects: 'Livewire applied returned effects and events.',
          Morphed: 'Livewire updated the page HTML.',
          Rendered: 'The browser finished this render pass.',
        }[name] ?? 'Livewire recorded this phase.'
      );
    },

    livewireDuration(item) {
      if (item?.durationMs === null || item?.durationMs === undefined)
        return item?.status === 'updating' ? 'In progress' : '—';
      return formatDuration(item.durationMs);
    },

    livewireInitialRenderDuration(item) {
      if (item?.initialRenderDurationMs === null || item?.initialRenderDurationMs === undefined) return 'Not captured';
      const duration = Number(item?.initialRenderDurationMs);
      if (!Number.isFinite(duration)) return 'Not captured';

      return this.livewireDuration({
        durationMs: duration,
        status: 'complete',
      });
    },

    livewireMountTime(item) {
      if (item?.requestAtMs === null || item?.requestAtMs === undefined) return 'Not captured';
      const at = Number(item?.requestAtMs);
      if (!Number.isFinite(at)) return 'Not captured';

      return `+${formatDuration(at)}`;
    },

    livewireActivityTime(item) {
      return item?.kind === 'mount' && item?.requestAtMs !== null && item?.requestAtMs !== undefined
        ? this.livewireMountTime(item)
        : this.livewireActivityAge(item);
    },

    livewireActivityDuration(item) {
      if (item?.kind !== 'mount') return this.livewireDuration(item);

      const duration = this.livewireInitialRenderDuration(item);

      return duration === 'Not captured' ? 'Render —' : `Render ${duration}`;
    },

    livewireActivityAge(item) {
      const occurredAt = Number(item?.occurredAt);
      if (!Number.isFinite(occurredAt) || occurredAt <= 0) return 'Current request';

      const seconds = Math.max(0, Math.floor((this.livewireClock - occurredAt) / 1000));
      if (seconds < 1) return 'Now';
      if (seconds < 60) return `${seconds} sec ago`;

      const minutes = Math.floor(seconds / 60);
      if (minutes < 60) return `${minutes} min ago`;

      const hours = Math.floor(minutes / 60);
      if (hours < 24) return `${hours} hr ago`;

      const days = Math.floor(hours / 24);
      return `${days} ${days === 1 ? 'day' : 'days'} ago`;
    },

    scheduleLivewireClock() {
      if (!this.livewireClockRunning) return;

      this.livewireClockTimer = browser.schedule?.(() => {
        this.livewireClock = browser.now?.() ?? Date.now();
        this.livewireClockTimer = null;
        this.scheduleLivewireClock();
      }, 1000);
    },
  };
}
