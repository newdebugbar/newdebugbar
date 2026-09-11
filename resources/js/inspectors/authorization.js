import { filterList } from './list.js';
import { readInspectorPayload } from '../runtime.js';

/** Owns authorization inspector state and interactions. */
export function createAuthorization(context) {
  const { browser, shell } = context;
  const summary = shell.summary;
  return {
    refresh() {
      const payload = readInspectorPayload(this.$root, '[data-ndb-authorization-payload]');
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
    visibleAuthorizationCount: summary.inspector_counts?.authorization ?? 0,

    get selectedAuthorizationDecision() {
      return (
        this.authorizationDecisions.find((decision) => decision.execution === this.authorizationSelected) ??
        null
      );
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

      const { visible, firstVisible, selectedVisible } = filterList(list.children, {
        selected: this.authorizationSelected,
        key: (item) => Number(item.dataset.ndbAuthorizationExecution),
        matches: (item) => {
          return (
            (this.authorizationFilter === 'all' ||
              item.dataset.ndbAuthorizationResult === this.authorizationFilter) &&
            (search === '' || item.dataset.ndbAuthorizationSearchValue?.includes(search))
          );
        },
      });

      this.visibleAuthorizationCount = visible;

      if (!selectedVisible) {
        this.authorizationSelected = firstVisible;
        this.authorizationDetailOpen = firstVisible !== null && this.authorizationDetailOpen;
        this.resetAuthorizationDetail();
      }
    },
  };
}
