/** Owns livewire components inspector state and interactions. */
export function createComponents(context) {
  const { browser } = context;
  return {
    livewireSelectedComponentId: null,
    livewireCollapsedComponents: [],
    livewireKnownComponentParents: [],

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

    get selectedLivewireComponent() {
      return this.livewireComponents.find((component) => component.id === this.livewireSelectedComponentId) ?? null;
    },

    selectLivewireComponent(id) {
      if (!this.livewireComponents.some((component) => component.id === id)) return;
      this.closeLivewireDrafts();
      this.livewireSelectedComponentId = id;
      this.livewireDetailTab = 'properties';
      this.livewireDetailOpen = true;
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
  };
}
