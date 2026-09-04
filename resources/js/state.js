import { formatDuration } from './duration.js';

const STORAGE_KEY = 'newdebugbar.preferences.v1';
const DEFAULT_SECTION = 'request';
const PROFILE_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const TOOLBAR_PLACEMENTS = ['top-left', 'top', 'top-right', 'bottom-left', 'bottom', 'bottom-right'];
const TOOLBAR_CORNER_PLACEMENTS = TOOLBAR_PLACEMENTS.filter((placement) => placement.includes('-'));
const ACTIVITY_POLL_LIMIT = 30;
const ACTIVITY_POLL_INTERVAL = 1000;
const MAIL_PREVIEW_WIDTHS = {
  desktop: 1024,
  mobile: 375,
};
const toolbarHorizontalPlacement = (placement) => {
  if (placement.endsWith('-left')) return 'left';
  if (placement.endsWith('-right')) return 'right';

  return 'center';
};
const toolbarVerticalPlacement = (placement) => (placement.startsWith('top') ? 'top' : 'bottom');
const isLivewirePrimitive = (value) =>
  value === null || typeof value === 'boolean' || typeof value === 'number' || typeof value === 'string';
const livewireValueType = (value) => {
  if (value === null) return 'Null';
  if (Array.isArray(value)) return 'Array';
  if (typeof value === 'boolean') return 'Boolean';
  if (typeof value === 'number') return Number.isInteger(value) ? 'Integer' : 'Float';
  if (typeof value === 'string') return 'String';

  return 'Object';
};
const livewireValueCopy = (value) => {
  try {
    return JSON.parse(JSON.stringify(value));
  } catch {
    return value;
  }
};
const livewireValueSummary = (value) => {
  if (value === null) return 'null';
  if (value === true) return 'true';
  if (value === false) return 'false';
  if (typeof value === 'string') return value === '' ? 'Empty string' : value;
  if (typeof value === 'number') return String(value);
  if (Array.isArray(value)) return `${value.length} ${value.length === 1 ? 'item' : 'items'}`;
  if (typeof value === 'object') {
    const count = Object.keys(value).length;
    return `${count} ${count === 1 ? 'property' : 'properties'}`;
  }

  return String(value);
};

const writeTextToClipboard = async (value) => {
  if (typeof window.navigator.clipboard?.writeText === 'function') {
    try {
      await window.navigator.clipboard.writeText(value);

      return true;
    } catch {
      // Fall back to the synchronous copy path below.
    }
  }

  const target = document.createElement('textarea');
  const activeElement = document.activeElement;
  const selection = document.getSelection();
  const selectedRanges = selection
    ? Array.from({ length: selection.rangeCount }, (_, index) => selection.getRangeAt(index))
    : [];

  target.value = value;
  target.readOnly = true;
  target.tabIndex = -1;
  target.style.setProperty('all', 'initial', 'important');
  target.style.setProperty('display', 'block', 'important');
  target.style.setProperty('position', 'fixed', 'important');
  target.style.setProperty('inset', '0 auto auto 0', 'important');
  target.style.setProperty('width', '1px', 'important');
  target.style.setProperty('height', '1px', 'important');
  target.style.setProperty('padding', '0', 'important');
  target.style.setProperty('border', '0', 'important');
  target.style.setProperty('opacity', '0', 'important');
  target.style.setProperty('pointer-events', 'none', 'important');

  document.body.append(target);
  target.focus({ preventScroll: true });
  target.select();

  let copied = false;

  try {
    copied = document.execCommand?.('copy') === true;
  } catch {
    // Clipboard policies must never break the host page.
  } finally {
    target.remove();
    activeElement?.focus?.({ preventScroll: true });

    if (selection) {
      try {
        selection.removeAllRanges();
        selectedRanges.forEach((range) => selection.addRange(range));
      } catch {
        // The host selection may have changed while copying.
      }
    }
  }

  return copied;
};

const defaultRuntime = () => ({
  storage: {
    getItem: (key) => window.localStorage.getItem(key),
    setItem: (key, value) => window.localStorage.setItem(key, value),
  },
  matchMedia: (query) => window.matchMedia(query),
  activeElement: () => document.activeElement,
  queryAll: (selector) => document.querySelectorAll(selector),
  writeClipboard: writeTextToClipboard,
  highlight: () => window.newDebugBarHighlight?.(document.getElementById('newdebugbar')),
  afterPaint: (callback) => window.requestAnimationFrame(() => window.requestAnimationFrame(callback)),
  nextFrame: (callback) => window.requestAnimationFrame(callback),
  schedule: (callback, delay) => window.setTimeout(callback, delay),
  cancelSchedule: (timer) => window.clearTimeout(timer),
  now: () => Date.now(),
  viewportWidth: () => window.innerWidth,
  viewportHeight: () => window.innerHeight,
  lockHost: (root) => {
    if (!root || root.__newDebugBarHostLock) return;

    const body = document.body;
    const scrollbarWidth = Math.max(0, window.innerWidth - document.documentElement.clientWidth);
    const previous = {
      overflow: body.style.overflow,
      paddingRight: body.style.paddingRight,
      inert: [],
    };

    [...body.children].forEach((element) => {
      if (
        element === root ||
        element.contains(root) ||
        !(element instanceof HTMLElement) ||
        element.matches('script, style, link')
      )
        return;

      previous.inert.push([element, element.inert]);
      element.inert = true;
    });

    body.style.overflow = 'hidden';

    if (scrollbarWidth > 0) {
      body.style.paddingRight = `${Number.parseFloat(window.getComputedStyle(body).paddingRight || '0') + scrollbarWidth}px`;
    }

    root.__newDebugBarHostLock = previous;
  },
  unlockHost: (root) => {
    const previous = root?.__newDebugBarHostLock;
    if (!previous) return;

    document.body.style.overflow = previous.overflow;
    document.body.style.paddingRight = previous.paddingRight;
    previous.inert.forEach(([element, inert]) => {
      element.inert = inert;
    });
    delete root.__newDebugBarHostLock;
  },
  toolbarPlacement: (root, preferred = 'bottom') => {
    const toolbar = root?.querySelector?.('[data-ndb-toolbar-shell]');
    if (!toolbar) return 'bottom';

    const validPreferred = TOOLBAR_PLACEMENTS.includes(preferred) ? preferred : 'bottom';
    const toolbarBox = toolbar.getBoundingClientRect();
    const width = Math.min(toolbarBox.width, Math.max(0, window.innerWidth - 24));
    const height = toolbarBox.height;
    const horizontal = toolbarHorizontalPlacement(validPreferred);
    const left =
      horizontal === 'left'
        ? 12
        : horizontal === 'right'
          ? Math.max(12, window.innerWidth - width - 12)
          : (window.innerWidth - width) / 2;
    const topPlacement = horizontal === 'center' ? 'top' : `top-${horizontal}`;
    const bottomPlacement = horizontal === 'center' ? 'bottom' : `bottom-${horizontal}`;
    const candidates = {
      [topPlacement]: {
        left,
        right: left + width,
        top: 12,
        bottom: 12 + height,
      },
      [bottomPlacement]: {
        left,
        right: left + width,
        top: window.innerHeight - height - 12,
        bottom: window.innerHeight - 12,
      },
    };
    const dialogs = [...document.querySelectorAll('dialog[open], [role="dialog"]')]
      .filter((dialog) => !root.contains(dialog))
      .map((dialog) => ({ dialog, box: dialog.getBoundingClientRect() }))
      .filter(({ dialog, box }) => {
        const style = window.getComputedStyle(dialog);

        return box.width > 0 && box.height > 0 && style.display !== 'none' && style.visibility !== 'hidden';
      })
      .map(({ box }) => box);
    const overlap = (candidate) =>
      dialogs.reduce((area, dialog) => {
        const widthOverlap = Math.max(
          0,
          Math.min(candidate.right, dialog.right) - Math.max(candidate.left, dialog.left),
        );
        const heightOverlap = Math.max(
          0,
          Math.min(candidate.bottom, dialog.bottom) - Math.max(candidate.top, dialog.top),
        );

        return area + widthOverlap * heightOverlap;
      }, 0);

    const topOverlap = overlap(candidates[topPlacement]);
    const bottomOverlap = overlap(candidates[bottomPlacement]);

    if (topOverlap === bottomOverlap) return validPreferred;

    return topOverlap < bottomOverlap ? topPlacement : bottomPlacement;
  },
  watchHostDialogs: (_root, callback) => {
    let frame = null;
    const schedule = () => {
      if (frame !== null) return;
      frame = window.requestAnimationFrame(() => {
        frame = null;
        callback();
      });
    };
    const observer = new MutationObserver(schedule);
    observer.observe(document.body, {
      attributes: true,
      attributeFilter: ['open', 'aria-hidden', 'aria-modal', 'class', 'style'],
      childList: true,
      subtree: true,
    });
    window.addEventListener('resize', schedule);
    window.addEventListener('scroll', schedule, true);

    return () => {
      observer.disconnect();
      window.removeEventListener('resize', schedule);
      window.removeEventListener('scroll', schedule, true);
      if (frame !== null) window.cancelAnimationFrame(frame);
    };
  },
  observeNearEnd: (target, scrollOwner, callback) => {
    if (!target || !scrollOwner || typeof callback !== 'function') return null;

    if (typeof window.IntersectionObserver === 'function') {
      const observer = new window.IntersectionObserver(
        (entries) => {
          if (entries.some((entry) => entry.isIntersecting)) callback();
        },
        {
          root: scrollOwner,
          rootMargin: '0px 0px 160px 0px',
          threshold: 0,
        },
      );
      observer.observe(target);

      return () => observer.disconnect();
    }

    let frame = null;
    const check = () => {
      frame = null;
      if (!target.isConnected || !scrollOwner.isConnected) return;

      const targetBox = target.getBoundingClientRect();
      const ownerBox = scrollOwner.getBoundingClientRect();

      if (targetBox.top <= ownerBox.bottom + 160 && targetBox.bottom >= ownerBox.top) callback();
    };
    const schedule = () => {
      if (frame !== null) return;
      frame = window.requestAnimationFrame(check);
    };

    scrollOwner.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule);
    schedule();

    return () => {
      scrollOwner.removeEventListener('scroll', schedule);
      window.removeEventListener('resize', schedule);
      if (frame !== null) window.cancelAnimationFrame(frame);
    };
  },
});

export function createNewDebugBar(
  summary = {},
  runtime = null,
  recentProfiles = [],
  profileLimit = 20,
  livewireTrace = null,
) {
  const browser = runtime ?? defaultRuntime();
  const trace = livewireTrace ?? browser.livewireTrace ?? null;
  const requestLimit = Number.isInteger(profileLimit) && profileLimit > 0 ? profileLimit : 20;
  const requests = [];

  [summary, ...(Array.isArray(recentProfiles) ? recentProfiles : [])].forEach((profile) => {
    if (!PROFILE_PATTERN.test(profile?.id ?? '') || requests.some((request) => request.id === profile.id)) return;
    requests.push(profile);
  });

  return {
    formatDuration,
    barVisible: true,
    inspectorOpen: false,
    inspectorReturnFocus: null,
    mobileSectionsOpen: false,
    mobileSectionsReturnFocus: null,
    mobileToolbarMenu: null,
    mobileToolbarReturnFocus: null,
    themeMenuScope: null,
    themeMenuReturnFocus: null,
    requestPickerScope: null,
    requestPickerReturnFocus: null,
    requestPickerArrowLeft: 0,
    recentProfiles: requests.slice(0, requestLimit),
    currentRequestId: summary.id ?? null,
    profileLimit: requestLimit,
    pendingProfileIds: [],
    requestSelectionPending: null,
    relatedProfileSelection: null,
    loadedSection: null,
    requestedSection: null,
    sectionLoading: false,
    sectionLoadingIndicator: false,
    sectionLoadingTimer: null,
    sectionTransitioning: false,
    sectionError: false,
    sectionRequestVersion: 0,
    activityPollAttempts: 0,
    activityPollTimer: null,
    activityRefreshPending: false,
    selected: DEFAULT_SECTION,
    theme: ['system', 'light', 'dark'].includes(summary.theme) ? summary.theme : 'system',
    resolvedTheme: 'light',
    toolbarPlacement: 'bottom',
    toolbarPreferredPlacement: 'bottom',
    stopToolbarPlacementWatch: null,
    toolbarDragging: false,
    toolbarRebasing: false,
    toolbarSnapping: false,
    toolbarDragPointerId: null,
    toolbarDragStartX: 0,
    toolbarDragStartY: 0,
    toolbarDragPointerOffsetX: 0,
    toolbarDragPointerOffsetY: 0,
    toolbarDragPointerRatioX: 0.5,
    toolbarDragPointerRatioY: 0.5,
    toolbarDragWidth: 0,
    toolbarDragHeight: 0,
    toolbarDragOffsetX: 0,
    toolbarDragOffsetY: 0,
    toolbarCenterWidth: 0,
    toolbarCenterHeight: 60,
    toolbarCornerWidth: 196,
    toolbarCornerHeight: 56,
    toolbarDragTarget: 'bottom',
    toolbarDragOriginPlacement: 'bottom',
    toolbarSuppressClick: false,
    toolbarSnapTimer: null,
    toolbarSnapVersion: 0,
    toolbarClickTimer: null,
    favorites: [],
    favoriteDrag: null,
    favoriteDrop: null,
    favoriteDropAfter: false,
    cacheOperations: [],
    cacheFilter: 'all',
    cacheSearch: '',
    cacheSelected: null,
    cacheDetailOpen: false,
    visibleCacheCount: summary.section_counts?.cache ?? 0,
    httpClientRequests: [],
    httpClientFilter: 'all',
    httpClientSearch: '',
    httpClientSort: 'execution',
    httpClientSortDirection: 'asc',
    httpClientSelected: null,
    httpClientDetailOpen: false,
    httpClientDetailTab: 'response',
    visibleHttpClientCount: summary.section_counts?.http_client ?? 0,
    mailMessages: [],
    pendingMailMessageId: null,
    mailFilter: 'all',
    mailSearch: '',
    mailSelected: null,
    mailDetailOpen: false,
    mailDetailTab: 'preview',
    mailPreviewFormat: 'html',
    mailPreviewViewport: 'desktop',
    mailPreviewFrameCleanup: null,
    visibleMailCount: summary.section_counts?.mail ?? 0,
    notificationGroups: [],
    notificationFilter: 'all',
    notificationSearch: '',
    notificationSelected: null,
    notificationDetailOpen: false,
    notificationDetailTab: 'delivery',
    notificationChannel: null,
    visibleNotificationCount: summary.section_counts?.notifications ?? 0,
    queryRecords: [],
    queueActivities: [],
    queueFilter: 'all',
    queueSearch: '',
    queueSelected: null,
    queueDetailOpen: false,
    visibleQueueCount: summary.section_counts?.queue ?? 0,
    redisCommands: [],
    redisFilter: 'all',
    redisSearch: '',
    redisSelected: null,
    redisDetailOpen: false,
    visibleRedisCount: summary.section_counts?.redis ?? 0,
    queryFilter: 'all',
    querySearch: '',
    querySort: 'execution',
    querySortDirection: 'asc',
    querySelected: null,
    querySelectedExecution: null,
    queryDetailOpen: false,
    queryDetailTab: 'overview',
    queryDetailReturnFocus: null,
    queryExplain: null,
    queryExplainError: null,
    queryExplainLoading: false,
    queryExplainExecution: null,
    queryExplainScrollTop: null,
    queryFocusFilter: null,
    viewGroups: [],
    viewFilter: 'application',
    viewSearch: '',
    viewSelected: null,
    viewDetailOpen: false,
    viewRenderOrder: null,
    viewData: null,
    viewDataLoaded: false,
    viewDataLoading: false,
    viewDataError: false,
    viewDataRequest: 0,
    visibleViewCount: 0,
    visibleViewRenderCount: 0,
    visibleQueryCount: summary.query_count ?? 0,
    modelGroupCount: 0,
    modelSearch: '',
    modelSort: 'capture',
    modelSortDirection: 'asc',
    visibleModelCount: 0,
    modelSelected: null,
    modelDetailOpen: false,
    modelDetailTab: 'records',
    modelListScrollTop: 0,
    authorizationDecisions: [],
    authorizationFilter: 'all',
    authorizationSearch: '',
    authorizationSelected: null,
    authorizationDetailOpen: false,
    visibleAuthorizationCount: summary.section_counts?.authorization ?? 0,
    timelineFilter: 'key',
    timelineSearch: '',
    timelineSelected: null,
    timelineDetailOpen: false,
    timelineLoadingMore: false,
    timelinePaginationError: false,
    timelinePaginationCleanup: null,
    timelinePaginationRequest: 0,
    visibleTimelineCount: summary.section_counts?.timeline ?? 0,
    eventGroups: [],
    eventSource: 'all',
    eventSearch: '',
    eventSelected: null,
    eventDetailOpen: false,
    eventDetailTab: 'overview',
    eventDetailReturnFocus: null,
    visibleEventCount: summary.section_counts?.events ?? 0,
    visibleEventGroupCount: 0,
    logLevel: 'all',
    logChannel: 'all',
    logSearch: '',
    logDetailSequence: null,
    logDetailOpen: false,
    visibleLogCount: summary.section_counts?.logs ?? 0,
    visibleLogGroupCount: summary.section_counts?.logs ?? 0,
    livewireTab: 'activity',
    livewireSearch: '',
    livewireActivityType: 'all',
    livewireDetailTab: 'overview',
    livewireSelectedActivityId: null,
    livewireActivitySelectionPinned: false,
    livewireSelectedComponentId: null,
    livewireDetailOpen: false,
    livewireTrace: {
      ready: false,
      components: [],
      activity: [],
      dropped: { components: 0, activity: 0 },
    },
    livewireServerComponents: [],
    livewireServerActivity: [],
    livewireCollapsedComponents: [],
    livewireKnownComponentParents: [],
    livewireExpandedProperties: [],
    livewireDrafts: {},
    stopLivewireTrace: null,
    livewireClock: browser.now?.() ?? Date.now(),
    livewireClockTimer: null,
    livewireClockRunning: false,
    paletteOpen: false,
    paletteSearch: '',
    paletteIndex: 0,
    paletteShowQuiet: false,
    paletteReturnFocus: null,
    colorScheme: null,
    colorSchemeListener: null,
    summary,

    init() {
      this.restore();
      this.stopLivewireTrace =
        trace?.subscribe?.((snapshot) => {
          if (
            this.livewireTrace.pageSequence !== undefined &&
            snapshot.pageSequence !== this.livewireTrace.pageSequence
          ) {
            this.livewireSelectedActivityId = null;
            this.livewireSelectedComponentId = null;
            this.livewireActivitySelectionPinned = false;
            this.livewireCollapsedComponents = [];
            this.livewireKnownComponentParents = [];
          }
          this.livewireTrace = snapshot;
          this.syncLivewireSelection();
        }) ?? null;
      this.livewireClockRunning = true;
      this.scheduleLivewireClock();
      this.colorScheme = browser.matchMedia?.('(prefers-color-scheme: dark)') ?? null;
      this.colorSchemeListener = () => {
        if (this.theme === 'system') this.applyTheme();
      };
      this.applyTheme();
      this.$nextTick?.(() => {
        this.syncSectionPanels();
        this.syncHostLock();
        this.syncToolbarPlacement();
        this.stopToolbarPlacementWatch =
          browser.watchHostDialogs?.(this.$root, () => this.syncToolbarPlacement()) ?? null;
      });
      this.colorScheme?.addEventListener?.('change', this.colorSchemeListener);
    },

    destroy() {
      this.resetTimelinePagination();
      this.mailPreviewFrameCleanup?.();
      this.mailPreviewFrameCleanup = null;
      this.colorScheme?.removeEventListener?.('change', this.colorSchemeListener);
      this.colorScheme = null;
      this.colorSchemeListener = null;
      this.stopToolbarPlacementWatch?.();
      this.stopToolbarPlacementWatch = null;
      this.stopLivewireTrace?.();
      this.stopLivewireTrace = null;
      this.livewireClockRunning = false;
      browser.cancelSchedule?.(this.livewireClockTimer);
      this.livewireClockTimer = null;
      this.requestPickerScope = null;
      this.requestPickerReturnFocus = null;
      this.toolbarSnapVersion += 1;
      browser.cancelSchedule?.(this.toolbarSnapTimer);
      browser.cancelSchedule?.(this.toolbarClickTimer);
      browser.cancelSchedule?.(this.activityPollTimer);
      browser.cancelSchedule?.(this.sectionLoadingTimer);
      this.toolbarSnapTimer = null;
      this.toolbarClickTimer = null;
      this.activityPollTimer = null;
      this.sectionLoadingTimer = null;
      this.activityRefreshPending = false;
      browser.unlockHost?.(this.$root);
    },

    restore() {
      try {
        const saved = JSON.parse(browser.storage?.getItem(STORAGE_KEY) ?? '{}');

        if (['system', 'light', 'dark'].includes(saved.theme)) this.theme = saved.theme;
        if (TOOLBAR_PLACEMENTS.includes(saved.toolbarAnchor)) {
          this.toolbarPreferredPlacement = saved.toolbarAnchor;
          this.toolbarPlacement = saved.toolbarAnchor;
          this.toolbarDragTarget = saved.toolbarAnchor;
          this.toolbarDragOriginPlacement = saved.toolbarAnchor;
        }
        if (Array.isArray(saved.favorites)) {
          const allowed = this.sectionKeys;
          this.favorites = [...new Set(saved.favorites)].filter((key) => allowed.includes(key));
        }
      } catch {
        // A broken preference must never break the host page.
      }
    },

    persist() {
      try {
        browser.storage?.setItem(
          STORAGE_KEY,
          JSON.stringify({
            theme: this.theme,
            toolbarAnchor: this.toolbarPreferredPlacement,
            favorites: this.favorites,
          }),
        );
      } catch {
        // Private browsing and strict storage policies are allowed.
      }
    },

    get sectionKeys() {
      return (this.summary.sections ?? []).map((section) => section.key);
    },

    get allCommands() {
      const sections = (this.summary.sections ?? []).map((section) => ({
        id: `section:${section.key}`,
        label: `Go to ${section.label}`,
        hint: section.active === false ? 'Other collector' : section.attention ? 'Needs attention' : 'Active section',
        priority: section.attention ? 0 : section.active === false ? 2 : 1,
      }));

      return [
        ...sections.sort((left, right) => left.priority - right.priority || left.label.localeCompare(right.label)),
        { id: 'theme:system', label: 'Use system theme', hint: 'Theme' },
        { id: 'theme:light', label: 'Use light theme', hint: 'Theme' },
        { id: 'theme:dark', label: 'Use dark theme', hint: 'Theme' },
        { id: 'toolbar:top', label: 'Pin to top', hint: 'Toolbar' },
        { id: 'toolbar:bottom', label: 'Pin to bottom', hint: 'Toolbar' },
        { id: 'toolbar:top-left', label: 'Pin to top left', hint: 'Toolbar' },
        { id: 'toolbar:top-right', label: 'Pin to top right', hint: 'Toolbar' },
        {
          id: 'toolbar:bottom-left',
          label: 'Pin to bottom left',
          hint: 'Toolbar',
        },
        {
          id: 'toolbar:bottom-right',
          label: 'Pin to bottom right',
          hint: 'Toolbar',
        },
      ];
    },

    get toolbarIsCorner() {
      return TOOLBAR_CORNER_PLACEMENTS.includes(this.toolbarPlacement);
    },

    get toolbarIsTop() {
      return toolbarVerticalPlacement(this.toolbarPlacement) === 'top';
    },

    get toolbarIsLeft() {
      return toolbarHorizontalPlacement(this.toolbarPlacement) === 'left';
    },

    get toolbarIsRight() {
      return toolbarHorizontalPlacement(this.toolbarPlacement) === 'right';
    },

    get toolbarVerticalPlacement() {
      return toolbarVerticalPlacement(this.toolbarPlacement);
    },

    get hiddenCommandCount() {
      return this.allCommands.filter((command) => command.hint === 'Other collector').length;
    },

    get filteredCommands() {
      const words = this.paletteSearch.toLowerCase().trim().split(/\s+/).filter(Boolean);

      if (words.length === 0 && !this.paletteShowQuiet) {
        const active = this.allCommands.filter((command) => command.hint !== 'Other collector');

        return this.hiddenCommandCount > 0
          ? [
              ...active,
              {
                id: 'collectors:show',
                label: 'Show other collectors',
                hint: `${this.hiddenCommandCount} hidden`,
              },
            ]
          : active;
      }

      if (words.length === 0) return this.allCommands;

      return this.allCommands.filter((command) => {
        const value = `${command.label} ${command.hint}`.toLowerCase();
        return words.every((word) => value.includes(word));
      });
    },

    get orderedSections() {
      const allSections = this.summary.sections ?? [];
      const byKey = new Map(allSections.map((section) => [section.key, section]));
      const favorites = this.favorites.map((key) => byKey.get(key)).filter(Boolean);
      const sections = allSections
        .filter((section) => !this.isFavorite(section.key))
        .sort((left, right) =>
          left.label.localeCompare(right.label, undefined, {
            sensitivity: 'base',
          }),
        );

      return [...favorites, ...sections];
    },

    get firstVisibleNonFavoriteKey() {
      return (
        this.orderedSections.find((section) => !this.isFavorite(section.key) && this.isSectionVisible(section))?.key ??
        null
      );
    },

    get selectedSection() {
      return (
        (this.summary.sections ?? []).find((section) => section.key === this.selected) ?? {
          key: DEFAULT_SECTION,
          label: 'Requests',
          description: '',
          layout: 'workspace',
          count: null,
        }
      );
    },

    get selectedHttpClientRequest() {
      return this.httpClientRequests.find((request) => request.execution === this.httpClientSelected) ?? null;
    },

    get selectedCacheOperation() {
      return this.cacheOperations.find((operation) => operation.execution === this.cacheSelected) ?? null;
    },

    get selectedMailMessage() {
      return this.mailMessages.find((message) => message.execution === this.mailSelected) ?? null;
    },

    get selectedNotification() {
      return (
        this.notificationGroups.find((notification) => notification.execution === this.notificationSelected) ?? null
      );
    },

    get selectedNotificationDelivery() {
      const deliveries = this.selectedNotification?.deliveries ?? [];

      return deliveries.find((delivery) => delivery.channel === this.notificationChannel) ?? deliveries[0] ?? null;
    },

    get selectedQueryRecord() {
      return this.queryRecords.find((record) => record.key === this.querySelected) ?? null;
    },

    get selectedQuery() {
      const executions = this.selectedQueryRecord?.executions ?? [];

      return executions.find((query) => query.execution === this.querySelectedExecution) ?? executions[0] ?? null;
    },

    get selectedQueryHasSource() {
      return (
        this.selectedQuery?.source_available === true ||
        (Array.isArray(this.selectedQuery?.stack) && this.selectedQuery.stack.length > 0)
      );
    },

    get selectedQueueActivity() {
      return this.queueActivities.find((activity) => activity.execution === this.queueSelected) ?? null;
    },

    get selectedRedisCommand() {
      return this.redisCommands.find((command) => command.execution === this.redisSelected) ?? null;
    },

    get selectedAuthorizationDecision() {
      return this.authorizationDecisions.find((decision) => decision.execution === this.authorizationSelected) ?? null;
    },

    get selectedViewGroup() {
      return this.viewGroups.find((group) => group.id === this.viewSelected) ?? null;
    },

    get selectedViewRender() {
      return (
        this.selectedViewGroup?.items?.find((view) => Number(view.render_order) === Number(this.viewRenderOrder)) ??
        null
      );
    },

    get viewDataIsEmpty() {
      return this.viewData === null || typeof this.viewData !== 'object' || Object.keys(this.viewData).length === 0;
    },

    get formattedViewData() {
      return JSON.stringify(this.viewData ?? {}, null, 2);
    },

    get selectedEvent() {
      return this.eventGroups.find((event) => event.id === this.eventSelected) ?? null;
    },

    get selectedTimelineItem() {
      return this.timelineSelected;
    },

    get visibleEventSummary() {
      if (this.visibleEventGroupCount === 0) return 'No events';

      const events = `${this.visibleEventGroupCount} ${this.visibleEventGroupCount === 1 ? 'event' : 'events'}`;

      if (this.visibleEventCount === this.visibleEventGroupCount) return events;

      const dispatches = `${this.visibleEventCount} dispatches`;

      return `${events}, ${dispatches}`;
    },

    get livewireComponents() {
      const serverById = new Map(this.livewireServerComponents.map((component) => [String(component.id), component]));
      const browserComponents = this.livewireTrace.ready
        ? this.livewireTrace.components
        : this.livewireServerComponents.map((component, index) => ({
            id: String(component.id),
            name: component.name,
            title: component.title,
            parentId: component.parent_id ?? null,
            sequence: index + 1,
            mounted: false,
            status: 'stale',
            latestActivityId: null,
            properties: (component.properties ?? []).map((property) => ({
              path: property.path,
              type: property.type,
              value: property.server_value,
            })),
          }));
      const merged = browserComponents.map((component) => ({
        ...component,
        server: serverById.get(String(component.id)) ?? null,
      }));
      const byId = new Map(merged.map((component) => [String(component.id), component]));

      const components = merged
        .map((component) => {
          let current = component;
          const seen = new Set([String(component.id)]);
          const ancestorIds = [];

          while (current?.parentId && byId.has(String(current.parentId)) && !seen.has(String(current.parentId))) {
            const parentId = String(current.parentId);
            seen.add(parentId);
            ancestorIds.push(parentId);
            current = byId.get(parentId);
          }

          return {
            ...component,
            ancestorIds,
            depth: ancestorIds.length,
          };
        })
        .sort((left, right) => Number(left.sequence ?? 0) - Number(right.sequence ?? 0));
      const parents = new Set(components.map((component) => String(component.parentId ?? '')).filter(Boolean));

      return components.map((component) => ({
        ...component,
        hasChildren: parents.has(String(component.id)),
      }));
    },

    get matchingLivewireComponents() {
      const search = this.livewireSearch.toLowerCase().trim();
      if (search === '') return this.livewireComponents;

      return this.livewireComponents.filter((component) =>
        [component.title, component.name, component.id, component.server?.class, component.server?.source?.file].some(
          (value) =>
            String(value ?? '')
              .toLowerCase()
              .includes(search),
        ),
      );
    },

    get filteredLivewireComponents() {
      const search = this.livewireSearch.toLowerCase().trim();
      if (search !== '') {
        const visible = new Set(
          this.matchingLivewireComponents.flatMap((component) => [String(component.id), ...component.ancestorIds]),
        );

        return this.livewireComponents.filter((component) => visible.has(String(component.id)));
      }

      return this.matchingLivewireComponents.filter(
        (component) => !component.ancestorIds.some((id) => this.livewireCollapsedComponents.includes(id)),
      );
    },

    get livewireActivity() {
      const currentProfileId = PROFILE_PATTERN.test(this.summary?.id ?? '') ? this.summary.id : null;
      const isCurrentLivewireRequest = this.summary?.request_type === 'livewire' && currentProfileId !== null;
      const serverActivity = this.livewireServerActivity.map((item, index) => ({
        id: item.id ?? `server-livewire-${index + 1}`,
        sequence: index + 1,
        componentId: String(item.component_id ?? ''),
        componentName: item.component_name ?? '',
        componentTitle: item.component_title ?? 'Livewire component',
        title: item.name ?? 'Livewire activity',
        kind: item.type ?? 'activity',
        status: item.status ?? 'complete',
        occurredAt: null,
        startedAt: item.at_ms ?? null,
        requestAtMs: item.at_ms ?? null,
        finishedAt: null,
        durationMs: item.duration_ms ?? null,
        initialRenderDurationMs: null,
        profileIds: [
          ...new Set([
            ...(Array.isArray(item.profile_ids) ? item.profile_ids : []),
            ...(isCurrentLivewireRequest ? [currentProfileId] : []),
          ]),
        ],
        actions: item.method
          ? [
              {
                name: item.method,
                params: item.params ?? [],
                metadata: item.metadata ?? {},
              },
            ]
          : [],
        changes: item.property
          ? [
              {
                path: item.property,
                before: item.before,
                submitted: item.submitted,
                server: item.server,
              },
            ]
          : [],
        events: item.event
          ? [
              {
                name: item.event,
                params: item.params ?? {},
                mode: item.mode ?? 'unknown',
                declaredTarget: item.declared_target ?? null,
                observedRecipientIds: [],
              },
            ]
          : [],
        effects: item.effect ? { [item.effect]: true } : {},
        phases: [],
        error: item.message ?? null,
        callsite: item.callsite ?? null,
      }));

      if (!this.livewireTrace.ready) return serverActivity;

      const renderEvidence = new Map();
      const lastLifecycleByComponent = new Map();
      serverActivity.forEach((item) => {
        const componentId = String(item.componentId);
        if (item.kind === 'render') {
          const owner = lastLifecycleByComponent.get(componentId);
          if (!owner) return;

          const renders = renderEvidence.get(owner.id) ?? [];
          renders.push(item);
          renderEvidence.set(owner.id, renders);

          return;
        }

        lastLifecycleByComponent.set(componentId, item);
      });

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
        const renderDurations = renders
          .map((render) => render.durationMs)
          .filter((duration) => duration !== null && duration !== undefined)
          .map(Number)
          .filter(Number.isFinite);
        const mount = evidence.find((serverItem) => serverItem.kind === 'mount') ?? null;
        const callsite = evidence.find((serverItem) => serverItem.callsite)?.callsite ?? item.callsite ?? null;

        return {
          ...item,
          callsite,
          requestAtMs: requestTimes.length > 0 ? Math.min(...requestTimes) : null,
          initialRenderDurationMs: item.kind === 'mount' && renderDurations.length > 0 ? renderDurations[0] : null,
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
        const renderDurations = renders
          .map((render) => render.durationMs)
          .filter((duration) => duration !== null && duration !== undefined)
          .map(Number)
          .filter(Number.isFinite);

        return [
          {
            ...item,
            initialRenderDurationMs: item.kind === 'mount' && renderDurations.length > 0 ? renderDurations[0] : null,
            serverRenderDurationMs:
              renderDurations.length > 0 ? renderDurations.reduce((total, duration) => total + duration, 0) : null,
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

    get selectedLivewireComponent() {
      return this.livewireComponents.find((component) => component.id === this.livewireSelectedComponentId) ?? null;
    },

    get livewirePropertyRows() {
      const component = this.selectedLivewireComponent;
      if (!component) return [];

      const descriptors = new Map(
        (component.server?.properties ?? []).map((descriptor) => [descriptor.path, descriptor]),
      );
      const canonicalProperties = new Map(
        (component.serverProperties ?? []).map((property) => [property.path, property.value]),
      );
      const latestChanges = [...this.livewireActivity]
        .filter((item) => item.componentId === component.id)
        .flatMap((item) => item.changes)
        .reverse();
      const rows = [];
      const append = (
        path,
        label,
        value,
        depth,
        root,
        safePath = true,
        canonicalKnown = false,
        canonicalValue = null,
      ) => {
        const descriptor = descriptors.get(root);
        const childEntries = Array.isArray(value)
          ? value.map((child, index) => [String(index), child])
          : value && typeof value === 'object'
            ? Object.entries(value)
            : [];
        const hasChildren = childEntries.length > 0;
        const expanded = this.livewireExpandedProperties.includes(`${component.id}:${path}`);
        const nested = path !== root;
        const editable =
          component.mounted !== false &&
          isLivewirePrimitive(value) &&
          safePath &&
          (nested ? descriptor?.array_leaf_writable === true : descriptor?.writable === true);
        const latest = latestChanges.find(
          (change) => change.path === path && (change.serverKnown === true || change.server !== null),
        );
        const serverKnown =
          canonicalKnown ||
          latest !== undefined ||
          (!nested &&
            descriptor?.server_value !== undefined &&
            (descriptor.server_value !== null || descriptor.type === 'Null'));
        const serverValue = canonicalKnown
          ? canonicalValue
          : latest !== undefined
            ? latest.server
            : (descriptor?.server_value ?? null);
        const draft = this.livewireDrafts[this.livewireDraftKey({ componentId: component.id, path })];
        const state =
          draft?.status === 'updating'
            ? 'Updating'
            : descriptor?.write_reason === 'locked'
              ? 'Locked'
              : serverKnown
                ? JSON.stringify(serverValue) === JSON.stringify(value)
                  ? 'Synced'
                  : 'Dirty'
                : 'Unknown';

        rows.push({
          componentId: component.id,
          path,
          label,
          value,
          valueSummary: livewireValueSummary(value),
          type: nested ? livewireValueType(value) : (descriptor?.type ?? livewireValueType(value)),
          phpType: nested ? null : descriptor?.php_type,
          depth,
          hasChildren,
          expanded,
          editable,
          writeReason: editable
            ? null
            : (descriptor?.write_reason ?? (isLivewirePrimitive(value) ? 'unknown' : 'unsupported_type')),
          serverKnown,
          serverValue,
          serverSummary: serverKnown ? livewireValueSummary(serverValue) : 'Not confirmed',
          state,
        });

        if (!hasChildren || !expanded) return;

        childEntries.forEach(([key, child]) => {
          const canonicalChildKnown =
            canonicalKnown &&
            canonicalValue !== null &&
            typeof canonicalValue === 'object' &&
            Object.prototype.hasOwnProperty.call(canonicalValue, key);

          append(
            `${path}.${key}`,
            key,
            child,
            depth + 1,
            root,
            safePath && !key.includes('.'),
            canonicalChildKnown,
            canonicalChildKnown ? canonicalValue[key] : null,
          );
        });
      };

      component.properties.forEach((property) => {
        const canonicalKnown = canonicalProperties.has(property.path);
        append(
          property.path,
          property.path,
          property.value,
          0,
          property.path,
          true,
          canonicalKnown,
          canonicalKnown ? canonicalProperties.get(property.path) : null,
        );
      });

      return rows;
    },

    get requestBadgeCount() {
      return this.laterRequestCount > 9 ? '9+' : String(this.laterRequestCount);
    },

    get currentRequestProfile() {
      return this.recentProfiles.find((profile) => profile.id === this.currentRequestId) ?? this.summary;
    },

    get laterRequestProfiles() {
      return this.recentProfiles.filter((profile) => profile.id !== this.currentRequestId);
    },

    get laterRequestCount() {
      return this.laterRequestProfiles.length;
    },

    get hasOtherRequests() {
      return this.laterRequestCount > 0;
    },

    get requestPickerButtonLabel() {
      if (!this.hasOtherRequests) return 'No later requests yet';

      return `Choose request, ${this.laterRequestCount} later ${this.laterRequestCount === 1 ? 'request' : 'requests'}`;
    },

    isFavorite(key) {
      return this.favorites.includes(key);
    },

    isSectionActive(section) {
      return section?.active !== false;
    },

    isSectionVisible(section) {
      return this.isSectionActive(section) || this.isFavorite(section.key) || section.key === this.selected;
    },

    selectSection(section, filter = null, focusHeading = false) {
      const focusContentHeading = focusHeading || this.mobileSectionsOpen;
      const nextSection = this.sectionKeys.includes(section) ? section : DEFAULT_SECTION;
      const needsSection = this.inspectorOpen && (this.loadedSection !== nextSection || this.sectionError);
      if (nextSection !== 'timeline') this.resetTimelinePagination();
      this.selected = nextSection;
      if (this.selected === 'queries' && ['repeated', 'slow'].includes(filter)) {
        this.queryFilter = 'attention';
        this.queryFocusFilter = filter;
      }
      if (this.selected === 'authorization' && ['all', 'allowed', 'denied'].includes(filter))
        this.authorizationFilter = filter;
      this.mobileSectionsOpen = false;
      this.mobileSectionsReturnFocus = null;
      this.$nextTick?.(() => {
        this.syncSectionPanels();
        if (this.$refs?.content) this.$refs.content.scrollTop = 0;
        if (this.selected === 'queries') {
          this.applyQueryView();
          if (['repeated', 'slow'].includes(filter)) this.focusQueryFinding(filter);
        }
        if (this.selected === 'http_client') this.applyHttpClientView();
        if (this.selected === 'notifications') this.applyNotificationView();
        if (this.selected === 'views') this.applyViewFilters();
        if (this.selected === 'authorization') this.applyAuthorizationView();
        if (this.selected === 'timeline') this.applyTimelineFilters();
        if (this.selected === 'events') this.applyEventFilters();
        if (this.selected === 'logs') this.applyLogFilters();
        if (focusContentHeading) this.$refs?.sectionHeading?.focus?.();
        browser.highlight?.();
      });

      if (needsSection) this.requestSection(this.selected);
    },

    navigateToSection(section, filter = null) {
      const target = this.sectionKeys.includes(section) ? section : DEFAULT_SECTION;

      this.selectSection(target, filter, true);
    },

    openRequestSection(returnFocus = null) {
      this.closeRequestPicker(false);

      if (this.inspectorOpen) {
        this.selectSection('request');

        return;
      }

      this.openInspector('request', returnFocus);
    },

    openMobileSections(returnFocus = null) {
      if (this.mobileSectionsOpen) return;

      this.closeRequestPicker(false);
      this.mobileSectionsReturnFocus = returnFocus ?? browser.activeElement?.();
      this.mobileSectionsOpen = true;
      this.$nextTick?.(() => {
        const navigation = this.$refs?.mobileSectionsNav;
        const selectedSection = navigation?.querySelector?.('[data-ndb-select-section][aria-current="page"]');
        const firstSection = navigation?.querySelector?.('[data-ndb-select-section]');

        (selectedSection ?? firstSection)?.focus?.();
      });
    },

    toggleMobileSections() {
      this.mobileSectionsOpen ? this.closeMobileSections() : this.openMobileSections();
    },

    closeMobileSections(restoreFocus = true) {
      const returnFocus = this.mobileSectionsReturnFocus;
      this.mobileSectionsOpen = false;
      this.mobileSectionsReturnFocus = null;

      if (restoreFocus) this.$nextTick?.(() => returnFocus?.focus?.());
    },

    syncSectionPanels() {
      const panels = this.$root?.querySelectorAll?.('[data-ndb-section-panel]') ?? [];
      const visibleSection = this.sectionLoading ? this.loadedSection : this.selected;

      panels.forEach((panel) => {
        panel.hidden = panel.dataset.ndbSectionPanel !== visibleSection;
      });
    },

    clearSectionLoadingIndicator() {
      browser.cancelSchedule?.(this.sectionLoadingTimer);
      this.sectionLoadingTimer = null;
      this.sectionLoadingIndicator = false;
    },

    syncSectionHeading() {
      if (this.$refs?.sectionHeading) {
        this.$refs.sectionHeading.textContent = this.selectedSection.label;
      }
      if (this.$refs?.sectionDescription) {
        this.$refs.sectionDescription.textContent = this.selectedSection.description ?? '';
      }
    },

    syncHostLock() {
      if (this.barVisible && (this.inspectorOpen || this.paletteOpen)) browser.lockHost?.(this.$root);
      else browser.unlockHost?.(this.$root);
    },

    syncToolbarPlacement() {
      if (this.requestPickerScope !== null) this.syncRequestPickerArrow();
      if (this.toolbarDragging || this.toolbarRebasing || this.toolbarSnapping) return;

      const placement = browser.toolbarPlacement?.(this.$root, this.toolbarPreferredPlacement);

      if (TOOLBAR_PLACEMENTS.includes(placement) && placement !== this.toolbarPlacement) {
        this.moveToolbarTo(placement);
      }
    },

    toolbarAnchorLeft(placement, width) {
      const viewportWidth = browser.viewportWidth?.() ?? 0;
      const horizontal = toolbarHorizontalPlacement(placement);

      if (horizontal === 'left') return 12;
      if (horizontal === 'right') return Math.max(12, viewportWidth - width - 12);

      return Math.max(12, (viewportWidth - width) / 2);
    },

    toolbarAnchorTop(placement, height) {
      if (toolbarVerticalPlacement(placement) === 'top') return 12;

      return Math.max(12, (browser.viewportHeight?.() ?? 0) - height - 12);
    },

    toolbarPreviewWidth(placement) {
      if (TOOLBAR_CORNER_PLACEMENTS.includes(placement)) return this.toolbarCornerWidth;

      return this.toolbarCenterWidth || Math.min(1024, Math.max(0, (browser.viewportWidth?.() ?? 0) - 24));
    },

    toolbarPreviewHeight(placement) {
      return TOOLBAR_CORNER_PLACEMENTS.includes(placement) ? this.toolbarCornerHeight : this.toolbarCenterHeight;
    },

    toolbarTargetAt(clientX, clientY) {
      const width = Math.max(1, browser.viewportWidth?.() ?? 0);
      const height = Math.max(1, browser.viewportHeight?.() ?? 0);
      const vertical = clientY < height / 2 ? 'top' : 'bottom';
      const horizontal = clientX < width / 3 ? 'left' : clientX > (width * 2) / 3 ? 'right' : 'center';

      return horizontal === 'center' ? vertical : `${vertical}-${horizontal}`;
    },

    startToolbarDrag(event) {
      if (!this.barVisible || this.inspectorOpen || this.toolbarDragPointerId !== null) return;
      if (event.isPrimary === false || (event.pointerType === 'mouse' && event.button !== 0)) return;
      if (event.target?.closest?.('[role="menu"], [role="listbox"], [role="dialog"], input, select, textarea')) return;

      const toolbar = event.currentTarget;
      const box = toolbar?.getBoundingClientRect?.();
      if (!toolbar || !box || box.width <= 0 || box.height <= 0) return;

      browser.cancelSchedule?.(this.toolbarSnapTimer);
      this.toolbarSnapTimer = null;
      this.toolbarSnapVersion += 1;
      this.toolbarRebasing = true;
      this.toolbarSnapping = false;
      this.toolbarDragPointerId = event.pointerId;
      this.toolbarDragStartX = event.clientX;
      this.toolbarDragStartY = event.clientY;
      this.toolbarDragPointerOffsetX = Math.min(Math.max(event.clientX - box.left, 0), box.width);
      this.toolbarDragPointerOffsetY = Math.min(Math.max(event.clientY - box.top, 0), box.height);
      this.toolbarDragPointerRatioX = this.toolbarDragPointerOffsetX / box.width;
      this.toolbarDragPointerRatioY = this.toolbarDragPointerOffsetY / box.height;
      this.toolbarDragWidth = box.width;
      this.toolbarDragHeight = box.height;
      this.toolbarDragOffsetX = box.left - this.toolbarAnchorLeft(this.toolbarPlacement, box.width);
      this.toolbarDragOffsetY = box.top - this.toolbarAnchorTop(this.toolbarPlacement, box.height);
      if (this.toolbarIsCorner) {
        this.toolbarCornerWidth = box.width;
        this.toolbarCornerHeight = box.height;
      } else {
        this.toolbarCenterWidth = box.width;
        this.toolbarCenterHeight = box.height;
      }
      this.toolbarDragTarget = this.toolbarPlacement;
      this.toolbarDragOriginPlacement = this.toolbarPlacement;
    },

    moveToolbarDrag(event) {
      if (event.pointerId !== this.toolbarDragPointerId) return;

      const distance = Math.hypot(event.clientX - this.toolbarDragStartX, event.clientY - this.toolbarDragStartY);

      if (!this.toolbarDragging && distance < 6) return;

      if (!this.toolbarDragging) {
        this.toolbarDragging = true;
        this.closeRequestPicker(false);
        this.closeThemeMenu(false);
        this.mobileToolbarMenu = null;
        this.mobileToolbarReturnFocus = null;
        this.$root?.querySelector?.('[data-ndb-toolbar-shell]')?.setPointerCapture?.(event.pointerId);
      }

      event.preventDefault?.();
      const width = this.toolbarDragWidth;
      const height = this.toolbarDragHeight;
      const viewportWidth = browser.viewportWidth?.() ?? 0;
      const viewportHeight = browser.viewportHeight?.() ?? 0;
      const minLeft = 12;
      const maxLeft = Math.max(minLeft, viewportWidth - width - 12);
      const minTop = 12;
      const maxTop = Math.max(minTop, viewportHeight - height - 12);
      const left = Math.min(maxLeft, Math.max(minLeft, event.clientX - this.toolbarDragPointerOffsetX));
      const top = Math.min(maxTop, Math.max(minTop, event.clientY - this.toolbarDragPointerOffsetY));

      this.toolbarDragOffsetX = left - this.toolbarAnchorLeft(this.toolbarPlacement, width);
      this.toolbarDragOffsetY = top - this.toolbarAnchorTop(this.toolbarPlacement, height);
      this.toolbarDragTarget = this.toolbarTargetAt(event.clientX, event.clientY);
    },

    endToolbarDrag(event) {
      if (event.pointerId !== this.toolbarDragPointerId) return;

      const toolbar = this.$root?.querySelector?.('[data-ndb-toolbar-shell]');
      const currentBox = toolbar?.getBoundingClientRect?.();
      if (toolbar?.hasPointerCapture?.(event.pointerId)) {
        toolbar.releasePointerCapture?.(event.pointerId);
      }
      this.toolbarDragPointerId = null;

      if (!this.toolbarDragging) {
        this.moveToolbarTo(this.toolbarPlacement, false, currentBox, event);

        return;
      }

      event.preventDefault?.();
      this.toolbarDragging = false;
      this.suppressToolbarClick();
      this.moveToolbarTo(this.toolbarDragTarget, true, currentBox, event);
    },

    cancelToolbarDrag(event) {
      if (event.pointerId !== this.toolbarDragPointerId) return;

      const toolbar = this.$root?.querySelector?.('[data-ndb-toolbar-shell]');
      const currentBox = toolbar?.getBoundingClientRect?.();
      if (toolbar?.hasPointerCapture?.(event.pointerId)) {
        toolbar.releasePointerCapture?.(event.pointerId);
      }
      this.toolbarDragPointerId = null;

      if (!this.toolbarDragging) {
        this.moveToolbarTo(this.toolbarPlacement, false, currentBox);

        return;
      }

      this.toolbarDragging = false;
      this.suppressToolbarClick();
      this.moveToolbarTo(this.toolbarDragOriginPlacement, false, currentBox);
    },

    suppressToolbarClick() {
      this.toolbarSuppressClick = true;
      browser.cancelSchedule?.(this.toolbarClickTimer);
      this.toolbarClickTimer =
        browser.schedule?.(() => {
          this.toolbarSuppressClick = false;
          this.toolbarClickTimer = null;
        }, 250) ?? null;
    },

    consumeToolbarClick(event) {
      if (!this.toolbarSuppressClick) return;

      event.preventDefault?.();
      event.stopPropagation?.();
      this.toolbarSuppressClick = false;
      browser.cancelSchedule?.(this.toolbarClickTimer);
      this.toolbarClickTimer = null;
    },

    pinToolbar(placement) {
      if (!TOOLBAR_PLACEMENTS.includes(placement)) return;

      this.mobileToolbarMenu = null;
      this.mobileToolbarReturnFocus = null;
      this.moveToolbarTo(placement, true);
    },

    moveToolbarTo(placement, remember = false, currentBox = null, releasePointer = null) {
      if (!TOOLBAR_PLACEMENTS.includes(placement)) return;

      const snapVersion = ++this.toolbarSnapVersion;
      const toolbar = this.$root?.querySelector?.('[data-ndb-toolbar-shell]');
      const sourceBox = currentBox ?? toolbar?.getBoundingClientRect?.();
      const source =
        sourceBox && Number.isFinite(sourceBox.left) && Number.isFinite(sourceBox.top)
          ? {
              left: sourceBox.left,
              top: sourceBox.top,
              width: sourceBox.width,
              height: sourceBox.height,
            }
          : null;
      const pointer =
        releasePointer && Number.isFinite(releasePointer.clientX) && Number.isFinite(releasePointer.clientY)
          ? { x: releasePointer.clientX, y: releasePointer.clientY }
          : null;
      const viewportWidth = browser.viewportWidth?.() ?? 0;
      const viewportHeight = browser.viewportHeight?.() ?? 0;
      const positionAtRelease = (width, height) => {
        const desiredLeft = pointer
          ? pointer.x - width * this.toolbarDragPointerRatioX
          : source.left + (source.width - width) / 2;
        const desiredTop = pointer
          ? pointer.y - height * this.toolbarDragPointerRatioY
          : source.top + (source.height - height) / 2;

        return {
          left: Math.min(Math.max(12, viewportWidth - width - 12), Math.max(12, desiredLeft)),
          top: Math.min(Math.max(12, viewportHeight - height - 12), Math.max(12, desiredTop)),
        };
      };

      browser.cancelSchedule?.(this.toolbarSnapTimer);
      this.toolbarSnapTimer = null;
      this.toolbarRebasing = true;
      this.toolbarSnapping = false;

      if (remember) {
        this.toolbarPreferredPlacement = placement;
        this.persist();
      }

      if (!toolbar || !source || source.width <= 0 || source.height <= 0) {
        this.toolbarPlacement = placement;
        this.toolbarDragTarget = placement;
        this.toolbarDragOffsetX = 0;
        this.toolbarDragOffsetY = 0;
        this.toolbarRebasing = false;
        this.toolbarSnapping = false;

        return;
      }

      const provisionalWidth = this.toolbarPreviewWidth(placement);
      const provisionalHeight = this.toolbarPreviewHeight(placement);
      const provisional = positionAtRelease(provisionalWidth, provisionalHeight);

      this.toolbarPlacement = placement;
      this.toolbarDragTarget = placement;
      this.toolbarDragOffsetX = provisional.left - this.toolbarAnchorLeft(placement, provisionalWidth);
      this.toolbarDragOffsetY = provisional.top - this.toolbarAnchorTop(placement, provisionalHeight);

      const rebase = () => {
        const destination = toolbar.getBoundingClientRect?.();
        if (!destination || destination.width <= 0 || destination.height <= 0) {
          this.toolbarRebasing = false;

          return;
        }

        const release = positionAtRelease(destination.width, destination.height);
        const baseLeft = destination.left - this.toolbarDragOffsetX;
        const baseTop = destination.top - this.toolbarDragOffsetY;
        const offsetX = release.left - baseLeft;
        const offsetY = release.top - baseTop;

        this.toolbarDragOffsetX = Math.abs(offsetX) > 0.5 ? offsetX : 0;
        this.toolbarDragOffsetY = Math.abs(offsetY) > 0.5 ? offsetY : 0;

        if (this.toolbarDragOffsetX === 0 && this.toolbarDragOffsetY === 0) {
          this.toolbarRebasing = false;

          return;
        }

        const prepare = () => {
          if (snapVersion !== this.toolbarSnapVersion) return;

          this.toolbarRebasing = false;
          this.toolbarSnapping = true;

          const settle = () => {
            if (snapVersion !== this.toolbarSnapVersion) return;

            this.toolbarDragOffsetX = 0;
            this.toolbarDragOffsetY = 0;
            this.toolbarSnapTimer = browser.schedule?.(() => this.finishToolbarSnap(snapVersion), 500) ?? null;
          };

          if (browser.nextFrame) browser.nextFrame(settle);
          else this.$nextTick?.(settle);
        };

        if (browser.afterPaint) browser.afterPaint(prepare);
        else prepare();
      };

      if (this.$nextTick) this.$nextTick(rebase);
      else rebase();
    },

    finishToolbarSnap(snapVersion = null) {
      if (snapVersion !== null && snapVersion !== this.toolbarSnapVersion) return;
      if (!this.toolbarSnapping || this.toolbarRebasing || this.toolbarDragging) return;

      browser.cancelSchedule?.(this.toolbarSnapTimer);
      this.toolbarSnapTimer = null;
      this.toolbarDragOffsetX = 0;
      this.toolbarDragOffsetY = 0;
      this.toolbarRebasing = false;
      this.toolbarSnapping = false;
      this.syncToolbarPlacement();
    },

    openInspector(section = this.selected, returnFocus = null) {
      if (!this.barVisible) return;

      if (!this.inspectorOpen) {
        this.inspectorReturnFocus =
          returnFocus ?? (this.mobileToolbarMenu ? this.mobileToolbarReturnFocus : null) ?? browser.activeElement?.();
      }

      this.mobileToolbarMenu = null;
      this.mobileToolbarReturnFocus = null;
      this.closeThemeMenu(false);
      this.closeRequestPicker(false);
      this.mobileSectionsOpen = false;
      this.mobileSectionsReturnFocus = null;
      this.inspectorOpen = true;
      this.selectSection(section);
      this.scheduleActivityRefresh(true);
      this.syncHostLock();
      this.$nextTick?.(() => {
        const focus = () =>
          this.$root
            ?.querySelector?.('[data-ndb-window-controls="expanded"] [data-ndb-window-action="shrink"]')
            ?.focus?.();
        browser.afterPaint ? browser.afterPaint(focus) : focus();
      });
    },

    requestSection(section = this.selected, force = false) {
      const target = this.sectionKeys.includes(section) ? section : DEFAULT_SECTION;
      if (!force && this.loadedSection === target) return;
      if (!force && this.sectionLoading && this.requestedSection === target) return;

      const island = this.$wire?.$island;
      const scopedWire = typeof island === 'function' ? island.call(this.$wire, 'section-details') : this.$wire;
      const action = scopedWire?.loadSection;
      const profileId = this.summary.id;
      const requestVersion = ++this.sectionRequestVersion;
      this.requestedSection = target;
      this.sectionLoading = true;
      this.sectionTransitioning = true;
      this.sectionError = false;
      this.clearSectionLoadingIndicator();
      this.sectionLoadingTimer =
        browser.schedule?.(() => {
          this.sectionLoadingTimer = null;

          if (
            requestVersion === this.sectionRequestVersion &&
            profileId === this.summary.id &&
            this.requestedSection === target &&
            this.sectionLoading
          ) {
            this.sectionLoadingIndicator = true;
          }
        }, 200) ?? null;
      this.syncSectionPanels();

      if (typeof action !== 'function') {
        this.sectionLoading = false;
        this.sectionTransitioning = false;
        this.sectionError = true;
        this.clearSectionLoadingIndicator();
        this.syncSectionPanels();

        return;
      }

      Promise.resolve(action.call(scopedWire, target))
        .then(() => {
          if (requestVersion !== this.sectionRequestVersion || profileId !== this.summary.id) return;
          if (this.loadedSection !== target) this.receiveSection(target);
        })
        .catch(() => {
          if (requestVersion !== this.sectionRequestVersion || profileId !== this.summary.id) return;

          this.requestedSection = null;
          this.sectionLoading = false;
          this.sectionTransitioning = false;
          this.sectionError = true;
          this.clearSectionLoadingIndicator();
          this.syncSectionPanels();
        });
    },

    receiveSection(section) {
      if (!this.sectionKeys.includes(section) || section !== this.selected) return;

      this.loadedSection = section;
      this.requestedSection = null;
      this.sectionLoading = false;
      this.sectionError = false;
      this.clearSectionLoadingIndicator();
      this.$nextTick?.(() => {
        this.sectionTransitioning = false;
        this.syncSectionPanels();
        this.applyQueryView();
        this.applyViewFilters();
        this.applyAuthorizationFilters();
        this.applyTimelineFilters();
        this.applyEventFilters();
        this.applyLogFilters();
        this.applyNotificationView();
        this.syncHostLock();
        browser.highlight?.();
      });
    },

    closeInspector() {
      if (!this.inspectorOpen) return;

      const returnFocus = this.inspectorReturnFocus;
      this.inspectorOpen = false;
      this.cancelActivityRefresh();
      this.inspectorReturnFocus = null;
      this.mobileSectionsOpen = false;
      this.mobileSectionsReturnFocus = null;
      this.mobileToolbarMenu = null;
      this.mobileToolbarReturnFocus = null;
      this.closeThemeMenu(false);
      this.closeRequestPicker(false);
      this.syncHostLock();
      this.$nextTick?.(() => {
        const focus = () => returnFocus?.focus?.();
        browser.afterPaint ? browser.afterPaint(focus) : focus();
      });
    },

    dismissBar() {
      if (!this.barVisible) return;

      const activeElement = browser.activeElement?.();
      this.resetTimelinePagination();
      this.barVisible = false;
      this.inspectorOpen = false;
      this.cancelActivityRefresh();
      this.inspectorReturnFocus = null;
      this.mobileSectionsOpen = false;
      this.mobileSectionsReturnFocus = null;
      this.mobileToolbarMenu = null;
      this.mobileToolbarReturnFocus = null;
      this.themeMenuScope = null;
      this.themeMenuReturnFocus = null;
      this.requestPickerScope = null;
      this.requestPickerReturnFocus = null;
      this.paletteOpen = false;
      this.paletteSearch = '';
      this.paletteIndex = 0;
      this.paletteShowQuiet = false;
      this.paletteReturnFocus = null;
      this.syncHostLock();
      this.$nextTick?.(() => {
        const blur = () => activeElement?.blur?.();
        browser.afterPaint ? browser.afterPaint(blur) : blur();
      });
    },

    rememberProfile(summary) {
      if (!PROFILE_PATTERN.test(summary?.id ?? '')) return;

      const existing = this.recentProfiles.findIndex((profile) => profile.id === summary.id);

      if (existing === -1) {
        const current = this.recentProfiles.find((profile) => profile.id === this.currentRequestId);
        const later = [summary, ...this.recentProfiles.filter((profile) => profile.id !== this.currentRequestId)].slice(
          0,
          Math.max(0, this.profileLimit - (current ? 1 : 0)),
        );
        this.recentProfiles = current ? [...later, current] : later;

        return;
      }

      this.recentProfiles = this.recentProfiles.map((profile, index) =>
        index === existing ? { ...profile, ...summary } : profile,
      );
    },

    receiveProfile(summary) {
      if (!PROFILE_PATTERN.test(summary?.id ?? '')) return;

      this.pendingProfileIds = this.pendingProfileIds.filter((id) => id !== summary.id);
      this.rememberProfile(summary);
    },

    hasPendingActivity(summary = this.summary) {
      return summary?.completion_state === 'terminating' || summary?.background_pending === true;
    },

    cancelActivityRefresh(reset = false) {
      browser.cancelSchedule?.(this.activityPollTimer);
      this.activityPollTimer = null;

      if (reset) this.activityPollAttempts = 0;
    },

    scheduleActivityRefresh(reset = false) {
      if (reset) this.cancelActivityRefresh(true);
      if (
        !this.inspectorOpen ||
        !this.hasPendingActivity() ||
        this.activityRefreshPending ||
        this.activityPollTimer !== null ||
        this.activityPollAttempts >= ACTIVITY_POLL_LIMIT
      )
        return;

      this.activityPollTimer =
        browser.schedule?.(() => {
          this.activityPollTimer = null;
          this.refreshBackgroundActivity();
        }, ACTIVITY_POLL_INTERVAL) ?? null;
    },

    refreshBackgroundActivity(reset = false) {
      if (reset) this.cancelActivityRefresh(true);
      if (!this.inspectorOpen || this.activityRefreshPending) return;

      // A renderless poll must not share the pending message that renders a section island.
      const action = this.$wire?.refreshRelatedActivity;
      if (typeof action !== 'function') return;
      if (!reset && (!this.hasPendingActivity() || this.activityPollAttempts >= ACTIVITY_POLL_LIMIT)) return;

      const profileId = this.summary.id;
      this.activityPollAttempts++;
      this.activityRefreshPending = true;

      Promise.resolve(action.call(this.$wire))
        .then(() => {
          if (profileId !== this.summary.id) return;

          this.activityRefreshPending = false;
          this.scheduleActivityRefresh();
        })
        .catch(() => {
          this.activityRefreshPending = false;
          this.cancelActivityRefresh();
        });
    },

    receiveActivityRefresh(summary, relatedProfiles = []) {
      if (!PROFILE_PATTERN.test(summary?.id ?? '') || summary.id !== this.summary.id) return;

      const nextSummary = { ...this.summary, ...summary };
      const backgroundChanged =
        nextSummary.background_pending !== this.summary.background_pending ||
        nextSummary.background_activity_count !== this.summary.background_activity_count ||
        JSON.stringify(nextSummary.related_profile_ids ?? []) !==
          JSON.stringify(this.summary.related_profile_ids ?? []);
      const relatedChanged = (Array.isArray(relatedProfiles) ? relatedProfiles : []).some((profile) => {
        const existing = this.recentProfiles.find((recent) => recent.id === profile?.id);

        return !existing || JSON.stringify({ ...existing, ...profile }) !== JSON.stringify(existing);
      });
      const sectionNeedsRefresh =
        ['timeline', 'queue', 'mail', 'notifications'].includes(this.selected) && (backgroundChanged || relatedChanged);

      this.summary = nextSummary;
      this.rememberProfile(this.summary);
      (Array.isArray(relatedProfiles) ? relatedProfiles : []).forEach((profile) => this.receiveProfile(profile));
      this.activityRefreshPending = false;

      if (sectionNeedsRefresh && this.inspectorOpen && this.loadedSection === this.selected && !this.sectionLoading) {
        this.requestSection(this.selected, true);
      }

      if (this.hasPendingActivity()) this.scheduleActivityRefresh();
      else this.cancelActivityRefresh();
    },

    openRelatedProfile(profileId, section = DEFAULT_SECTION) {
      if (!PROFILE_PATTERN.test(profileId ?? '')) return;

      if (profileId === this.summary.id) {
        this.selectSection(section);

        return;
      }

      const action = this.$wire?.switchProfile;
      if (typeof action !== 'function') return;

      this.relatedProfileSelection = { id: profileId, section };
      Promise.resolve(action.call(this.$wire, profileId)).catch(() => {
        if (this.relatedProfileSelection?.id === profileId) this.relatedProfileSelection = null;
      });
    },

    requestTitle(profile) {
      return profile?.activity || profile?.path || 'Request';
    },

    requestTypeLabel(type) {
      return (
        {
          ajax: 'Ajax',
          artisan: 'Command',
          cli: 'CLI',
          download: 'Download',
          full_page: 'Page',
          json: 'JSON',
          queue: 'Worker',
          redirect: 'Redirect',
          stream: 'Stream',
          test: 'Test',
        }[type] ?? 'Request'
      );
    },

    requestStatusClass(status) {
      const code = Number(status);

      if (code >= 500) return 'ndb:text-red-600 ndb:dark:text-red-300';
      if (code >= 400) return 'ndb:text-amber-600 ndb:dark:text-amber-300';
      if (code >= 300) return 'ndb:text-sky-600 ndb:dark:text-sky-300';
      if (code >= 200) return 'ndb:text-emerald-600 ndb:dark:text-emerald-300';

      return 'ndb:text-zinc-500 ndb:dark:text-zinc-400';
    },

    relativeRequestTime(profile) {
      const recordedAt = Date.parse(profile?.recorded_at ?? '');
      if (!Number.isFinite(recordedAt)) return profile?.recorded_time ?? '';

      const seconds = Math.max(0, Math.floor((Date.now() - recordedAt) / 1000));
      if (seconds < 5) return 'now';
      if (seconds < 60) return `${seconds}s ago`;

      const minutes = Math.floor(seconds / 60);
      if (minutes < 60) return `${minutes}m ago`;

      return profile?.recorded_time ?? '';
    },

    switchProfile(summary) {
      if (!PROFILE_PATTERN.test(summary?.id ?? '')) return;

      this.resetTimelinePagination();
      const selectedFromPicker = this.requestSelectionPending === summary.id;
      const selectedFromRelation = this.relatedProfileSelection?.id === summary.id;
      const requestedSection = selectedFromPicker
        ? 'request'
        : selectedFromRelation
          ? this.relatedProfileSelection.section
          : this.selected;
      const selected = (summary.sections ?? []).some((section) => section.key === requestedSection)
        ? requestedSection
        : DEFAULT_SECTION;
      this.cancelActivityRefresh(true);
      this.activityRefreshPending = false;
      this.sectionRequestVersion++;
      this.summary = summary ?? {};
      this.requestSelectionPending = null;
      this.relatedProfileSelection = null;
      this.pendingProfileIds = this.pendingProfileIds.filter((id) => id !== summary.id);
      if (selectedFromPicker || selectedFromRelation) {
        this.rememberProfile(summary);
      } else {
        this.currentRequestId = summary.id;
        this.recentProfiles = [summary];
      }
      this.loadedSection = null;
      this.requestedSection = null;
      this.sectionLoading = false;
      this.clearSectionLoadingIndicator();
      this.sectionTransitioning = false;
      this.sectionError = false;
      this.selected = selected;
      this.cacheOperations = [];
      this.cacheFilter = 'all';
      this.cacheSearch = '';
      this.cacheSelected = null;
      this.cacheDetailOpen = false;
      this.visibleCacheCount = 0;
      this.httpClientRequests = [];
      this.httpClientFilter = 'all';
      this.httpClientSearch = '';
      this.httpClientSort = 'execution';
      this.httpClientSortDirection = 'asc';
      this.httpClientSelected = null;
      this.httpClientDetailOpen = false;
      this.httpClientDetailTab = 'response';
      this.visibleHttpClientCount = 0;
      this.mailMessages = [];
      this.pendingMailMessageId = null;
      this.mailFilter = 'all';
      this.mailSearch = '';
      this.mailSelected = null;
      this.mailDetailOpen = false;
      this.mailDetailTab = 'preview';
      this.mailPreviewFormat = 'html';
      this.mailPreviewViewport = 'desktop';
      this.visibleMailCount = 0;
      this.notificationGroups = [];
      this.notificationFilter = 'all';
      this.notificationSearch = '';
      this.notificationSelected = null;
      this.notificationDetailOpen = false;
      this.notificationDetailTab = 'delivery';
      this.notificationChannel = null;
      this.visibleNotificationCount = 0;
      this.queryRecords = [];
      this.queueActivities = [];
      this.queueFilter = 'all';
      this.queueSearch = '';
      this.queueSelected = null;
      this.queueDetailOpen = false;
      this.visibleQueueCount = 0;
      this.redisCommands = [];
      this.redisFilter = 'all';
      this.redisSearch = '';
      this.redisSelected = null;
      this.redisDetailOpen = false;
      this.visibleRedisCount = 0;
      this.queryFilter = 'all';
      this.querySearch = '';
      this.querySort = 'execution';
      this.querySortDirection = 'asc';
      this.querySelected = null;
      this.querySelectedExecution = null;
      this.queryDetailOpen = false;
      this.queryDetailTab = 'overview';
      this.queryDetailReturnFocus = null;
      this.queryExplain = null;
      this.queryExplainError = null;
      this.queryExplainLoading = false;
      this.queryExplainExecution = null;
      this.queryExplainScrollTop = null;
      this.queryFocusFilter = null;
      this.visibleQueryCount = 0;
      this.viewGroups = [];
      this.viewFilter = 'application';
      this.viewSearch = '';
      this.viewSelected = null;
      this.viewDetailOpen = false;
      this.viewRenderOrder = null;
      this.resetViewData();
      this.visibleViewCount = 0;
      this.visibleViewRenderCount = 0;
      this.modelGroupCount = 0;
      this.modelSearch = '';
      this.modelSort = 'capture';
      this.modelSortDirection = 'asc';
      this.visibleModelCount = 0;
      this.modelSelected = null;
      this.modelDetailOpen = false;
      this.modelDetailTab = 'records';
      this.modelListScrollTop = 0;
      this.authorizationDecisions = [];
      this.authorizationFilter = 'all';
      this.authorizationSearch = '';
      this.authorizationSelected = null;
      this.authorizationDetailOpen = false;
      this.visibleAuthorizationCount = 0;
      this.timelineFilter = 'key';
      this.timelineSearch = '';
      this.timelineSelected = null;
      this.timelineDetailOpen = false;
      this.timelineLoadingMore = false;
      this.timelinePaginationError = false;
      this.visibleTimelineCount = 0;
      this.eventGroups = [];
      this.eventSource = 'all';
      this.eventSearch = '';
      this.logLevel = 'all';
      this.logChannel = 'all';
      this.logSearch = '';
      this.logDetailSequence = null;
      this.logDetailOpen = false;
      this.visibleLogCount = 0;
      this.visibleLogGroupCount = 0;
      this.eventSelected = null;
      this.eventDetailOpen = false;
      this.eventDetailTab = 'overview';
      this.eventDetailReturnFocus = null;
      this.visibleEventGroupCount = 0;
      if (this.inspectorOpen || selectedFromPicker || selectedFromRelation) {
        this.openInspector(selected);
      } else {
        this.$nextTick?.(() => this.syncSectionPanels());
      }
    },

    noticeProfile(profileId, foreground = false) {
      if (!PROFILE_PATTERN.test(profileId ?? '')) return;
      if (
        profileId === this.summary.id ||
        this.recentProfiles.some((profile) => profile.id === profileId) ||
        this.pendingProfileIds.includes(profileId)
      )
        return;

      const method = foreground ? 'switchProfile' : 'noticeProfile';
      const action = this.$wire?.[method];

      if (typeof action !== 'function') return;

      this.pendingProfileIds = [...this.pendingProfileIds, profileId];

      Promise.resolve(action.call(this.$wire, profileId)).catch(() => {
        this.pendingProfileIds = this.pendingProfileIds.filter((id) => id !== profileId);
      });
    },

    mergeLivewireServer(payload = {}) {
      const byId = new Map(this.livewireServerComponents.map((component) => [String(component.id), component]));
      (payload.components ?? []).forEach((component) => {
        if (component?.id) byId.set(String(component.id), component);
      });
      this.livewireServerComponents = [...byId.values()];
      this.livewireServerActivity = Array.isArray(payload.activity) ? payload.activity : [];
      trace?.mergeServerComponents?.(payload.components ?? []);
      this.syncLivewireSelection();
    },

    syncLivewireSelection() {
      this.syncLivewireComponentCollapseState();

      const visibleActivity = this.filteredLivewireActivity;
      const selectedActivityIsVisible = visibleActivity.some((item) => item.id === this.livewireSelectedActivityId);
      const defaultActivityId = visibleActivity[0]?.id ?? null;

      if (!this.livewireActivitySelectionPinned) {
        this.livewireSelectedActivityId = defaultActivityId;
      } else if (!selectedActivityIsVisible) {
        this.livewireSelectedActivityId = defaultActivityId;
      }

      const search = this.livewireSearch.toLowerCase().trim();
      const selectableComponents = search === '' ? this.livewireComponents : this.matchingLivewireComponents;
      if (!selectableComponents.some((component) => component.id === this.livewireSelectedComponentId)) {
        this.closeLivewireDrafts();
        this.livewireSelectedComponentId = selectableComponents[0]?.id ?? null;
      }
    },

    setLivewireTab(tab) {
      if (!['activity', 'components'].includes(tab)) return;
      this.closeLivewireDrafts();
      this.livewireTab = tab;
      this.livewireDetailTab = tab === 'activity' ? 'overview' : 'properties';
      this.livewireDetailOpen = false;
      this.livewireSearch = '';
      this.syncLivewireSelection();
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

    selectLivewireComponent(id) {
      if (!this.livewireComponents.some((component) => component.id === id)) return;
      this.closeLivewireDrafts();
      this.livewireSelectedComponentId = id;
      this.livewireDetailTab = 'properties';
      this.livewireDetailOpen = true;
    },

    setLivewireDetailTab(tab) {
      const allowed = this.livewireTab === 'activity' ? ['overview', 'trace'] : ['properties', 'source'];
      if (!allowed.includes(tab)) return;
      if (tab === 'trace' && (this.selectedLivewireActivity?.phases?.length ?? 0) === 0) return;

      this.livewireDetailTab = tab;
      this.$nextTick?.(() => browser.highlight?.());
    },

    syncLivewireComponentCollapseState() {
      const parentIds = this.livewireComponents
        .filter((component) => component.hasChildren)
        .map((component) => String(component.id));
      const newParentIds = parentIds.filter((id) => !this.livewireKnownComponentParents.includes(id));

      this.livewireCollapsedComponents = [
        ...new Set([...this.livewireCollapsedComponents.filter((id) => parentIds.includes(id)), ...newParentIds]),
      ];
      this.livewireKnownComponentParents = parentIds;
    },

    toggleLivewireComponent(component) {
      if (!component?.hasChildren) return;

      const id = String(component.id);
      const collapsed = this.livewireCollapsedComponents.includes(id);
      this.livewireCollapsedComponents = collapsed
        ? this.livewireCollapsedComponents.filter((item) => item !== id)
        : [...this.livewireCollapsedComponents, id];

      if (!collapsed && this.selectedLivewireComponent?.ancestorIds.includes(id)) {
        this.closeLivewireDrafts();
        this.livewireSelectedComponentId = id;
      }
    },

    livewireComponentCollapsed(component) {
      return this.livewireCollapsedComponents.includes(String(component?.id));
    },

    livewireComponentIsSearchContext(component) {
      return (
        this.livewireSearch.trim() !== '' && !this.matchingLivewireComponents.some((item) => item.id === component?.id)
      );
    },

    inspectLivewireActivityComponent() {
      const id = this.selectedLivewireActivity?.componentId;
      this.inspectLivewireComponent(id);
    },

    inspectLivewireComponent(id) {
      if (!id || !this.livewireComponents.some((component) => component.id === String(id))) return;
      this.livewireSelectedComponentId = String(id);
      this.livewireTab = 'components';
      this.livewireDetailTab = 'properties';
      this.livewireDetailOpen = true;
      this.livewireSearch = '';
    },

    inspectLivewireComponentActivity() {
      const id = this.selectedLivewireComponent?.latestActivityId;
      if (!id || !this.livewireActivity.some((item) => item.id === id)) return;
      this.livewireSelectedActivityId = id;
      this.livewireActivitySelectionPinned = true;
      this.livewireTab = 'activity';
      this.livewireDetailTab = 'overview';
      this.livewireDetailOpen = true;
      this.livewireSearch = '';
      this.$nextTick?.(() => browser.highlight?.());
    },

    livewireComponentTitle(id) {
      return this.livewireComponents.find((component) => component.id === String(id))?.title ?? String(id);
    },

    livewireComponentById(id) {
      return this.livewireComponents.find((component) => component.id === String(id)) ?? null;
    },

    livewireComponentStatusDescription(component) {
      return (
        {
          idle: 'Mounted and waiting for the next update.',
          updating: 'A Livewire update is running.',
          failed: 'The latest Livewire update failed.',
          stale: 'Only server-captured state is available for this request.',
        }[component?.status] ?? 'Component state was captured.'
      );
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

    livewirePropertyStateLabel(row) {
      return row?.state === 'Unknown' ? 'Not confirmed' : row?.state;
    },

    livewirePropertyStateDescription(row) {
      return (
        {
          Synced: 'Client and server values match.',
          Dirty: 'The client value differs from the latest server value.',
          Updating: 'A Livewire update is in progress.',
          Locked: 'Livewire prevents this property from being edited.',
          Unknown: 'No server-confirmed value was captured.',
        }[row?.state] ?? ''
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

    livewireComponentPropertyCount(component) {
      return Array.isArray(component?.properties) ? component.properties.length : 0;
    },

    livewireComponentPropertyCountLabel(component) {
      const count = this.livewireComponentPropertyCount(component);

      return `${count} ${count === 1 ? 'property' : 'properties'}`;
    },

    livewireComponentPropertyStateSummary(component) {
      const descriptors = Array.isArray(component?.server?.properties) ? component.server.properties : [];
      const editable = descriptors.filter(
        (property) => property?.writable === true || property?.array_leaf_writable === true,
      ).length;
      const changed =
        String(component?.id ?? '') === String(this.selectedLivewireComponent?.id ?? '')
          ? this.livewirePropertyRows.filter((row) => row.depth === 0 && row.state === 'Dirty').length
          : 0;

      return `${changed} changed, ${editable} editable`;
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

    livewireDraftKey(row) {
      return `${row.componentId}:${row.path}`;
    },

    toggleLivewireProperty(row) {
      if (!row?.hasChildren) return;
      const key = `${row.componentId}:${row.path}`;
      this.livewireExpandedProperties = this.livewireExpandedProperties.includes(key)
        ? this.livewireExpandedProperties.filter((item) => item !== key)
        : [...this.livewireExpandedProperties, key];
    },

    editLivewireProperty(row) {
      if (!row?.editable) return;
      const key = this.livewireDraftKey(row);
      const type = row.value === null ? 'String' : row.type;
      this.closeLivewireDrafts();
      this.livewireDrafts = {
        ...this.livewireDrafts,
        [key]: {
          componentId: row.componentId,
          path: row.path,
          baseline: livewireValueCopy(row.value),
          type,
          value: row.value === null ? '' : livewireValueCopy(row.value),
          status: 'editing',
          error: null,
        },
      };
    },

    toggleLivewirePropertyEditor(row) {
      if (!row?.editable) return;
      const draft = this.livewireDrafts[this.livewireDraftKey(row)];

      if (draft && draft.status !== 'closing') {
        this.cancelLivewireDraft(row);

        return;
      }

      this.editLivewireProperty(row);
    },

    cancelLivewireDraft(row, restoreFocus = false) {
      const key = this.livewireDraftKey(row);
      const draft = this.livewireDrafts[key];
      if (!draft) return;

      draft.status = 'closing';
      if (restoreFocus) this.focusLivewirePropertyEditor(row);
      this.scheduleLivewireDraftCleanup();
    },

    closeLivewireDrafts() {
      const drafts = Object.values(this.livewireDrafts);
      if (drafts.length === 0) return;

      drafts.forEach((draft) => {
        draft.status = 'closing';
      });
      this.scheduleLivewireDraftCleanup();
    },

    scheduleLivewireDraftCleanup() {
      const cleanup = () => {
        this.livewireDrafts = Object.fromEntries(
          Object.entries(this.livewireDrafts).filter(([, draft]) => draft.status !== 'closing'),
        );
      };

      if (typeof this.$nextTick === 'function') {
        this.$nextTick(cleanup);

        return;
      }
      if (browser.nextFrame) {
        browser.nextFrame(cleanup);

        return;
      }

      cleanup();
    },

    focusLivewirePropertyEditor(row, trigger = null) {
      const key = this.livewireDraftKey(row);
      browser.afterPaint(() => {
        browser.afterPaint(() => {
          const candidates =
            browser.queryAll?.('[data-ndb-livewire-edit-key]') ??
            this.$root.querySelectorAll('[data-ndb-livewire-edit-key]');
          const button =
            trigger?.isConnected !== false && trigger?.dataset?.ndbLivewireEditKey === key
              ? trigger
              : [...candidates].find((item) => item.dataset.ndbLivewireEditKey === key);
          if (button) button.focus();
        });
      });
    },

    toggleLivewireBoolean(row) {
      const draft = this.livewireDrafts[this.livewireDraftKey(row)];
      if (draft) draft.value = !Boolean(draft.value);
    },

    livewireMutationValue(draft) {
      if (draft.type === 'Boolean') return Boolean(draft.value);
      if (draft.type === 'Integer') {
        if (!/^-?\d+$/.test(String(draft.value).trim())) throw new Error('Enter a whole number.');
        return Number.parseInt(draft.value, 10);
      }
      if (draft.type === 'Float') {
        const value = Number(draft.value);
        if (String(draft.value).trim() === '' || !Number.isFinite(value)) throw new Error('Enter a valid number.');
        return value;
      }

      return String(draft.value);
    },

    async applyLivewireDraft(row, trigger = null) {
      const key = this.livewireDraftKey(row);
      const draft = this.livewireDrafts[key];
      if (!draft || draft.status === 'updating') return false;

      draft.error = null;

      try {
        const value = this.livewireMutationValue(draft);
        draft.status = 'updating';
        if (!trace?.applyMutation) throw new Error('Livewire is not available on this page.');
        const confirmed = await trace.applyMutation({
          componentId: draft.componentId,
          path: draft.path,
          baseline: draft.baseline,
          value,
        });
        draft.baseline = livewireValueCopy(confirmed);
        draft.value = livewireValueCopy(confirmed);
        this.cancelLivewireDraft(row);
        this.focusLivewirePropertyEditor(row, trigger);

        return true;
      } catch (error) {
        draft.status = 'failed';
        draft.error = error?.message ?? 'The Livewire update failed.';

        return false;
      }
    },

    async copyText(value) {
      if (value === null || value === undefined || typeof browser.writeClipboard !== 'function') return false;

      try {
        const copied = await browser.writeClipboard(String(value));

        return copied !== false;
      } catch {
        // Clipboard policies must never break the host page.

        return false;
      }
    },

    initializeModels(count) {
      const normalized = Number(count);

      this.modelGroupCount = Number.isInteger(normalized) && normalized > 0 ? normalized : 0;
      this.modelSearch = '';
      this.modelSort = 'capture';
      this.modelSortDirection = 'asc';
      this.visibleModelCount = this.modelGroupCount;
      this.modelSelected = null;
      this.modelDetailOpen = false;
      this.modelDetailTab = 'records';
      this.modelListScrollTop = 0;
    },

    applyModelView() {
      const list = this.$refs?.modelList;
      const rows = [...(list?.querySelectorAll?.('[data-ndb-model-group]') ?? [])];
      const search = this.modelSearch.toLowerCase().trim();
      let visible = 0;

      rows
        .sort((left, right) => this.compareModels(left, right))
        .forEach((row) => {
          const matches = search === '' || row.dataset.ndbModelSearchValue?.includes(search);
          row.hidden = !matches;

          if (matches) {
            row.style.removeProperty('display');
            visible++;
          } else {
            row.style.setProperty('display', 'none', 'important');
          }

          list?.appendChild?.(row);
        });

      this.visibleModelCount = visible;

      const selectedRow = rows.find((row) => Number(row.dataset.ndbModelIndex) === this.modelSelected);

      if (Number.isInteger(this.modelSelected) && selectedRow?.hidden) {
        this.modelSelected = null;
        this.modelDetailOpen = false;
        this.modelDetailTab = 'records';
      }
    },

    toggleModelSort(sort) {
      if (!['model', 'retrieved', 'writes', 'reloads'].includes(sort)) return;

      const firstDirection = sort === 'model' ? 'asc' : 'desc';

      if (this.modelSort !== sort) {
        this.modelSort = sort;
        this.modelSortDirection = firstDirection;
      } else if (this.modelSortDirection === firstDirection) {
        this.modelSortDirection = firstDirection === 'asc' ? 'desc' : 'asc';
      } else {
        this.modelSort = 'capture';
        this.modelSortDirection = 'asc';
      }

      this.applyModelView();
    },

    compareModels(left, right) {
      const captureComparison = Number(left.dataset.ndbModelIndex ?? 0) - Number(right.dataset.ndbModelIndex ?? 0);

      if (this.modelSort === 'capture') return captureComparison;

      let comparison = 0;

      if (this.modelSort === 'model') {
        comparison = String(left.dataset.ndbModelSortName ?? '').localeCompare(
          String(right.dataset.ndbModelSortName ?? ''),
          undefined,
          { numeric: true, sensitivity: 'base' },
        );
      } else {
        const attribute = {
          retrieved: 'ndbModelSortRetrieved',
          writes: 'ndbModelSortWrites',
          reloads: 'ndbModelSortReloads',
        }[this.modelSort];
        comparison = Number(left.dataset[attribute] ?? 0) - Number(right.dataset[attribute] ?? 0);
      }

      const directedComparison = this.modelSortDirection === 'asc' ? comparison : -comparison;

      return directedComparison || captureComparison;
    },

    selectModelGroup(index) {
      const selected = Number(index);

      if (!Number.isInteger(selected) || selected < 0 || selected >= this.modelGroupCount) return;

      this.modelListScrollTop = Math.max(
        Number(this.$refs?.modelList?.scrollTop ?? 0),
        Number(this.$refs?.content?.scrollTop ?? 0),
      );
      this.modelSelected = selected;
      this.modelDetailOpen = true;
      this.modelDetailTab = 'records';
      this.$nextTick?.(() => {
        this.$refs?.content?.scrollTo?.({ top: 0, behavior: 'instant' });
        this.$refs?.modelDetail?.scrollTo?.({ top: 0, behavior: 'instant' });
        const focus = () => this.$refs?.modelDetail?.focus?.({ preventScroll: true });

        browser.afterPaint ? browser.afterPaint(focus) : focus();
      });
    },

    setModelDetailTab(tab) {
      if (!['records', 'source'].includes(tab)) return;

      this.modelDetailTab = tab;
      this.$nextTick?.(() => {
        this.$refs?.modelDetail?.scrollTo?.({ top: 0, behavior: 'instant' });
      });
    },

    closeModelDetail() {
      const selected = this.modelSelected;

      if (!Number.isInteger(selected) || !this.modelDetailOpen) return;

      this.modelDetailOpen = false;
      this.$nextTick?.(() => {
        this.$refs?.modelList?.scrollTo?.({
          top: this.modelListScrollTop,
          behavior: 'instant',
        });
        this.$refs?.content?.scrollTo?.({
          top: this.modelListScrollTop,
          behavior: 'instant',
        });
        const focus = () =>
          [...(this.$root?.querySelectorAll?.('[data-ndb-model-group]') ?? [])]
            .find((row) => Number(row.dataset.ndbModelIndex) === selected)
            ?.focus?.({ preventScroll: true });

        browser.afterPaint ? browser.afterPaint(focus) : focus();
      });
    },

    initializeQueue(activities) {
      this.queueActivities = Array.isArray(activities)
        ? activities.map((activity) => ({
            ...activity,
            search: [
              activity?.job,
              activity?.job_label,
              activity?.connection,
              activity?.queue,
              activity?.job_id,
              activity?.status,
              activity?.communication_type,
              activity?.communication_class,
              ...(Array.isArray(activity?.channels) ? activity.channels : []),
              ...(Array.isArray(activity?.notifiable_types) ? activity.notifiable_types : []),
              activity?.exception_class,
              ...(Array.isArray(activity?.attempts)
                ? activity.attempts.map((attempt) => attempt?.exception_class)
                : []),
            ]
              .filter((value) => value !== null && value !== undefined)
              .join(' ')
              .toLowerCase(),
          }))
        : [];
      this.queueFilter = 'all';
      this.queueSearch = '';
      this.queueSelected = this.queueActivities[0]?.execution ?? null;
      this.queueDetailOpen = false;
      this.$nextTick?.(() => this.applyQueueView());
    },

    setQueueFilter(filter) {
      if (!['all', 'waiting', 'failed', 'completed'].includes(filter)) return;

      this.queueFilter = filter;
      this.applyQueueView();
    },

    selectQueueActivity(execution) {
      if (!this.queueActivities.some((activity) => activity.execution === execution)) return;

      this.queueSelected = execution;
      this.queueDetailOpen = true;
      this.resetQueueDetail(true);
    },

    closeQueueDetail() {
      const selected = this.queueSelected;

      if (!this.queueDetailOpen) return;

      this.queueDetailOpen = false;
      this.$nextTick?.(() => {
        const row = [...(this.$refs?.queueList?.children ?? [])].find(
          (item) => Number(item.dataset.ndbQueueExecution) === selected,
        );
        row?.focus?.({ preventScroll: true });
      });
    },

    resetQueueDetail(focus = false) {
      this.$nextTick?.(() => {
        this.$refs?.content?.scrollTo?.({ top: 0, behavior: 'instant' });
        this.$refs?.queueDetail?.scrollTo?.({ top: 0, behavior: 'instant' });
        if (focus) this.$refs?.queueDetail?.focus?.({ preventScroll: true });
        browser.highlight?.();
      });
    },

    applyQueueView() {
      const list = this.$refs?.queueList;
      const search = this.queueSearch.toLowerCase().trim();
      const activities = new Map(this.queueActivities.map((activity) => [activity.execution, activity]));
      let visible = 0;
      let firstVisible = null;
      let selectedVisible = false;

      [...(list?.children ?? [])].forEach((item) => {
        const execution = Number(item.dataset.ndbQueueExecution);
        const activity = activities.get(execution);
        const matchesFilter = this.queueFilter === 'all' || item.dataset.ndbQueueGroup === this.queueFilter;
        const matches = activity !== undefined && matchesFilter && (search === '' || activity.search.includes(search));
        item.hidden = !matches;

        if (matches) {
          item.style.removeProperty('display');
          firstVisible ??= execution;
          selectedVisible ||= execution === this.queueSelected;
          visible++;
        } else {
          item.style.setProperty('display', 'none', 'important');
        }
      });

      this.visibleQueueCount = visible;

      if (!selectedVisible) {
        this.queueSelected = firstVisible;
        if (firstVisible === null) this.queueDetailOpen = false;
      }
    },

    initializeRedis(commands) {
      this.redisCommands = Array.isArray(commands)
        ? commands.map((command) => ({
            ...command,
            search: [
              command?.command,
              command?.connection,
              ...(Array.isArray(command?.keys) ? command.keys : []),
              ...(Array.isArray(command?.key_hashes) ? command.key_hashes : []),
              command?.exception_class,
            ]
              .filter((value) => value !== null && value !== undefined)
              .join(' ')
              .toLowerCase(),
          }))
        : [];
      this.redisFilter = 'all';
      this.redisSearch = '';
      this.redisSelected = this.redisCommands[0]?.execution ?? null;
      this.redisDetailOpen = false;
      this.$nextTick?.(() => this.applyRedisView());
    },

    setRedisFilter(filter) {
      if (!['all', 'failed'].includes(filter)) return;

      this.redisFilter = filter;
      this.applyRedisView();
    },

    selectRedisCommand(execution) {
      if (!this.redisCommands.some((command) => command.execution === execution)) return;

      this.redisSelected = execution;
      this.redisDetailOpen = true;
      this.resetRedisDetail(true);
    },

    closeRedisDetail() {
      const selected = this.redisSelected;

      if (!this.redisDetailOpen) return;

      this.redisDetailOpen = false;
      this.$nextTick?.(() => {
        const row = [...(this.$refs?.redisList?.children ?? [])].find(
          (item) => Number(item.dataset.ndbRedisExecution) === selected,
        );
        row?.focus?.({ preventScroll: true });
      });
    },

    resetRedisDetail(focus = false) {
      this.$nextTick?.(() => {
        this.$refs?.content?.scrollTo?.({ top: 0, behavior: 'instant' });
        this.$refs?.redisDetail?.scrollTo?.({ top: 0, behavior: 'instant' });
        if (focus) this.$refs?.redisDetail?.focus?.({ preventScroll: true });
        browser.highlight?.();
      });
    },

    applyRedisView() {
      const list = this.$refs?.redisList;
      const search = this.redisSearch.toLowerCase().trim();
      const commands = new Map(this.redisCommands.map((command) => [command.execution, command]));
      let visible = 0;
      let firstVisible = null;
      let selectedVisible = false;

      [...(list?.children ?? [])].forEach((item) => {
        const execution = Number(item.dataset.ndbRedisExecution);
        const command = commands.get(execution);
        const failed = item.dataset.ndbRedisFailed === 'true';
        const matchesFilter = this.redisFilter === 'all' || (this.redisFilter === 'failed' && failed);
        const matches = command !== undefined && matchesFilter && (search === '' || command.search.includes(search));
        item.hidden = !matches;

        if (matches) {
          item.style.removeProperty('display');
          firstVisible ??= execution;
          selectedVisible ||= execution === this.redisSelected;
          visible++;
        } else {
          item.style.setProperty('display', 'none', 'important');
        }
      });

      this.visibleRedisCount = visible;

      if (!selectedVisible) {
        this.redisSelected = firstVisible;
        if (firstVisible === null) this.redisDetailOpen = false;
      }
    },

    initializeCache(operations) {
      this.cacheOperations = Array.isArray(operations) ? operations : [];
      this.cacheFilter = 'all';
      this.cacheSearch = '';
      this.cacheDetailOpen = false;
      this.cacheSelected = this.cacheOperations[0]?.execution ?? null;

      if (this.cacheOperations.length === 0) {
        this.visibleCacheCount = 0;

        return;
      }

      this.$nextTick?.(() => this.applyCacheView());
    },

    setCacheFilter(filter) {
      if (!['all', 'reads', 'writes', 'deletes', 'failed'].includes(filter)) return;

      this.cacheFilter = filter;
      this.applyCacheView();
    },

    selectCacheOperation(execution) {
      if (!this.cacheOperations.some((operation) => operation.execution === execution)) return;

      this.cacheSelected = execution;
      this.cacheDetailOpen = true;
      this.resetCacheDetailScroll();
    },

    resetCacheDetailScroll() {
      this.$nextTick?.(() => {
        this.$refs?.content?.scrollTo?.({ top: 0, behavior: 'instant' });
        this.$refs?.cacheDetail?.scrollTo?.({ top: 0, behavior: 'instant' });
        browser.highlight?.();
      });
    },

    applyCacheView() {
      const list = this.$refs?.cacheList;
      const search = this.cacheSearch.toLowerCase().trim();
      let visible = 0;
      let firstVisible = null;
      let selectedVisible = false;

      [...(list?.children ?? [])].forEach((item) => {
        const matchesFilter =
          this.cacheFilter === 'all' ||
          (this.cacheFilter === 'reads' && item.dataset.ndbCacheCategory === 'read') ||
          (this.cacheFilter === 'writes' && item.dataset.ndbCacheCategory === 'write') ||
          (this.cacheFilter === 'deletes' && item.dataset.ndbCacheCategory === 'delete') ||
          (this.cacheFilter === 'failed' && item.dataset.ndbCacheFailed === 'true');
        const matches = matchesFilter && (search === '' || item.dataset.ndbCacheSearchText?.includes(search));
        item.hidden = !matches;

        if (matches) {
          item.style.removeProperty('display');
        } else {
          item.style.setProperty('display', 'none', 'important');
        }

        if (matches) {
          const execution = Number(item.dataset.ndbCacheExecution);
          firstVisible ??= execution;
          selectedVisible ||= execution === this.cacheSelected;
          visible++;
        }
      });

      this.visibleCacheCount = visible;

      if (!selectedVisible) {
        this.cacheSelected = firstVisible;
      }
    },

    initializeHttpClient(requests) {
      this.httpClientRequests = Array.isArray(requests) ? requests : [];
      this.httpClientFilter = 'all';
      this.httpClientSearch = '';
      this.httpClientSort = 'execution';
      this.httpClientSortDirection = 'asc';
      this.httpClientDetailOpen = false;
      this.httpClientDetailTab = 'response';
      this.httpClientSelected = this.httpClientRequests[0]?.execution ?? null;
      this.$nextTick?.(() => this.applyHttpClientView());
    },

    setHttpClientFilter(filter) {
      if (!['all', 'failed', 'slow'].includes(filter)) return;

      this.httpClientFilter = filter;
      this.applyHttpClientView();
    },

    toggleHttpClientSort(sort) {
      if (sort !== 'duration') return;

      if (this.httpClientSort !== sort) {
        this.httpClientSort = sort;
        this.httpClientSortDirection = 'desc';
      } else if (this.httpClientSortDirection === 'desc') {
        this.httpClientSortDirection = 'asc';
      } else {
        this.httpClientSort = 'execution';
        this.httpClientSortDirection = 'asc';
      }

      this.applyHttpClientView();
    },

    selectHttpClientRequest(execution) {
      if (!this.httpClientRequests.some((request) => request.execution === execution)) return;

      this.httpClientSelected = execution;
      this.httpClientDetailOpen = true;
      this.httpClientDetailTab = 'response';
      this.$nextTick?.(() => browser.highlight?.());
    },

    setHttpClientDetailTab(tab) {
      if (!['response', 'request', 'source'].includes(tab)) return;

      this.httpClientDetailTab = tab;
      this.$nextTick?.(() => {
        this.$refs?.httpClientDetail?.scrollTo?.({
          top: 0,
          behavior: 'instant',
        });
        browser.highlight?.();
      });
    },

    applyHttpClientView() {
      const list = this.$refs?.httpClientList;
      const search = this.httpClientSearch.toLowerCase().trim();
      const items = [...(list?.querySelectorAll?.('[data-ndb-http-client-item]') ?? [])];
      let visible = 0;
      let firstVisible = null;
      let selectedVisible = false;

      items
        .sort((left, right) => this.compareHttpClientRequests(left, right))
        .forEach((item) => {
          const matchesFilter =
            this.httpClientFilter === 'all' ||
            (this.httpClientFilter === 'failed' && item.dataset.ndbFailed === 'true') ||
            (this.httpClientFilter === 'slow' && item.dataset.ndbSlow === 'true');
          const matches = matchesFilter && (search === '' || item.dataset.ndbSearch?.includes(search));
          item.hidden = !matches;
          if (matches) {
            item.style.removeProperty('display');
          } else {
            item.style.setProperty('display', 'none', 'important');
          }

          if (matches) {
            const execution = Number(item.dataset.ndbExecution);
            firstVisible ??= execution;
            selectedVisible ||= execution === this.httpClientSelected;
            visible++;
          }

          list?.appendChild?.(item);
        });

      this.visibleHttpClientCount = visible;

      if (!selectedVisible) {
        this.httpClientSelected = firstVisible;
        this.httpClientDetailTab = 'response';
      }
    },

    compareHttpClientRequests(left, right) {
      const executionComparison = Number(left.dataset.ndbExecution ?? 0) - Number(right.dataset.ndbExecution ?? 0);
      let comparison = 0;

      if (this.httpClientSort === 'duration') {
        const leftDuration = Number(left.dataset.ndbDuration ?? -1);
        const rightDuration = Number(right.dataset.ndbDuration ?? -1);

        if (leftDuration < 0 || rightDuration < 0) {
          if (leftDuration < 0 && rightDuration >= 0) return 1;
          if (rightDuration < 0 && leftDuration >= 0) return -1;
        }

        comparison = leftDuration - rightDuration;
      } else {
        return executionComparison;
      }

      const directedComparison = this.httpClientSortDirection === 'asc' ? comparison : -comparison;

      return directedComparison || executionComparison;
    },

    formatHttpClientEvidence(value) {
      if (value === null || value === undefined || value === '') return '—';
      if (typeof value === 'string') return value;

      return JSON.stringify(value, null, 2);
    },

    initializeMail(messages) {
      this.mailMessages = Array.isArray(messages) ? messages : [];
      const requestedMessage =
        this.pendingMailMessageId === null
          ? undefined
          : this.mailMessages.find((message) => message.transport_message_id === this.pendingMailMessageId);
      this.mailFilter = 'all';
      this.mailSearch = '';
      this.mailSelected = requestedMessage?.execution ?? this.mailMessages[0]?.execution ?? null;
      this.mailDetailOpen = requestedMessage !== undefined;
      this.pendingMailMessageId = null;
      this.resetMailDetail();
      this.$nextTick?.(() => {
        this.applyMailView();
        if (requestedMessage !== undefined) this.$refs?.mailDetail?.focus?.();
      });
    },

    setMailFilter(filter) {
      if (!['all', 'attachments'].includes(filter)) return;

      this.mailFilter = filter;
      this.applyMailView();
    },

    selectMailMessage(execution) {
      if (!this.mailMessages.some((message) => message.execution === execution)) return;

      this.mailSelected = execution;
      this.mailDetailOpen = true;
      this.resetMailDetail();
    },

    setMailDetailTab(tab) {
      if (!['preview', 'message', 'source'].includes(tab)) return;

      this.mailDetailTab = tab;
      this.$nextTick?.(() => {
        this.$refs?.mailDetail?.scrollTo?.({ top: 0, behavior: 'instant' });
        browser.highlight?.();
      });
    },

    setMailPreviewFormat(format) {
      if (!['html', 'text'].includes(format)) return;

      const message = this.selectedMailMessage;
      if (format === 'html' && !message?.has_html) return;
      if (format === 'text' && !message?.has_text) return;

      this.mailPreviewFormat = format;
      this.$nextTick?.(() => {
        const frame = this.$refs?.mailPreviewFrame;
        if (!frame) return;

        frame.style.setProperty('height', '20rem', 'important');
        this.resizeMailPreviewFrame(frame);
      });
    },

    setMailPreviewViewport(viewport) {
      if (!['desktop', 'mobile'].includes(viewport)) return;
      if (this.mailPreviewFormat === 'text') return;

      this.mailPreviewViewport = viewport;
      this.$nextTick?.(() => {
        const frame = this.$refs?.mailPreviewFrame;
        if (!frame) return;

        frame.style.setProperty('height', '20rem', 'important');
        this.resizeMailPreviewFrame(frame);
      });
    },

    connectMailPreviewFrame(frame) {
      if (typeof HTMLIFrameElement === 'undefined' || !(frame instanceof HTMLIFrameElement)) return;

      this.mailPreviewFrameCleanup?.();
      frame.__newDebugBarMailPreviewCleanup?.();

      const canvas = frame.closest('[data-ndb-mail-preview-canvas]');
      let connected = true;
      let layoutScheduled = false;
      const scheduleLayout = () => {
        if (!connected || layoutScheduled) return;

        layoutScheduled = true;
        browser.nextFrame(() => {
          layoutScheduled = false;
          if (connected) this.layoutMailPreviewFrame(frame);
        });
      };

      const handlePreviewMessage = (event) => {
        if (event.source !== frame.contentWindow) return;

        if (event.data?.type === 'newdebugbar:mail-preview-scroll' && Number.isFinite(event.data.deltaY)) {
          const detail = frame.closest('[data-ndb-mail-detail]');
          if (!detail) return;

          const multiplier = event.data.deltaMode === 1 ? 16 : event.data.deltaMode === 2 ? detail.clientHeight : 1;
          detail.scrollBy({ top: event.data.deltaY * multiplier });

          return;
        }

        if (event.data?.type !== 'newdebugbar:mail-preview-height' || !Number.isFinite(event.data.height)) return;

        const height = Math.min(100_000, Math.max(320, Math.ceil(event.data.height)));
        frame.style.setProperty('height', `${height}px`, 'important');
        this.layoutMailPreviewFrame(frame);
      };

      window.addEventListener('message', handlePreviewMessage);
      if (canvas && typeof ResizeObserver === 'function') {
        const observer = new ResizeObserver(scheduleLayout);
        observer.observe(canvas);
        frame.__newDebugBarMailPreviewCanvasObserver = observer;
      }

      const cleanup = () => {
        connected = false;
        window.removeEventListener('message', handlePreviewMessage);
        frame.__newDebugBarMailPreviewObserver?.disconnect?.();
        frame.__newDebugBarMailPreviewCanvasObserver?.disconnect?.();
      };
      frame.__newDebugBarMailPreviewCleanup = cleanup;
      this.mailPreviewFrameCleanup = cleanup;
      scheduleLayout();
    },

    layoutMailPreviewFrame(frame) {
      if (typeof HTMLIFrameElement === 'undefined' || !(frame instanceof HTMLIFrameElement)) return;

      const canvas = frame.closest('[data-ndb-mail-preview-canvas]');
      const availableWidth = canvas?.clientWidth ?? 0;
      if (!canvas || availableWidth <= 0) return;

      const fixedWidth = this.mailPreviewFormat === 'html' ? MAIL_PREVIEW_WIDTHS[this.mailPreviewViewport] : null;
      const frameWidth = fixedWidth ?? availableWidth;
      const scale = fixedWidth ? Math.min(1, availableWidth / fixedWidth) : 1;

      frame.style.setProperty('width', `${frameWidth}px`, 'important');
      frame.style.setProperty('transform', `translateX(-50%) scale(${scale})`, 'important');

      const frameHeight = frame.offsetHeight;
      if (frameHeight > 0) {
        canvas.style.setProperty('height', `${Math.ceil(frameHeight * scale)}px`, 'important');
      }
    },

    resizeMailPreviewFrame(frame) {
      if (typeof HTMLIFrameElement === 'undefined' || !(frame instanceof HTMLIFrameElement)) return;

      frame.__newDebugBarMailPreviewObserver?.disconnect?.();

      try {
        const frameDocument = frame.contentDocument;
        const body = frameDocument?.body;
        const root = frameDocument?.documentElement;

        if (body && root) {
          let scheduled = false;
          const resize = () => {
            scheduled = false;
            const height = Math.min(
              100_000,
              Math.max(320, body.scrollHeight, body.offsetHeight, root.scrollHeight, root.offsetHeight),
            );

            const currentHeight = Number.parseFloat(frame.style.height);
            if (!Number.isFinite(currentHeight) || Math.abs(currentHeight - height) > 1) {
              frame.style.setProperty('height', `${Math.ceil(height)}px`, 'important');
            }
            this.layoutMailPreviewFrame(frame);
          };
          const scheduleResize = () => {
            if (scheduled) return;
            scheduled = true;
            browser.nextFrame(resize);
          };

          if (typeof ResizeObserver === 'function') {
            const observer = new ResizeObserver(scheduleResize);
            observer.observe(body);
            frame.__newDebugBarMailPreviewObserver = observer;
          }

          scheduleResize();
        }
      } catch {
        // Sandboxed HTML previews report their own height below.
      }

      this.layoutMailPreviewFrame(frame);
      frame.contentWindow?.postMessage({ type: 'newdebugbar:measure-mail-preview' }, '*');
    },

    applyMailView() {
      const list = this.$refs?.mailList;
      const search = this.mailSearch.toLowerCase().trim();
      let visible = 0;
      let firstVisible = null;
      let selectedVisible = false;

      if (this.mailMessages.length === 0) {
        this.visibleMailCount = 0;
        this.mailSelected = null;

        return;
      }

      if (!list) {
        this.visibleMailCount = this.mailMessages.length;

        return;
      }

      [...list.children].forEach((item) => {
        const matches =
          (this.mailFilter === 'all' || item.dataset.ndbAttachments === 'true') &&
          (search === '' || item.dataset.ndbSearch?.includes(search));
        item.hidden = !matches;
        if (matches) {
          item.style.removeProperty('display');
          const execution = Number(item.dataset.ndbExecution);
          firstVisible ??= execution;
          selectedVisible ||= execution === this.mailSelected;
          visible++;
        } else {
          item.style.setProperty('display', 'none', 'important');
        }
      });

      this.visibleMailCount = visible;

      if (!selectedVisible) {
        this.mailSelected = firstVisible;
        this.resetMailDetail();
      }
    },

    resetMailDetail() {
      this.mailDetailTab = 'preview';
      this.mailPreviewFormat = this.selectedMailMessage?.has_html ? 'html' : 'text';
      this.mailPreviewViewport = 'desktop';
      this.$nextTick?.(() => {
        this.$refs?.mailDetail?.scrollTo?.({ top: 0, behavior: 'instant' });
        browser.highlight?.();
      });
    },

    mailPreviewUrl(message = this.selectedMailMessage) {
      if (!message) return null;

      return this.mailPreviewFormat === 'text' ? message.text_url : message.html_url;
    },

    formatMailAddresses(addresses) {
      return Array.isArray(addresses) && addresses.length > 0 ? addresses.join(', ') : '—';
    },

    initializeNotifications(notifications) {
      this.notificationGroups = Array.isArray(notifications) ? notifications : [];
      this.notificationFilter = 'all';
      this.notificationSearch = '';
      this.notificationSelected = this.notificationGroups[0]?.execution ?? null;
      this.notificationDetailOpen = false;
      this.resetNotificationDetail();
      this.$nextTick?.(() => this.applyNotificationView());
    },

    setNotificationFilter(filter) {
      if (!['all', 'attention', 'sent'].includes(filter)) return;

      this.notificationFilter = filter;
      this.applyNotificationView();
    },

    selectNotification(execution) {
      if (!this.notificationGroups.some((notification) => notification.execution === execution)) return;

      this.notificationSelected = execution;
      this.notificationDetailOpen = true;
      this.resetNotificationDetail();
    },

    setNotificationDetailTab(tab) {
      if (!['delivery', 'payload', 'source'].includes(tab)) return;

      this.notificationDetailTab = tab;
      this.$nextTick?.(() => {
        this.$refs?.notificationDetail?.scrollTo?.({
          top: 0,
          behavior: 'instant',
        });
        browser.highlight?.();
      });
    },

    setNotificationChannel(channel) {
      if (!this.selectedNotification?.deliveries?.some((delivery) => delivery.channel === channel)) return;

      this.notificationChannel = channel;
      this.$nextTick?.(() => browser.highlight?.());
    },

    applyNotificationView() {
      const list = this.$refs?.notificationList;
      const search = this.notificationSearch.toLowerCase().trim();
      let visible = 0;
      let firstVisible = null;
      let selectedVisible = false;

      if (this.notificationGroups.length === 0) {
        this.visibleNotificationCount = 0;
        this.notificationSelected = null;

        return;
      }

      [...(list?.children ?? [])].forEach((item) => {
        const status = item.dataset.ndbStatus;
        const matchesFilter =
          this.notificationFilter === 'all' ||
          (this.notificationFilter === 'attention' && status !== 'sent') ||
          (this.notificationFilter === 'sent' && status === 'sent');
        const matches = matchesFilter && (search === '' || item.dataset.ndbSearch?.includes(search));
        item.hidden = !matches;
        if (matches) {
          item.style.removeProperty('display');
          const execution = Number(item.dataset.ndbExecution);
          firstVisible ??= execution;
          selectedVisible ||= execution === this.notificationSelected;
          visible++;
        } else {
          item.style.setProperty('display', 'none', 'important');
        }
      });

      this.visibleNotificationCount = visible;

      if (!selectedVisible) {
        this.notificationSelected = firstVisible;
        this.resetNotificationDetail();
      }
    },

    resetNotificationDetail() {
      this.notificationDetailTab = 'delivery';
      this.notificationChannel = this.selectedNotification?.deliveries?.[0]?.channel ?? null;
      this.$nextTick?.(() => {
        this.$refs?.notificationDetail?.scrollTo?.({
          top: 0,
          behavior: 'instant',
        });
        browser.highlight?.();
      });
    },

    formatNotificationEvidence(value, empty = 'No data was captured.') {
      if (value === null || value === undefined || value === '') return empty;
      if (typeof value === 'string') return value;

      return JSON.stringify(value, null, 2);
    },

    openNotificationMail(messageId) {
      const message = this.mailMessages.find((mail) => mail.transport_message_id === messageId);
      this.pendingMailMessageId = messageId;

      this.selectSection('mail');

      if (message) {
        this.pendingMailMessageId = null;
        this.selectMailMessage(message.execution);
        this.$nextTick?.(() => this.$refs?.mailDetail?.focus?.());
      }
    },

    initializeQueries(records) {
      this.queryRecords = Array.isArray(records) ? records : [];
      this.queryFilter = this.queryFocusFilter ? 'attention' : 'all';
      this.querySearch = '';
      this.querySort = 'execution';
      this.querySortDirection = 'asc';
      this.querySelected = this.queryRecords[0]?.key ?? null;
      this.querySelectedExecution = this.queryRecords[0]?.executions?.[0]?.execution ?? null;
      this.queryDetailOpen = false;
      this.queryDetailTab = 'overview';
      this.queryDetailReturnFocus = null;
      this.syncQueryExplain();
      this.$nextTick?.(() => {
        this.applyQueryView();
        if (this.queryFocusFilter) this.focusQueryFinding(this.queryFocusFilter);
      });
    },

    setQueryFilter(filter) {
      if (!['all', 'attention', 'read', 'write'].includes(filter)) return;

      this.queryFilter = filter;
      this.queryFocusFilter = null;
      this.applyQueryView();
    },

    toggleQuerySort(sort) {
      if (sort !== 'duration') return;

      if (this.querySort !== sort) {
        this.querySort = sort;
        this.querySortDirection = 'desc';
      } else if (this.querySortDirection === 'desc') {
        this.querySortDirection = 'asc';
      } else {
        this.querySort = 'execution';
        this.querySortDirection = 'asc';
      }

      this.applyQueryView();
    },

    selectQueryRecord(key) {
      const record = this.queryRecords.find((candidate) => candidate.key === key);
      if (!record) return;

      this.querySelected = record.key;
      this.querySelectedExecution = record.executions?.[0]?.execution ?? null;
      this.queryDetailOpen = true;
      this.queryDetailTab = 'overview';
      this.queryDetailReturnFocus = browser.activeElement?.() ?? null;
      this.syncQueryExplain();
      this.$nextTick?.(() => {
        this.$refs?.queryDetail?.scrollTo?.({ top: 0, behavior: 'instant' });
        if ((browser.viewportWidth?.() ?? 1024) < 1024) this.$refs?.queryDetail?.focus?.({ preventScroll: true });
        browser.highlight?.();
      });
    },

    closeQueryDetail() {
      const returnFocus = this.queryDetailReturnFocus;
      const selectedRow = [...(this.$refs?.queryList?.querySelectorAll?.('[data-ndb-query-item]') ?? [])].find(
        (item) => item.dataset.ndbQueryKey === this.querySelected,
      );
      this.queryDetailOpen = false;
      this.queryDetailReturnFocus = null;
      this.$nextTick?.(() => {
        const focus = () =>
          (returnFocus?.isConnected === false ? selectedRow : (returnFocus ?? selectedRow))?.focus?.();
        browser.afterPaint ? browser.afterPaint(focus) : focus();
      });
    },

    selectQueryExecution(execution) {
      if (!this.selectedQueryRecord?.executions?.some((query) => query.execution === execution)) return;

      this.querySelectedExecution = execution;
      this.syncQueryExplain();
      this.$nextTick?.(() => browser.highlight?.());
    },

    setQueryDetailTab(tab) {
      if (!['overview', 'explain'].includes(tab)) return;

      this.queryDetailTab = tab;
      this.$nextTick?.(() => {
        this.$refs?.queryDetail?.scrollTo?.({ top: 0, behavior: 'instant' });
        browser.highlight?.();
      });
    },

    openQueryExplain(wire) {
      this.setQueryDetailTab('explain');

      return this.runQueryExplain(wire);
    },

    async runQueryExplain(wire) {
      if (typeof wire?.explainQuery !== 'function') return;

      const execution = this.beginQueryExplain();
      if (execution === null) return;

      try {
        await wire.explainQuery(execution);
      } catch {
        this.failQueryExplain(execution);
      }
    },

    syncQueryExplain() {
      this.queryExplain = this.selectedQuery?.explain ?? null;
      this.queryExplainError = this.selectedQuery?.explain_error ?? null;
      this.queryExplainLoading = this.selectedQuery?.explain_loading === true;
      this.queryExplainExecution = this.selectedQuery?.execution ?? null;
      this.queryExplainScrollTop = null;
    },

    beginQueryExplain() {
      const query = this.selectedQuery;
      if (!query?.explain_available || query.explain_loading === true) return null;
      if (query.explain != null || query.explain_error != null) return null;

      this.queryDetailTab = 'explain';
      query.explain = null;
      query.explain_error = null;
      query.explain_loading = true;
      this.queryExplain = null;
      this.queryExplainError = null;
      this.queryExplainLoading = true;
      this.queryExplainExecution = query.execution;
      this.queryExplainScrollTop = this.$refs?.queryDetail?.scrollTop ?? null;

      return query.execution;
    },

    receiveQueryExplain(detail = {}) {
      const execution = Number(detail.execution);
      if (!Number.isFinite(execution)) return;

      this.queryRecords.forEach((record) =>
        (record.executions ?? []).forEach((query) => {
          if (query.execution !== execution) return;

          query.explain = detail.explain ?? null;
          query.explain_error = detail.error ?? null;
          query.explain_loading = false;
        }),
      );

      if (execution !== this.querySelectedExecution) return;

      this.queryExplain = detail.explain ?? null;
      this.queryExplainError = detail.error ?? null;
      this.queryExplainLoading = false;
      this.$nextTick?.(() => {
        if (this.queryExplainScrollTop !== null) {
          this.$refs?.queryDetail?.scrollTo?.({
            top: this.queryExplainScrollTop,
            behavior: 'instant',
          });
        }
        browser.highlight?.();
      });
    },

    failQueryExplain(execution = this.queryExplainExecution) {
      const normalizedExecution = Number(execution);
      if (!Number.isFinite(normalizedExecution)) return;

      this.queryRecords.forEach((record) =>
        (record.executions ?? []).forEach((query) => {
          if (query.execution !== normalizedExecution) return;

          query.explain = null;
          query.explain_error = 'EXPLAIN could not be completed.';
          query.explain_loading = false;
        }),
      );

      if (normalizedExecution !== this.querySelectedExecution) return;

      this.queryExplain = null;
      this.queryExplainLoading = false;
      this.queryExplainError = 'EXPLAIN could not be completed.';
    },

    focusQueryFinding(filter) {
      if (!['repeated', 'slow'].includes(filter)) return;

      const selector =
        filter === 'repeated'
          ? '[data-ndb-query-item][data-ndb-repeated="true"]:not([hidden])'
          : '[data-ndb-query-item][data-ndb-slow="true"]:not([hidden])';
      const item = this.$refs?.queryList?.querySelector?.(selector);
      if (!item) return;

      this.queryFocusFilter = null;
      this.selectQueryRecord(item.dataset.ndbQueryKey);
      item.scrollIntoView?.({ block: 'nearest' });
    },

    applyQueryView() {
      const list = this.$refs?.queryList;
      const search = this.querySearch.toLowerCase().trim();
      const items = [...(list?.querySelectorAll?.('[data-ndb-query-item]') ?? [])];
      let visible = 0;
      let firstVisible = null;
      let selectedVisible = false;

      items
        .sort((left, right) => this.compareQueries(left, right))
        .forEach((item) => {
          const matchesFilter =
            this.queryFilter === 'all' ||
            (this.queryFilter === 'attention' && item.dataset.ndbAttention === 'true') ||
            (this.queryFilter === 'read' && item.dataset.ndbQueryType === 'read') ||
            (this.queryFilter === 'write' && item.dataset.ndbQueryType === 'write');
          const matchesSearch = search === '' || item.dataset.ndbSearch?.includes(search);
          const matches = matchesFilter && matchesSearch;
          item.hidden = !matches;

          if (matches) {
            item.style?.removeProperty?.('display');
            firstVisible ??= item.dataset.ndbQueryKey;
            selectedVisible ||= item.dataset.ndbQueryKey === this.querySelected;
            visible += Number(item.dataset.ndbQueryExecutionCount ?? 1);
          } else {
            item.style?.setProperty?.('display', 'none', 'important');
          }

          list?.appendChild?.(item);
        });

      this.visibleQueryCount = visible;

      if (!selectedVisible) {
        const record = this.queryRecords.find((candidate) => candidate.key === firstVisible) ?? null;
        this.querySelected = record?.key ?? null;
        this.querySelectedExecution = record?.executions?.[0]?.execution ?? null;
        if (record === null) this.queryDetailOpen = false;
        this.queryDetailTab = 'overview';
        this.queryDetailReturnFocus = null;
        this.syncQueryExplain();
      }
    },

    compareQueries(left, right) {
      if (this.querySort === 'duration') {
        const durationComparison = Number(left.dataset.ndbDuration ?? 0) - Number(right.dataset.ndbDuration ?? 0);
        const directedComparison = this.querySortDirection === 'asc' ? durationComparison : -durationComparison;

        return directedComparison || Number(left.dataset.ndbExecution ?? 0) - Number(right.dataset.ndbExecution ?? 0);
      }

      return Number(left.dataset.ndbExecution ?? 0) - Number(right.dataset.ndbExecution ?? 0);
    },

    formatQueryEvidence(value) {
      if (value === null || value === undefined || value === '') return 'No evidence was captured.';
      if (typeof value === 'string') return value;

      return JSON.stringify(value, null, 2);
    },

    formatQueryType(type) {
      const value = typeof type === 'string' && type !== '' ? type : 'query';

      return value.charAt(0).toUpperCase() + value.slice(1);
    },

    highlightQueryCode(code) {
      if (typeof code?.textContent === 'string') code.textContent = code.textContent;
      code?.removeAttribute?.('data-highlighted');
      browser.highlight?.();
    },

    initializeViews(groups) {
      this.viewGroups = Array.isArray(groups) ? groups : [];
      this.viewFilter = this.viewGroups.some((group) => group.origin === 'application') ? 'application' : 'all';
      this.viewSearch = '';
      this.viewSelected = null;
      this.viewDetailOpen = false;
      this.viewRenderOrder = null;
      this.resetViewData();
      this.$nextTick?.(() => this.applyViewFilters());
    },

    setViewFilter(filter) {
      if (!['application', 'all', 'framework'].includes(filter)) return;

      this.viewFilter = filter;
      this.applyViewFilters();
    },

    applyViewFilters() {
      const groups = this.$refs?.viewGroups ?? this.$root?.querySelector?.('[x-ref="viewGroups"]');

      if (!groups?.querySelectorAll) {
        this.visibleViewCount = 0;
        this.visibleViewRenderCount = 0;

        return;
      }

      const search = this.viewSearch.toLowerCase().trim();
      let visibleGroups = 0;
      let visibleRenders = 0;

      [...groups.querySelectorAll('[data-ndb-view-group]')].forEach((group) => {
        const matchesFilter = this.viewFilter === 'all' || group.dataset.ndbViewOrigin === this.viewFilter;
        const matchesSearch = search === '' || group.dataset.ndbViewSearchValue?.includes(search);
        group.hidden = !matchesFilter || !matchesSearch;

        if (!group.hidden) {
          visibleGroups++;
          visibleRenders += Number(group.dataset.ndbViewCount ?? 0);
        }
      });

      this.visibleViewCount = visibleGroups;
      this.visibleViewRenderCount = visibleRenders;

      if (
        this.viewSelected &&
        ![...groups.querySelectorAll('[data-ndb-view-group]:not([hidden])')].some(
          (group) => group.dataset.ndbViewGroup === this.viewSelected,
        )
      ) {
        this.viewSelected = null;
        this.viewDetailOpen = false;
        this.viewRenderOrder = null;
        this.resetViewData();
      }
    },

    selectViewGroup(id) {
      const group = this.viewGroups.find((candidate) => candidate.id === id);

      if (!group) return;

      this.viewSelected = id;
      this.viewDetailOpen = true;
      this.viewRenderOrder = group.items?.[0]?.render_order ?? null;
      this.resetViewData();
      this.$nextTick?.(() => {
        if (this.$refs?.content) this.$refs.content.scrollTop = 0;
        this.$refs?.viewDetail?.focus?.({ preventScroll: true });
      });
    },

    closeViewDetail() {
      const id = this.viewSelected;
      this.viewDetailOpen = false;
      this.$nextTick?.(() => {
        const groups = this.$refs?.viewGroups?.querySelectorAll?.('[data-ndb-view-group]') ?? [];
        [...groups].find((group) => group.dataset.ndbViewGroup === id)?.focus?.({ preventScroll: true });
      });
    },

    selectViewRender(renderOrder) {
      const order = Number(renderOrder);

      if (!this.selectedViewGroup?.items?.some((view) => Number(view.render_order) === order)) return;
      if (Number(this.viewRenderOrder) === order) return;

      this.viewRenderOrder = order;
      this.resetViewData();
    },

    resetViewData() {
      this.viewDataRequest++;
      this.viewData = null;
      this.viewDataLoaded = false;
      this.viewDataLoading = false;
      this.viewDataError = false;
    },

    loadSelectedViewData(wire, force = false) {
      if (!force && (this.viewDataLoaded || this.viewDataLoading)) return;

      const renderOrder = Number(this.viewRenderOrder);
      const action = wire?.loadViewData;

      if (!Number.isInteger(renderOrder) || renderOrder <= 0 || typeof action !== 'function') {
        this.viewDataError = true;

        return;
      }

      const request = ++this.viewDataRequest;
      this.viewDataLoading = true;
      this.viewDataError = false;
      Promise.resolve(action.call(wire, renderOrder))
        .then((data) => {
          if (request !== this.viewDataRequest || renderOrder !== Number(this.viewRenderOrder)) return;

          this.viewData = data ?? {};
          this.viewDataLoaded = true;
          this.viewDataLoading = false;
          this.$nextTick?.(() => browser.highlight?.());
        })
        .catch(() => {
          if (request !== this.viewDataRequest) return;

          this.viewDataLoading = false;
          this.viewDataError = true;
        });
    },

    initializeAuthorization(decisions) {
      this.authorizationDecisions = Array.isArray(decisions) ? decisions : [];
      this.authorizationFilter = 'all';
      this.authorizationSearch = '';
      this.authorizationSelected = this.authorizationDecisions[0]?.execution ?? null;
      this.authorizationDetailOpen = false;
      this.resetAuthorizationDetail();
      this.$nextTick?.(() => this.applyAuthorizationView());
    },

    setAuthorizationFilter(filter) {
      if (!['all', 'allowed', 'denied'].includes(filter)) return;

      this.authorizationFilter = filter;
      this.applyAuthorizationView();
    },

    selectAuthorizationDecision(execution) {
      if (!this.authorizationDecisions.some((decision) => decision.execution === execution)) return;

      this.authorizationSelected = execution;
      this.authorizationDetailOpen = true;
      this.resetAuthorizationDetail();
      this.$nextTick?.(() => {
        const detail = this.$refs?.authorizationDetail;
        const mobile = browser.matchMedia?.('(max-width: 1023px)')?.matches ?? false;

        if (mobile) {
          if (this.$refs?.content) this.$refs.content.scrollTop = 0;
          detail?.focus?.({ preventScroll: true });
        }
      });
    },

    closeAuthorizationDetail() {
      const execution = this.authorizationSelected;
      this.authorizationDetailOpen = false;
      this.$nextTick?.(() => {
        this.$root?.querySelector?.(`[data-ndb-authorization-item="${execution}"]`)?.focus?.();
      });
    },

    resetAuthorizationDetail() {
      this.$nextTick?.(() => {
        this.$refs?.authorizationDetail?.scrollTo?.({
          top: 0,
          behavior: 'instant',
        });
        browser.highlight?.();
      });
    },

    applyAuthorizationView() {
      const list = this.$refs?.authorizationList;
      const search = this.authorizationSearch.toLowerCase().trim();
      let visible = 0;
      let firstVisible = null;
      let selectedVisible = false;

      if (this.authorizationDecisions.length === 0) {
        this.visibleAuthorizationCount = 0;
        this.authorizationSelected = null;
        this.authorizationDetailOpen = false;

        return;
      }

      if (!list?.children) {
        this.visibleAuthorizationCount = this.authorizationDecisions.length;

        return;
      }

      [...list.children].forEach((item) => {
        const matches =
          (this.authorizationFilter === 'all' || item.dataset.ndbAuthorizationResult === this.authorizationFilter) &&
          (search === '' || item.dataset.ndbAuthorizationSearchValue?.includes(search));
        item.hidden = !matches;
        if (matches) {
          item.style?.removeProperty?.('display');
          const execution = Number(item.dataset.ndbAuthorizationExecution);
          firstVisible ??= execution;
          selectedVisible ||= execution === this.authorizationSelected;
          visible++;
        } else {
          item.style?.setProperty?.('display', 'none', 'important');
        }
      });

      this.visibleAuthorizationCount = visible;

      if (!selectedVisible) {
        this.authorizationSelected = firstVisible;
        this.authorizationDetailOpen = firstVisible !== null && this.authorizationDetailOpen;
        this.resetAuthorizationDetail();
      }
    },

    applyAuthorizationFilters() {
      this.applyAuthorizationView();
    },

    stopTimelinePagination() {
      this.timelinePaginationCleanup?.();
      this.timelinePaginationCleanup = null;
    },

    resetTimelinePagination() {
      this.stopTimelinePagination();
      this.timelinePaginationRequest += 1;
      this.timelineLoadingMore = false;
      this.timelinePaginationError = false;
    },

    observeTimelinePageEnd(element, wire = this.$wire) {
      this.stopTimelinePagination();

      const scrollOwner = this.$refs?.timelineList;
      if (!element?.isConnected || !scrollOwner) return;

      this.timelinePaginationCleanup =
        browser.observeNearEnd?.(element, scrollOwner, () => this.loadNextTimelinePage(wire)) ?? null;
    },

    loadNextTimelinePage(wire = this.$wire) {
      if (this.timelineLoadingMore || this.timelinePaginationError || this.selected !== 'timeline') {
        return Promise.resolve(false);
      }

      const island = wire?.$island;
      const scopedWire = typeof island === 'function' ? island.call(wire, 'section-details') : wire;
      const action = scopedWire?.loadMoreTimeline;

      if (typeof action !== 'function') {
        this.timelinePaginationError = true;

        return Promise.resolve(false);
      }

      const request = ++this.timelinePaginationRequest;
      const profileId = this.summary.id;
      let loaded = false;
      this.timelineLoadingMore = true;
      this.timelinePaginationError = false;

      return Promise.resolve(action.call(scopedWire))
        .then(() => {
          if (request !== this.timelinePaginationRequest || profileId !== this.summary.id) return false;

          loaded = true;

          return true;
        })
        .catch(() => {
          if (request === this.timelinePaginationRequest && profileId === this.summary.id) {
            this.timelinePaginationError = true;
          }

          return false;
        })
        .finally(() => {
          if (request !== this.timelinePaginationRequest || profileId !== this.summary.id) return;

          this.timelineLoadingMore = false;
          if (!loaded) return;

          this.stopTimelinePagination();
          this.$nextTick?.(() => {
            const sentinel = this.$root?.querySelector?.('[data-ndb-timeline-page-sentinel]');
            if (sentinel) this.observeTimelinePageEnd(sentinel, wire);
          });
        });
    },

    retryTimelinePage(wire = this.$wire) {
      this.timelinePaginationError = false;

      return this.loadNextTimelinePage(wire);
    },

    setTimelineFilter(filter) {
      if (!this.sectionKeys.includes(filter) && !['all', 'key'].includes(filter)) return;

      this.timelineFilter = filter;
      this.applyTimelineFilters();
    },

    applyTimelineFilters() {
      const list = this.$refs?.timelineList ?? this.$root?.querySelector?.('[x-ref="timelineList"]');

      if (!list?.querySelectorAll) {
        this.visibleTimelineCount = 0;

        return;
      }

      const search = this.timelineSearch.toLowerCase().trim();
      let visible = 0;

      [...list.querySelectorAll('[data-ndb-timeline-item]')].forEach((item) => {
        const matches =
          (this.timelineFilter === 'all' ||
            (this.timelineFilter === 'key' && item.dataset.ndbTimelineKey === 'true') ||
            item.dataset.ndbTimelineSection === this.timelineFilter) &&
          (search === '' || item.dataset.ndbTimelineSearchValue?.includes(search));
        item.hidden = !matches;
        if (matches) visible++;
      });

      this.visibleTimelineCount = visible;

      if (
        this.timelineSelected &&
        ![...list.querySelectorAll('[data-ndb-timeline-item]:not([hidden])')].some(
          (item) => item.dataset.ndbTimelineItem === this.timelineSelected.id,
        )
      ) {
        this.timelineSelected = null;
        this.timelineDetailOpen = false;
      }
    },

    selectTimelineItem(id) {
      const items = this.$refs?.timelineList?.querySelectorAll?.('[data-ndb-timeline-item]') ?? [];
      const item = [...items].find((candidate) => candidate.dataset.ndbTimelineItem === id);

      if (!item || item.hidden) return;

      this.timelineSelected = {
        id,
        section: item.dataset.ndbTimelineSection,
        sectionLabel: item.dataset.ndbTimelineSectionLabel,
        kind: item.dataset.ndbTimelineKind,
        label: item.dataset.ndbTimelineLabel,
        atMs: Number(item.dataset.ndbTimelineAt),
        atLabel: item.dataset.ndbTimelineAtLabel,
        startMs: item.dataset.ndbTimelineStart === '' ? null : Number(item.dataset.ndbTimelineStart),
        startLabel: item.dataset.ndbTimelineStartLabel || null,
        durationMs: item.dataset.ndbTimelineDuration === '' ? null : Number(item.dataset.ndbTimelineDuration),
        durationLabel: item.dataset.ndbTimelineDurationLabel || null,
        source: item.dataset.ndbTimelineSource || null,
      };
      this.timelineDetailOpen = true;
      this.$nextTick?.(() => {
        if (this.$refs?.content) this.$refs.content.scrollTop = 0;
        this.$refs?.timelineDetail?.focus?.({ preventScroll: true });
      });
    },

    closeTimelineDetail() {
      const id = this.timelineSelected?.id;
      this.timelineDetailOpen = false;
      this.$nextTick?.(() => {
        const focus = () => {
          const items = this.$refs?.timelineList?.querySelectorAll?.('[data-ndb-timeline-item]') ?? [];
          [...items].find((item) => item.dataset.ndbTimelineItem === id)?.focus?.({ preventScroll: true });
        };

        browser.afterPaint ? browser.afterPaint(focus) : focus();
      });
    },

    initializeEvents(groups) {
      this.eventGroups = Array.isArray(groups) ? groups : [];
      const firstApplicationEvent = this.eventGroups.find((event) => event.source === 'application');
      this.eventSource = firstApplicationEvent ? 'application' : 'all';
      this.eventSearch = '';
      this.eventDetailOpen = false;
      this.eventDetailTab = 'overview';
      this.eventDetailReturnFocus = null;
      this.eventSelected = firstApplicationEvent?.id ?? this.eventGroups[0]?.id ?? null;
      this.$nextTick?.(() => this.applyEventFilters());
    },

    setEventSource(source) {
      if (!['all', 'application', 'framework'].includes(source)) return;

      this.eventSource = source;
      this.eventDetailOpen = false;
      this.eventDetailTab = 'overview';
      this.eventDetailReturnFocus = null;
      this.eventSelected = null;
      this.applyEventFilters();
    },

    selectEvent(id, returnFocus = null) {
      if (!this.eventGroups.some((event) => event.id === id)) return;

      this.eventSelected = id;
      this.eventDetailOpen = true;
      this.eventDetailTab = 'overview';
      this.eventDetailReturnFocus = returnFocus;
      this.$nextTick?.(() => {
        if (browser.viewportWidth?.() < 1024) this.$refs?.eventDetail?.focus?.();
      });
    },

    closeEventDetail() {
      const returnFocus = this.eventDetailReturnFocus;
      this.eventDetailOpen = false;
      this.eventDetailReturnFocus = null;
      this.$nextTick?.(() => returnFocus?.isConnected && returnFocus.focus?.());
    },

    setEventDetailTab(tab) {
      if (!['overview', 'payload', 'source'].includes(tab)) return;

      this.eventDetailTab = tab;
      this.$nextTick?.(() => this.$refs?.eventDetail?.scrollTo?.({ top: 0, behavior: 'instant' }));
    },

    applyEventFilters() {
      const list = this.$refs?.eventList ?? this.$root?.querySelector?.('[x-ref="eventList"]');
      const search = this.eventSearch.toLowerCase().trim();
      let visibleEvents = 0;
      let visibleGroups = 0;
      let firstVisible = null;
      let selectedVisible = false;

      [...(list?.children ?? [])].forEach((item) => {
        const matches =
          (this.eventSource === 'all' || item.dataset.ndbEventSourceValue === this.eventSource) &&
          (search === '' || item.dataset.ndbEventSearchValue?.includes(search));
        item.hidden = !matches;

        if (matches) {
          item.style.removeProperty('display');
          const id = Number(item.dataset.ndbEventId);
          firstVisible ??= id;
          selectedVisible ||= id === this.eventSelected;
          visibleEvents += Number(item.dataset.ndbEventOccurrenceCount ?? 0);
          visibleGroups++;
        } else {
          item.style.setProperty('display', 'none', 'important');
        }
      });

      this.visibleEventCount = visibleEvents;
      this.visibleEventGroupCount = visibleGroups;

      if (!selectedVisible) {
        if (this.eventSelected !== firstVisible) this.eventDetailTab = 'overview';
        this.eventSelected = firstVisible;
      }
    },

    formatEventTime(value) {
      return formatDuration(value);
    },

    initializeLogs() {
      this.logLevel = 'all';
      this.logChannel = 'all';
      this.logSearch = '';
      this.logDetailSequence = null;
      this.logDetailOpen = false;
      this.$nextTick?.(() => this.applyLogFilters());
    },

    selectLogEntry(sequence) {
      const list = this.$refs?.logList ?? this.$root?.querySelector?.('[x-ref="logList"]');
      const entry = [...(list?.children ?? [])].find(
        (item) => Number(item.dataset.ndbLogFirstSequence) === Number(sequence),
      );
      if (!entry || entry.hidden) return;

      this.logDetailSequence = Number(sequence);
      this.logDetailOpen = true;
      this.$nextTick?.(() => {
        this.$refs?.logDetail?.scrollTo?.({ top: 0, behavior: 'instant' });
        if ((browser.viewportWidth?.() ?? 1024) < 1024) this.$refs?.logDetail?.focus?.({ preventScroll: true });
        browser.highlight?.();
      });
    },

    closeLogDetail() {
      const sequence = this.logDetailSequence;
      this.logDetailOpen = false;
      this.$nextTick?.(() => {
        this.$root?.querySelector?.(`[data-ndb-log-entry][data-ndb-log-first-sequence="${sequence}"]`)?.focus?.();
      });
    },

    setLogLevel(level) {
      const list = this.$refs?.logList ?? this.$root?.querySelector?.('[x-ref="logList"]');
      const available = [...(list?.children ?? [])].map((item) => item.dataset.ndbLogLevel);
      if (!['all', 'attention'].includes(level) && !available.includes(level)) return;

      this.logLevel = level;
      this.applyLogFilters();
    },

    setLogChannel(channel) {
      const list = this.$refs?.logList ?? this.$root?.querySelector?.('[x-ref="logList"]');
      const available = [...(list?.children ?? [])].map((item) => item.dataset.ndbLogChannel);
      if (channel !== 'all' && !available.includes(channel)) return;

      this.logChannel = channel;
      this.applyLogFilters();
    },

    applyLogFilters() {
      const list = this.$refs?.logList ?? this.$root?.querySelector?.('[x-ref="logList"]');
      const search = this.logSearch.toLowerCase().trim();
      let visibleRecords = 0;
      let visibleGroups = 0;
      let selectedVisible = false;

      [...(list?.children ?? [])].forEach((item) => {
        const matchesLevel =
          this.logLevel === 'all' ||
          item.dataset.ndbLogLevel === this.logLevel ||
          (this.logLevel === 'attention' && item.dataset.ndbLogAttention === 'true');
        const matches =
          matchesLevel &&
          (this.logChannel === 'all' || item.dataset.ndbLogChannel === this.logChannel) &&
          (search === '' || item.dataset.ndbLogSearchText?.includes(search));
        item.hidden = !matches;
        if (matches) {
          item.style?.removeProperty?.('display');
          visibleRecords += Math.max(1, Number(item.dataset.ndbLogRecordCount) || 1);
          visibleGroups++;
          selectedVisible ||= Number(item.dataset.ndbLogFirstSequence) === this.logDetailSequence;
        } else {
          item.style?.setProperty?.('display', 'none', 'important');
        }
      });

      this.visibleLogCount = visibleRecords;
      this.visibleLogGroupCount = visibleGroups;

      if (this.logDetailSequence !== null && !selectedVisible) {
        this.logDetailSequence = null;
        this.logDetailOpen = false;
      }
    },

    keepFocusWithin(event, container) {
      if (event.key !== 'Tab') return;

      const focusable = [
        ...(container?.querySelectorAll?.(
          'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])',
        ) ?? []),
      ].filter((element) => element.hidden !== true && (element.getClientRects?.().length ?? 1) > 0);

      if (focusable.length === 0) return;

      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      const active = browser.activeElement?.();

      if (event.shiftKey && (active === first || !container.contains(active))) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && (active === last || !container.contains(active))) {
        event.preventDefault();
        first.focus();
      }
    },

    toggleFavorite(key) {
      if (!this.sectionKeys.includes(key)) return;

      this.favorites = this.favorites.includes(key)
        ? this.favorites.filter((favorite) => favorite !== key)
        : [...this.favorites, key];
      this.persist();
    },

    moveFavorite(key, direction) {
      const index = this.favorites.indexOf(key);
      const target = index + direction;

      if (index < 0 || target < 0 || target >= this.favorites.length) return;

      const reordered = [...this.favorites];
      [reordered[index], reordered[target]] = [reordered[target], reordered[index]];
      this.favorites = reordered;
      this.persist();
    },

    startFavoriteDrag(key, event = null) {
      if (!this.favorites.includes(key)) return;

      this.favoriteDrag = key;
      event?.dataTransfer?.setData?.('text/plain', key);
      if (event?.dataTransfer) event.dataTransfer.effectAllowed = 'move';
      this.syncFavoriteDragVisuals();
    },

    hoverFavorite(key, after = false) {
      if (!this.favoriteDrag || this.favoriteDrag === key || !this.isFavorite(key)) return;

      this.favoriteDrop = key;
      this.favoriteDropAfter = after;
      this.syncFavoriteDragVisuals();
    },

    leaveFavorite(key) {
      if (this.favoriteDrop !== key) return;

      this.favoriteDrop = null;
      this.favoriteDropAfter = false;
      this.syncFavoriteDragVisuals();
    },

    dropFavorite(target, after = false) {
      const source = this.favoriteDrag;
      this.endFavoriteDrag();

      if (!source || source === target || !this.favorites.includes(target)) return;

      const reordered = this.favorites.filter((key) => key !== source);
      const targetIndex = reordered.indexOf(target);
      reordered.splice(targetIndex + (after ? 1 : 0), 0, source);
      this.favorites = reordered;
      this.persist();
    },

    endFavoriteDrag() {
      this.favoriteDrag = null;
      this.favoriteDrop = null;
      this.favoriteDropAfter = false;
      this.syncFavoriteDragVisuals();
    },

    syncFavoriteDragVisuals() {
      const rows = this.$root?.querySelectorAll?.('[data-ndb-section]') ?? [];

      rows.forEach((row) => {
        const key = row.dataset.ndbSection;
        const dragging = this.favoriteDrag === key;
        const dropBefore = this.favoriteDrop === key && !this.favoriteDropAfter;
        const dropAfter = this.favoriteDrop === key && this.favoriteDropAfter;

        row.dataset.ndbDragging = dragging ? 'true' : 'false';
        row.classList.toggle('ndb-favorite-dragging', dragging);
        row.querySelector('[data-ndb-favorite-drop-before]')?.toggleAttribute('hidden', !dropBefore);
        row.querySelector('[data-ndb-favorite-drop-after]')?.toggleAttribute('hidden', !dropAfter);
      });
    },

    toggleTheme() {
      this.setTheme(this.resolvedTheme === 'dark' ? 'light' : 'dark');
    },

    setTheme(theme) {
      if (!['system', 'light', 'dark'].includes(theme)) return;

      this.theme = theme;
      this.applyTheme();
      this.persist();
    },

    toggleThemeMenu(scope, returnFocus = null) {
      if (this.themeMenuScope === scope) {
        this.closeThemeMenu();

        return;
      }

      this.openThemeMenu(scope, returnFocus);
    },

    openThemeMenu(scope, returnFocus = null) {
      const compactMenu = scope === 'toolbar';
      const inspectorMenu = scope === 'header';

      if (
        !this.barVisible ||
        (!compactMenu && !inspectorMenu) ||
        (compactMenu && this.inspectorOpen) ||
        (inspectorMenu && !this.inspectorOpen)
      )
        return;

      this.mobileToolbarMenu = null;
      this.mobileToolbarReturnFocus = null;
      this.closeRequestPicker(false);
      this.themeMenuReturnFocus = returnFocus ?? browser.activeElement?.();
      this.themeMenuScope = scope;
      this.$nextTick?.(() => {
        const focus = () => {
          const menu = this.$root?.querySelector?.(`[data-ndb-theme-menu="${scope}"]`);
          const options = [...(menu?.querySelectorAll?.('[data-ndb-theme-option]') ?? [])];
          (options.find((option) => option.dataset.ndbThemeOption === this.theme) ?? options[0])?.focus?.();
        };
        browser.afterPaint ? browser.afterPaint(focus) : focus();
      });
    },

    closeThemeMenu(restoreFocus = true) {
      if (this.themeMenuScope === null) return;

      const returnFocus = this.themeMenuReturnFocus;
      this.themeMenuScope = null;
      this.themeMenuReturnFocus = null;

      if (restoreFocus)
        this.$nextTick?.(() => {
          const focus = () => returnFocus?.focus?.();
          browser.afterPaint ? browser.afterPaint(focus) : focus();
        });
    },

    moveThemeMenu(direction, menu) {
      const options = [...(menu?.querySelectorAll?.('[data-ndb-theme-option]') ?? [])];
      if (options.length === 0) return;

      const active = options.indexOf(browser.activeElement?.());
      const selected = options.findIndex((option) => option.dataset.ndbThemeOption === this.theme);
      const current = active >= 0 ? active : Math.max(0, selected);
      const next = (current + direction + options.length) % options.length;

      options[next]?.focus?.();
    },

    applyTheme() {
      this.resolvedTheme =
        this.theme === 'system'
          ? (this.colorScheme ?? browser.matchMedia?.('(prefers-color-scheme: dark)'))?.matches
            ? 'dark'
            : 'light'
          : this.theme;
    },

    toggleRequestPicker(scope, returnFocus = null) {
      if (this.requestPickerScope === scope) {
        this.closeRequestPicker();

        return;
      }

      this.openRequestPicker(scope, returnFocus);
    },

    openRequestPicker(scope, returnFocus = null) {
      const compactPicker = ['toolbar', 'corner'].includes(scope);
      const inspectorPicker = ['header-mobile', 'header'].includes(scope);

      if (
        !this.barVisible ||
        !this.hasOtherRequests ||
        (!compactPicker && !inspectorPicker) ||
        (compactPicker && this.inspectorOpen) ||
        (inspectorPicker && !this.inspectorOpen)
      )
        return;

      this.mobileToolbarMenu = null;
      this.mobileToolbarReturnFocus = null;
      this.closeThemeMenu(false);
      this.requestPickerReturnFocus = returnFocus ?? browser.activeElement?.();
      this.syncRequestPickerArrow(scope, this.requestPickerReturnFocus);
      this.requestPickerScope = scope;

      this.$nextTick?.(() => {
        const focus = () => {
          this.syncRequestPickerArrow(scope, this.requestPickerReturnFocus);
          const switcher =
            this.requestPickerReturnFocus?.closest?.('[data-ndb-request-switcher]') ??
            this.$root?.querySelector?.(`[data-ndb-request-switcher="${scope}"]`);
          const options = [...(switcher?.querySelectorAll?.('[data-ndb-request-option]') ?? [])];
          const selected = options.find((option) => option.dataset.ndbProfileId === this.summary.id);

          (selected ?? options[0])?.focus?.();
        };
        browser.afterPaint ? browser.afterPaint(focus) : focus();
      });
    },

    syncRequestPickerArrow(scope = this.requestPickerScope, trigger = this.requestPickerReturnFocus) {
      if (!['toolbar', 'corner', 'header-mobile', 'header'].includes(scope)) return;

      const switcher =
        trigger?.closest?.('[data-ndb-request-switcher]') ??
        this.$root?.querySelector?.(`[data-ndb-request-switcher="${scope}"]`);
      const pickerTrigger = switcher?.querySelector?.(`[data-ndb-request-picker-trigger="${scope}"]`) ?? trigger;
      const popover = switcher?.querySelector?.(`[data-ndb-request-popover="${scope}"]`);
      const switcherBox = switcher?.getBoundingClientRect?.();
      const triggerBox = pickerTrigger?.getBoundingClientRect?.();
      const popoverBox = popover?.getBoundingClientRect?.();

      if (!switcherBox || !triggerBox) return;

      const originLeft = popoverBox?.width > 0 ? popoverBox.left : switcherBox.left;
      const maximum = popoverBox?.width > 0 ? popoverBox.width - 16 : Number.POSITIVE_INFINITY;
      this.requestPickerArrowLeft = Math.max(
        0,
        Math.min(maximum, Math.round(triggerBox.left - originLeft + (triggerBox.width - 16) / 2)),
      );
    },

    closeRequestPicker(restoreFocus = true) {
      if (this.requestPickerScope === null) return;

      const returnFocus = this.requestPickerReturnFocus;
      this.requestPickerScope = null;
      this.requestPickerReturnFocus = null;

      if (restoreFocus)
        this.$nextTick?.(() => {
          const focus = () => returnFocus?.focus?.();
          browser.afterPaint ? browser.afterPaint(focus) : focus();
        });
    },

    moveRequestPicker(direction, listbox) {
      const options = [...(listbox?.querySelectorAll?.('[data-ndb-request-option]') ?? [])];
      if (options.length === 0) return;

      const active = options.indexOf(browser.activeElement?.());
      const selected = options.findIndex((option) => option.dataset.ndbProfileId === this.summary.id);
      const current = active >= 0 ? active : Math.max(0, selected);
      const next = (current + direction + options.length) % options.length;

      options[next]?.focus?.();
    },

    focusRequestPickerEdge(edge, listbox) {
      const options = [...(listbox?.querySelectorAll?.('[data-ndb-request-option]') ?? [])];
      if (options.length === 0) return;

      options[edge === 'end' ? options.length - 1 : 0]?.focus?.();
    },

    selectRequest(profileId) {
      if (!PROFILE_PATTERN.test(profileId ?? '')) return;

      const returnFocus = this.requestPickerReturnFocus;
      this.closeRequestPicker();
      if (profileId === this.summary.id) {
        this.openRequestSection(returnFocus);

        return;
      }
      if (this.requestSelectionPending === profileId) return;

      const action = this.$wire?.switchProfile;
      if (typeof action !== 'function') return;

      this.requestSelectionPending = profileId;
      Promise.resolve(action.call(this.$wire, profileId)).catch(() => {
        if (this.requestSelectionPending === profileId) this.requestSelectionPending = null;
      });
    },

    togglePalette() {
      this.paletteOpen ? this.closePalette() : this.openPalette();
    },

    toggleMobileToolbarMenu(menu, returnFocus = null) {
      if (this.mobileToolbarMenu === menu) {
        this.closeMobileToolbarMenu();

        return;
      }

      this.openMobileToolbarMenu(menu, returnFocus);
    },

    openMobileToolbarMenu(menu, returnFocus = null) {
      const compactMenu = menu === 'actions';
      const inspectorMenu = menu === 'header-actions';

      if (
        !this.barVisible ||
        (!compactMenu && !inspectorMenu) ||
        (compactMenu && this.inspectorOpen) ||
        (inspectorMenu && !this.inspectorOpen)
      )
        return;

      this.mobileToolbarMenu = menu;
      this.closeRequestPicker(false);
      this.closeThemeMenu(false);
      this.mobileToolbarReturnFocus = returnFocus ?? browser.activeElement?.();
      this.$nextTick?.(() => {
        const focus = () =>
          this.$root?.querySelector?.(`[data-ndb-mobile-toolbar-menu="${menu}"] [role="menuitem"]`)?.focus?.();
        browser.afterPaint ? browser.afterPaint(focus) : focus();
      });
    },

    openMobileSectionsFromToolbar() {
      const returnFocus = this.mobileToolbarReturnFocus;
      this.closeMobileToolbarMenu(false);
      this.openMobileSections(returnFocus);
    },

    closeMobileToolbarMenu(restoreFocus = true) {
      if (!this.mobileToolbarMenu) return;

      const returnFocus = this.mobileToolbarReturnFocus;
      this.mobileToolbarMenu = null;
      this.mobileToolbarReturnFocus = null;

      if (restoreFocus)
        this.$nextTick?.(() => {
          const focus = () => returnFocus?.focus?.();
          browser.afterPaint ? browser.afterPaint(focus) : focus();
        });
    },

    openPalette() {
      if (!this.barVisible) return;

      this.paletteReturnFocus = this.requestPickerScope
        ? this.requestPickerReturnFocus
        : this.themeMenuScope
          ? this.themeMenuReturnFocus
          : this.mobileToolbarMenu
            ? this.mobileToolbarReturnFocus
            : this.mobileSectionsOpen
              ? this.mobileSectionsReturnFocus
              : browser.activeElement?.();
      this.requestPickerScope = null;
      this.requestPickerReturnFocus = null;
      this.themeMenuScope = null;
      this.themeMenuReturnFocus = null;
      this.mobileToolbarMenu = null;
      this.mobileToolbarReturnFocus = null;
      this.mobileSectionsOpen = false;
      this.mobileSectionsReturnFocus = null;
      this.paletteOpen = true;
      this.syncHostLock();
      this.paletteSearch = '';
      this.paletteIndex = 0;
      this.paletteShowQuiet = false;
      this.$nextTick?.(() => {
        const focus = () => this.$refs?.paletteSearch?.focus();
        browser.afterPaint ? browser.afterPaint(focus) : focus();
      });
    },

    closePalette(restoreFocus = true) {
      const returnFocus = this.paletteReturnFocus;
      this.paletteOpen = false;
      this.paletteSearch = '';
      this.paletteIndex = 0;
      this.paletteShowQuiet = false;
      this.paletteReturnFocus = null;
      this.syncHostLock();

      if (restoreFocus)
        this.$nextTick?.(() => {
          const focus = () => returnFocus?.focus?.();
          browser.afterPaint ? browser.afterPaint(focus) : focus();
        });
    },

    movePalette(direction) {
      const count = this.filteredCommands.length;
      if (count === 0) return;

      this.paletteIndex = (this.paletteIndex + direction + count) % count;
    },

    commandIndex(id) {
      return this.filteredCommands.findIndex((command) => command.id === id);
    },

    runActiveCommand() {
      const command = this.filteredCommands[this.paletteIndex];
      if (command) this.runCommand(command.id);
    },

    runCommand(id) {
      const [kind, value] = id.split(':');

      if (kind === 'section') {
        const returnFocus = this.paletteReturnFocus;
        this.closePalette(false);
        this.openInspector(value, returnFocus);

        return;
      }

      if (kind === 'theme') this.setTheme(value);

      if (kind === 'toolbar') this.pinToolbar(value);

      if (kind === 'collectors' && value === 'show') {
        this.paletteShowQuiet = true;
        this.paletteIndex = 0;

        return;
      }

      this.closePalette();
    },

    handleShortcut(event) {
      if (!this.barVisible) return;
      if (event.defaultPrevented) return;

      if ((event.metaKey || event.ctrlKey) && event.shiftKey && event.key.toLowerCase() === 'p') {
        event.preventDefault();
        this.togglePalette();
      }

      if (event.key === 'Escape') {
        if (this.paletteOpen) this.closePalette();
        else if (this.themeMenuScope) this.closeThemeMenu();
        else if (this.requestPickerScope) this.closeRequestPicker();
        else if (this.mobileToolbarMenu) this.closeMobileToolbarMenu();
        else if (this.mobileSectionsOpen) this.closeMobileSections();
        else if (this.inspectorOpen) this.closeInspector();
      }
    },
  };
}

export { STORAGE_KEY };
