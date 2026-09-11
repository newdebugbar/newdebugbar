import { filterList } from './list.js';
import { readInspectorPayload } from '../runtime.js';

/** Owns notifications inspector state and interactions. */
export function createNotifications(context) {
  const { browser, shell } = context;
  const summary = shell.summary;
  return {
    refresh() {
      const payload = readInspectorPayload(this.$root, '[data-ndb-notification-payload]');
      if (payload !== null) this.initializeNotifications(payload);
    },

    notificationGroups: [],
    notificationFilter: 'all',
    notificationSearch: '',
    notificationSelected: null,
    notificationDetailOpen: false,
    notificationDetailTab: 'delivery',
    notificationChannel: null,
    visibleNotificationCount: summary.inspector_counts?.notifications ?? 0,

    get selectedNotification() {
      return (
        this.notificationGroups.find(
          (notification) => notification.execution === this.notificationSelected,
        ) ?? null
      );
    },

    get selectedNotificationDelivery() {
      const deliveries = this.selectedNotification?.deliveries ?? [];

      return (
        deliveries.find((delivery) => delivery.channel === this.notificationChannel) ?? deliveries[0] ?? null
      );
    },

    initializeNotifications(notifications) {
      this.notificationGroups = Array.isArray(notifications) ? notifications : [];
      if (this.initialized) {
        this.$nextTick?.(() => this.applyNotificationView());
        return;
      }
      this.initialized = true;
      this.notificationFilter = 'all';
      this.notificationSearch = '';
      this.notificationSelected = this.notificationGroups[0]?.execution ?? null;
      this.notificationDetailOpen = false;
      this.resetNotificationDetail();
      this.$nextTick?.(() => this.applyNotificationView());
    },

    setNotificationFilter(filter) {
      if (!['all', 'attention', 'sent'].includes(filter)) return;

      this.notificationFilter = filter;
      this.applyNotificationView();
    },

    selectNotification(execution) {
      if (!this.notificationGroups.some((notification) => notification.execution === execution)) return;

      this.notificationSelected = execution;
      this.notificationDetailOpen = true;
      this.resetNotificationDetail();
    },

    setNotificationDetailTab(tab) {
      if (!['delivery', 'payload', 'source'].includes(tab)) return;

      this.notificationDetailTab = tab;
      this.$nextTick?.(() => {
        this.$refs?.notificationDetail?.scrollTo?.({
          top: 0,
          behavior: 'instant',
        });
        browser.highlight?.();
      });
    },

    setNotificationChannel(channel) {
      if (!this.selectedNotification?.deliveries?.some((delivery) => delivery.channel === channel)) return;

      this.notificationChannel = channel;
      this.$nextTick?.(() => browser.highlight?.());
    },

    applyNotificationView() {
      const list = this.$refs?.notificationList;
      const search = this.notificationSearch.toLowerCase().trim();

      if (this.notificationGroups.length === 0) {
        this.visibleNotificationCount = 0;
        this.notificationSelected = null;
        this.notificationDetailOpen = false;

        return;
      }

      const { visible, firstVisible, selectedVisible } = filterList(list?.children ?? [], {
        selected: this.notificationSelected,
        key: (item) => Number(item.dataset.ndbExecution),
        matches: (item) => {
          const status = item.dataset.ndbStatus;
          const matchesFilter =
            this.notificationFilter === 'all' ||
            (this.notificationFilter === 'attention' && status !== 'sent') ||
            (this.notificationFilter === 'sent' && status === 'sent');
          return matchesFilter && (search === '' || item.dataset.ndbSearch?.includes(search));
        },
      });

      this.visibleNotificationCount = visible;

      if (!selectedVisible) {
        this.notificationSelected = firstVisible;
        this.resetNotificationDetail();
      }
    },

    resetNotificationDetail() {
      this.notificationDetailTab = 'delivery';
      this.notificationChannel = this.selectedNotification?.deliveries?.[0]?.channel ?? null;
      this.$nextTick?.(() => {
        this.$refs?.notificationDetail?.scrollTo?.({
          top: 0,
          behavior: 'instant',
        });
        browser.highlight?.();
      });
    },

    formatNotificationEvidence(value, empty = 'No data was captured.') {
      if (value === null || value === undefined || value === '') return empty;
      if (typeof value === 'string') return value;

      return JSON.stringify(value, null, 2);
    },

    openNotificationMail(messageId) {
      shell.selectInspector('mail', { messageId });
    },
  };
}
