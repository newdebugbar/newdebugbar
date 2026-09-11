export const isLivewirePrimitive = (value) =>
  value === null || typeof value === 'boolean' || typeof value === 'number' || typeof value === 'string';
export const livewireValueType = (value) => {
  if (value === null) return 'Null';
  if (Array.isArray(value)) return 'Array';
  if (typeof value === 'boolean') return 'Boolean';
  if (typeof value === 'number') return Number.isInteger(value) ? 'Integer' : 'Float';
  if (typeof value === 'string') return 'String';

  return 'Object';
};
export const livewireValueCopy = (value) => {
  try {
    return JSON.parse(JSON.stringify(value));
  } catch {
    return value;
  }
};
export const livewireValueSummary = (value) => {
  if (value === null) return 'null';
  if (value === true) return 'true';
  if (value === false) return 'false';
  if (typeof value === 'string') return value === '' ? 'Empty string' : value;
  if (typeof value === 'number') return String(value);
  if (Array.isArray(value)) return `${value.length} ${value.length === 1 ? 'item' : 'items'}`;
  if (typeof value === 'object') {
    const count = Object.keys(value).length;
    return `${count} ${count === 1 ? 'property' : 'properties'}`;
  }

  return String(value);
};

/** Owns livewire properties inspector state and interactions. */
export function createProperties(context) {
  const { browser, trace, shell, profileId } = context;
  return {
    livewireDrafts: {},
    livewireExpandedProperties: [],

    get livewirePropertyRows() {
      const component = this.selectedLivewireComponent;
      if (!component) return [];

      const descriptors = new Map(
        (component.server?.properties ?? []).map((descriptor) => [descriptor.path, descriptor]),
      );
      const canonicalProperties = new Map(
        (component.serverProperties ?? []).map((property) => [property.path, property.value]),
      );
      const latestChanges = [...this.livewireActivity]
        .filter((item) => item.componentId === component.id)
        .flatMap((item) => item.changes)
        .reverse();
      const rows = [];
      const append = (
        path,
        label,
        value,
        depth,
        root,
        safePath = true,
        canonicalKnown = false,
        canonicalValue = null,
      ) => {
        const descriptor = descriptors.get(root);
        const childEntries = Array.isArray(value)
          ? value.map((child, index) => [String(index), child])
          : value && typeof value === 'object'
            ? Object.entries(value)
            : [];
        const hasChildren = childEntries.length > 0;
        const expanded = this.livewireExpandedProperties.includes(`${component.id}:${path}`);
        const nested = path !== root;
        const editable =
          component.mounted !== false &&
          isLivewirePrimitive(value) &&
          safePath &&
          (nested ? descriptor?.array_leaf_writable === true : descriptor?.writable === true);
        const latest = latestChanges.find(
          (change) => change.path === path && (change.serverKnown === true || change.server !== null),
        );
        const serverKnown =
          canonicalKnown ||
          latest !== undefined ||
          (!nested &&
            descriptor?.server_value !== undefined &&
            (descriptor.server_value !== null || descriptor.type === 'Null'));
        const serverValue = canonicalKnown
          ? canonicalValue
          : latest !== undefined
            ? latest.server
            : (descriptor?.server_value ?? null);
        const draft = this.livewireDrafts[this.livewireDraftKey({ componentId: component.id, path })];
        const state =
          draft?.status === 'updating'
            ? 'Updating'
            : descriptor?.write_reason === 'locked'
              ? 'Locked'
              : serverKnown
                ? JSON.stringify(serverValue) === JSON.stringify(value)
                  ? 'Synced'
                  : 'Dirty'
                : 'Unknown';

        rows.push({
          componentId: component.id,
          path,
          label,
          value,
          valueSummary: livewireValueSummary(value),
          type: nested ? livewireValueType(value) : (descriptor?.type ?? livewireValueType(value)),
          phpType: nested ? null : descriptor?.php_type,
          depth,
          hasChildren,
          expanded,
          editable,
          writeReason: editable
            ? null
            : (descriptor?.write_reason ?? (isLivewirePrimitive(value) ? 'unknown' : 'unsupported_type')),
          serverKnown,
          serverValue,
          serverSummary: serverKnown ? livewireValueSummary(serverValue) : 'Not confirmed',
          state,
        });

        if (!hasChildren || !expanded) return;

        childEntries.forEach(([key, child]) => {
          const canonicalChildKnown =
            canonicalKnown &&
            canonicalValue !== null &&
            typeof canonicalValue === 'object' &&
            Object.prototype.hasOwnProperty.call(canonicalValue, key);

          append(
            `${path}.${key}`,
            key,
            child,
            depth + 1,
            root,
            safePath && !key.includes('.'),
            canonicalChildKnown,
            canonicalChildKnown ? canonicalValue[key] : null,
          );
        });
      };

      component.properties.forEach((property) => {
        const canonicalKnown = canonicalProperties.has(property.path);
        append(
          property.path,
          property.path,
          property.value,
          0,
          property.path,
          true,
          canonicalKnown,
          canonicalKnown ? canonicalProperties.get(property.path) : null,
        );
      });

      return rows;
    },

    livewirePropertyStateLabel(row) {
      return row?.state === 'Unknown' ? 'Not confirmed' : row?.state;
    },

    livewirePropertyStateDescription(row) {
      return (
        {
          Synced: 'Client and server values match.',
          Dirty: 'The client value differs from the latest server value.',
          Updating: 'A Livewire update is in progress.',
          Locked: 'Livewire prevents this property from being edited.',
          Unknown: 'No server-confirmed value was captured.',
        }[row?.state] ?? ''
      );
    },

    livewireDraftKey(row) {
      return `${row.componentId}:${row.path}`;
    },

    toggleLivewireProperty(row) {
      if (!row?.hasChildren) return;
      const key = `${row.componentId}:${row.path}`;
      this.livewireExpandedProperties = this.livewireExpandedProperties.includes(key)
        ? this.livewireExpandedProperties.filter((item) => item !== key)
        : [...this.livewireExpandedProperties, key];
    },

    editLivewireProperty(row) {
      if (!row?.editable) return;
      const key = this.livewireDraftKey(row);
      const type = row.value === null ? 'String' : row.type;
      this.closeLivewireDrafts();
      this.livewireDrafts = {
        ...this.livewireDrafts,
        [key]: {
          componentId: row.componentId,
          path: row.path,
          baseline: livewireValueCopy(row.value),
          type,
          value: row.value === null ? '' : livewireValueCopy(row.value),
          status: 'editing',
          error: null,
        },
      };
    },

    toggleLivewirePropertyEditor(row) {
      if (!row?.editable) return;
      const draft = this.livewireDrafts[this.livewireDraftKey(row)];

      if (draft && draft.status !== 'closing') {
        this.cancelLivewireDraft(row);

        return;
      }

      this.editLivewireProperty(row);
    },

    cancelLivewireDraft(row, restoreFocus = false) {
      const key = this.livewireDraftKey(row);
      const draft = this.livewireDrafts[key];
      if (!draft) return;

      draft.status = 'closing';
      if (restoreFocus) this.focusLivewirePropertyEditor(row);
      this.scheduleLivewireDraftCleanup();
    },

    closeLivewireDrafts() {
      const drafts = Object.values(this.livewireDrafts);
      if (drafts.length === 0) return;

      drafts.forEach((draft) => {
        draft.status = 'closing';
      });
      this.scheduleLivewireDraftCleanup();
    },

    scheduleLivewireDraftCleanup() {
      const cleanup = () => {
        this.livewireDrafts = Object.fromEntries(
          Object.entries(this.livewireDrafts).filter(([, draft]) => draft.status !== 'closing'),
        );
      };

      if (typeof this.$nextTick === 'function') {
        this.$nextTick(cleanup);

        return;
      }
      if (browser.nextFrame) {
        browser.nextFrame(cleanup);

        return;
      }

      cleanup();
    },

    focusLivewirePropertyEditor(row, trigger = null) {
      const key = this.livewireDraftKey(row);
      browser.afterPaint(() => {
        browser.afterPaint(() => {
          if (
            this.destroyed ||
            !shell.inspectorOpen ||
            !shell.barVisible ||
            shell.selected !== 'livewire' ||
            profileId !== shell.summary.id
          )
            return;
          const candidates =
            browser.queryAll?.('[data-ndb-livewire-edit-key]') ??
            this.$root.querySelectorAll('[data-ndb-livewire-edit-key]');
          const button =
            trigger?.isConnected !== false && trigger?.dataset?.ndbLivewireEditKey === key
              ? trigger
              : [...candidates].find((item) => item.dataset.ndbLivewireEditKey === key);
          if (button) button.focus();
        });
      });
    },

    toggleLivewireBoolean(row) {
      const draft = this.livewireDrafts[this.livewireDraftKey(row)];
      if (draft) draft.value = !Boolean(draft.value);
    },

    livewireMutationValue(draft) {
      if (draft.type === 'Boolean') return Boolean(draft.value);
      if (draft.type === 'Integer') {
        if (!/^-?\d+$/.test(String(draft.value).trim())) throw new Error('Enter a whole number.');
        return Number.parseInt(draft.value, 10);
      }
      if (draft.type === 'Float') {
        const value = Number(draft.value);
        if (String(draft.value).trim() === '' || !Number.isFinite(value))
          throw new Error('Enter a valid number.');
        return value;
      }

      return String(draft.value);
    },

    applyLivewireDraftOnEnter(row, trigger, event) {
      if (event.metaKey || event.ctrlKey || event.altKey || event.shiftKey || event.isComposing) return;

      event.preventDefault();
      return this.applyLivewireDraft(row, trigger);
    },

    async applyLivewireDraft(row, trigger = null) {
      const key = this.livewireDraftKey(row);
      const draft = this.livewireDrafts[key];
      if (!draft || draft.status === 'updating') return false;

      draft.error = null;

      try {
        const value = this.livewireMutationValue(draft);
        draft.status = 'updating';
        if (!trace?.applyMutation) throw new Error('Livewire is not available on this page.');
        const confirmed = await trace.applyMutation({
          componentId: draft.componentId,
          path: draft.path,
          baseline: draft.baseline,
          value,
        });
        draft.baseline = livewireValueCopy(confirmed);
        draft.value = livewireValueCopy(confirmed);
        this.cancelLivewireDraft(row);
        this.focusLivewirePropertyEditor(row, trigger);

        return true;
      } catch (error) {
        draft.status = 'failed';
        draft.error = error?.message ?? 'The Livewire update failed.';

        return false;
      }
    },
  };
}
