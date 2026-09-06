export const DEFAULT_SECTION = 'request';

/** Owns navigation shell behavior. */
export function createNavigation(context) {
  const { browser, summary } = context;
  return {
    mobileSectionsOpen: false,
    mobileSectionsReturnFocus: null,

    get sectionKeys() {
      return (this.summary.sections ?? []).map((section) => section.key);
    },

    get orderedSections() {
      const allSections = this.summary.sections ?? [];
      const byKey = new Map(allSections.map((section) => [section.key, section]));
      const favorites = this.favorites.map((key) => byKey.get(key)).filter(Boolean);
      const sections = allSections
        .filter((section) => !this.isFavorite(section.key))
        .sort((left, right) =>
          left.label.localeCompare(right.label, undefined, {
            sensitivity: 'base',
          }),
        );

      return [...favorites, ...sections];
    },

    get firstVisibleNonFavoriteKey() {
      return (
        this.orderedSections.find((section) => !this.isFavorite(section.key) && this.isSectionVisible(section))?.key ??
        null
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
      const focusContentHeading = focusHeading || this.mobileSectionsOpen;
      const nextSection = this.sectionKeys.includes(section) ? section : DEFAULT_SECTION;
      const needsSection = this.inspectorOpen && (this.loadedSection !== nextSection || this.sectionError);
      this.selected = nextSection;
      if (filter !== null) this.pendingSectionIntent = { profileId: this.summary.id, section: nextSection, filter };
      else if (this.pendingSectionIntent?.section !== nextSection) this.pendingSectionIntent = null;
      this.mobileSectionsOpen = false;
      this.mobileSectionsReturnFocus = null;
      this.syncSectionLifecycle();
      this.$nextTick?.(() => {
        this.syncSectionPanels();
        if (this.$refs?.content) this.$refs.content.scrollTop = 0;
        this.deliverSectionIntent();
        if (focusContentHeading) this.$refs?.sectionHeading?.focus?.();
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

    openMobileSections(returnFocus = null) {
      if (this.mobileSectionsOpen) return;

      this.closeRequestPicker(false);
      this.mobileSectionsReturnFocus = returnFocus ?? browser.activeElement?.();
      this.mobileSectionsOpen = true;
      this.$nextTick?.(() => {
        const navigation = this.$refs?.mobileSectionsNav;
        const selectedSection = navigation?.querySelector?.('[data-ndb-select-section][aria-current="page"]');
        const firstSection = navigation?.querySelector?.('[data-ndb-select-section]');

        (selectedSection ?? firstSection)?.focus?.();
      });
    },

    toggleMobileSections() {
      this.mobileSectionsOpen ? this.closeMobileSections() : this.openMobileSections();
    },

    closeMobileSections(restoreFocus = true) {
      const returnFocus = this.mobileSectionsReturnFocus;
      this.mobileSectionsOpen = false;
      this.mobileSectionsReturnFocus = null;

      if (restoreFocus) this.$nextTick?.(() => returnFocus?.focus?.());
    },
  };
}
