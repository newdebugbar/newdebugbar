import { DEFAULT_INSPECTOR } from './navigation.js';
import { formatDuration } from '../duration.js';
import { createCopyControl } from './copy.js';

/** Owns inspector shell behavior. */
export function createInspectorShell(context) {
  const { browser, summary } = context;
  return {
    pendingInspectorIntent: null,
    get inspector() {
      return context.shell ?? this;
    },
    mountInspector(inspector, profileId, controller, instance) {
      this.unmountActiveInspector();
      context.inspector = { inspector, profileId, controller, instance, active: false };
      this.syncInspectorLifecycle();
      this.$nextTick?.(() => this.refreshInspector());
    },
    unmountInspector(instance) {
      if (context.inspector?.instance === instance) this.unmountActiveInspector();
    },
    unmountActiveInspector() {
      context.inspector?.controller.deactivate?.();
      context.inspector = null;
    },
    syncInspectorLifecycle() {
      const current = context.inspector;
      if (!current) return;
      const active =
        this.barVisible &&
        this.inspectorOpen &&
        current.inspector === this.selected &&
        current.profileId === this.summary.id;
      if (active === current.active) return;
      current.active = active;
      if (active) current.controller.activate?.();
      else current.controller.deactivate?.();
    },
    refreshInspector() {
      const current = context.inspector;
      if (!current || current.profileId !== this.summary.id) return;
      current.controller.refresh?.();
      this.syncInspectorLifecycle();
      this.deliverInspectorIntent();
    },
    deliverInspectorIntent() {
      const intent = this.pendingInspectorIntent;
      const current = context.inspector;
      if (
        !intent ||
        !current ||
        !current.controller.initialized ||
        intent.profileId !== current.profileId ||
        intent.inspector !== current.inspector
      )
        return;
      this.pendingInspectorIntent = null;
      current.controller.receiveIntent?.(intent.filter);
    },

    formatDuration,

    barVisible: true,
    inspectorOpen: false,
    inspectorReturnFocus: null,
    loadedInspector: null,
    requestedInspector: null,
    inspectorLoading: false,
    inspectorLoadingIndicator: false,
    inspectorLoadingTimer: null,
    inspectorTransitioning: false,
    inspectorError: false,
    inspectorRequestVersion: 0,
    selected: DEFAULT_INSPECTOR,

    summary,

    init() {
      context.shell = this;
      this.restore();
      this.colorScheme = browser.matchMedia?.('(prefers-color-scheme: dark)') ?? null;
      this.colorSchemeListener = () => {
        if (this.theme === 'system') this.applyTheme();
      };
      this.applyTheme();
      this.$nextTick?.(() => {
        this.syncInspectorPanels();
        this.syncHostLock();
        this.syncToolbarPlacement();
        this.stopToolbarPlacementWatch =
          browser.watchHostDialogs?.(this.$root, () => this.syncToolbarPlacement()) ?? null;
      });
      this.colorScheme?.addEventListener?.('change', this.colorSchemeListener);
    },

    destroy() {
      this.unmountActiveInspector();
      this.colorScheme?.removeEventListener?.('change', this.colorSchemeListener);
      this.colorScheme = null;
      this.colorSchemeListener = null;
      this.stopToolbarPlacementWatch?.();
      this.stopToolbarPlacementWatch = null;
      this.requestPickerScope = null;
      this.requestPickerReturnFocus = null;
      this.toolbarSnapVersion += 1;
      browser.cancelSchedule?.(this.toolbarSnapTimer);
      browser.cancelSchedule?.(this.toolbarClickTimer);
      browser.cancelSchedule?.(this.activityPollTimer);
      browser.cancelSchedule?.(this.inspectorLoadingTimer);
      this.toolbarSnapTimer = null;
      this.toolbarClickTimer = null;
      this.activityPollTimer = null;
      this.inspectorLoadingTimer = null;
      this.activityRefreshPending = false;
      browser.unlockHost?.(this.$root);
    },

    syncInspectorPanels() {
      const panels = this.$root?.querySelectorAll?.('[data-ndb-inspector-panel]') ?? [];
      const visibleInspector = this.inspectorLoading ? this.loadedInspector : this.selected;

      panels.forEach((panel) => {
        panel.hidden = panel.dataset.ndbInspectorPanel !== visibleInspector;
      });
    },

    clearInspectorLoadingIndicator() {
      browser.cancelSchedule?.(this.inspectorLoadingTimer);
      this.inspectorLoadingTimer = null;
      this.inspectorLoadingIndicator = false;
    },

    syncInspectorHeading() {
      if (this.$refs?.inspectorHeading) {
        this.$refs.inspectorHeading.textContent = this.selectedInspector.label;
      }
      if (this.$refs?.inspectorDescription) {
        this.$refs.inspectorDescription.textContent = this.selectedInspector.description ?? '';
      }
    },

    syncHostLock() {
      if (this.barVisible && (this.inspectorOpen || this.paletteOpen)) browser.lockHost?.(this.$root);
      else browser.unlockHost?.(this.$root);
    },

    openInspector(inspector = this.selected, returnFocus = null) {
      if (!this.barVisible) return;

      if (!this.inspectorOpen) {
        this.inspectorReturnFocus =
          returnFocus ??
          (this.mobileToolbarMenu ? this.mobileToolbarReturnFocus : null) ??
          browser.activeElement?.();
      }

      this.mobileToolbarMenu = null;
      this.mobileToolbarReturnFocus = null;
      this.closeThemeMenu(false);
      this.closeRequestPicker(false);
      this.inspectorOpen = true;
      this.selectInspector(inspector);
      this.scheduleActivityRefresh(true);
      this.syncHostLock();
      this.$nextTick?.(() => {
        const focus = () => {
          const selector =
            browser.viewportWidth?.() < 640
              ? '[data-ndb-inspector-heading]'
              : '[data-ndb-window-controls="expanded"] [data-ndb-window-action="shrink"]';
          this.$root?.querySelector?.(selector)?.focus?.();
        };
        browser.afterPaint ? browser.afterPaint(focus) : focus();
      });
    },

    requestInspector(inspector = this.selected, force = false) {
      const target = this.inspectorKeys.includes(inspector) ? inspector : DEFAULT_INSPECTOR;
      if (!force && this.loadedInspector === target) return;
      if (!force && this.inspectorLoading && this.requestedInspector === target) return;

      const island = this.$wire?.$island;
      const scopedWire =
        typeof island === 'function' ? island.call(this.$wire, 'inspector-details') : this.$wire;
      const action = scopedWire?.loadInspector;
      const profileId = this.summary.id;
      const requestVersion = ++this.inspectorRequestVersion;
      this.requestedInspector = target;
      this.inspectorLoading = true;
      this.inspectorTransitioning = true;
      this.inspectorError = false;
      this.clearInspectorLoadingIndicator();
      this.inspectorLoadingTimer =
        browser.schedule?.(() => {
          this.inspectorLoadingTimer = null;

          if (
            requestVersion === this.inspectorRequestVersion &&
            profileId === this.summary.id &&
            this.requestedInspector === target &&
            this.inspectorLoading
          ) {
            this.inspectorLoadingIndicator = true;
          }
        }, 200) ?? null;
      this.syncInspectorPanels();

      if (typeof action !== 'function') {
        this.inspectorLoading = false;
        this.inspectorTransitioning = false;
        this.inspectorError = true;
        this.clearInspectorLoadingIndicator();
        this.syncInspectorPanels();

        return;
      }

      Promise.resolve(action.call(scopedWire, target))
        .then(() => {
          if (requestVersion !== this.inspectorRequestVersion || profileId !== this.summary.id) return;
          if (this.loadedInspector !== target) this.receiveInspector(target, profileId);
        })
        .catch(() => {
          if (requestVersion !== this.inspectorRequestVersion || profileId !== this.summary.id) return;

          this.requestedInspector = null;
          this.inspectorLoading = false;
          this.inspectorTransitioning = false;
          this.inspectorError = true;
          this.clearInspectorLoadingIndicator();
          this.syncInspectorPanels();
        });
    },

    receiveInspector(inspector, profileId) {
      if (
        profileId !== this.summary.id ||
        !this.inspectorKeys.includes(inspector) ||
        inspector !== this.selected
      )
        return;

      this.loadedInspector = inspector;
      this.requestedInspector = null;
      this.inspectorLoading = false;
      this.inspectorError = false;
      this.clearInspectorLoadingIndicator();
      this.$nextTick?.(() => {
        this.inspectorTransitioning = false;
        this.syncInspectorPanels();
        this.refreshInspector();
        this.syncHostLock();
        browser.highlight?.();
      });
    },

    closeInspector() {
      if (!this.inspectorOpen) return;

      const returnFocus = this.inspectorReturnFocus;
      this.inspectorOpen = false;
      this.syncInspectorLifecycle();
      this.cancelActivityRefresh();
      this.inspectorReturnFocus = null;
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
      this.barVisible = false;
      this.inspectorOpen = false;
      this.syncInspectorLifecycle();
      this.cancelActivityRefresh();
      this.inspectorReturnFocus = null;
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

    copyControl() {
      return createCopyControl((value) => this.copyText(value), browser);
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
  };
}
