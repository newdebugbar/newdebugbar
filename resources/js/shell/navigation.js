export const DEFAULT_INSPECTOR = 'request';

/** Owns navigation shell behavior. */
export function createNavigation(context) {
  const { browser } = context;
  return {
    get inspectorKeys() {
      return (this.summary.inspectors ?? []).map((inspector) => inspector.key);
    },

    get inspectorsInOrder() {
      const allInspectors = this.summary.inspectors ?? [];
      const byKey = new Map(allInspectors.map((inspector) => [inspector.key, inspector]));
      const remaining = allInspectors
        .filter((inspector) => !this.inspectorOrder.includes(inspector.key))
        .sort((left, right) =>
          left.label.localeCompare(right.label, undefined, {
            sensitivity: 'base',
          }),
        );

      return [...this.inspectorOrder.map((key) => byKey.get(key)).filter(Boolean), ...remaining];
    },

    get orderedInspectors() {
      const inspectors = this.inspectorsInOrder;
      const byKey = new Map(inspectors.map((inspector) => [inspector.key, inspector]));
      const favorites = this.favorites.map((key) => byKey.get(key)).filter(Boolean);

      return [...favorites, ...inspectors.filter((inspector) => !this.isFavorite(inspector.key))];
    },

    navigationInspectors(favorite) {
      return this.orderedInspectors.filter(
        (inspector) => this.isFavorite(inspector.key) === favorite && this.isInspectorVisible(inspector),
      );
    },

    get selectedInspector() {
      return (
        (this.summary.inspectors ?? []).find((inspector) => inspector.key === this.selected) ?? {
          key: DEFAULT_INSPECTOR,
          label: 'Requests',
          description: '',
          layout: 'workspace',
          count: null,
        }
      );
    },

    isInspectorActive(inspector) {
      return inspector?.active !== false;
    },

    isInspectorVisible(inspector) {
      return (
        this.isInspectorActive(inspector) || this.isFavorite(inspector.key) || inspector.key === this.selected
      );
    },

    selectInspector(inspector, filter = null, focusHeading = false) {
      const nextInspector = this.inspectorKeys.includes(inspector) ? inspector : DEFAULT_INSPECTOR;
      const needsInspector =
        this.inspectorOpen && (this.loadedInspector !== nextInspector || this.inspectorError);
      this.selected = nextInspector;
      if (filter !== null)
        this.pendingInspectorIntent = { profileId: this.summary.id, inspector: nextInspector, filter };
      else if (this.pendingInspectorIntent?.inspector !== nextInspector) this.pendingInspectorIntent = null;
      this.syncInspectorLifecycle();
      this.$nextTick?.(() => {
        this.syncInspectorPanels();
        if (this.$refs?.content) this.$refs.content.scrollTop = 0;
        this.deliverInspectorIntent();
        if (focusHeading) this.$refs?.inspectorHeading?.focus?.();
        browser.highlight?.();
      });
      if (needsInspector) this.requestInspector(this.selected);
    },

    navigateToInspector(inspector, filter = null) {
      const target = this.inspectorKeys.includes(inspector) ? inspector : DEFAULT_INSPECTOR;

      this.selectInspector(target, filter, true);
    },

    openRequestInspector(returnFocus = null) {
      this.closeRequestPicker(false);

      if (this.inspectorOpen) {
        this.selectInspector('request');

        return;
      }

      this.openInspector('request', returnFocus);
    },
  };
}
