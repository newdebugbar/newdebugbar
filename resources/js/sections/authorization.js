import { readSectionPayload } from '../runtime.js';

/** Owns authorization inspector state and interactions. */
export function createAuthorization(context) {
  const { browser, shell, profileId } = context;
  const summary = shell.summary;
  const instance = Symbol();
  return {
    profileId,
    initialized: false,
    destroyed: false,
    init() {
      shell.mountSection('authorization', profileId, this, instance);
    },
    destroy() {
      this.destroyed = true;
      this.deactivate?.();
      shell.unmountSection(instance);
    },
    refresh() {
      if (this.destroyed || profileId !== shell.summary.id) return;
      const payload = readSectionPayload(this.$root, '[data-ndb-authorization-payload]');
      if (payload !== null) this.initializeAuthorization(payload);
    },
    receiveIntent(filter) {
      this.setAuthorizationFilter(filter);
    },

    authorizationDecisions: [],
    authorizationFilter: 'all',
    authorizationSearch: '',
    authorizationSelected: null,
    authorizationDetailOpen: false,
    visibleAuthorizationCount: summary.section_counts?.authorization ?? 0,

    get selectedAuthorizationDecision() {
      return this.authorizationDecisions.find((decision) => decision.execution === this.authorizationSelected) ?? null;
    },

    initializeAuthorization(decisions) {
      this.authorizationDecisions = Array.isArray(decisions) ? decisions : [];
      if (this.initialized) {
        this.$nextTick?.(() => this.applyAuthorizationView());
        return;
      }
      this.initialized = true;
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
  };
}
