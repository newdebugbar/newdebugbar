import { PROFILE_PATTERN } from './requests.js';

const ACTIVITY_POLL_LIMIT = 30;
const ACTIVITY_POLL_INTERVAL = 1000;

/** Owns activity-refresh shell behavior. */
export function createActivityRefresh(context) {
  const { browser, summary } = context;
  return {
    activityPollAttempts: 0,
    activityPollTimer: null,
    activityRefreshPending: false,

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

      const island = this.$wire?.$island;
      const scopedWire = typeof island === 'function' ? island.call(this.$wire, 'section-details') : this.$wire;
      const action = scopedWire?.refreshRelatedActivity;
      if (typeof action !== 'function') return;
      if (!reset && (!this.hasPendingActivity() || this.activityPollAttempts >= ACTIVITY_POLL_LIMIT)) return;

      const profileId = this.summary.id;
      this.activityPollAttempts++;
      this.activityRefreshPending = true;

      Promise.resolve(action.call(scopedWire))
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
  };
}
