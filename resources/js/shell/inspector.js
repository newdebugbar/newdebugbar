import { DEFAULT_SECTION } from './navigation.js';
import { formatDuration } from '../duration.js';
import { createCopyControl } from './copy.js';

/** Owns inspector shell behavior. */
export function createInspector(context) {
  const { browser, summary } = context;
  return {
    pendingSectionIntent: null,
    get inspector() {
      return context.shell ?? this;
    },
    mountSection(section, profileId, controller, instance) {
      this.unmountActiveSection();
      context.section = { section, profileId, controller, instance, active: false };
      this.syncSectionLifecycle();
      this.$nextTick?.(() => this.refreshSection());
    },
    unmountSection(instance) {
      if (context.section?.instance === instance) this.unmountActiveSection();
    },
    unmountActiveSection() {
      context.section?.controller.deactivate?.();
      context.section = null;
    },
    syncSectionLifecycle() {
      const current = context.section;
      if (!current) return;
      const active =
        this.barVisible &&
        this.inspectorOpen &&
        current.section === this.selected &&
        current.profileId === this.summary.id;
      if (active === current.active) return;
      current.active = active;
      if (active) current.controller.activate?.();
      else current.controller.deactivate?.();
    },
    refreshSection() {
      const current = context.section;
      if (!current || current.profileId !== this.summary.id) return;
      current.controller.refresh?.();
      this.syncSectionLifecycle();
      this.deliverSectionIntent();
    },
    deliverSectionIntent() {
      const intent = this.pendingSectionIntent;
      const current = context.section;
      if (
        !intent ||
        !current ||
        !current.controller.initialized ||
        intent.profileId !== current.profileId ||
        intent.section !== current.section
      )
        return;
      this.pendingSectionIntent = null;
      current.controller.receiveIntent?.(intent.filter);
    },

    formatDuration,

    barVisible: true,
    inspectorOpen: false,
    inspectorReturnFocus: null,
    loadedSection: null,
    requestedSection: null,
    sectionLoading: false,
    sectionLoadingIndicator: false,
    sectionLoadingTimer: null,
    sectionTransitioning: false,
    sectionError: false,
    sectionRequestVersion: 0,
    selected: DEFAULT_SECTION,

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
        this.syncSectionPanels();
        this.syncHostLock();
        this.syncToolbarPlacement();
        this.stopToolbarPlacementWatch =
          browser.watchHostDialogs?.(this.$root, () => this.syncToolbarPlacement()) ?? null;
      });
      this.colorScheme?.addEventListener?.('change', this.colorSchemeListener);
    },

    destroy() {
      this.unmountActiveSection();
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
      browser.cancelSchedule?.(this.sectionLoadingTimer);
      this.toolbarSnapTimer = null;
      this.toolbarClickTimer = null;
      this.activityPollTimer = null;
      this.sectionLoadingTimer = null;
      this.activityRefreshPending = false;
      browser.unlockHost?.(this.$root);
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
          if (this.loadedSection !== target) this.receiveSection(target, profileId);
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

    receiveSection(section, profileId) {
      if (profileId !== this.summary.id || !this.sectionKeys.includes(section) || section !== this.selected) return;

      this.loadedSection = section;
      this.requestedSection = null;
      this.sectionLoading = false;
      this.sectionError = false;
      this.clearSectionLoadingIndicator();
      this.$nextTick?.(() => {
        this.sectionTransitioning = false;
        this.syncSectionPanels();
        this.refreshSection();
        this.syncHostLock();
        browser.highlight?.();
      });
    },

    closeInspector() {
      if (!this.inspectorOpen) return;

      const returnFocus = this.inspectorReturnFocus;
      this.inspectorOpen = false;
      this.syncSectionLifecycle();
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
      this.barVisible = false;
      this.inspectorOpen = false;
      this.syncSectionLifecycle();
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
