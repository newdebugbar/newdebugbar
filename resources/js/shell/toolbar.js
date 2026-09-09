export const TOOLBAR_PLACEMENTS = ['top-left', 'top', 'top-right', 'bottom-left', 'bottom', 'bottom-right'];
const TOOLBAR_CORNER_PLACEMENTS = TOOLBAR_PLACEMENTS.filter((placement) => placement.includes('-'));
export const toolbarHorizontalPlacement = (placement) => {
  if (placement.endsWith('-left')) return 'left';
  if (placement.endsWith('-right')) return 'right';

  return 'center';
};
export const toolbarVerticalPlacement = (placement) => (placement.startsWith('top') ? 'top' : 'bottom');

/** Owns toolbar shell behavior. */
export function createToolbar(context) {
  const { browser } = context;
  return {
    mobileToolbarMenu: null,
    mobileToolbarReturnFocus: null,
    toolbarPlacement: 'bottom',
    toolbarPreferredPlacement: 'bottom',
    stopToolbarPlacementWatch: null,
    toolbarDragging: false,
    toolbarRebasing: false,
    toolbarSnapping: false,
    toolbarDragPointerId: null,
    toolbarDragStartX: 0,
    toolbarDragStartY: 0,
    toolbarDragPointerOffsetX: 0,
    toolbarDragPointerOffsetY: 0,
    toolbarDragPointerRatioX: 0.5,
    toolbarDragPointerRatioY: 0.5,
    toolbarDragWidth: 0,
    toolbarDragHeight: 0,
    toolbarDragOffsetX: 0,
    toolbarDragOffsetY: 0,
    toolbarCenterWidth: 0,
    toolbarCenterHeight: 60,
    toolbarCornerWidth: 196,
    toolbarCornerHeight: 56,
    toolbarDragTarget: 'bottom',
    toolbarDragOriginPlacement: 'bottom',
    toolbarSuppressClick: false,
    toolbarSnapTimer: null,
    toolbarSnapVersion: 0,
    toolbarClickTimer: null,

    get toolbarIsCorner() {
      return TOOLBAR_CORNER_PLACEMENTS.includes(this.toolbarPlacement);
    },

    get toolbarIsTop() {
      return toolbarVerticalPlacement(this.toolbarPlacement) === 'top';
    },

    get toolbarIsLeft() {
      return toolbarHorizontalPlacement(this.toolbarPlacement) === 'left';
    },

    get toolbarIsRight() {
      return toolbarHorizontalPlacement(this.toolbarPlacement) === 'right';
    },

    get toolbarVerticalPlacement() {
      return toolbarVerticalPlacement(this.toolbarPlacement);
    },

    syncToolbarPlacement() {
      if (this.requestPickerScope !== null) this.syncRequestPickerArrow();
      if (this.toolbarDragging || this.toolbarRebasing || this.toolbarSnapping) return;

      const placement = browser.toolbarPlacement?.(this.$root, this.toolbarPreferredPlacement);

      if (TOOLBAR_PLACEMENTS.includes(placement) && placement !== this.toolbarPlacement) {
        this.moveToolbarTo(placement);
      }
    },

    toolbarAnchorLeft(placement, width) {
      const viewportWidth = browser.viewportWidth?.() ?? 0;
      const horizontal = toolbarHorizontalPlacement(placement);

      if (horizontal === 'left') return 12;
      if (horizontal === 'right') return Math.max(12, viewportWidth - width - 12);

      return Math.max(12, (viewportWidth - width) / 2);
    },

    toolbarAnchorTop(placement, height) {
      if (toolbarVerticalPlacement(placement) === 'top') return 12;

      return Math.max(12, (browser.viewportHeight?.() ?? 0) - height - 12);
    },

    toolbarPreviewWidth(placement) {
      if (TOOLBAR_CORNER_PLACEMENTS.includes(placement)) return this.toolbarCornerWidth;

      return this.toolbarCenterWidth || Math.min(1024, Math.max(0, (browser.viewportWidth?.() ?? 0) - 24));
    },

    toolbarPreviewHeight(placement) {
      return TOOLBAR_CORNER_PLACEMENTS.includes(placement) ? this.toolbarCornerHeight : this.toolbarCenterHeight;
    },

    toolbarTargetAt(clientX, clientY) {
      const width = Math.max(1, browser.viewportWidth?.() ?? 0);
      const height = Math.max(1, browser.viewportHeight?.() ?? 0);
      const vertical = clientY < height / 2 ? 'top' : 'bottom';
      const horizontal = clientX < width / 3 ? 'left' : clientX > (width * 2) / 3 ? 'right' : 'center';

      return horizontal === 'center' ? vertical : `${vertical}-${horizontal}`;
    },

    startToolbarDrag(event) {
      if (!this.barVisible || this.inspectorOpen || this.toolbarDragPointerId !== null) return;
      if (event.isPrimary === false || (event.pointerType === 'mouse' && event.button !== 0)) return;
      if (event.target?.closest?.('[role="menu"], [role="listbox"], [role="dialog"], input, select, textarea')) return;

      const toolbar = event.currentTarget;
      const box = toolbar?.getBoundingClientRect?.();
      if (!toolbar || !box || box.width <= 0 || box.height <= 0) return;

      browser.cancelSchedule?.(this.toolbarSnapTimer);
      this.toolbarSnapTimer = null;
      this.toolbarSnapVersion += 1;
      this.toolbarRebasing = true;
      this.toolbarSnapping = false;
      this.toolbarDragPointerId = event.pointerId;
      this.toolbarDragStartX = event.clientX;
      this.toolbarDragStartY = event.clientY;
      this.toolbarDragPointerOffsetX = Math.min(Math.max(event.clientX - box.left, 0), box.width);
      this.toolbarDragPointerOffsetY = Math.min(Math.max(event.clientY - box.top, 0), box.height);
      this.toolbarDragPointerRatioX = this.toolbarDragPointerOffsetX / box.width;
      this.toolbarDragPointerRatioY = this.toolbarDragPointerOffsetY / box.height;
      this.toolbarDragWidth = box.width;
      this.toolbarDragHeight = box.height;
      this.toolbarDragOffsetX = box.left - this.toolbarAnchorLeft(this.toolbarPlacement, box.width);
      this.toolbarDragOffsetY = box.top - this.toolbarAnchorTop(this.toolbarPlacement, box.height);
      if (this.toolbarIsCorner) {
        this.toolbarCornerWidth = box.width;
        this.toolbarCornerHeight = box.height;
      } else {
        this.toolbarCenterWidth = box.width;
        this.toolbarCenterHeight = box.height;
      }
      this.toolbarDragTarget = this.toolbarPlacement;
      this.toolbarDragOriginPlacement = this.toolbarPlacement;
    },

    moveToolbarDrag(event) {
      if (event.pointerId !== this.toolbarDragPointerId) return;

      const distance = Math.hypot(event.clientX - this.toolbarDragStartX, event.clientY - this.toolbarDragStartY);

      if (!this.toolbarDragging && distance < 6) return;

      if (!this.toolbarDragging) {
        this.toolbarDragging = true;
        this.closeRequestPicker(false);
        this.closeThemeMenu(false);
        this.mobileToolbarMenu = null;
        this.mobileToolbarReturnFocus = null;
        this.$root?.querySelector?.('[data-ndb-toolbar-shell]')?.setPointerCapture?.(event.pointerId);
      }

      event.preventDefault?.();
      const width = this.toolbarDragWidth;
      const height = this.toolbarDragHeight;
      const viewportWidth = browser.viewportWidth?.() ?? 0;
      const viewportHeight = browser.viewportHeight?.() ?? 0;
      const minLeft = 12;
      const maxLeft = Math.max(minLeft, viewportWidth - width - 12);
      const minTop = 12;
      const maxTop = Math.max(minTop, viewportHeight - height - 12);
      const left = Math.min(maxLeft, Math.max(minLeft, event.clientX - this.toolbarDragPointerOffsetX));
      const top = Math.min(maxTop, Math.max(minTop, event.clientY - this.toolbarDragPointerOffsetY));

      this.toolbarDragOffsetX = left - this.toolbarAnchorLeft(this.toolbarPlacement, width);
      this.toolbarDragOffsetY = top - this.toolbarAnchorTop(this.toolbarPlacement, height);
      this.toolbarDragTarget = this.toolbarTargetAt(event.clientX, event.clientY);
    },

    endToolbarDrag(event) {
      if (event.pointerId !== this.toolbarDragPointerId) return;

      const toolbar = this.$root?.querySelector?.('[data-ndb-toolbar-shell]');
      const currentBox = toolbar?.getBoundingClientRect?.();
      if (toolbar?.hasPointerCapture?.(event.pointerId)) {
        toolbar.releasePointerCapture?.(event.pointerId);
      }
      this.toolbarDragPointerId = null;

      if (!this.toolbarDragging) {
        this.moveToolbarTo(this.toolbarPlacement, false, currentBox, event);

        return;
      }

      event.preventDefault?.();
      this.toolbarDragging = false;
      this.suppressToolbarClick();
      this.moveToolbarTo(this.toolbarDragTarget, true, currentBox, event);
    },

    cancelToolbarDrag(event) {
      if (event.pointerId !== this.toolbarDragPointerId) return;

      const toolbar = this.$root?.querySelector?.('[data-ndb-toolbar-shell]');
      const currentBox = toolbar?.getBoundingClientRect?.();
      if (toolbar?.hasPointerCapture?.(event.pointerId)) {
        toolbar.releasePointerCapture?.(event.pointerId);
      }
      this.toolbarDragPointerId = null;

      if (!this.toolbarDragging) {
        this.moveToolbarTo(this.toolbarPlacement, false, currentBox);

        return;
      }

      this.toolbarDragging = false;
      this.suppressToolbarClick();
      this.moveToolbarTo(this.toolbarDragOriginPlacement, false, currentBox);
    },

    suppressToolbarClick() {
      this.toolbarSuppressClick = true;
      browser.cancelSchedule?.(this.toolbarClickTimer);
      this.toolbarClickTimer =
        browser.schedule?.(() => {
          this.toolbarSuppressClick = false;
          this.toolbarClickTimer = null;
        }, 250) ?? null;
    },

    consumeToolbarClick(event) {
      if (!this.toolbarSuppressClick) return;

      event.preventDefault?.();
      event.stopPropagation?.();
      this.toolbarSuppressClick = false;
      browser.cancelSchedule?.(this.toolbarClickTimer);
      this.toolbarClickTimer = null;
    },

    pinToolbar(placement) {
      if (!TOOLBAR_PLACEMENTS.includes(placement)) return;

      this.mobileToolbarMenu = null;
      this.mobileToolbarReturnFocus = null;
      this.moveToolbarTo(placement, true);
    },

    moveToolbarTo(placement, remember = false, currentBox = null, releasePointer = null) {
      if (!TOOLBAR_PLACEMENTS.includes(placement)) return;

      const snapVersion = ++this.toolbarSnapVersion;
      const toolbar = this.$root?.querySelector?.('[data-ndb-toolbar-shell]');
      const sourceBox = currentBox ?? toolbar?.getBoundingClientRect?.();
      const source =
        sourceBox && Number.isFinite(sourceBox.left) && Number.isFinite(sourceBox.top)
          ? {
              left: sourceBox.left,
              top: sourceBox.top,
              width: sourceBox.width,
              height: sourceBox.height,
            }
          : null;
      const pointer =
        releasePointer && Number.isFinite(releasePointer.clientX) && Number.isFinite(releasePointer.clientY)
          ? { x: releasePointer.clientX, y: releasePointer.clientY }
          : null;
      const viewportWidth = browser.viewportWidth?.() ?? 0;
      const viewportHeight = browser.viewportHeight?.() ?? 0;
      const positionAtRelease = (width, height) => {
        const desiredLeft = pointer
          ? pointer.x - width * this.toolbarDragPointerRatioX
          : source.left + (source.width - width) / 2;
        const desiredTop = pointer
          ? pointer.y - height * this.toolbarDragPointerRatioY
          : source.top + (source.height - height) / 2;

        return {
          left: Math.min(Math.max(12, viewportWidth - width - 12), Math.max(12, desiredLeft)),
          top: Math.min(Math.max(12, viewportHeight - height - 12), Math.max(12, desiredTop)),
        };
      };

      browser.cancelSchedule?.(this.toolbarSnapTimer);
      this.toolbarSnapTimer = null;
      this.toolbarRebasing = true;
      this.toolbarSnapping = false;

      if (remember) {
        this.toolbarPreferredPlacement = placement;
        this.persist();
      }

      if (!toolbar || !source || source.width <= 0 || source.height <= 0) {
        this.toolbarPlacement = placement;
        this.toolbarDragTarget = placement;
        this.toolbarDragOffsetX = 0;
        this.toolbarDragOffsetY = 0;
        this.toolbarRebasing = false;
        this.toolbarSnapping = false;

        return;
      }

      const provisionalWidth = this.toolbarPreviewWidth(placement);
      const provisionalHeight = this.toolbarPreviewHeight(placement);
      const provisional = positionAtRelease(provisionalWidth, provisionalHeight);

      this.toolbarPlacement = placement;
      this.toolbarDragTarget = placement;
      this.toolbarDragOffsetX = provisional.left - this.toolbarAnchorLeft(placement, provisionalWidth);
      this.toolbarDragOffsetY = provisional.top - this.toolbarAnchorTop(placement, provisionalHeight);

      const rebase = () => {
        const destination = toolbar.getBoundingClientRect?.();
        if (!destination || destination.width <= 0 || destination.height <= 0) {
          this.toolbarRebasing = false;

          return;
        }

        const release = positionAtRelease(destination.width, destination.height);
        const baseLeft = destination.left - this.toolbarDragOffsetX;
        const baseTop = destination.top - this.toolbarDragOffsetY;
        const offsetX = release.left - baseLeft;
        const offsetY = release.top - baseTop;

        this.toolbarDragOffsetX = Math.abs(offsetX) > 0.5 ? offsetX : 0;
        this.toolbarDragOffsetY = Math.abs(offsetY) > 0.5 ? offsetY : 0;

        if (this.toolbarDragOffsetX === 0 && this.toolbarDragOffsetY === 0) {
          this.toolbarRebasing = false;

          return;
        }

        const prepare = () => {
          if (snapVersion !== this.toolbarSnapVersion) return;

          this.toolbarRebasing = false;
          this.toolbarSnapping = true;

          const settle = () => {
            if (snapVersion !== this.toolbarSnapVersion) return;

            this.toolbarDragOffsetX = 0;
            this.toolbarDragOffsetY = 0;
            this.toolbarSnapTimer = browser.schedule?.(() => this.finishToolbarSnap(snapVersion), 500) ?? null;
          };

          if (browser.nextFrame) browser.nextFrame(settle);
          else this.$nextTick?.(settle);
        };

        if (browser.afterPaint) browser.afterPaint(prepare);
        else prepare();
      };

      if (this.$nextTick) this.$nextTick(rebase);
      else rebase();
    },

    finishToolbarSnap(snapVersion = null) {
      if (snapVersion !== null && snapVersion !== this.toolbarSnapVersion) return;
      if (!this.toolbarSnapping || this.toolbarRebasing || this.toolbarDragging) return;

      browser.cancelSchedule?.(this.toolbarSnapTimer);
      this.toolbarSnapTimer = null;
      this.toolbarDragOffsetX = 0;
      this.toolbarDragOffsetY = 0;
      this.toolbarRebasing = false;
      this.toolbarSnapping = false;
      this.syncToolbarPlacement();
    },

    toggleMobileToolbarMenu(menu, returnFocus = null) {
      if (this.mobileToolbarMenu === menu) {
        this.closeMobileToolbarMenu();

        return;
      }

      this.openMobileToolbarMenu(menu, returnFocus);
    },

    openMobileToolbarMenu(menu, returnFocus = null) {
      const compactMenu = menu === 'actions';
      const inspectorMenu = menu === 'header-actions';

      if (
        !this.barVisible ||
        (!compactMenu && !inspectorMenu) ||
        (compactMenu && this.inspectorOpen) ||
        (inspectorMenu && !this.inspectorOpen)
      )
        return;

      this.mobileToolbarMenu = menu;
      this.closeRequestPicker(false);
      this.closeThemeMenu(false);
      this.mobileToolbarReturnFocus = returnFocus ?? browser.activeElement?.();
      this.$nextTick?.(() => {
        const focus = () => {
          const popover = this.$root?.querySelector?.(`[data-ndb-mobile-toolbar-menu="${menu}"]`);
          const items = popover?.querySelector?.('[data-ndb-mobile-toolbar-popover-items]');
          if (items) items.scrollTop = 0;
          popover?.querySelector?.('[role="menuitem"]')?.focus?.();
        };
        browser.afterPaint ? browser.afterPaint(focus) : focus();
      });
    },

    openSectionFromToolbar(section) {
      const returnFocus = this.mobileToolbarReturnFocus;
      this.closeMobileToolbarMenu(false);

      if (this.inspectorOpen) this.selectSection(section, null, true);
      else this.openInspector(section, returnFocus);
    },

    moveMobileToolbarMenu(direction, menu) {
      const items = [...(menu?.querySelectorAll?.('button:not([disabled])') ?? [])].filter(
        (item) => item.getClientRects().length > 0,
      );
      if (!items.length) return;

      const index = items.indexOf(browser.activeElement?.());
      items[(index + direction + items.length) % items.length]?.focus?.();
    },

    closeMobileToolbarMenu(restoreFocus = true) {
      if (!this.mobileToolbarMenu) return;

      const returnFocus = this.mobileToolbarReturnFocus;
      this.mobileToolbarMenu = null;
      this.mobileToolbarReturnFocus = null;

      if (restoreFocus)
        this.$nextTick?.(() => {
          const focus = () => returnFocus?.focus?.();
          browser.afterPaint ? browser.afterPaint(focus) : focus();
        });
    },
  };
}
