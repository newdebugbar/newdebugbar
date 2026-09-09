export const DEFAULT_SECTION = 'request';

/** Owns navigation shell behavior. */
export function createNavigation(context) {
  const { browser } = context;
  return {
    get sectionKeys() {
      return (this.summary.sections ?? []).map((section) => section.key);
    },

    get sectionsInOrder() {
      const allSections = this.summary.sections ?? [];
      const byKey = new Map(allSections.map((section) => [section.key, section]));
      const remaining = allSections
        .filter((section) => !this.sectionOrder.includes(section.key))
        .sort((left, right) =>
          left.label.localeCompare(right.label, undefined, {
            sensitivity: 'base',
          }),
        );

      return [...this.sectionOrder.map((key) => byKey.get(key)).filter(Boolean), ...remaining];
    },

    get orderedSections() {
      const sections = this.sectionsInOrder;
      const byKey = new Map(sections.map((section) => [section.key, section]));
      const favorites = this.favorites.map((key) => byKey.get(key)).filter(Boolean);

      return [...favorites, ...sections.filter((section) => !this.isFavorite(section.key))];
    },

    navigationSections(favorite) {
      return this.orderedSections.filter(
        (section) => this.isFavorite(section.key) === favorite && this.isSectionVisible(section),
      );
    },

    get selectedSection() {
      return (
        (this.summary.sections ?? []).find((section) => section.key === this.selected) ?? {
          key: DEFAULT_SECTION,
          label: 'Requests',
          description: '',
          layout: 'workspace',
          count: null,
        }
      );
    },

    isSectionActive(section) {
      return section?.active !== false;
    },

    isSectionVisible(section) {
      return this.isSectionActive(section) || this.isFavorite(section.key) || section.key === this.selected;
    },

    selectSection(section, filter = null, focusHeading = false) {
      const nextSection = this.sectionKeys.includes(section) ? section : DEFAULT_SECTION;
      const needsSection = this.inspectorOpen && (this.loadedSection !== nextSection || this.sectionError);
      this.selected = nextSection;
      if (filter !== null) this.pendingSectionIntent = { profileId: this.summary.id, section: nextSection, filter };
      else if (this.pendingSectionIntent?.section !== nextSection) this.pendingSectionIntent = null;
      this.syncSectionLifecycle();
      this.$nextTick?.(() => {
        this.syncSectionPanels();
        if (this.$refs?.content) this.$refs.content.scrollTop = 0;
        this.deliverSectionIntent();
        if (focusHeading) this.$refs?.sectionHeading?.focus?.();
        browser.highlight?.();
      });
      if (needsSection) this.requestSection(this.selected);
    },

    navigateToSection(section, filter = null) {
      const target = this.sectionKeys.includes(section) ? section : DEFAULT_SECTION;

      this.selectSection(target, filter, true);
    },

    openRequestSection(returnFocus = null) {
      this.closeRequestPicker(false);

      if (this.inspectorOpen) {
        this.selectSection('request');

        return;
      }

      this.openInspector('request', returnFocus);
    },
  };
}
