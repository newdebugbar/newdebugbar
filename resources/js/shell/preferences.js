import { TOOLBAR_PLACEMENTS } from './toolbar.js';

export const STORAGE_KEY = 'newdebugbar.preferences.v1';

/** Owns preferences shell behavior. */
export function createPreferences(context) {
  const { browser, summary } = context;
  return {
    themeMenuScope: null,
    themeMenuReturnFocus: null,
    theme: ['system', 'light', 'dark'].includes(summary.theme) ? summary.theme : 'system',
    resolvedTheme: 'light',
    favorites: [],
    inspectorOrder: [],
    colorScheme: null,
    colorSchemeListener: null,

    restore() {
      try {
        const saved = JSON.parse(browser.storage?.getItem(STORAGE_KEY) ?? '{}');

        if (['system', 'light', 'dark'].includes(saved.theme)) this.theme = saved.theme;
        if (TOOLBAR_PLACEMENTS.includes(saved.toolbarAnchor)) {
          this.toolbarPreferredPlacement = saved.toolbarAnchor;
          this.toolbarPlacement = saved.toolbarAnchor;
          this.toolbarDragTarget = saved.toolbarAnchor;
          this.toolbarDragOriginPlacement = saved.toolbarAnchor;
        }
        for (const preference of ['favorites', 'inspectorOrder']) {
          if (Array.isArray(saved[preference])) {
            this[preference] = [...new Set(saved[preference])].filter((key) =>
              this.inspectorKeys.includes(key),
            );
          }
        }
      } catch {
        // A broken preference must never break the host page.
      }
    },

    persist() {
      try {
        browser.storage?.setItem(
          STORAGE_KEY,
          JSON.stringify({
            theme: this.theme,
            toolbarAnchor: this.toolbarPreferredPlacement,
            favorites: this.favorites,
            inspectorOrder: this.inspectorOrder,
          }),
        );
      } catch {
        // Private browsing and strict storage policies are allowed.
      }
    },

    isFavorite(key) {
      return this.favorites.includes(key);
    },

    toggleFavorite(key) {
      if (!this.inspectorKeys.includes(key)) return;

      this.favorites = this.favorites.includes(key)
        ? this.favorites.filter((favorite) => favorite !== key)
        : [...this.favorites, key];
      this.persist();
      this.$nextTick?.(() => this.$root?.querySelector?.(`[data-ndb-toggle-favorite="${key}"]`)?.focus?.());
    },

    get inspectorSortConfig() {
      return {
        draggable: '[data-ndb-inspector]',
        dataIdAttr: 'data-ndb-inspector',
        ghostClass: 'ndb-inspector-dragging',
        chosenClass: 'ndb-inspector-chosen',
        dragClass: 'ndb-inspector-drag',
        fallbackClass: 'ndb-inspector-drag',
        delay: 180,
        delayOnTouchOnly: true,
        touchStartThreshold: 5,
        fallbackTolerance: 4,
        // Drag previews must not become Alpine components or alter the host body.
        onClone: ({ clone }) => clone.setAttribute('x-ignore', ''),
        onStart: ({ item }) => {
          item.parentElement.querySelectorAll('.ndb-inspector-drag').forEach((preview) => {
            if (preview !== item) preview.setAttribute('x-ignore', '');
          });
        },
        onEnd: null,
      };
    },

    moveInspector(key, direction) {
      const peers = this.navigationInspectors(this.isFavorite(key));
      const index = peers.findIndex((inspector) => inspector.key === key);
      if (index >= 0) this.sortInspector(key, index + direction);
    },

    sortInspector(key, position) {
      const peers = this.navigationInspectors(this.isFavorite(key));
      const index = peers.findIndex((inspector) => inspector.key === key);
      const target = peers[position]?.key;
      if (index < 0 || !target || target === key) return;

      const preference = this.isFavorite(key) ? 'favorites' : 'inspectorOrder';
      const order =
        preference === 'favorites'
          ? this.favorites
          : this.inspectorsInOrder.map((inspector) => inspector.key);
      const reordered = order.filter((inspector) => inspector !== key);
      reordered.splice(reordered.indexOf(target) + (position > index ? 1 : 0), 0, key);
      this[preference] = reordered;
      this.persist();
    },

    setTheme(theme) {
      if (!['system', 'light', 'dark'].includes(theme)) return;

      this.theme = theme;
      this.applyTheme();
      this.persist();
    },

    toggleThemeMenu(scope, returnFocus = null) {
      if (this.themeMenuScope === scope) {
        this.closeThemeMenu();

        return;
      }

      this.openThemeMenu(scope, returnFocus);
    },

    openThemeMenu(scope, returnFocus = null) {
      const compactMenu = scope === 'toolbar';
      const inspectorMenu = scope === 'header';

      if (
        !this.barVisible ||
        (!compactMenu && !inspectorMenu) ||
        (compactMenu && this.inspectorOpen) ||
        (inspectorMenu && !this.inspectorOpen)
      )
        return;

      this.mobileToolbarMenu = null;
      this.mobileToolbarReturnFocus = null;
      this.closeRequestPicker(false);
      this.themeMenuReturnFocus = returnFocus ?? browser.activeElement?.();
      this.themeMenuScope = scope;
      this.$nextTick?.(() => {
        const focus = () => {
          const menu = this.$root?.querySelector?.(`[data-ndb-theme-menu="${scope}"]`);
          const options = [...(menu?.querySelectorAll?.('[data-ndb-theme-option]') ?? [])];
          (options.find((option) => option.dataset.ndbThemeOption === this.theme) ?? options[0])?.focus?.();
        };
        browser.afterPaint ? browser.afterPaint(focus) : focus();
      });
    },

    closeThemeMenu(restoreFocus = true) {
      if (this.themeMenuScope === null) return;

      const returnFocus = this.themeMenuReturnFocus;
      this.themeMenuScope = null;
      this.themeMenuReturnFocus = null;

      if (restoreFocus)
        this.$nextTick?.(() => {
          const focus = () => returnFocus?.focus?.();
          browser.afterPaint ? browser.afterPaint(focus) : focus();
        });
    },

    moveThemeMenu(direction, menu) {
      const options = [...(menu?.querySelectorAll?.('[data-ndb-theme-option]') ?? [])];
      if (options.length === 0) return;

      const active = options.indexOf(browser.activeElement?.());
      const selected = options.findIndex((option) => option.dataset.ndbThemeOption === this.theme);
      const current = active >= 0 ? active : Math.max(0, selected);
      const next = (current + direction + options.length) % options.length;

      options[next]?.focus?.();
    },

    applyTheme() {
      this.resolvedTheme =
        this.theme === 'system'
          ? (this.colorScheme ?? browser.matchMedia?.('(prefers-color-scheme: dark)'))?.matches
            ? 'dark'
            : 'light'
          : this.theme;
    },
  };
}
