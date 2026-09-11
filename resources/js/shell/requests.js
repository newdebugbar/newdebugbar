import { DEFAULT_INSPECTOR } from './navigation.js';

export const PROFILE_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

/** Owns requests shell behavior. */
export function createRequests(context) {
  const { browser, summary } = context;
  const profileLimit = context.profileLimit;
  const requestLimit = Number.isInteger(profileLimit) && profileLimit > 0 ? profileLimit : 20;
  const requests = [];
  [summary, ...(Array.isArray(context.recentProfiles) ? context.recentProfiles : [])].forEach((profile) => {
    if (!PROFILE_PATTERN.test(profile?.id ?? '') || requests.some((request) => request.id === profile.id))
      return;
    requests.push(profile);
  });
  return {
    requestPickerScope: null,
    requestPickerReturnFocus: null,
    requestPickerArrowLeft: 0,
    recentProfiles: requests.slice(0, requestLimit),
    currentRequestId: summary.id ?? null,
    viewedProfileIds: PROFILE_PATTERN.test(summary.id ?? '') ? [summary.id] : [],
    profileLimit: requestLimit,
    pendingProfileIds: [],
    requestSelectionPending: null,
    relatedProfileSelection: null,

    get requestBadgeCount() {
      return this.unreadRequestCount > 9 ? '9+' : String(this.unreadRequestCount);
    },

    get unreadRequestCount() {
      return this.laterRequestProfiles.filter((profile) => this.requestIsUnread(profile)).length;
    },

    requestIsUnread(profile) {
      return profile.id !== this.currentRequestId && !this.viewedProfileIds.includes(profile.id);
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

      if (this.unreadRequestCount === 0) return 'Choose request';

      return `Choose request, ${this.unreadRequestCount} unread ${this.unreadRequestCount === 1 ? 'request' : 'requests'}`;
    },

    rememberProfile(summary) {
      if (!PROFILE_PATTERN.test(summary?.id ?? '')) return;

      const existing = this.recentProfiles.findIndex((profile) => profile.id === summary.id);

      if (existing === -1) {
        const current = this.recentProfiles.find((profile) => profile.id === this.currentRequestId);
        const later = [
          summary,
          ...this.recentProfiles.filter((profile) => profile.id !== this.currentRequestId),
        ].slice(0, Math.max(0, this.profileLimit - (current ? 1 : 0)));
        this.recentProfiles = current ? [...later, current] : later;
        this.viewedProfileIds = this.viewedProfileIds.filter((id) =>
          this.recentProfiles.some((profile) => profile.id === id),
        );

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

    openRelatedProfile(profileId, inspector = DEFAULT_INSPECTOR) {
      if (!PROFILE_PATTERN.test(profileId ?? '')) return;

      if (profileId === this.summary.id) {
        this.selectInspector(inspector);

        return;
      }

      const action = this.$wire?.switchProfile;
      if (typeof action !== 'function') return;

      this.relatedProfileSelection = { id: profileId, inspector };
      Promise.resolve(action.call(this.$wire, profileId)).catch(() => {
        if (this.relatedProfileSelection?.id === profileId) this.relatedProfileSelection = null;
      });
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

      this.unmountActiveInspector();
      this.pendingInspectorIntent = null;
      const selectedFromPicker = this.requestSelectionPending === summary.id;
      const selectedFromRelation = this.relatedProfileSelection?.id === summary.id;
      const requestedInspector = selectedFromPicker
        ? 'request'
        : selectedFromRelation
          ? this.relatedProfileSelection.inspector
          : this.selected;
      const selected = (summary.inspectors ?? []).some((inspector) => inspector.key === requestedInspector)
        ? requestedInspector
        : DEFAULT_INSPECTOR;
      this.cancelActivityRefresh(true);
      this.activityRefreshPending = false;
      this.inspectorRequestVersion++;
      this.summary = summary ?? {};
      this.requestSelectionPending = null;
      this.relatedProfileSelection = null;
      this.pendingProfileIds = this.pendingProfileIds.filter((id) => id !== summary.id);
      if (selectedFromPicker || selectedFromRelation) {
        this.rememberProfile(summary);
        this.viewedProfileIds = [...new Set([...this.viewedProfileIds, summary.id])];
      } else {
        this.currentRequestId = summary.id;
        this.recentProfiles = [summary];
        this.viewedProfileIds = [summary.id];
      }
      this.loadedInspector = null;
      this.requestedInspector = null;
      this.inspectorLoading = false;
      this.clearInspectorLoadingIndicator();
      this.inspectorTransitioning = false;
      this.inspectorError = false;
      this.selected = selected;
      if (this.inspectorOpen || selectedFromPicker || selectedFromRelation) {
        this.openInspector(selected);
      } else {
        this.$nextTick?.(() => this.syncInspectorPanels());
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
      const pickerTrigger =
        switcher?.querySelector?.(`[data-ndb-request-picker-trigger="${scope}"]`) ?? trigger;
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
        this.openRequestInspector(returnFocus);

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
  };
}
