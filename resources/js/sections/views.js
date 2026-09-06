import { readSectionPayload } from '../runtime.js';

/** Owns views inspector state and interactions. */
export function createViews(context) {
  const { browser, shell, profileId } = context;
  const summary = shell.summary;
  const instance = Symbol();
  return {
    profileId,
    initialized: false,
    destroyed: false,
    init() {
      shell.mountSection('views', profileId, this, instance);
    },
    destroy() {
      this.destroyed = true;
      this.deactivate?.();
      this.resetViewData();
      shell.unmountSection(instance);
    },
    refresh() {
      if (this.destroyed || profileId !== shell.summary.id) return;
      const payload = readSectionPayload(this.$root, '[data-ndb-view-payload]');
      if (payload !== null) this.initializeViews(payload);
    },

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

    initializeViews(groups) {
      this.viewGroups = Array.isArray(groups) ? groups : [];
      if (this.initialized) {
        if (this.viewRenderOrder !== null && !this.selectedViewRender) {
          this.viewRenderOrder = this.selectedViewGroup?.items?.[0]?.render_order ?? null;
          if (!this.selectedViewGroup) {
            this.viewSelected = null;
            this.viewDetailOpen = false;
          }
          this.resetViewData();
        }
        this.$nextTick?.(() => this.applyViewFilters());
        return;
      }
      this.initialized = true;
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
      if (this.destroyed || profileId !== shell.summary.id) return;
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
          if (
            this.destroyed ||
            profileId !== shell.summary.id ||
            request !== this.viewDataRequest ||
            renderOrder !== Number(this.viewRenderOrder)
          )
            return;

          this.viewData = data ?? {};
          this.viewDataLoaded = true;
          this.viewDataLoading = false;
          this.$nextTick?.(() => browser.highlight?.());
        })
        .catch(() => {
          if (this.destroyed || profileId !== shell.summary.id || request !== this.viewDataRequest) return;

          this.viewDataLoading = false;
          this.viewDataError = true;
        });
    },
  };
}
