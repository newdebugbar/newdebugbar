/** Owns palette shell behavior. */
export function createPalette(context) {
  const { browser, summary } = context;
  return {
    paletteOpen: false,
    paletteSearch: '',
    paletteIndex: 0,
    paletteShowQuiet: false,
    paletteReturnFocus: null,

    get allCommands() {
      const sections = (this.summary.sections ?? []).map((section) => ({
        id: `section:${section.key}`,
        label: `Go to ${section.label}`,
        hint: section.active === false ? 'Other collector' : section.attention ? 'Needs attention' : 'Active section',
        priority: section.attention ? 0 : section.active === false ? 2 : 1,
      }));

      return [
        ...sections.sort((left, right) => left.priority - right.priority || left.label.localeCompare(right.label)),
        { id: 'theme:system', label: 'Use system theme', hint: 'Theme' },
        { id: 'theme:light', label: 'Use light theme', hint: 'Theme' },
        { id: 'theme:dark', label: 'Use dark theme', hint: 'Theme' },
        { id: 'toolbar:top', label: 'Pin to top', hint: 'Toolbar' },
        { id: 'toolbar:bottom', label: 'Pin to bottom', hint: 'Toolbar' },
        { id: 'toolbar:top-left', label: 'Pin to top left', hint: 'Toolbar' },
        { id: 'toolbar:top-right', label: 'Pin to top right', hint: 'Toolbar' },
        {
          id: 'toolbar:bottom-left',
          label: 'Pin to bottom left',
          hint: 'Toolbar',
        },
        {
          id: 'toolbar:bottom-right',
          label: 'Pin to bottom right',
          hint: 'Toolbar',
        },
      ];
    },

    get hiddenCommandCount() {
      return this.allCommands.filter((command) => command.hint === 'Other collector').length;
    },

    get filteredCommands() {
      const words = this.paletteSearch.toLowerCase().trim().split(/\s+/).filter(Boolean);

      if (words.length === 0 && !this.paletteShowQuiet) {
        const active = this.allCommands.filter((command) => command.hint !== 'Other collector');

        return this.hiddenCommandCount > 0
          ? [
              ...active,
              {
                id: 'collectors:show',
                label: 'Show other collectors',
                hint: `${this.hiddenCommandCount} hidden`,
              },
            ]
          : active;
      }

      if (words.length === 0) return this.allCommands;

      return this.allCommands.filter((command) => {
        const value = `${command.label} ${command.hint}`.toLowerCase();
        return words.every((word) => value.includes(word));
      });
    },

    togglePalette() {
      this.paletteOpen ? this.closePalette() : this.openPalette();
    },

    openPalette() {
      if (!this.barVisible) return;

      this.paletteReturnFocus = this.requestPickerScope
        ? this.requestPickerReturnFocus
        : this.themeMenuScope
          ? this.themeMenuReturnFocus
          : this.mobileToolbarMenu
            ? this.mobileToolbarReturnFocus
            : browser.activeElement?.();
      this.requestPickerScope = null;
      this.requestPickerReturnFocus = null;
      this.themeMenuScope = null;
      this.themeMenuReturnFocus = null;
      this.mobileToolbarMenu = null;
      this.mobileToolbarReturnFocus = null;
      this.paletteOpen = true;
      this.syncHostLock();
      this.paletteSearch = '';
      this.paletteIndex = 0;
      this.paletteShowQuiet = false;
      this.$nextTick?.(() => {
        const focus = () => this.$refs?.paletteSearch?.focus();
        browser.afterPaint ? browser.afterPaint(focus) : focus();
      });
    },

    closePalette(restoreFocus = true) {
      const returnFocus = this.paletteReturnFocus;
      this.paletteOpen = false;
      this.paletteSearch = '';
      this.paletteIndex = 0;
      this.paletteShowQuiet = false;
      this.paletteReturnFocus = null;
      this.syncHostLock();

      if (restoreFocus)
        this.$nextTick?.(() => {
          const focus = () => returnFocus?.focus?.();
          browser.afterPaint ? browser.afterPaint(focus) : focus();
        });
    },

    movePalette(direction) {
      const count = this.filteredCommands.length;
      if (count === 0) return;

      this.paletteIndex = (this.paletteIndex + direction + count) % count;
    },

    commandIndex(id) {
      return this.filteredCommands.findIndex((command) => command.id === id);
    },

    runActiveCommand() {
      const command = this.filteredCommands[this.paletteIndex];
      if (command) this.runCommand(command.id);
    },

    runCommand(id) {
      const [kind, value] = id.split(':');

      if (kind === 'section') {
        const returnFocus = this.paletteReturnFocus;
        this.closePalette(false);
        this.openInspector(value, returnFocus);

        return;
      }

      if (kind === 'theme') this.setTheme(value);

      if (kind === 'toolbar') {
        this.pinToolbar(value);
        this.closePalette(!this.inspectorOpen);
        this.closeInspector();

        return;
      }

      if (kind === 'collectors' && value === 'show') {
        this.paletteShowQuiet = true;
        this.paletteIndex = 0;

        return;
      }

      this.closePalette();
    },

    handleShortcut(event) {
      if (!this.barVisible) return;
      if (event.defaultPrevented) return;

      if ((event.metaKey || event.ctrlKey) && event.shiftKey && event.key.toLowerCase() === 'p') {
        event.preventDefault();
        this.togglePalette();
      }

      if (event.key === 'Escape') {
        if (this.paletteOpen) this.closePalette();
        else if (this.themeMenuScope) this.closeThemeMenu();
        else if (this.requestPickerScope) this.closeRequestPicker();
        else if (this.mobileToolbarMenu) this.closeMobileToolbarMenu();
        else if (this.inspectorOpen) this.closeInspector();
      }
    },
  };
}
