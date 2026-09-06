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
    favoriteDrag: null,
    favoriteDrop: null,
    favoriteDropAfter: false,
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
        if (Array.isArray(saved.favorites)) {
          const allowed = this.sectionKeys;
          this.favorites = [...new Set(saved.favorites)].filter((key) => allowed.includes(key));
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
      if (!this.sectionKeys.includes(key)) return;

      this.favorites = this.favorites.includes(key)
        ? this.favorites.filter((favorite) => favorite !== key)
        : [...this.favorites, key];
      this.persist();
    },

    moveFavorite(key, direction) {
      const index = this.favorites.indexOf(key);
      const target = index + direction;

      if (index < 0 || target < 0 || target >= this.favorites.length) return;

      const reordered = [...this.favorites];
      [reordered[index], reordered[target]] = [reordered[target], reordered[index]];
      this.favorites = reordered;
      this.persist();
    },

    startFavoriteDrag(key, event = null) {
      if (!this.favorites.includes(key)) return;

      this.favoriteDrag = key;
      event?.dataTransfer?.setData?.('text/plain', key);
      if (event?.dataTransfer) event.dataTransfer.effectAllowed = 'move';
      this.syncFavoriteDragVisuals();
    },

    hoverFavorite(key, after = false) {
      if (!this.favoriteDrag || this.favoriteDrag === key || !this.isFavorite(key)) return;

      this.favoriteDrop = key;
      this.favoriteDropAfter = after;
      this.syncFavoriteDragVisuals();
    },

    leaveFavorite(key) {
      if (this.favoriteDrop !== key) return;

      this.favoriteDrop = null;
      this.favoriteDropAfter = false;
      this.syncFavoriteDragVisuals();
    },

    dropFavorite(target, after = false) {
      const source = this.favoriteDrag;
      this.endFavoriteDrag();

      if (!source || source === target || !this.favorites.includes(target)) return;

      const reordered = this.favorites.filter((key) => key !== source);
      const targetIndex = reordered.indexOf(target);
      reordered.splice(targetIndex + (after ? 1 : 0), 0, source);
      this.favorites = reordered;
      this.persist();
    },

    endFavoriteDrag() {
      this.favoriteDrag = null;
      this.favoriteDrop = null;
      this.favoriteDropAfter = false;
      this.syncFavoriteDragVisuals();
    },

    syncFavoriteDragVisuals() {
      const rows = this.$root?.querySelectorAll?.('[data-ndb-section]') ?? [];

      rows.forEach((row) => {
        const key = row.dataset.ndbSection;
        const dragging = this.favoriteDrag === key;
        const dropBefore = this.favoriteDrop === key && !this.favoriteDropAfter;
        const dropAfter = this.favoriteDrop === key && this.favoriteDropAfter;

        row.dataset.ndbDragging = dragging ? 'true' : 'false';
        row.classList.toggle('ndb-favorite-dragging', dragging);
        row.querySelector('[data-ndb-favorite-drop-before]')?.toggleAttribute('hidden', !dropBefore);
        row.querySelector('[data-ndb-favorite-drop-after]')?.toggleAttribute('hidden', !dropAfter);
      });
    },

    toggleTheme() {
      this.setTheme(this.resolvedTheme === 'dark' ? 'light' : 'dark');
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
