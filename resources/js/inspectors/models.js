/** Owns models inspector state and interactions. */
export function createModels(context) {
  const { browser } = context;
  return {
    refresh() {
      this.initializeModels(this.$root?.querySelectorAll?.('[data-ndb-model-group]').length ?? 0);
    },

    modelGroupCount: 0,
    modelSearch: '',
    modelSort: 'capture',
    modelSortDirection: 'asc',
    visibleModelCount: 0,
    modelSelected: null,
    modelDetailOpen: false,
    modelDetailTab: 'records',
    modelListScrollTop: 0,

    initializeModels(count) {
      const normalized = Number(count);

      this.modelGroupCount = Number.isInteger(normalized) && normalized > 0 ? normalized : 0;
      if (this.initialized) {
        this.$nextTick?.(() => this.applyModelView());
        return;
      }
      this.initialized = true;
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
      const captureComparison =
        Number(left.dataset.ndbModelIndex ?? 0) - Number(right.dataset.ndbModelIndex ?? 0);

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
  };
}
