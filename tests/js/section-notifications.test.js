import assert from 'node:assert/strict';
import test from 'node:test';

import { runtime, summary, sectionHarness, listRow } from './state-test-support.js';

test('notifications default to all and group channel delivery diagnostics', () => {
  const browser = runtime();
  const { state, shell } = sectionHarness(
    'notifications',
    {
      sections: [
        { key: 'request', label: 'Requests' },
        { key: 'mail', label: 'Mail' },
        { key: 'notifications', label: 'Notifications' },
      ],
    },
    browser,
  );
  let notificationScrolls = 0;
  let mailFocuses = 0;
  const element = (execution, status, search) =>
    listRow({
      ndbExecution: String(execution),
      ndbStatus: status,
      ndbSearch: search,
    });
  const first = element(1, 'partial', 'journey ready profile sms');
  const second = element(2, 'sent', 'departure push');
  const third = element(3, 'failed', 'payment failure slack');
  state.$root = { querySelectorAll: () => [] };
  state.$refs = {
    notificationList: { children: [first, second, third] },
    notificationDetail: { scrollTo: () => notificationScrolls++ },
    mailDetail: { focus: () => mailFocuses++ },
  };
  state.$nextTick = (callback) => callback();

  state.initializeNotifications([
    {
      execution: 1,
      status: 'partial',
      deliveries: [
        { channel: 'mail', channel_label: 'Mail' },
        { channel: 'profiled-sms', channel_label: 'Profiled Sms' },
      ],
    },
    {
      execution: 2,
      status: 'sent',
      deliveries: [{ channel: 'profiled-push', channel_label: 'Profiled Push' }],
    },
    {
      execution: 3,
      status: 'failed',
      deliveries: [{ channel: 'slack', channel_label: 'Slack' }],
    },
  ]);
  assert.equal(state.notificationFilter, 'all');
  assert.equal(state.notificationSelected, 1);
  assert.equal(state.notificationDetailOpen, false);
  assert.equal(state.notificationDetailTab, 'delivery');
  assert.equal(state.notificationChannel, 'mail');
  assert.equal(state.selectedNotificationDelivery.channel, 'mail');
  assert.equal(state.visibleNotificationCount, 3);

  state.setNotificationFilter('sent');
  assert.equal(first.hidden, true);
  assert.equal(second.hidden, false);
  assert.equal(third.hidden, true);
  assert.equal(state.notificationSelected, 2);
  assert.equal(state.notificationChannel, 'profiled-push');
  assert.equal(state.visibleNotificationCount, 1);

  state.setNotificationFilter('attention');
  assert.equal(first.hidden, false);
  assert.equal(second.hidden, true);
  assert.equal(third.hidden, false);
  assert.equal(state.notificationSelected, 1);

  state.notificationSearch = 'payment';
  state.applyNotificationView();
  assert.equal(first.hidden, true);
  assert.equal(third.hidden, false);
  assert.equal(state.notificationSelected, 3);

  state.notificationSearch = '';
  state.setNotificationFilter('all');
  state.selectNotification(1);
  state.setNotificationDetailTab('payload');
  state.setNotificationChannel('profiled-sms');
  assert.equal(state.notificationDetailOpen, true);
  assert.equal(state.notificationDetailTab, 'payload');
  assert.equal(state.selectedNotificationDelivery.channel, 'profiled-sms');
  assert.ok(notificationScrolls > 0);

  state.setNotificationFilter('invalid');
  state.setNotificationDetailTab('invalid');
  state.setNotificationChannel('invalid');
  state.selectNotification(99);
  assert.equal(state.notificationFilter, 'all');
  assert.equal(state.notificationDetailTab, 'payload');
  assert.equal(state.notificationChannel, 'profiled-sms');
  assert.equal(state.notificationSelected, 1);
  assert.equal(state.formatNotificationEvidence(null), 'No data was captured.');
  assert.equal(state.formatNotificationEvidence({ ready: true }), '{\n  "ready": true\n}');

  state.openNotificationMail('mail-7');
  assert.equal(shell.selected, 'mail');
  assert.equal(shell.pendingSectionIntent.filter.messageId, 'mail-7');
  const mail = shell.createSection('mail');
  mail.$nextTick = (callback) => callback();
  mail.$refs = { mailDetail: { focus: () => mailFocuses++ } };
  mail.initializeMail([{ execution: 7, transport_message_id: 'mail-7', has_html: true }]);
  mail.init();
  assert.equal(mail.mailSelected, 7);
  assert.equal(mail.mailDetailOpen, true);
  assert.equal(shell.pendingSectionIntent, null);
  assert.equal(mailFocuses, 1);

  mail.initializeMail([
    { execution: 8, transport_message_id: 'mail-8', has_html: true },
    { execution: 9, transport_message_id: 'mail-9', has_html: true },
  ]);
  state.openNotificationMail('mail-8');
  assert.equal(shell.selected, 'mail');
  assert.equal(mail.mailSelected, 8);
  assert.equal(mail.mailDetailOpen, true);
  assert.equal(mailFocuses, 2);

  state.initializeNotifications('invalid');
  assert.deepEqual(state.notificationGroups, []);
  assert.equal(state.notificationSelected, null);
  assert.equal(state.notificationDetailOpen, false);
});
