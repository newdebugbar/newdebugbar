import assert from 'node:assert/strict';
import test from 'node:test';

import { runtime, summary, sectionHarness } from './state-test-support.js';

test('authorization controls filter search selection detail and section navigation', () => {
  const browser = runtime();
  const { state, shell } = sectionHarness(
    'authorization',
    {
      sections: [
        { key: 'request', label: 'Requests' },
        { key: 'authorization', label: 'Authorization' },
      ],
    },
    browser,
  );
  let headingFocused = 0;
  let selectedFocused = 0;
  let detailScrolled = 0;
  let detailFocusOptions = null;
  const style = () => ({ removeProperty() {}, setProperty() {} });
  const allowed = {
    dataset: {
      ndbAuthorizationExecution: '1',
      ndbAuthorizationResult: 'allowed',
      ndbAuthorizationSearchValue: 'inspect-profile mara trip',
    },
    hidden: false,
    style: style(),
    focus: () => selectedFocused++,
  };
  const denied = {
    dataset: {
      ndbAuthorizationExecution: '2',
      ndbAuthorizationResult: 'denied',
      ndbAuthorizationSearchValue: 'delete-profile guest model',
    },
    hidden: false,
    style: style(),
    focus: () => selectedFocused++,
  };
  state.$root = {
    querySelectorAll: () => [],
    querySelector: (selector) => (selector.includes('="2"') ? denied : allowed),
  };
  state.$refs = {
    authorizationList: { children: [allowed, denied] },
    authorizationDetail: {
      scrollTo: () => detailScrolled++,
      focus: (options) => {
        detailFocusOptions = options;
      },
    },
    content: { scrollTop: 42 },
    sectionHeading: { focus: () => headingFocused++ },
  };
  state.$nextTick = (callback) => callback();

  state.initializeAuthorization([
    { execution: 1, result: 'allowed', ability: 'inspect-profile' },
    { execution: 2, result: 'denied', ability: 'delete-profile' },
  ]);
  assert.equal(state.authorizationSelected, 1);
  assert.equal(state.selectedAuthorizationDecision.ability, 'inspect-profile');

  state.setAuthorizationFilter('allowed');
  assert.equal(allowed.hidden, false);
  assert.equal(denied.hidden, true);
  assert.equal(state.visibleAuthorizationCount, 1);

  state.authorizationSearch = 'guest';
  state.applyAuthorizationView();
  assert.equal(allowed.hidden, true);
  assert.equal(denied.hidden, true);
  assert.equal(state.visibleAuthorizationCount, 0);
  assert.equal(state.authorizationSelected, null);

  state.authorizationSearch = '';
  state.setAuthorizationFilter('all');
  state.selectAuthorizationDecision(2);
  assert.equal(state.authorizationSelected, 2);
  assert.equal(state.authorizationDetailOpen, true);
  assert.equal(state.$refs.content.scrollTop, 0);
  assert.deepEqual(detailFocusOptions, { preventScroll: true });
  assert.equal(detailScrolled > 0, true);
  state.closeAuthorizationDetail();
  assert.equal(state.authorizationDetailOpen, false);
  assert.equal(selectedFocused, 1);

  shell.$refs = state.$refs;
  state.init();
  shell.navigateToSection('authorization', 'denied');
  assert.equal(shell.selected, 'authorization');
  assert.equal(state.authorizationFilter, 'denied');
  assert.equal(allowed.hidden, true);
  assert.equal(denied.hidden, false);
  assert.equal(headingFocused, 1);

  state.setAuthorizationFilter('invalid');
  assert.equal(state.authorizationFilter, 'denied');
});
