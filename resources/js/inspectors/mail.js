import { filterList } from './list.js';
import { readInspectorPayload, composeState } from '../runtime.js';
import { createMailPreview } from './mail-preview.js';

/** Owns mail inspector state and interactions. */
export function createMail(context) {
  const { browser, shell } = context;
  const summary = shell.summary;
  return composeState(createMailPreview(context), {
    refresh() {
      const payload = readInspectorPayload(this.$root, '[data-ndb-mail-payload]');
      if (payload !== null) this.initializeMail(payload);
    },
    activate() {
      this.$nextTick?.(() => {
        const frame = this.$refs?.mailPreviewFrame;
        if (frame) this.connectMailPreviewFrame(frame);
      });
    },
    deactivate() {
      this.mailPreviewFrameCleanup?.();
      this.mailPreviewFrameCleanup = null;
    },
    receiveIntent(intent) {
      const message = this.mailMessages.find((message) => message.transport_message_id === intent?.messageId);
      if (!message) return;
      this.mailFilter = 'all';
      this.mailSearch = '';
      this.selectMailMessage(message.execution);
      this.applyMailView();
      this.$nextTick?.(() => this.$refs?.mailDetail?.focus?.());
    },

    mailMessages: [],
    mailFilter: 'all',
    mailSearch: '',
    mailSelected: null,
    mailDetailOpen: false,
    mailDetailTab: 'preview',
    visibleMailCount: summary.inspector_counts?.mail ?? 0,

    get selectedMailMessage() {
      return this.mailMessages.find((message) => message.execution === this.mailSelected) ?? null;
    },

    initializeMail(messages) {
      this.mailMessages = Array.isArray(messages) ? messages : [];
      if (!this.initialized) {
        this.initialized = true;
        this.mailSelected = this.mailMessages[0]?.execution ?? null;
        this.resetMailDetail();
      }
      this.$nextTick?.(() => this.applyMailView());
    },

    setMailFilter(filter) {
      if (!['all', 'attachments'].includes(filter)) return;

      this.mailFilter = filter;
      this.applyMailView();
    },

    selectMailMessage(execution) {
      if (!this.mailMessages.some((message) => message.execution === execution)) return;

      this.mailSelected = execution;
      this.mailDetailOpen = true;
      this.resetMailDetail();
    },

    setMailDetailTab(tab) {
      if (!['preview', 'message', 'source'].includes(tab)) return;
      if (tab === 'source' && !this.selectedMailHasSource) return;

      this.mailDetailTab = tab;
      if (tab !== 'preview') this.deactivate();
      else this.activate();
      this.$nextTick?.(() => {
        this.$refs?.mailDetail?.scrollTo?.({ top: 0, behavior: 'instant' });
        browser.highlight?.();
      });
    },

    get selectedMailHasSource() {
      const message = this.selectedMailMessage;

      return Boolean(message?.source || message?.callsite?.file || message?.stack?.length);
    },

    applyMailView() {
      const list = this.$refs?.mailList;
      const search = this.mailSearch.toLowerCase().trim();

      if (this.mailMessages.length === 0) {
        this.visibleMailCount = 0;
        this.mailSelected = null;
        this.mailDetailOpen = false;

        return;
      }

      if (!list) {
        this.visibleMailCount = this.mailMessages.length;

        return;
      }

      const { visible, firstVisible, selectedVisible } = filterList(list.children, {
        selected: this.mailSelected,
        key: (item) => Number(item.dataset.ndbExecution),
        matches: (item) => {
          return (
            (this.mailFilter === 'all' || item.dataset.ndbAttachments === 'true') &&
            (search === '' || item.dataset.ndbSearch?.includes(search))
          );
        },
      });

      this.visibleMailCount = visible;

      if (!selectedVisible) {
        this.mailSelected = firstVisible;
        this.resetMailDetail();
      }
    },

    resetMailDetail() {
      this.mailDetailTab = 'preview';
      this.mailPreviewFormat = this.selectedMailMessage?.has_html ? 'html' : 'text';
      this.mailPreviewViewport = 'desktop';
      this.$nextTick?.(() => {
        this.$refs?.mailDetail?.scrollTo?.({ top: 0, behavior: 'instant' });
        browser.highlight?.();
      });
    },

    formatMailAddresses(addresses) {
      return Array.isArray(addresses) && addresses.length > 0 ? addresses.join(', ') : '—';
    },
  });
}
